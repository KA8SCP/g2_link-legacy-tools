<?php
require_once __DIR__ . '/dashboard_config.php';
/*
 * Local DPlus forwarding headers.
 * Development only. PHP 5.4 compatible.
 * Preserve logged headers; do not infer them from link state.
 */
function dplusLocalHeadersRead($logfile)
{
    $result = array('records' => array(), 'error' => '');
    $fh = @fopen($logfile, 'r');
    if (!$fh) {
        $result['error'] = 'Cannot read local header log';
        return $result;
    }

    $pending = null;
    $session = 0;
    $lineNumber = 0;

    while (($line = fgets($fh)) !== false) {
        $lineNumber++;

        if (preg_match('/^D-Star Plus version .* startup /', $line)) {
            $session++;
            $pending = null;
            continue;
        }

        if (preg_match(
            '/^Remote client forward start streamid\s+' .
            '([0-9a-f]+)\s+(\d{4}\/\d{2}\/\d{2} ' .
            '\d{2}:\d{2}:\d{2}(?:\.\d+)?)\s*$/i',
            $line, $m
        )) {
            $pending = array(
                'time' => $m[2],
                'streamid' => strtolower($m[1]),
                'session' => $session,
                'line_number' => $lineNumber
            );
            continue;
        }

        if ($pending === null) {
            continue;
        }

        if (preg_match(
            '/^\s+rpt2\s+\(([^)]*)\)\s+rpt1\s+\(([^)]*)\)' .
            '\s+urcall\s+\(([^)]*)\)\s+mycall\s+\(([^)]*)\)/i',
            $line, $m
        )) {
            $rpt1 = preg_replace('/\s+/', ' ', trim($m[2]));
            $rpt2 = preg_replace('/\s+/', ' ', trim($m[1]));
            $parts = explode('/', trim($m[4]));
            $call = strtoupper(trim($parts[0]));

            if ($call !== '' &&
                preg_match('/^' . preg_quote(dashboardConfig('callsign'), '/') . ' ([A-E])$/', $rpt1, $module)) {
                $record = $pending;
                $record['callsign'] = $call;
                $record['module'] = $module[1];
                $record['rpt1'] = $rpt1;
                $record['rpt2'] = $rpt2;
                $record['urcall'] = trim($m[3]);
                $result['records'][] = $record;
            }
        }

        // Only the immediately following header belongs to this start.
        $pending = null;
    }

    if (!feof($fh)) {
        $result['error'] = 'Local header log read failed';
        $result['records'] = array();
    }
    fclose($fh);
    return $result;
}

/*
 * Match local activity to one logged forwarding start.
 * Multiple candidates remain ambiguous, even if headers agree.
 * Remote activity is never enriched by this function.
 */
function dplusLocalHeadersAnnotate($rows, $headers)
{
    $matches = array();
    $zone = new DateTimeZone(dashboardConfig('timezone'));

    foreach ($headers as $header) {
        $date = DateTime::createFromFormat(
            '!Y/m/d H:i:s', substr($header['time'], 0, 19), $zone
        );
        $errors = DateTime::getLastErrors();
        if ($date === false ||
            ($errors !== false &&
             ($errors['warning_count'] || $errors['error_count']))) {
            continue;
        }

        $key = strtoupper(trim($header['callsign'])) . '|' .
            trim($header['module']) . '|' . $date->getTimestamp();
        if (!isset($matches[$key])) {
            $matches[$key] = array();
        }
        $matches[$key][] = $header;
    }

    foreach ($rows as &$row) {
        if (!isset($row['activity']) ||
            $row['activity'] !== 'Local RF (reported)') {
            continue;
        }

        $row['local_header_source'] = 'no_match';
        if (!isset($row['epoch'], $row['callsign'], $row['module'])) {
            continue;
        }

        $key = strtoupper(trim($row['callsign'])) . '|' .
            trim($row['module']) . '|' . (int)$row['epoch'];
        if (!isset($matches[$key])) {
            continue;
        }
        if (count($matches[$key]) !== 1) {
            $row['local_header_source'] = 'ambiguous';
            continue;
        }

        $header = $matches[$key][0];
        if ((isset($row['rpt1']) && $row['rpt1'] !== '' &&
             $row['rpt1'] !== $header['rpt1']) ||
            (isset($row['rpt2']) && $row['rpt2'] !== '' &&
             $row['rpt2'] !== $header['rpt2'])) {
            $row['local_header_source'] = 'conflict';
            continue;
        }

        $row['rpt1'] = $header['rpt1'];
        $row['rpt2'] = $header['rpt2'];
        $row['local_header_source'] = 'dplus_log';
    }
    unset($row);
    return $rows;
}
