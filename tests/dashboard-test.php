<?php
ob_start();
/* Run on PHP 5.4+; fixtures never access the gateway's real logs. */
$fixture = sys_get_temp_dir() . '/dashboard-test-' . getmypid();
if (!mkdir($fixture, 0700)) exit(1);
$root = dirname(__DIR__) . '/dashboard';
/* Isolated package copy so production/local settings never change. */
foreach (glob($root . '/*') as $path) {
    if (is_file($path) && basename($path) !== 'dashboard.local.php') {
        copy($path, $fixture . '/' . basename($path));
    }
}
$webFixture = preg_replace('/^[A-Za-z]:/', '', str_replace('\\', '/', $fixture));
$settings = array('callsign'=>'W4CRL','timezone'=>'UTC', 'document_root'=>$webFixture,
    'dplus_status'=>$webFixture.'/dp.html', 'g2_status'=>$webFixture.'/g2.html',
    'dplus_log'=>$webFixture.'/dp.log', 'g2_log'=>$webFixture.'/g2.log');
file_put_contents($fixture . '/dashboard.local.php', '<?php return ' . var_export($settings,true) . ';');
require $fixture . '/dashboard_config.php';
date_default_timezone_set(dashboardConfig('timezone'));
require $fixture . '/dplus_local_headers.php';
require $fixture . '/g2_dashboard_history.php';
require $fixture . '/unified_activity.php';
$checks = 0; $failures = 0;
function check($name, $ok) {
    global $checks, $failures;
    $checks++; if (!$ok) $failures++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
}
check('site callsign', dashboardConfig('callsign') === 'W4CRL');
check('site timezone', dashboardConfig('timezone') === 'UTC');
$local = "Remote client forward start streamid 67cb  2026/10/11 09:28:54.806\n" .
    "   rpt2 (W4CRL  G) rpt1 (W4CRL  C) urcall (       I) mycall (K1NR    /    )\n";
$log = $fixture . '/local.log'; file_put_contents($log, $local);
$headers = dplusLocalHeadersRead($log);
check('W4CRL local headers parsed', count($headers['records']) === 1);
$rows = array(array('callsign'=>'K1NR','module'=>'C','epoch'=>strtotime('2026-10-11 09:28:54'),
    'activity'=>'Local RF (reported)','rpt1'=>'','rpt2'=>''));
$annotated = dplusLocalHeadersAnnotate($rows, $headers['records']);
check('local headers enriched', $annotated[0]['rpt1'] === 'W4CRL C' && $annotated[0]['rpt2'] === 'W4CRL G');
$duplicate = array_merge($headers['records'], $headers['records']);
$annotated = dplusLocalHeadersAnnotate($rows, $duplicate);
check('ambiguous local headers remain blank', $annotated[0]['rpt1'] === '');
$link = "101026 at 12:00:00:sending link request from mod C to link with: [DCS049  ] mod C [54.237.10.173]\n" .
    "101026 at 12:00:03:sending File:[/root/g2_link/linked.dat], mod:[C], RADIO_ID=[LINKED DCS049 C     ]\n";
$tx = "101126 at 12:01:00:START from dcs: streamID=9,45, my=KA8SCP  , sfx=NXDN, ur=CQCQCQ  , rpt1=DCS049 C, rpt2=KA8SCP B, 100 bytes fromIP=54.237.10.173, source=DCS049 C\n";
$g2log = $fixture . '/g2.log';
file_put_contents($g2log . '-20261011', $link);
file_put_contents($g2log, $tx . str_replace(array('12:01:00','9,45','NXDN'),array('12:02:00','10,45','P25 '),$tx));
$history = g2DashboardHistoryRead($g2log);
check('rotated evidence retains separate streams', count($history['records']) === 2);
check('suffixes remain independent', $history['records'][0]['suffix'] === 'NXDN' && $history['records'][1]['suffix'] === 'P25');
check('configured timestamp timezone', $history['records'][0]['epoch'] === strtotime('2026-10-11 12:01:00'));
file_put_contents($g2log, "startup\n" . $tx);
$history = g2DashboardHistoryRead($g2log);
check('restart clears previous association', count($history['records']) === 0);
file_put_contents($g2log, $link . str_replace('sfx=NXDN','sfx=M17 ', $tx));
$history = g2DashboardHistoryRead($g2log);
check('M17 suffix retained', count($history['records']) === 1 && $history['records'][0]['suffix'] === 'M17');
$record = array('linked_local_module'=>'B','linked_module_source'=>'link_event','connection'=>'REF050 C','rpt2'=>'REF050 B');
check('inapplicable DPlus header excluded', !unifiedActivityRemoteApplies($record));
$record['rpt2'] = 'REF050 C';
check('applicable DPlus header included', unifiedActivityRemoteApplies($record));
file_put_contents($fixture.'/dp.html', '');
file_put_contents($fixture.'/g2.html', '');
file_put_contents($fixture.'/dp.log', '');
ob_start();
require $fixture.'/index.php';
$page = ob_get_clean();
check('site title rendered', strpos($page, '<title>W4CRL D-STAR Gateway</title>') !== false);
check('RF reference uses site callsign', strpos($page, 'Send commands on the W4CRL module') !== false);
check('production tables and reference rendered', strpos($page,'Unified Last Heard') !== false && strpos($page,'RF Command Reference') !== false);
foreach (glob($fixture . '/*') as $path) if (is_file($path)) unlink($path);
rmdir($fixture);
echo "Checks: $checks; failures: $failures\n";
ob_end_flush();
exit($failures ? 1 : 0);
