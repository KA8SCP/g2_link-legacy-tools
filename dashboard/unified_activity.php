<?php
/*
 * Unified D-STAR Activity Adapter
 * Development only — PHP 5.4 compatible.
 *
 * Inputs:
 *   $localRows  - normalized local HTML activity records
 *   $remoteRows - dplusHistoricalRead()['records']
 *
 * No production files or services are modified.
 */

function unifiedActivityBuild($localRows, $remoteRows)
{
    $activity = array();
    $seenLocal = array();

    foreach ($localRows as $r) {
        $call = isset($r['callsign']) ? trim($r['callsign']) : '';
        $module = isset($r['module']) ? trim($r['module']) : '';
        $time = isset($r['time']) ? $r['time'] : '';
        $epoch = strtotime($time);

        if ($epoch === false) {
            continue;
        }

        $key = $call . '|' . $module . '|' . $epoch;

        if (isset($seenLocal[$key])) {
            $idx = $seenLocal[$key];
            $source = isset($r["source"]) ? trim($r["source"]) : "";
            $existing = $activity[$idx]["connection"];
            if ($source !== "" && !in_array($source, explode(" + ", $existing))) {
                $activity[$idx]["connection"] .= ($existing !== "" ? " + " : "") . $source;
            }
            $message = isset($r["message"]) ? $r["message"] : "";
            if ($activity[$idx]["message"] === "" && $message !== "") {
                $activity[$idx]["message"] = $message;
            }
            continue;
        }
        $seenLocal[$key] = count($activity);

        $activity[] = array(
            'callsign' => $call,
            'module' => $module,
            'activity' => 'Local RF (reported)',
            'connection' => isset($r['source']) ? $r['source'] : '',
            'via' => '',
            'time' => $time,
            'epoch' => $epoch,
            'message' => isset($r['message']) ? $r['message'] : '',
            'streamid' => '',
            'rpt1' => '',
            'rpt2' => ''
        );
    }

    foreach ($remoteRows as $r) {
        if (!unifiedActivityRemoteApplies($r)) {
            continue;
        }
        $time = isset($r['time']) ? $r['time'] : '';
        $epoch = strtotime($time);

        if ($epoch === false) {
            continue;
        }

        $activity[] = array(
            'callsign' => isset($r['callsign']) ? trim($r['callsign']) : '',
            'module' => isset($r['local_module']) ? $r['local_module'] : '',
            'activity' => 'Remote',
            'linked_local_module' => isset($r['linked_local_module'])
                ? $r['linked_local_module'] : 'UNKNOWN',
            'linked_module_source' => isset($r['linked_module_source'])
                ? $r['linked_module_source'] : 'unknown',
            'rf_delivery' => 'unconfirmed',
            'history_session' => isset($r['history_session'])
                ? $r['history_session'] : '',
            'connection' => 'DPlus',
            'via' => isset($r['connection']) ? $r['connection'] : '',
            'time' => $time,
            'epoch' => $epoch,
            'message' => isset($r['message']) ? $r['message'] : '',
            'streamid' => isset($r['streamid']) ? $r['streamid'] : '',
            'rpt1' => isset($r['rpt1']) ? $r['rpt1'] : '',
            'rpt2' => isset($r['rpt2']) ? $r['rpt2'] : ''
        );
    }

    usort($activity, 'unifiedActivitySort');

    return $activity;
}

function unifiedActivitySort($a, $b)
{
    if ($a['epoch'] == $b['epoch']) {
        return 0;
    }

    return ($a['epoch'] > $b['epoch']) ? -1 : 1;
}


/*
 * Show only remote streams matching a known historical link association
 * and the same reflector/module in the transmitted RPT2 header.
 * Applicability does not establish RF delivery.
 */
function unifiedActivityRemoteApplies($record)
{
    if (!isset(
        $record['linked_local_module'],
        $record['linked_module_source'],
        $record['connection'],
        $record['rpt2']
    ) ||
        $record['linked_module_source'] !== 'link_event' ||
        !in_array($record['linked_local_module'],
            array('A', 'B', 'C', 'D', 'E'), true)) {
        return false;
    }

    $pattern = '/^(REF[0-9]{3})\s+([A-Z])$/';
    if (!preg_match($pattern, strtoupper(trim($record['connection'])), $link) ||
        !preg_match($pattern, strtoupper(trim($record['rpt2'])), $header)) {
        return false;
    }

    return $link[1] === $header[1] && $link[2] === $header[2];
}
