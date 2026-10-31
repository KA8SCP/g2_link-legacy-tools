<?php
require_once __DIR__ . '/dashboard_config.php';
/* Retained copytruncate archives plus active log. PHP 5.4. */
require_once __DIR__ . '/g2_remote_history.php';

function g2DashboardHistoryRead($logfile)
{
    $result = array(
        'records' => array(), 'excluded' => 0, 'error' => '',
        'sources' => array(), 'clock_regressions' => 0,
        'duplicate_records' => 0,
        'link_history' => array('events'=>array(), 'first'=>null, 'last'=>null, 'ordered'=>false)
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
    $previousStamp = null;

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
        // PHP 5.4 fgets() is very slow on multi-megabyte NUL-only lines.
        // Normalize in bounded blocks before line parsing. A missing region
        // invalidates link evidence; never join text across a NUL gap.
        $normalized = tmpfile();
        if (!$normalized) {
            fclose($fh);
            $result['error'] = 'Cannot create normalized g2_link source';
            break;
        }
        while (!feof($fh)) {
            $block = fread($fh, 65536);
            if ($block === false || ($block === '' && !feof($fh))) {
                $result['error'] = 'Cannot read g2_link source';
                break;
            }
            $block = preg_replace('/\\x00+/', "\nstartup: NUL gap; clear link evidence\n", $block);
            if ($block === null || fwrite($normalized, $block) !== strlen($block)) {
                $result['error'] = 'Cannot normalize g2_link source';
                break;
            }
        }
        fclose($fh);
        $fh = $normalized;
        if ($result['error'] !== '') {
            fclose($fh);
            break;
        }
        rewind($fh);
        $firstStamp = null;
        $lastStamp = null;
        while (($line = fgets($fh)) !== false) {
            // Ignore an unfinished final line in a live log.
            if (substr($line, -1) !== "\n") break;

            if (preg_match('/^(\d{6}) at (\d{2}:\d{2}:\d{2}):/', $line, $m)) {
                $stamp = $m[1] . ' ' . $m[2];
                if ($stamp !== $previousStamp) {
                $date = DateTime::createFromFormat(
                    '!mdy H:i:s', $stamp, $zone
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
                $previousStamp = $stamp;
                }
                $lastStamp = $m[1] . ' at ' . $m[2];
                if ($firstStamp === null) {
                    $firstStamp = $lastStamp;
                    $boundary = $firstStamp . ":history coverage boundary\n";
                    if (fwrite($copy, $boundary) !== strlen($boundary)) {
                        $result['error'] = 'Cannot write g2_link coverage boundary';
                        break;
                    }
                }
            }

            // Keep every state-changing pattern used by g2RemoteHistoryRead,
            // plus DCS and XRF/DExtra headers. Other lines have already had their timestamps
            // checked above and need not be copied and scanned twice more.
            if (!preg_match('/startup|starting|shutdown|timeout|timed out|disconnect|' .
                'sending link request|Unlinking from|sending File:|START from (?:dcs|remote g2):/i', $line)) continue;
            if (fwrite($copy, $line) !== strlen($line)) {
                $result['error'] = 'Cannot copy g2_link source';
                break;
            }
        }
        if ($result['error'] === '' && $lastStamp !== null) {
            $boundary = $lastStamp . ":history coverage boundary\n";
            if (fwrite($copy, $boundary) !== strlen($boundary)) {
                $result['error'] = 'Cannot write g2_link coverage boundary';
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
    $result['link_history'] = $history['link_history'];
    if ($result['clock_regressions']) $result['link_history']['ordered'] = false;

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

/* Only confirmed DCS and XRF/DExtra associations are currently supported. Absence of evidence
 * is Unknown, never an assertion that the module was disconnected. */
function g2DashboardLinkAt($history, $epoch, $module)
{
    if ($history['error'] !== '' || !isset($history['link_history']) ||
        !is_numeric($epoch) || !in_array($module, array('A','B','C','D','E'), true)) return 'Unknown';
    $index = $history['link_history'];
    if (!$index['ordered'] || $index['first'] === null ||
        $epoch < $index['first'] || $epoch + 1 > $index['last']) return 'Unknown';
    $events = $index['events'];
    $lo = 0; $hi = count($events);
    while ($lo < $hi) {
        $mid = (int)floor(($lo + $hi) / 2);
        if ($events[$mid]['epoch'] < $epoch) $lo = $mid + 1;
        else $hi = $mid;
    }
    $selected = $lo - 1;
    // A change in the transmission's one-second timestamp window is ambiguous.
    if (isset($events[$lo]) && $events[$lo]['epoch'] <= $epoch + 1) return 'Unknown';
    if ($selected < 0 || !isset($events[$selected]['state'][$module])) return 'Unknown';
    return $events[$selected]['state'][$module]['via'];
}

function dashboardAssociatedLinksAnnotate($history, $rows)
{
    foreach ($rows as &$row) {
        $dplus = isset($row['dplus_history']) ? $row['dplus_history'] : 'Unknown';
        if ($row['activity'] === 'Remote' && $row['connection'] === 'g2_link') {
            // This row already passed unique source/IP/module confirmation.
            $row['associated_link'] = isset($row['linked_module_source']) &&
                $row['linked_module_source'] === 'g2_link_announcement' ?
                'g2_link: '.$row['via'] : 'g2_link: Unknown';
            continue;
        }
        $module = $row['module'];
        if ($row['activity'] === 'Remote') {
            $module = isset($row['linked_local_module']) ? $row['linked_local_module'] : 'UNKNOWN';
        }
        $g2 = g2DashboardLinkAt($history, $row['epoch'], $module);
        $row['associated_link'] = 'DPlus: '.$dplus.'; g2_link: '.$g2;
    }
    unset($row);
    return $rows;
}
