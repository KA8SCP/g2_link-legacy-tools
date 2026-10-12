<?php
/*
 * Unified D-STAR Dashboard v0.4.2
 * DPlus Remote Activity Parser - Test 3
 * PHP 5.4 compatible
 * Read-only experimental parser
 */

function dplusAdapterNormalizeStreamId($id) {
    $id = strtolower(ltrim($id, '0'));
    return $id === '' ? '0' : $id;
}


function dplusAdapterRead($logfile = '/var/log/dplus.log') {
$records = array();
$active = array();
$localActive = array();
$localStarts = 0;
$localEnds = 0;
$localPending = null;
$pending = -1;
$staleUnmatched = 0;

if (!is_readable($logfile)) {
    return array('records'=>array(), 'error'=>'Log not readable');
}

$fh = fopen($logfile, 'r');
if (!$fh) {
    return array('records'=>array(), 'error'=>'Cannot open log');
}

$localHeadersParsed = 0;

while (($line = fgets($fh)) !== false) {

    // Track local RF forwarding separately from incoming remote traffic.
    if (preg_match(
        '/^Remote client forward start streamid\\s+([0-9a-f]+)/i',
        $line, $m
    )) {
        $id = dplusAdapterNormalizeStreamId($m[1]);
        $localActive[$id] = array(
            'callsign' => '',
            'suffix' => ''
        );
        $localPending = $id;
        $localStarts++;
        continue;
    }

    if (preg_match(
        '/^Stream end\\s+streamid\\s+([0-9a-f]+)/i',
        $line, $m
    )) {
        $id = dplusAdapterNormalizeStreamId($m[1]);
        if (isset($localActive[$id])) {
            unset($localActive[$id]);
            $localEnds++;
        }
        continue;
    }

    // Capture the local RF header immediately following its start.
    if ($localPending !== null) {
        if (preg_match(
            '/rpt2\\s+\\(([^)]*)\\).*?rpt1\\s+\\(([^)]*)\\).*?mycall\\s+\\(([^)]*)\\)/i',
            $line, $m
        )) {
            $localHeadersParsed++;
            $parts = preg_split('/\\s+/', trim($m[3]));
            $localActive[$localPending]['callsign'] =
                isset($parts[0]) ? $parts[0] : '';

            if (preg_match('/\\/([A-Z0-9]+)/i', $m[3], $sm)) {
                $localActive[$localPending]['suffix'] =
                    strtoupper($sm[1]);
            }
        }

        $localPending = null;
        continue;
    }

    // Transmission start
    if (preg_match(
        '/Remote gateway tx start\s+\(([^)]+)\)\s+streamid\s+([0-9a-f]+)\s+hdr\s+(\S+)\s+(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?)/i',
        $line, $m
    )) {
        $connection = trim($m[1]);
        $id = dplusAdapterNormalizeStreamId($m[2]);
        $key = $connection . '|' . $id;

        // A previous record with this key may have
        // ended or been left incomplete.
        if (isset($active[$key])) {
            $records[$active[$key]]['status'] =
                'Superseded';
        }

        $records[] = array(
            'time' => $m[4],
            'connection' => $connection,
            'streamid' => $id,
            'header' => strtoupper($m[3]),
            'callsign' => '',
            'suffix' => '',
            'rpt1' => '',
            'rpt2' => '',
            'message' => '',
            'origin_r1' => '',
            'origin_r2' => '',
            'duration' => '',
            'missed' => '',
            'status' => 'Incomplete'
        );

        $pending = count($records) - 1;
        $active[$key] = $pending;
        continue;
    }

    // Header details must immediately follow start.
    if ($pending >= 0) {
        if (preg_match(
            '/rpt2\s+\(([^)]*)\).*?rpt1\s+\(([^)]*)\).*?mycall\s+\(([^)]*)\)/i',
            $line, $m
        )) {
            $records[$pending]['rpt2'] = trim($m[1]);
            $records[$pending]['rpt1'] = trim($m[2]);

            $call = preg_split('/\s+/', trim($m[3]));
            $records[$pending]['callsign'] =
                isset($call[0]) ? $call[0] : '';

            if (preg_match('/\/([A-Z0-9]+)/i', $m[3], $s)) {
                $records[$pending]['suffix'] = strtoupper($s[1]);
            }
        }

        $pending = -1;
        continue;
    }

    // Optional message associated with an active stream.
    if (preg_match(
        '/user message\s+\(([^)]*)\).*?streamid\s+([0-9a-f]+)/i',
        $line, $m
    )) {
        $id = dplusAdapterNormalizeStreamId($m[2]);

        $matches = array();

        foreach ($active as $key => $index) {
            if ($records[$index]['streamid'] === $id) {
                $matches[] = $index;
            }
        }

        // Assign only when the stream ID is unambiguous
        // across incoming remote and local RF forwarding.
        if (count($matches) === 1 &&
            !isset($localActive[$id])) {
            $records[$matches[0]]['message'] = trim($m[1]);
        }

        continue;
    }

    // Extended D-STAR header: no stream ID is supplied.
    // Associate only with one unambiguous active callsign/suffix.
    if (preg_match(
        '/hdr f [0-9a-f]+ r2 (.*?) r1 (.*?) ur .*? my (.*?) fc [0-9a-f]+/i',
        $line, $m
    )) {
        $originR2 = trim($m[1]);
        $originR1 = trim($m[2]);
        $mycall = trim($m[3]);

        $parts = preg_split('/\\s+/', $mycall);
        $call = isset($parts[0]) ? $parts[0] : '';

        $suffix = '';
        if (preg_match('/\\/([A-Z0-9]+)/i', $mycall, $sm)) {
            $suffix = strtoupper($sm[1]);
        }

        $matches = array();

        foreach ($active as $key => $index) {
            if ($records[$index]['callsign'] === $call &&
                $records[$index]['suffix'] === $suffix) {
                $matches[] = $index;
            }
        }

        // Check whether a local RF stream could own this header.
        $localMatches = 0;

        foreach ($localActive as $localId => $local) {
            if ($local['callsign'] === $call &&
                $local['suffix'] === $suffix) {
                $localMatches++;
            }
        }

        // Never assign an ambiguous local/remote header.
        if (count($matches) === 1 && $localMatches === 0) {
            $records[$matches[0]]['origin_r1'] = $originR1;
            $records[$matches[0]]['origin_r2'] = $originR2;
        }

        continue;
    }

    // DPlus stale stream cache removal
    if (preg_match(
        '/removed stale streamcache\s+\d+\s+([0-9a-f]+)/i',
        $line, $m
    )) {
        $id = dplusAdapterNormalizeStreamId($m[1]);
        $matches = array();

        foreach ($active as $key => $index) {
            if ($records[$index]['streamid'] === $id) {
                $matches[] = $key;
            }
        }

        // Do not guess if multiple active streams share an ID.
        if (count($matches) === 1) {
            $key = $matches[0];
            $index = $active[$key];

            $records[$index]['status'] = 'Stale stream removed';
            unset($active[$key]);
        } else {
            $staleUnmatched++;
        }

        continue;
    }

    // Transmission completion
    if (preg_match(
        '/Remote gateway tx end\s+\(([^)]+)\)\s+streamid\s+([0-9a-f]+):[0-9a-f]+\s+cnt\s+(\d+)\s+missed\s+(\d+)\s+elapsed\s+([0-9:]+)/i',
        $line, $m
    )) {
        $key = trim($m[1]) . '|' . dplusAdapterNormalizeStreamId($m[2]);

        if (isset($active[$key])) {
            $i = $active[$key];

            $records[$i]['duration'] = $m[5];
            $records[$i]['missed'] = $m[4];
            $records[$i]['status'] = 'Completed';

            unset($active[$key]);
        }
    }
}

fclose($fh);


return array(
    'records' => array_reverse($records),
    'error' => '',
    'stats' => array(
        'local_starts' => $localStarts,
        'local_ends' => $localEnds,
        'local_active' => count($localActive),
        'remote_total' => count($records)
    )
);
}
