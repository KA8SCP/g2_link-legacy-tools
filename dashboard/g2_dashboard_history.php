<?php
require_once __DIR__ . '/dashboard_config.php';
/* Retained copytruncate archives plus active log. PHP 5.4. */
require_once __DIR__ . '/g2_remote_history.php';

function g2DashboardHistoryRead($logfile)
{
    $result = array(
        'records' => array(), 'excluded' => 0, 'error' => '',
        'sources' => array(), 'clock_regressions' => 0,
        'duplicate_records' => 0
    );
    $paths = glob($logfile . '-*');
    $archives = array();
    if ($paths !== false) {
        foreach ($paths as $path) {
            $suffix = substr($path, strlen($logfile) + 1);
            if (!preg_match('/^\d{8}$/', $suffix) || !is_file($path)) continue;
            $date = DateTime::createFromFormat(
                '!Ymd', $suffix, new DateTimeZone(dashboardConfig('timezone'))
            );
            if ($date !== false && $date->format('Ymd') === $suffix) {
                $archives[$suffix] = $path;
            }
        }
    }
    ksort($archives, SORT_STRING);
    $result['sources'] = array_values($archives);
    $result['sources'][] = $logfile;
    $copy = tmpfile();
    if (!$copy) {
        $result['error'] = 'Cannot create combined g2_link log';
        return $result;
    }
    $zone = new DateTimeZone(dashboardConfig('timezone'));
    $previousTime = null;
    $previousArchive = null;

    foreach ($result['sources'] as $path) {
        $suffix = substr($path, strlen($logfile) + 1);
        if (isset($archives[$suffix])) {
            $archiveDate = DateTime::createFromFormat('!Ymd', $suffix, $zone);
            if ($previousArchive !== null &&
                $archiveDate->getTimestamp() -
                $previousArchive->getTimestamp() > 90000) {
                fwrite($copy, "startup: archive gap; clear link evidence\n");
            }
            $previousArchive = $archiveDate;
        }
        $fh = @fopen($path, 'r');
        if (!$fh) {
            $result['error'] = 'Cannot read g2_link source: ' . $path;
            break;
        }
        while (($line = fgets($fh)) !== false) {
            // Ignore an unfinished final line in a live log.
            if (substr($line, -1) !== "\n") break;

            if (preg_match('/^(\d{6}) at (\d{2}:\d{2}:\d{2}):/', $line, $m)) {
                $date = DateTime::createFromFormat(
                    '!mdy H:i:s', $m[1] . ' ' . $m[2], $zone
                );
                $errors = DateTime::getLastErrors();
                if ($date === false ||
                    ($errors !== false &&
                     ($errors['warning_count'] || $errors['error_count']))) {
                    $result['error'] = 'Invalid g2_link log timestamp';
                    break;
                }
                $epoch = $date->getTimestamp();
                if ($previousTime !== null && $epoch < $previousTime) {
                    $result['clock_regressions']++;
                    $reset = "startup: clock regression; clear link evidence\n";
                    if (fwrite($copy, $reset) !== strlen($reset)) {
                        $result['error'] = 'Cannot write g2_link clock boundary';
                        break;
                    }
                }
                $previousTime = $epoch;
            }

            if (fwrite($copy, $line) !== strlen($line)) {
                $result['error'] = 'Cannot copy g2_link source';
                break;
            }
        }
        if ($result['error'] === '' && !feof($fh) &&
            ($line === false || substr($line, -1) === "\n")) {
            $result['error'] = 'g2_link source read failed';
        }
        fclose($fh);
        if ($result['error'] !== '') break;
    }

    if ($result['error'] !== '' || !fflush($copy)) {
        if ($result['error'] === '') {
            $result['error'] = 'Cannot flush combined g2_link log';
        }
        fclose($copy);
        return $result;
    }
    $meta = stream_get_meta_data($copy);
    $history = g2RemoteHistoryRead($meta['uri']);
    fclose($copy);
    if ($history['error'] !== '') {
        $result['error'] = $history['error'];
        return $result;
    }
    $result['excluded'] = $history['excluded'];

    $unique = array();
    $conflicts = array();
    foreach ($history['records'] as $row) {
        $key = $row['epoch'] . '|' . $row['streamid'] . '|' .
            $row['callsign'] . '|' . $row['via'] . '|' . $row['remote_ip'];
        if (isset($conflicts[$key])) {
            $result['excluded']++;
            continue;
        }
        if (isset($unique[$key])) {
            $before = $unique[$key];
            if ($before['suffix'] !== $row['suffix'] ||
                $before['rpt1'] !== $row['rpt1'] ||
                $before['rpt2'] !== $row['rpt2'] ||
                $before['linked_local_module'] !== $row['linked_local_module']) {
                unset($unique[$key]);
                $conflicts[$key] = true;
                $result['excluded'] += 2;
            } else {
                $result['duplicate_records']++;
            }
            continue;
        }
        $unique[$key] = $row;
    }
    $result['records'] = array_values($unique);
    return $result;
}
