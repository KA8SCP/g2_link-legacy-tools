<?php
/* Package identity, independent of site configuration. PHP 5.4 compatible. */
define('DASHBOARD_VERSION', '0.5.1');
function dashboardBuildRevision()
{
    $path = __DIR__ . '/dashboard-build.json';
    if (!is_file($path) || !is_readable($path) || filesize($path) > 1024) return '';
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data) || !isset($data['revision']) || !is_string($data['revision']) ||
        !preg_match('/^[0-9a-f]{40,64}$/D', $data['revision'])) return '';
    return substr($data['revision'], 0, 12);
}
