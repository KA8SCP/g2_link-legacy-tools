<?php
require_once __DIR__ . '/dashboard_config.php';
/*
 * Collect logged incoming DCS headers and callsign suffixes.
 * PHP 5.4 compatible. Suffix is separate from User Message.
 */
function g2RemoteHeadersRead($logfile)
{
    $result = array('records' => array(), 'error' => '');
    $fh = @fopen($logfile, 'r');
    if (!$fh) {
        $result['error'] = 'Cannot read g2_link header log';
        return $result;
    }

    $zone = new DateTimeZone(dashboardConfig('timezone'));
    $lineNumber = 0;
    while (($line = fgets($fh)) !== false) {
        $lineNumber++;
        if (!preg_match(
            '/^(\d{6}) at (\d{2}:\d{2}:\d{2}):START from dcs: ' .
            'streamID=(\d+,\d+), my=([^,]*), sfx=([^,]*), ' .
            'ur=([^,]*), rpt1=([^,]*), rpt2=([^,]*), ' .
            '\d+ bytes fromIP=([0-9.]+), source=([^\r\n]+)\s*$/',
            $line, $m
        )) {
            continue;
        }

        $date = DateTime::createFromFormat(
            '!mdy H:i:s', $m[1] . ' ' . $m[2], $zone
        );
        $errors = DateTime::getLastErrors();
        if ($date === false ||
            ($errors !== false &&
             ($errors['warning_count'] || $errors['error_count'])) ||
            $date->format('mdy H:i:s') !== $m[1] . ' ' . $m[2]) {
            continue;
        }

        $call = strtoupper(trim($m[4]));
        if ($call === '') {
            continue;
        }

        $result['records'][] = array(
            'callsign' => $call,
            'epoch' => $date->getTimestamp(),
            'time' => $date->format('Y-m-d H:i:s'),
            'streamid' => $m[3],
            'suffix' => strtoupper(trim($m[5])),
            'urcall' => trim($m[6]),
            'rpt1' => preg_replace('/\s+/', ' ', trim($m[7])),
            'rpt2' => preg_replace('/\s+/', ' ', trim($m[8])),
            'remote_ip' => $m[9],
            'source' => preg_replace('/\s+/', ' ', trim($m[10])),
            'line_number' => $lineNumber
        );
    }

    if (!feof($fh)) {
        $result['error'] = 'g2_link header log read failed';
        $result['records'] = array();
    }
    fclose($fh);
    return $result;
}

/*
 * Enrich g2_link last-heard activity only with one exact header match.
 * Match callsign, source and second; preserve the reported local module.
 */
function g2RemoteHeadersAnnotate($rows, $headers)
{
    $index = array();
    foreach ($headers as $header) {
        $key = strtoupper(trim($header['callsign'])) . '|' .
            strtoupper(trim($header['source'])) . '|' . $header['epoch'];
        if (!isset($index[$key])) {
            $index[$key] = array();
        }
        $index[$key][] = $header;
    }

    foreach ($rows as &$row) {
        if (!isset($row['module_source']) ||
            $row['module_source'] !== 'g2_remote_report' ||
            $row['activity'] !== 'Remote' ||
            $row['connection'] !== 'g2_link') {
            continue;
        }

        $row['g2_header_source'] = 'no_match';
        $key = strtoupper(trim($row['callsign'])) . '|' .
            strtoupper(trim($row['via'])) . '|' . $row['epoch'];
        if (!isset($index[$key])) {
            continue;
        }
        if (count($index[$key]) !== 1) {
            $row['g2_header_source'] = 'ambiguous';
            continue;
        }

        $header = $index[$key][0];
        if ((isset($row['rpt1']) && $row['rpt1'] !== '' &&
             $row['rpt1'] !== $header['rpt1']) ||
            (isset($row['rpt2']) && $row['rpt2'] !== '' &&
             $row['rpt2'] !== $header['rpt2']) ||
            (isset($row['suffix']) && $row['suffix'] !== '' &&
             $row['suffix'] !== $header['suffix'])) {
            $row['g2_header_source'] = 'conflict';
            continue;
        }

        $row['rpt1'] = $header['rpt1'];
        $row['rpt2'] = $header['rpt2'];
        $row['suffix'] = $header['suffix'];
        $row['streamid'] = $header['streamid'];
        $row['g2_header_source'] = 'g2_log';
    }
    unset($row);
    return $rows;
}
