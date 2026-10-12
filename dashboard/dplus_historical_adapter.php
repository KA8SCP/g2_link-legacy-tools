<?php
/*
 * Historical DPlus incoming transmission adapter.
 * PHP 5.4 compatible.
 * Development only.
 */

require_once __DIR__ . '/dplus_adapter.php';
require_once __DIR__ . '/dplus_linkstate.php';

function dplusHistoricalNormalizeStreamId($id) {
    $id = strtolower(trim($id));
    $id = ltrim($id, '0');
    return $id === '' ? '0' : $id;
}

function dplusHistoricalKey($time, $streamid, $connection) {
    return $time . '|' .
        dplusHistoricalNormalizeStreamId($streamid) . '|' .
        trim($connection);
}

function dplusHistoricalRead($logfile) {
    $result = dplusAdapterRead($logfile);

    if (!isset($result['records']) || !is_array($result['records'])) {
        return $result;
    }

    $mapping = array();
    $state = array();

    $fh = @fopen($logfile, 'r');

    if (!$fh) {
        $result['error'] = 'Cannot read historical DPlus log';
        return $result;
    }

    while (($line = fgets($fh)) !== false) {
        $event = dplusLinkstateParseSuccess($line);

        if ($event !== null) {
            $state[$event['local_module']] = $event;
            continue;
        }

        $event = dplusLinkstateParseUnlink($line);

        if ($event !== null) {
            unset($state[$event['local_module']]);
            continue;
        }

        $event = dplusLinkstateParseTimeout($line);

        if ($event !== null) {
            foreach ($state as $module => $connection) {
                if ($connection['remote_ip'] === $event['remote_ip']) {
                    unset($state[$module]);
                }
            }
            continue;
        }

        if (!preg_match(
            '/^Remote gateway tx start \((REF[0-9]{3}\s+[A-Z])\)\s+streamid\s+([0-9a-fA-F]+).*?([0-9]{4}\/[0-9]{2}\/[0-9]{2}\s+[0-9:.]+)\s*$/',
            $line,
            $m
        )) {
            continue;
        }

        $key = dplusHistoricalKey($m[3], $m[2], $m[1]);

        $parts = explode(' ', trim($m[1]));
        $reflector = $parts[0];
        $remoteModule = $parts[1];

        $matches = array();

        foreach ($state as $module => $connection) {
            if ($connection['reflector'] === $reflector &&
                $connection['remote_module'] === $remoteModule) {
                $matches[] = $module;
            }
        }

        $resolved = count($matches) === 1
            ? $matches[0] : 'UNKNOWN';

        if (!isset($mapping[$key])) {
            $mapping[$key] = array(
                'local_module' => $resolved,
                'module_source' => $resolved === 'UNKNOWN'
                    ? 'unknown' : 'link_event'
            );
        } else {
            // A duplicate raw key is ambiguous.
            $mapping[$key] = array(
                'local_module' => 'UNKNOWN',
                'module_source' => 'duplicate_key'
            );
        }
    }

    fclose($fh);

    $counts = array(
        'mapped' => 0,
        'unknown' => 0,
        'missing_key' => 0,
        'duplicate_key' => 0
    );

    $seen = array();

    foreach ($result['records'] as &$record) {
        $key = dplusHistoricalKey(
            $record['time'],
            $record['streamid'],
            $record['connection']
        );

        if (isset($seen[$key])) {
            $record['local_module'] = 'UNKNOWN';
            $record['module_source'] = 'duplicate_adapter_key';
            $counts['duplicate_key']++;
        } elseif (isset($mapping[$key])) {
            // Link association does not confirm local RF delivery.
            $record['linked_local_module'] = $mapping[$key]['local_module'];
            $record['linked_module_source'] = $mapping[$key]['module_source'];
            $record['local_module'] = 'UNKNOWN';
            $record['module_source'] = 'delivery_unconfirmed';
            $counts['unknown']++;
        } else {
            $record['local_module'] = 'UNKNOWN';
            $record['module_source'] = 'missing_key';
            $counts['missing_key']++;
        }

        $seen[$key] = true;
    }

    unset($record);

    $result['historical_stats'] = $counts;

    return $result;
}
