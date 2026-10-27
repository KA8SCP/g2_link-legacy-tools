<?php
/* Offline package prerequisite, PHP 5.4 compatible. No exploit requests. */
function dashboardPhpPackageFixed($version, $release, $vendor)
{
    $upstream = array('8.2'=>'8.2.35','8.3'=>'8.3.36','8.4'=>'8.4.27','8.5'=>'8.5.12');
    $branch = implode('.', array_slice(explode('.', $version), 0, 2));
    if (isset($upstream[$branch]) && version_compare($version, $upstream[$branch], '>=')) return true;
    if ($version === '5.4.45' && stripos($vendor, 'GetPageSpeed') !== false &&
        preg_match('/^([0-9]+)\.el7$/D', $release, $m) && (int)$m[1] >= 20) return true;
    if (stripos($vendor, 'AlmaLinux') !== false && $version === '8.0.30' &&
        preg_match('/^8\.el9_8(?:\.([0-9]+)|\.alma\.([0-9]+))$/D', $release, $m)) {
        return (isset($m[1]) && (int)$m[1] >= 1) || (isset($m[2]) && (int)$m[2] >= 1);
    }
    return false;
}
function dashboardPhpSecurityInventory($text, $cliVersion)
{
    $packages = array();
    foreach (explode("\n", trim($text)) as $line) {
        $fields = explode("\t", $line);
        if (count($fields) !== 4) throw new RuntimeException('Unrecognized RPM inventory.');
        list($name,$version,$release,$vendor) = $fields;
        if (!in_array($name,array('php','php-cli','php-common','php-fpm'),true)) {
            if (preg_match('/(?:common|fpm|cli)$/D', $name)) throw new RuntimeException('Additional PHP runtime requires separate qualification: '.$name);
            continue;
        }
        if (isset($packages[$name])) throw new RuntimeException('Multiple package builds found: '.$name);
        if (!dashboardPhpPackageFixed($version,$release,$vendor)) {
            throw new RuntimeException('PHP2Shell prerequisite NOT verified: '.$name.'-'.$version.'-'.$release.'. Patch PHP before installation; see docs/PHP-SECURITY.md.');
        }
        $packages[$name] = array($version,$release);
    }
    if (!isset($packages['php-common'],$packages['php-cli']) || (!isset($packages['php']) && !isset($packages['php-fpm']))) {
        throw new RuntimeException('System PHP CLI/common and a packaged web runtime (php or php-fpm) are required for verification.');
    }
    foreach ($packages as $name=>$build) {
        if ($build !== $packages['php-common']) throw new RuntimeException('PHP component versions differ: '.$name);
    }
    if ($cliVersion !== $packages['php-cli'][0]) throw new RuntimeException('PHP CLI runtime differs from installed package.');
    return array_keys($packages);
}
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        $checked=dashboardPhpSecurityInventory(stream_get_contents(STDIN),PHP_VERSION);
        echo 'PHP2Shell fixed-package prerequisite verified: ',implode(', ',$checked),".\n";
    } catch (Exception $e) { fwrite(STDERR,'ERROR: '.$e->getMessage()."\n"); exit(1); }
}
