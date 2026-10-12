<?php
require_once __DIR__ . '/dashboard_config.php';
/*
 * Correlate connected DPlus users with DPlus Last Heard.
 * PHP 5.4 compatible. Returns reported last transmission time.
 */
function dplusRemoteLastHeardTime($remoteRow, $heardRows)
{
    if (!isset($remoteRow[0], $remoteRow[2])) {
        return '';
    }

    $call = strtoupper(trim($remoteRow[0]));
    $module = strtoupper(trim($remoteRow[2]));

    if ($call === '' ||
        !in_array($module, array('A', 'B', 'C', 'D', 'E'), true)) {
        return '';
    }

    $matches = array();
    foreach ($heardRows as $heard) {
        if (!isset($heard[0], $heard[2], $heard[3]) ||
            strtoupper(trim($heard[0])) !== $call ||
            strtoupper(trim($heard[2])) !== $module) {
            continue;
        }

        $time = trim($heard[3]);
        $date = DateTime::createFromFormat(
            '!Y/m/d H:i:s',
            $time,
            new DateTimeZone(dashboardConfig('timezone'))
        );
        $errors = DateTime::getLastErrors();

        if ($date === false ||
            ($errors !== false &&
             ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
            $date->format('Y/m/d H:i:s') !== $time) {
            continue;
        }

        $matches[$time] = true;
    }

    if (count($matches) !== 1) {
        return '';
    }

    $times = array_keys($matches);
    return $times[0];
}
