<?php
require_once __DIR__ . '/dashboard_config.php';
date_default_timezone_set(dashboardConfig('timezone'));
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$root = dashboardConfig('document_root');
$dpfile=dashboardConfig('dplus_status'); $g2file=dashboardConfig('g2_status');
function rd($f){return is_readable($f) ? (string)@file_get_contents($f) : '';}
function txt($s){return trim(preg_replace('/\s+/',' ',html_entity_decode(strip_tags($s),ENT_QUOTES,'UTF-8')));}
function links($h){
  $o=array(); if(!$h)return $o;
  if(!preg_match('/Linked\s+Gateways\s*\/?\s*Reflectors(.*?)(?:Remote Users|Software Clients|Local RF Users|Last Heard)/is',$h,$s))return $o;
  if(preg_match_all('/<tr[^>]*>.*?<td[^>]*>\s*<span[^>]*>\s*([A-E])\s*<\/span>\s*<\/td>.*?<td[^>]*>\s*<span[^>]*>\s*(.*?)\s*<\/span>/is',$s[1],$m,PREG_SET_ORDER))
    foreach($m as $r){$v=txt($r[2]);$o[strtoupper(txt($r[1]))]=($v===''||strcasecmp($v,'unlinked')===0||strcasecmp($v,'not linked')===0)?'Unlinked':$v;}
  return $o;
}
function ver($h,$p){return ($h&&preg_match($p,$h,$m))?txt($m[1]):'Unknown';}
function linked($v){return $v!=='N/A'&&$v!=='Unlinked';}
function agev($f){$t=@filemtime($f);if(!$t)return'Unknown';$a=max(0,time()-$t);return $a<90?$a.' sec ago':($a<5400?floor($a/60).' min ago':floor($a/3600).' hr ago');}
function rows($h,$head,$next){
  $out=array(); if(!$h)return$out;
  $n=implode('|',array_map(function($x){return preg_quote($x,'/');},$next));
  if(!preg_match('/'.preg_quote($head,'/').'(.*?)(?:'.$n.'|Status as of|Server Uptime)/is',$h,$s))return$out;
  if(preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is',$s[1],$tr,PREG_SET_ORDER))
    foreach($tr as $r){if(preg_match_all('/<td[^>]*>(.*?)<\/td>/is',$r[1],$td)){ $c=array_map('txt',$td[1]); if(count(array_filter($c,'strlen'))) $out[]=$c;}}
  return$out;
}
$dp=rd($dpfile);$g2=rd($g2file);$dl=links($dp);$gl=links($g2);
/*
 * Unified Last Heard:
 * DPlus includes local RF and directly connected hotspot activity.
 * g2_link Local RF Users includes repeater RF activity.
 * Keep source records separate until correlation is validated.
 */
$local=array();

$dpheard=rows($dp,'Last Heard',array('Status as of'));
foreach($dpheard as $r){
    if(count($r)<4)continue;
    $local[]=array(
        $r[0],
        $r[2],
        'DPlus',
        $r[1],
        $r[3]
    );
}

$g2local=rows($g2,'Local RF Users',array('Remote Users','Software Clients'));
foreach($g2local as $r){
    if(count($r)<3)continue;
    $local[]=array(
        $r[0],
        $r[1],
        'g2_link',
        '',
        $r[2]
    );
}

/*
 * Remote Users come from two independent applications with different
 * table layouts.  Normalize them here for the unified dashboard:
 *
 *   DPlus:   Callsign | User Message | Last TX on | Type
 *   g2_link: Callsign | Last TX on   | Source     | Date-Time
 *
 * Unified:   Callsign | Activity | Type / Source | User Message | Date-Time
 */
$remote=array();

$dpremote=rows($dp,'Remote Users',array('Last Heard','Linked Gateways','Status as of'));
require_once __DIR__ . '/dplus_remote_users.php';
foreach($dpremote as $r){
    $remote[]=array(
        isset($r[0])?$r[0]:'',
        isset($r[2])?$r[2]:'',
        'DPlus'.((isset($r[3]) && $r[3]!=='')?' / '.$r[3]:''),
        isset($r[1])?$r[1]:'',
        dplusRemoteLastHeardTime($r, $dpheard)
    );
}

$g2remote=rows($g2,'Remote Users',array('Local RF Users','Software Clients'));
$g2clients=rows($g2,'Software Clients',array('Local RF Users','Remote Users','Linked Gateways'));
foreach($g2clients as $r){
    if(count($r)!==3)continue;
    $remote[]=array(
        $r[0],
        $r[1],
        'g2_link'.($r[2]!==''?' / '.$r[2]:''),
        '',
        ''
    );
}

/*
 * Unified chronological activity.
 * Local HTML reports and historical DPlus remote transmissions.
 */
require_once __DIR__ . '/unified_activity.php';
require_once __DIR__ . '/dplus_historical_adapter.php';

$unifiedLocal = array();

foreach ($dpheard as $r) {
    if (count($r) < 4) continue;

    $unifiedLocal[] = array(
        'callsign' => $r[0],
        'module' => trim($r[2]),
        'source' => 'DPlus',
        'message' => $r[1],
        'time' => $r[3]
    );
}

foreach ($g2local as $r) {
    if (count($r) < 3) continue;

    $module = trim($r[1]);
    if (preg_match('/([A-Z])$/', $module, $m)) {
        $module = $m[1];
    }

    $unifiedLocal[] = array(
        'callsign' => $r[0],
        'module' => $module,
        'source' => 'g2_link',
        'message' => '',
        'time' => $r[2]
    );
}

require_once __DIR__ . '/dplus_dashboard_history.php';
$historical = dplusDashboardHistoryRead(dashboardConfig('dplus_log'));

$unifiedActivity = unifiedActivityBuild(
    $unifiedLocal,
    isset($historical['records']) ? $historical['records'] : array()
);

require_once __DIR__ . '/g2_dashboard_history.php';
$g2History = g2DashboardHistoryRead(dashboardConfig('g2_log'));
if ($g2History['error'] === '') {
    $unifiedActivity = array_merge(
        $unifiedActivity, $g2History['records']
    );
}
// Classify headers before filtering, but limit expensive timeline lookups
// to the visible page rather than every retained transmission.
if ($historical['error'] === '' && isset($historical['local_headers'])) {
    $unifiedActivity = dplusLocalHeadersAnnotate($unifiedActivity, $historical['local_headers']);
}
usort($unifiedActivity, 'unifiedActivitySort');

$activityFilter = isset($_GET['activity']) && is_string($_GET['activity'])
    ? $_GET['activity'] : 'all';

if (!in_array($activityFilter, array('all', 'local', 'remote'), true)) {
    $activityFilter = 'all';
}

$filteredActivity = array();

foreach ($unifiedActivity as $record) {
    $isRemote = isset($record['activity'])
        && $record['activity'] === 'Remote';

    if ($activityFilter === 'local' && $isRemote) continue;
    if ($activityFilter === 'remote' && !$isRemote) continue;

    $filteredActivity[] = $record;
}

$unifiedDisplay = array_slice($filteredActivity, 0, 30);
$unifiedDisplay = dplusDashboardHistoryAnnotate($historical, $unifiedDisplay);
$unifiedDisplay = dashboardAssociatedLinksAnnotate($g2History, $unifiedDisplay);

foreach ($unifiedDisplay as &$displayRow) {
    if ($displayRow['activity'] === 'Remote' &&
        $displayRow['connection'] === 'g2_link') {
        $displayRow['dplus_history'] = 'Not assessed';
    }
}
unset($displayRow);
$host=dashboardConfig('callsign');

/*
 * The g2_link Linked Gateways / Reflectors table is authoritative
 * for the repeater modules managed by this dashboard.
 */
$mods = array_keys($gl);
sort($mods);

?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="60"><title><?=htmlspecialchars($host)?> D-STAR Gateway</title><link rel="stylesheet" href="style.css"></head>
<body><header><div class="wrap"><h1><?=htmlspecialchars($host)?> D-STAR Gateway</h1><p>Unified DPlus + g2_link Status</p></div></header><main class="wrap">
<h2>Module Status</h2><section class="grid">
<?php foreach($mods as $m):$d=isset($dl[$m])?$dl[$m]:'N/A';$g=isset($gl[$m])?$gl[$m]:'N/A';$conf=linked($d)&&linked($g);?>
<article class="card"><h3>Module <?=htmlspecialchars($m)?></h3><div class="kv"><b>DPlus</b><span><?=htmlspecialchars($d)?></span></div><div class="kv"><b>g2_link</b><span><?=htmlspecialchars($g)?></span></div>
<?php
if ($conf) {
    $status = 'LINK CONFLICT';
    $statusClass = 'bad';
} elseif (linked($d)) {
    $status = 'DPlus active';
    $statusClass = 'ok';
} elseif (linked($g)) {
    $status = 'g2_link active';
    $statusClass = 'ok';
} else {
    $status = 'Available';
    $statusClass = 'ok';
}
?>
<div class="flag <?=$statusClass?>"><?=htmlspecialchars($status)?></div></article>
<?php endforeach;?></section>
<h2>Software &amp; Sources</h2><section class="grid two">
<article class="card"><h3>DPlus</h3><div class="kv"><b>Version</b><span><?=htmlspecialchars(ver($dp,'/DPLUS\s+version\s+([^<]+)/i'))?></span></div><div class="kv"><b>Source</b><span><?=$dp?'Online':'Unavailable'?></span></div><div class="kv"><b>Freshness</b><span><?=htmlspecialchars(agev($dpfile))?></span></div></article>
<article class="card"><h3>g2_link</h3><div class="kv"><b>Version</b><span><?=htmlspecialchars(ver($g2,'/g2_link\s+([0-9.]+)/i'))?></span></div><div class="kv"><b>Dashboard</b><span><?=htmlspecialchars(ver($g2,'/Dashboard\s+(v?[0-9.]+)/i'))?></span></div><div class="kv"><b>Source</b><span><?=$g2?'Online':'Unavailable'?></span></div><div class="kv"><b>Freshness</b><span><?=htmlspecialchars(agev($g2file))?></span></div></article></section>
<?php echo str_replace('WB1GOF', htmlspecialchars(dashboardConfig('callsign'), ENT_QUOTES, 'UTF-8'), file_get_contents(__DIR__ . '/rf_command_reference.html')); ?>
<h2>Remote Users</h2>
<div class="table">
<table>
<thead>
<tr>
<th>Callsign</th>
<th>Activity</th>
<th>Type / Source</th>
<th>User Message</th>
<th>Date-Time</th>
</tr>
</thead>
<tbody>
<?php if (empty($remote)): ?>
<tr><td colspan="5">No remote users reported.</td></tr>
<?php else: foreach ($remote as $user): ?>
<tr>
<?php for ($column = 0; $column < 5; $column++): ?>
<td><?=htmlspecialchars(isset($user[$column]) ? $user[$column] : '', ENT_QUOTES, 'UTF-8')?></td>
<?php endfor; ?>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<h2>Unified Last Heard</h2>
<nav aria-label="Activity filters">
<a href="?activity=all" <?=$activityFilter === 'all' ? 'aria-current="page"' : ''?>>All Activity</a> |
<a href="?activity=local" <?=$activityFilter === 'local' ? 'aria-current="page"' : ''?>>Local RF</a> |
<a href="?activity=remote" <?=$activityFilter === 'remote' ? 'aria-current="page"' : ''?>>Remote</a>
</nav>
<p>Showing <?=count($unifiedDisplay)?> of <?=count($filteredActivity)?> records (maximum 30).</p>
<p>Associated Link shows source-labelled historical evidence at the transmission time, not the current module link. g2_link associations currently require a confirmed DCS request/announcement pair in retained logs; missing, unsupported or ambiguous evidence is Unknown. Both sources are shown for local activity, including any simultaneous links. Remote modules are link associations, not confirmed RF delivery. Suffixes preserve reported callsign suffixes. DPlus history is not assessed for g2_link remote streams. DPlus Unlinked does not establish overall disconnection.</p>
<div class="table">
<table>
<thead>
<tr>
<th>Callsign / Suffix</th>
<th>Module / Link Association</th>
<th>Activity</th>
<th>Reported by</th>
<th>Associated Link</th>
<th>Reported Connection</th>
<th>Last Heard</th>
<th>User Message</th>
<th>RPT1</th>
<th>Header RPT2</th>
</tr>
</thead>
<tbody>
<?php if (!$unifiedDisplay): ?>
<tr><td colspan="10">No activity reported.</td></tr>
<?php else: foreach ($unifiedDisplay as $r): ?>
<tr>
<td><?=htmlspecialchars($r['callsign'] === '' ? 'Unknown (invalid MYCALL)' : $r['callsign'])?>
<?php if (isset($r['suffix']) && $r['suffix'] !== ''):
    $suffixDisplay = $r['suffix'] === 'DPLS' ? 'DPlus' : $r['suffix'];
?>
<br><small>Suffix: <?=htmlspecialchars($suffixDisplay, ENT_QUOTES, 'UTF-8')?></small>
<?php endif; ?>
</td>
<?php
$moduleDisplay = $r['module'];
if ($r['activity'] === 'Remote') {
    $moduleDisplay = 'Unknown';
    if (isset($r['module_source']) &&
        in_array($r['module_source'], array('g2_remote_report', 'dplus_hotspot_header'), true) &&
        in_array($r['module'], array('A','B','C','D','E'), true)) {
        $moduleDisplay = $r['module'];
    }
    if (isset($r['linked_local_module'], $r['linked_module_source']) &&
        in_array($r['linked_module_source'],
            array('link_event', 'g2_link_announcement'), true) &&
        in_array($r['linked_local_module'], array('A','B','C','D','E'), true)) {
        $moduleDisplay = $r['linked_local_module'] . ' (link only)';
    }
}
?>
<td><?=htmlspecialchars($moduleDisplay)?></td>
<td><?=htmlspecialchars($r['activity'])?></td>
<td><?=htmlspecialchars($r['connection'])?></td>
<td><?=htmlspecialchars($r['associated_link'])?></td>
<td><?=htmlspecialchars($r['via'])?></td>
<td><?=htmlspecialchars(date('Y-m-d H:i:s', $r['epoch']))?></td>
<td><?=htmlspecialchars($r['message'])?></td>
<td><?=htmlspecialchars($r['rpt1'])?></td>
<td><?=htmlspecialchars($r['rpt2'])?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<footer>Read-only prototype. DPlus and g2_link remain independent. Refreshes every 60 seconds.</footer></main></body></html>
