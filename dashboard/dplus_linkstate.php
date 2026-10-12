<?php
/*
 * Unified D-STAR Dashboard v0.4.2
 * DPlus Historical Link-State Helper
 * PHP 5.4 compatible
 * Development only
 */

function dplusLinkstateParseSuccess($line) {

    if (!preg_match(
        '/remote gw\/ref\s+(\S+)\s+(REF[0-9]{3})\s+([A-Z])\s+' .
        'now linked from\/to module\s+([A-Z])\/([A-Z])/i',
        $line,
        $m
    )) {
        return null;
    }

    return array(
        'type'          => 'linked',
        'remote_ip'     => $m[1],
        'reflector'     => strtoupper($m[2]),
        'remote_module' => strtoupper($m[3]),
        'local_module'  => strtoupper($m[4]),
        'linked_module' => strtoupper($m[5])
    );
}

/*
 * Parse confirmed DPlus unlink events.
 * An unlink request alone is not confirmation.
 */
function dplusLinkstateParseUnlink($line) {

    if (!preg_match(
        '/^module\s+([A-Z])\s+unlinked\s*$/i',
        trim($line),
        $m
    )) {
        return null;
    }

    return array(
        'type'         => 'unlinked',
        'local_module' => strtoupper($m[1])
    );
}

/*
 * Parse DPlus remote gateway/reflector timeouts.
 *
 * The timeout line identifies the remote IP only.
 * Do not infer a local module without corroboration.
 */
function dplusLinkstateParseTimeout($line) {

    if (!preg_match(
        '/^remote gw\/ref\s+(\S+)\s+timeout\s*$/i',
        trim($line),
        $m
    )) {
        return null;
    }

    return array(
        'type'         => 'timeout',
        'remote_ip'    => $m[1],
        'local_module' => null
    );
}

/*
 * Parse one entry from a DPlus linked-gateway snapshot.
 * Example:
 * gateway REF050 C -> B IP 71.162.120.76:20001
 */
function dplusLinkstateParseSnapshotEntry($line) {

    if (!preg_match(
        '/^\s+gateway\s+(REF[0-9]{3})\s+([A-Z])\s+' .
        '->\s+([A-Z])\s+IP\s+([0-9.]+):(\d+)\s*$/i',
        $line,
        $m
    )) {
        return null;
    }

    return array(
        'type'          => 'snapshot_entry',
        'reflector'     => strtoupper($m[1]),
        'remote_module' => strtoupper($m[2]),
        'local_module'  => strtoupper($m[3]),
        'remote_ip'     => $m[4],
        'remote_port'   => (int)$m[5]
    );
}

/*
 * Collect timestamped linked-gateway snapshots.
 * Does not modify historical link state.
 */
function dplusLinkstateCollectSnapshots($logfile) {

    $snapshots = array();
    $current = null;
    $invalid = 0;

    $fh = @fopen($logfile, 'r');
    if (!$fh) {
        return array(
            'snapshots' => array(),
            'invalid' => 0,
            'error' => 'Cannot open log'
        );
    }

    while (($line = fgets($fh)) !== false) {

        if (preg_match(
            '/^Linked gateway list:\s+' .
            '(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?)/',
            $line, $m
        )) {
            if ($current !== null) {
                $snapshots[] = $current;
            }

            $current = array(
                'time' => $m[1],
                'gateways' => array(),
                'valid' => true
            );
            continue;
        }

        if ($current === null) {
            continue;
        }

        $entry = dplusLinkstateParseSnapshotEntry($line);

        if ($entry !== null) {
            $module = $entry['local_module'];

            if (isset($current['gateways'][$module])) {
                $current['valid'] = false;
                $invalid++;
            }

            $current['gateways'][$module] = $entry;
            continue;
        }

        if (preg_match('/^\s+gateway\b/i', $line)) {
            $current['valid'] = false;
            $invalid++;
            continue;
        }

        // Allow interleaved log messages within a snapshot.
        // Finish at the Last Heard section boundary.
        if (preg_match('/^Last heard list:/i', $line)) {
            $snapshots[] = $current;
            $current = null;
        }
    }

    if ($current !== null) {
        // A snapshot at EOF may be incomplete.
        $current['valid'] = false;
        $snapshots[] = $current;
    }

    fclose($fh);

    return array(
        'snapshots' => $snapshots,
        'invalid' => $invalid,
        'error' => ''
    );
}
