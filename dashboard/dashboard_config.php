<?php
/* Site settings. Compatible with PHP 5.4; local configuration is not versioned. */
function dashboardConfig($key)
{
    static $settings = null;
    if ($settings === null) {
        $settings = array(
            'callsign' => 'WB1GOF',
            'timezone' => 'America/New_York',
            'document_root' => '/var/www/html',
            'dplus_status' => '/dstar/tmp/status.html',
            'g2_status' => '/var/www/html/fs.html',
            'dplus_log' => '/var/log/dplus.log',
            'g2_log' => '/var/log/g2_link.log'
        );
        $path = __DIR__ . '/dashboard.local.php';
        if (is_file($path)) {
            $local = require $path;
            if (!is_array($local)) throw new RuntimeException('Invalid dashboard configuration');
            foreach ($local as $name => $value) {
                if (!array_key_exists($name, $settings) || !is_string($value)) {
                    throw new RuntimeException('Invalid dashboard setting');
                }
                $settings[$name] = $value;
            }
        }
        if (!preg_match('/^[A-Z0-9]{3,7}$/', $settings['callsign'])) {
            throw new RuntimeException('Invalid gateway callsign');
        }
        new DateTimeZone($settings['timezone']);
        foreach (array('document_root', 'dplus_status', 'g2_status', 'dplus_log', 'g2_log') as $name) {
            if ($settings[$name] === '' || $settings[$name][0] !== '/' ||
                preg_match('/[\r\n\x00]/', $settings[$name])) {
                throw new RuntimeException('Invalid dashboard path');
            }
        }
    }
    return $settings[$key];
}
