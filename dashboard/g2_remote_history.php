<?php
/* DCS and XRF/DExtra history. PHP 5.4 compatible. */
require_once __DIR__ . '/g2_remote_headers.php';

function g2RemoteHistoryRead($logfile)
{
    $result = array('records' => array(), 'excluded' => 0, 'error' => '',
        'link_history' => array('events'=>array(), 'first'=>null, 'last'=>null, 'ordered'=>true));
    $source = @fopen($logfile, 'r');
    $copy = tmpfile();
    if (!$source || !$copy) {
        if ($source) fclose($source);
        if ($copy) fclose($copy);
        $result['error'] = 'Cannot create g2_link history copy';
        return $result;
    }
    $stat = fstat($source);
    $bytes = stream_copy_to_stream($source, $copy);
    fclose($source);
    if ($stat === false || $bytes === false ||
        $bytes < $stat['size'] || !fflush($copy)) {
        fclose($copy);
        $result['error'] = 'Cannot complete g2_link history copy';
        return $result;
    }
    $meta = stream_get_meta_data($copy);
    $headers = g2RemoteHeadersRead($meta['uri']);
    if ($headers['error'] !== '') {
        fclose($copy);
        $result['error'] = $headers['error'];
        return $result;
    }
    $byLine = array();
    foreach ($headers['records'] as $header) {
        $byLine[$header['line_number']] = $header;
    }
    rewind($copy);
    $state = array();
    $pending = array();
    $lineNumber = 0;
    $epoch = null;
    $previousState = array();
    $zone = new DateTimeZone(dashboardConfig('timezone'));

    while (($line = fgets($copy)) !== false) {
        $lineNumber++;
        if (preg_match('/^(\d{6}) at (\d{2}:\d{2}:\d{2}):/', $line, $stamp)) {
            $date = DateTime::createFromFormat('!mdy H:i:s', $stamp[1].' '.$stamp[2],
                $zone);
            $errors = DateTime::getLastErrors();
            if ($date === false || ($errors !== false &&
                ($errors['warning_count'] || $errors['error_count']))) {
                $result['link_history']['ordered'] = false;
            } else {
                $nextEpoch = $date->getTimestamp();
                if ($epoch !== null && $nextEpoch < $epoch) $result['link_history']['ordered'] = false;
                $epoch = $nextEpoch;
                if ($result['link_history']['first'] === null) $result['link_history']['first'] = $epoch;
                $result['link_history']['last'] = $epoch;
            }
        }
        // inbound_list client expiry is not a reflector unlink. Preserve
        // confirmed link evidence for this exact legacy diagnostic only;
        // genuine/unknown timeout, disconnect and restart events still reset it.
        $clientExpiry = preg_match(
            '/^\d{6} at \d{2}:\d{2}:\d{2}:call=[^,\r\n]+ timeout, removing [0-9.]+, users=\d+\s*$/',
            $line
        );
        if (!$clientExpiry &&
            preg_match('/startup|starting|shutdown|timeout|timed out|disconnect/i', $line)) {
            $state = array();
            $pending = array();
        }
        // Every new link request invalidates the previous association.
        if (preg_match(
            '/sending link request(?:\([^)]*\))? from mod ([A-E]) /',
            $line, $request
        )) {
            unset($state[$request[1]], $pending[$request[1]]);
        }
        if (preg_match(
            '/sending link request(?:\(dextra\))? from mod ([A-E]) to link with: ' .
            '\[((?:DCS|XRF)\d{3})\s*\] mod ([A-Z]) \[([0-9.]+)\]/', $line, $m
        )) {
            // Accept only known request/transport pairs. Every request above
            // already clears old evidence, including unsupported transports.
            $dextra = strpos($line, 'sending link request(dextra) ') !== false;
            if (($dextra && substr($m[2], 0, 3) === 'XRF') ||
                (!$dextra && substr($m[2], 0, 3) === 'DCS')) {
                $pending[$m[1]] = array(
                    'via' => $m[2] . ' ' . $m[3], 'ip' => $m[4]
                );
            }
        }
        if (preg_match('/Unlinking from \[((?:DCS|XRF)\d{3})\s*\] mod ([A-Z])/', $line, $m)) {
            $via = $m[1] . ' ' . $m[2];
            foreach ($state as $module => $link) {
                if ($link['via'] === $via) unset($state[$module]);
            }
            foreach ($pending as $module => $link) {
                if ($link['via'] === $via) unset($pending[$module]);
            }
        }
        if (preg_match('/sending File:\[[^]]*\/unlinked\.dat\], mod:\[([A-E])\]/', $line, $m)) {
            unset($state[$m[1]], $pending[$m[1]]);
        }
        if (preg_match(
            '/sending File:\[[^]]*\/linked\.dat\], mod:\[([A-E])\], ' .
            'RADIO_ID=\[LINKED ((?:DCS|XRF)\d{3}) ([A-Z])\s*\]/', $line, $m
        )) {
            $module = $m[1];
            $via = $m[2] . ' ' . $m[3];
            unset($state[$module]);
            if (isset($pending[$module]) &&
                $pending[$module]['via'] === $via) {
                $state[$module] = $pending[$module];
            }
            unset($pending[$module]);
        }

        // Reuse the same confirmed state as remote stream association; no second log scan.
        if ($state !== $previousState) {
            if ($epoch !== null) {
                $result['link_history']['events'][] = array('epoch'=>$epoch, 'state'=>$state);
            }
            $previousState = $state;
        }

        if (!isset($byLine[$lineNumber])) continue;
        $header = $byLine[$lineNumber];
        $matches = array();
        foreach ($state as $module => $link) {
            if ($link['via'] === $header['source'] &&
                $link['ip'] === $header['remote_ip']) {
                $matches[] = $module;
            }
        }
        if (count($matches) !== 1) {
            $result['excluded']++;
            continue;
        }

        $row = $header;
        $row['module'] = 'UNKNOWN';
        $row['linked_local_module'] = $matches[0];
        $row['linked_module_source'] = 'g2_link_announcement';
        $row['module_source'] = 'g2_log_association';
        $row['activity'] = 'Remote';
        $row['connection'] = 'g2_link';
        $row['via'] = $header['source'];
        $row['message'] = '';
        $row['rf_delivery'] = 'unconfirmed';
        $row['history_session'] = '';
        $result['records'][] = $row;
    }

    if (!feof($copy)) {
        $result['records'] = array();
        $result['error'] = 'g2_link history scan failed';
    }
    fclose($copy);
    return $result;
}
