<?php
require_once __DIR__ . '/dashboard_config.php';
/*
 * DPlus historical connection timeline: event-bound collection.
 * Development only. PHP 5.4 compatible.
 *
 * Bounds describe surrounding evidence, not exact event times.
 * DPlus unlink does not establish overall gateway disconnection.
 */

require_once __DIR__ . '/dplus_linkstate.php';

/*
 * Only recognized current log messages can supply time anchors.
 * Last Heard user rows contain historical activity and are excluded.
 * Last heard list headers are excluded because they truncate seconds.
 */
function dplusTimelineParseAnchor($line, $lineNumber)
{
    if (!preg_match(
        '/^(?:Linked gateway list:|User update \(|' .
        'Reading link file |Reading file |Finished reading file |' .
        'Reflector\/Gateway unlink request |Reflector link request |' .
        'Remote gateway tx start )/',
        $line
    )) {
        return null;
    }

    if (!preg_match(
        '/(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2})' .
        '(?:\.(\d+))?\s*$/',
        $line, $m
    )) {
        return null;
    }

    $date = DateTime::createFromFormat(
        '!Y/m/d H:i:s',
        $m[1],
        new DateTimeZone(dashboardConfig('timezone'))
    );
    $errors = DateTime::getLastErrors();

    if ($date === false ||
        ($errors !== false &&
         ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    $fraction = isset($m[2]) ? $m[2] : '';
    $epoch = (float)$date->getTimestamp();
    if ($fraction !== '') {
        $epoch += (float)('0.' . $fraction);
    }

    return array(
        'line_number' => $lineNumber,
        'text' => $m[1] . ($fraction === '' ? '' : '.' . $fraction),
        'lower_epoch' => $epoch,
        // A whole-second anchor has one second of uncertainty.
        'upper_epoch' => $fraction === '' ? $epoch + 1.0 : $epoch
    );
}

function dplusTimelineCollectBounds($logfile)
{
    $result = array(
        'events' => array(),
        'anchor_count' => 0,
        'clock_regressions' => array(),
        'error' => ''
    );

    $fh = @fopen($logfile, 'r');
    if (!$fh) {
        $result['error'] = 'Cannot open log';
        return $result;
    }

    $lineNumber = 0;
    $previous = null;
    $pending = array();

    while (($line = fgets($fh)) !== false) {
        $lineNumber++;
        $anchor = dplusTimelineParseAnchor($line, $lineNumber);

        if ($anchor !== null) {
            $result['anchor_count']++;

            if ($previous !== null &&
                $anchor['upper_epoch'] < $previous['lower_epoch']) {
                $result['clock_regressions'][] = array(
                    'before_line' => $previous['line_number'],
                    'after_line' => $anchor['line_number']
                );
            }

            foreach ($pending as $index) {
                $result['events'][$index]['after_anchor'] = $anchor;
                $result['events'][$index]['upper_epoch'] =
                    $anchor['upper_epoch'];
            }

            $pending = array();
            $previous = $anchor;
        }

        $event = dplusLinkstateParseSuccess($line);
        if ($event === null) {
            $event = dplusLinkstateParseUnlink($line);
        }
        if ($event === null) {
            $event = dplusLinkstateParseTimeout($line);
        }
        if ($event === null) {
            continue;
        }

        $event['line_number'] = $lineNumber;
        $event['exact_epoch'] = null;
        $event['before_anchor'] = $previous;
        $event['after_anchor'] = null;
        $event['lower_epoch'] = $previous === null
            ? null : $previous['lower_epoch'];
        $event['upper_epoch'] = null;

        $index = count($result['events']);
        $result['events'][] = $event;
        $pending[] = $index;
    }

    if (!feof($fh)) {
        $result['error'] = 'Log read failed';
    }
    fclose($fh);

    /*
     * Retain line-order evidence, but disable time bounds if the
     * recognized clock anchors regress or the read is incomplete.
     */
    if (!empty($result['clock_regressions']) || $result['error'] !== '') {
        foreach ($result['events'] as &$event) {
            $event['lower_epoch'] = null;
            $event['upper_epoch'] = null;
        }
        unset($event);
    }

    return $result;
}

/*
 * Replay confirmed events in log order.
 * A missing module means unknown, not confirmed unlinked.
 * Overall connection remains unknown without g2_link evidence.
 */
function dplusTimelineReplayEvents($events)
{
    $state = array();
    $transitions = array();
    $unresolved = array();

    foreach ($events as $event) {
        $before = $state;
        $affected = array();

        if ($event['type'] === 'linked') {
            $module = $event['local_module'];
            $state[$module] = array(
                'dplus_status' => 'linked',
                'connection' => array(
                    'reflector' => $event['reflector'],
                    'remote_module' => $event['remote_module'],
                    'remote_ip' => $event['remote_ip']
                ),
                'overall_connection' => 'unknown'
            );
            $affected[] = $module;

        } elseif ($event['type'] === 'unlinked') {
            $module = $event['local_module'];
            $state[$module] = array(
                'dplus_status' => 'unlinked',
                'connection' => null,
                'overall_connection' => 'unknown'
            );
            $affected[] = $module;

        } elseif ($event['type'] === 'timeout') {
            foreach ($state as $module => $connection) {
                if ($connection['dplus_status'] === 'linked' &&
                    $connection['connection']['remote_ip'] ===
                    $event['remote_ip']) {
                    $state[$module] = array(
                        'dplus_status' => 'unlinked',
                        'connection' => null,
                        'overall_connection' => 'unknown'
                    );
                    $affected[] = $module;
                }
            }

            if (empty($affected)) {
                $unresolved[] = $event;
            }
        }

        ksort($state);
        $transition = $event;
        $transition['affected_modules'] = $affected;
        $transition['state_before'] = $before;
        $transition['state_after'] = $state;
        $transitions[] = $transition;
    }

    return array(
        'transitions' => $transitions,
        'final_state' => $state,
        'unresolved_timeouts' => $unresolved
    );
}

/*
 * Attach log positions and precise anchors to existing snapshots.
 * A snapshot containing a transition is unsuitable for reconciliation.
 */
function dplusTimelineCollectSnapshots($logfile)
{
    $result = dplusLinkstateCollectSnapshots($logfile);
    if ($result['error'] !== '') {
        return $result;
    }

    $fh = @fopen($logfile, 'r');
    if (!$fh) {
        $result['error'] = 'Cannot read snapshot positions';
        return $result;
    }

    $positions = array();
    $current = null;
    $lineNumber = 0;

    while (($line = fgets($fh)) !== false) {
        $lineNumber++;

        if (preg_match(
            '/^Linked gateway list:\s+' .
            '(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?)/',
            $line, $m
        )) {
            if ($current !== null) {
                // The next snapshot header closes the previous list.
                $current['end_line'] = $lineNumber - 1;
                $positions[] = $current;
            }
            $current = array(
                'header_time' => $m[1],
                'line_number' => $lineNumber,
                'end_line' => null,
                'anchor' => dplusTimelineParseAnchor($line, $lineNumber),
                'interleaved_event' => false
            );
            continue;
        }

        if ($current === null) {
            continue;
        }

        if (dplusLinkstateParseSuccess($line) !== null ||
            dplusLinkstateParseUnlink($line) !== null ||
            dplusLinkstateParseTimeout($line) !== null) {
            $current['interleaved_event'] = true;
        }

        if (preg_match('/^Last heard list:/i', $line)) {
            $current['end_line'] = $lineNumber;
            $positions[] = $current;
            $current = null;
        }
    }

    if ($current !== null) {
        $positions[] = $current;
    }
    $readFailed = !feof($fh);
    fclose($fh);

    if ($readFailed ||
        count($positions) !== count($result['snapshots'])) {
        $result['error'] = 'Snapshot position scan inconsistent';
        $result['snapshots'] = array();
        return $result;
    }

    foreach ($result['snapshots'] as $i => &$snapshot) {
        $position = $positions[$i];
        if ($snapshot['time'] !== $position['header_time']) {
            unset($snapshot);
            $result['error'] = 'Snapshot header changed between scans';
            $result['snapshots'] = array();
            return $result;
        }

        $snapshot['line_number'] = $position['line_number'];
        $snapshot['end_line'] = $position['end_line'];
        $snapshot['anchor'] = $position['anchor'];
        $snapshot['timeline_usable'] =
            $snapshot['valid'] &&
            $position['end_line'] !== null &&
            $position['anchor'] !== null &&
            !$position['interleaved_event'];
    }
    unset($snapshot);

    return $result;
}

/*
 * Compare snapshots with replay at their log positions.
 * Unknown initial state is not evidence of an unlink.
 * Snapshot disagreements remain explicit.
 */
function dplusTimelineReconcile($transitions, $snapshots)
{
    $checks = array();
    $state = array();
    $eventIndex = 0;
    $eventCount = count($transitions);
    $excluded = 0;

    foreach ($snapshots as $snapshot) {
        if (!$snapshot['timeline_usable']) {
            $excluded++;
            continue;
        }

        while ($eventIndex < $eventCount &&
            $transitions[$eventIndex]['line_number'] <
            $snapshot['line_number']) {
            $state = $transitions[$eventIndex]['state_after'];
            $eventIndex++;
        }

        $mismatches = array();
        $unknown = array();
        $compared = 0;

        foreach ($state as $module => $connection) {
            $compared++;
            $present = isset($snapshot['gateways'][$module]);

            if ($connection['dplus_status'] === 'unlinked') {
                if ($present) {
                    $mismatches[] = $module;
                }
                continue;
            }

            if (!$present) {
                $mismatches[] = $module;
                continue;
            }

            $gateway = $snapshot['gateways'][$module];
            $expected = $connection['connection'];

            if ($gateway['reflector'] !== $expected['reflector'] ||
                $gateway['remote_module'] !== $expected['remote_module'] ||
                $gateway['remote_ip'] !== $expected['remote_ip']) {
                $mismatches[] = $module;
            }
        }

        foreach ($snapshot['gateways'] as $module => $gateway) {
            if (!isset($state[$module])) {
                $unknown[] = $module;
            }
        }

        $status = 'match';
        if (!empty($mismatches)) {
            $status = 'mismatch';
        } elseif ($compared === 0) {
            $status = 'uncorroborated';
        } elseif (!empty($unknown)) {
            $status = 'partial';
        }

        $checks[] = array(
            'line_number' => $snapshot['line_number'],
            'time' => $snapshot['anchor']['text'],
            'status' => $status,
            'compared_modules' => $compared,
            'mismatched_modules' => $mismatches,
            'unknown_modules' => $unknown
        );
    }

    return array(
        'checks' => $checks,
        'excluded_snapshots' => $excluded
    );
}

/*
 * Build from one temporary copy of the log.
 * Fail closed when evidence cannot support time lookup.
 */
function dplusTimelineBuild($logfile)
{
    $result = array(
        'error' => '',
        'lookup_enabled' => false,
        'bounds' => array(),
        'replay' => array(),
        'snapshots' => array(),
        'reconciliation' => array()
    );

    $source = @fopen($logfile, 'r');
    if (!$source) {
        $result['error'] = 'Cannot open timeline log';
        return $result;
    }

    $copy = tmpfile();
    if (!$copy) {
        fclose($source);
        $result['error'] = 'Cannot create temporary log copy';
        return $result;
    }

    $sourceStat = fstat($source);
    $copied = stream_copy_to_stream($source, $copy);
    $readFailed = $sourceStat === false ||
        ($copied !== false && $copied < $sourceStat['size']);
    fclose($source);

    if ($copied === false || $readFailed || !fflush($copy)) {
        fclose($copy);
        $result['error'] = 'Cannot complete temporary log copy';
        return $result;
    }

    $metadata = stream_get_meta_data($copy);
    $bounds = dplusTimelineCollectBounds($metadata['uri']);
    $snapshots = dplusTimelineCollectSnapshots($metadata['uri']);
    fclose($copy);

    $result['bounds'] = $bounds;
    $result['snapshots'] = $snapshots['snapshots'];

    if ($bounds['error'] !== '' || $snapshots['error'] !== '') {
        $result['error'] = $bounds['error'] !== ''
            ? $bounds['error'] : $snapshots['error'];
        return $result;
    }

    $replay = dplusTimelineReplayEvents($bounds['events']);
    $reconciliation = dplusTimelineReconcile(
        $replay['transitions'],
        $snapshots['snapshots']
    );

    $result['replay'] = $replay;
    $result['reconciliation'] = $reconciliation;

    $mismatch = false;
    foreach ($reconciliation['checks'] as $check) {
        if ($check['status'] === 'mismatch') {
            $mismatch = true;
            break;
        }
    }

    $result['lookup_enabled'] =
        empty($bounds['clock_regressions']) &&
        empty($replay['unresolved_timeouts']) &&
        !$mismatch &&
        !empty($reconciliation['checks']);

    return $result;
}

/*
 * Query with a numeric epoch, including fractional seconds.
 * Bounds are inclusive uncertainty windows.
 * Do not extrapolate beyond usable snapshot coverage.
 */
function dplusTimelineLookup($timeline, $epoch, $module)
{
    $unknown = array(
        'dplus_status' => 'unknown',
        'connection' => null,
        'overall_connection' => 'unknown',
        'reason' => 'invalid_query'
    );
    $module = strtoupper(trim($module));

    if (!is_numeric($epoch) ||
        !in_array($module, array('A', 'B', 'C', 'D', 'E'), true)) {
        return $unknown;
    }
    $epoch = (float)$epoch;
    if (!is_finite($epoch)) {
        return $unknown;
    }

    if (!$timeline['lookup_enabled'] || $timeline['error'] !== '') {
        $unknown['reason'] = 'unsafe_timeline';
        return $unknown;
    }

    $first = null;
    $last = null;
    $baseline = null;

    foreach ($timeline['snapshots'] as $snapshot) {
        if (!$snapshot['timeline_usable']) {
            continue;
        }
        if ($first === null) {
            $first = $snapshot;
        }
        $last = $snapshot;
        if ($snapshot['anchor']['upper_epoch'] <= $epoch) {
            $baseline = $snapshot;
        }
    }

    if ($first === null || $baseline === null ||
        $epoch < $first['anchor']['upper_epoch'] ||
        $epoch > $last['anchor']['lower_epoch']) {
        $unknown['reason'] = 'outside_snapshot_coverage';
        return $unknown;
    }

    $state = null;

    foreach ($timeline['replay']['transitions'] as $transition) {
        if ($transition['line_number'] < $baseline['line_number'] &&
            isset($transition['state_after'][$module])) {
            $state = $transition['state_after'][$module];
        }
    }

    if (isset($baseline['gateways'][$module])) {
        $gateway = $baseline['gateways'][$module];
        $state = array(
            'dplus_status' => 'linked',
            'connection' => array(
                'reflector' => $gateway['reflector'],
                'remote_module' => $gateway['remote_module'],
                'remote_ip' => $gateway['remote_ip']
            ),
            'overall_connection' => 'unknown'
        );
    }

    foreach ($timeline['replay']['transitions'] as $transition) {
        if (!in_array($module, $transition['affected_modules'], true)) {
            continue;
        }

        $lower = $transition['lower_epoch'];
        $upper = $transition['upper_epoch'];

        if ($upper === null || $lower === null) {
            if ($lower === null || $epoch >= $lower) {
                $unknown['reason'] = 'unbounded_transition';
                return $unknown;
            }
            continue;
        }

        if ($epoch >= $lower && $epoch <= $upper) {
            $unknown['reason'] = 'transition_window';
            return $unknown;
        }

        if ($transition['line_number'] > $baseline['line_number'] &&
            $upper < $epoch) {
            $state = $transition['state_after'][$module];
        }
    }

    if ($state === null) {
        $unknown['reason'] = 'no_module_evidence';
        return $unknown;
    }

    $state['reason'] = 'snapshot_and_event_order';
    return $state;
}

/*
 * Annotate activity without changing its reporting source or Via.
 * Whole-second activity times represent an uncertain second.
 */
function dplusTimelineAnnotateActivity($timeline, $rows)
{
    foreach ($rows as &$row) {
        $row['dplus_history'] = 'Unknown';
        $row['historical_overall_connection'] = 'unknown';

        if (!isset($row['epoch'], $row['module']) ||
            !is_numeric($row['epoch'])) {
            continue;
        }

        $queryModule = $row['module'];
        if (isset($row['activity']) && $row['activity'] === 'Remote') {
            $queryModule = isset(
                $row['linked_local_module'],
                $row['linked_module_source']
            ) && $row['linked_module_source'] === 'link_event'
                ? $row['linked_local_module'] : 'UNKNOWN';
        }

        $lower = (float)$row['epoch'];
        $upper = $lower + 1.0;

        if (isset($row['time']) &&
            preg_match(
                '/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}\.\d+$/',
                trim($row['time'])
            )) {
            $anchor = dplusTimelineParseAnchor(
                'Reading file test ' . trim($row['time']), 0
            );
            if ($anchor === null) {
                continue;
            }
            $lower = $anchor['lower_epoch'];
            $upper = $lower;
        }

        $before = dplusTimelineLookup($timeline, $lower, $queryModule);
        $after = dplusTimelineLookup($timeline, $upper, $queryModule);

        if ($before['dplus_status'] === 'unknown' ||
            $after['dplus_status'] === 'unknown' ||
            $before['dplus_status'] !== $after['dplus_status'] ||
            $before['connection'] !== $after['connection']) {
            continue;
        }

        $overlap = false;
        $module = strtoupper(trim($queryModule));
        foreach ($timeline['replay']['transitions'] as $transition) {
            if (!in_array($module, $transition['affected_modules'], true)) {
                continue;
            }
            if ($transition['lower_epoch'] === null ||
                $transition['upper_epoch'] === null ||
                ($transition['lower_epoch'] <= $upper &&
                 $transition['upper_epoch'] >= $lower)) {
                $overlap = true;
                break;
            }
        }
        if ($overlap) {
            continue;
        }

        if ($before['dplus_status'] === 'unlinked') {
            $row['dplus_history'] = 'Unlinked';
        } elseif ($before['dplus_status'] === 'linked') {
            $row['dplus_history'] =
                $before['connection']['reflector'] .
                $before['connection']['remote_module'];
        }
    }
    unset($row);

    return $rows;
}
