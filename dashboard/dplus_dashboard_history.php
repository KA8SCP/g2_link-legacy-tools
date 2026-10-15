<?php
require_once __DIR__ . '/dashboard_config.php';
/*
 * Dashboard history across the newest rotation and active log.
 * PHP 5.4 compatible. Restart sessions remain independent.
 */
require_once __DIR__ . '/dplus_historical_adapter.php';
require_once __DIR__ . '/dplus_connection_timeline.php';
require_once __DIR__ . '/dplus_local_headers.php';

function dplusDashboardHistorySources($logfile)
{
    $archives = array();
    $matches = glob($logfile . '-*');
    if ($matches !== false) {
        foreach ($matches as $path) {
            $suffix = substr($path, strlen($logfile) + 1);
            if (!preg_match('/^[0-9]{8}$/', $suffix)) {
                continue;
            }
            $date = DateTime::createFromFormat(
                '!Ymd', $suffix, new DateTimeZone(dashboardConfig('timezone'))
            );
            if ($date !== false && $date->format('Ymd') === $suffix &&
                is_file($path)) {
                $archives[] = $path;
            }
        }
    }

    sort($archives, SORT_STRING);
    $sources = array();
    if (!empty($archives)) {
        $sources[] = $archives[count($archives) - 1];
    }
    $sources[] = $logfile;
    return $sources;
}

function dplusDashboardHistoryFinish($copy, $id, &$result)
{
    if (!fflush($copy)) {
        $result['error'] = 'Cannot flush history session';
        return;
    }
    $meta = stream_get_meta_data($copy);
    $activity = dplusHistoricalRead($meta['uri']);
    $timeline = dplusTimelineBuild($meta['uri']);
    $local = dplusLocalHeadersRead($meta['uri']);

    if ($activity['error'] !== '' || $timeline['error'] !== '' ||
        $local['error'] !== '') {
        $result['error'] = 'Cannot parse history session: ' . $id;
        return;
    }

    foreach ($local['records'] as $header) {
        $header['history_session'] = $id;
        $result['local_headers'][] = $header;
    }
    $result['timelines'][$id] = $timeline;
    foreach ($activity['records'] as $record) {
        $record['history_session'] = $id;
        $result['records'][] = $record;
    }
}

function dplusDashboardHistoryRead($logfile)
{
    $result = array(
        'sources' => dplusDashboardHistorySources($logfile),
        'records' => array(),
        'local_headers' => array(),
        'timelines' => array(),
        'error' => ''
    );

    foreach ($result['sources'] as $path) {
        $fh = @fopen($path, 'r');
        if (!$fh) {
            $result['error'] = 'Cannot read history source: ' . $path;
            break;
        }
        $copy = null;
        $lineNumber = 0;
        $id = '';

        while (($line = fgets($fh)) !== false) {
            $lineNumber++;
            $startup = preg_match('/^D-Star Plus version .* startup /', $line);
            if ($copy !== null && $startup) {
                dplusDashboardHistoryFinish($copy, $id, $result);
                fclose($copy);
                $copy = null;
                if ($result['error'] !== '') {
                    break;
                }
            }
            if ($copy === null) {
                $copy = tmpfile();
                if (!$copy) {
                    $copy = null;
                    $result['error'] = 'Cannot create history session';
                    break;
                }
                $id = $path . ':' . $lineNumber;
            }
            if (fwrite($copy, $line) !== strlen($line)) {
                $result['error'] = 'Cannot write history session';
                break;
            }
        }

        if ($result['error'] === '' && !feof($fh)) {
            $result['error'] = 'History source read failed: ' . $path;
        }
        fclose($fh);
        if ($copy !== null) {
            if ($result['error'] === '') {
                dplusDashboardHistoryFinish($copy, $id, $result);
            }
            fclose($copy);
        }
        if ($result['error'] !== '') {
            break;
        }
    }

    if ($result['error'] !== '') {
        $result['records'] = array();
        $result['local_headers'] = array();
        $result['timelines'] = array();
    }
    return $result;
}

function dplusDashboardHistoryAnnotate($history, $rows)
{
    // Coverage is invariant across the displayed activity rows.
    $coverage = array();
    foreach ($history['timelines'] as $id => $timeline) {
        $index = isset($timeline['snapshot_index']) ? $timeline['snapshot_index'] :
            dplusTimelineSnapshotIndex($timeline['snapshots']);
        $coverage[$id] = array('first'=>$index['first'], 'last'=>$index['last']);
    }
    if ($history['error'] === '' && isset($history['local_headers'])) {
        $rows = dplusLocalHeadersAnnotate($rows, $history['local_headers']);
    }
    foreach ($rows as &$row) {
        $row['dplus_history'] = 'Unknown';
        $row['historical_overall_connection'] = 'unknown';
        if ($history['error'] !== '') {
            continue;
        }

        $candidates = array();
        if (isset($row['activity']) && $row['activity'] === 'Remote') {
            if (isset($row['history_session']) &&
                isset($history['timelines'][$row['history_session']])) {
                $candidates[] = $row['history_session'];
            }
        } elseif (isset($row['epoch']) && is_numeric($row['epoch'])) {
            foreach ($coverage as $id => $range) {
                $first = $range['first'];
                $last = $range['last'];
                if ($first !== null &&
                    $row['epoch'] >= $first && $row['epoch'] <= $last) {
                    $candidates[] = $id;
                }
            }
        }

        if (count($candidates) !== 1) {
            continue;
        }

        $annotated = dplusTimelineAnnotateActivity(
            $history['timelines'][$candidates[0]], array($row)
        );
        $row = $annotated[0];
    }
    unset($row);
    return $rows;
}
