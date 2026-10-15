<?php
/* Synthetic large history: no gateway files or services are used. */
require_once $fixture . '/dplus_dashboard_history.php';
$large = array('error'=>'', 'lookup_enabled'=>true,
    'snapshots'=>array(), 'replay'=>array('transitions'=>array()));
for ($i = 0; $i < 12500; $i++) {
    $large['snapshots'][] = array('timeline_usable'=>true, 'line_number'=>$i+1,
        'anchor'=>array('upper_epoch'=>1000+$i*60, 'lower_epoch'=>1000+$i*60),
        'gateways'=>array('B'=>array('reflector'=>$i % 2 ? 'REF051' : 'REF050',
            'remote_module'=>'C', 'remote_ip'=>'192.0.2.1')));
}
$large['snapshot_index'] = dplusTimelineSnapshotIndex($large['snapshots']);
check('large history indexed once', count($large['snapshot_index']['items']) === 12500);
foreach (array(0,1,37,1000,6692,12498,12499) as $i) {
    $answer = dplusTimelineLookup($large,1000+$i*60,'B');
    check('indexed baseline '.$i, $answer['dplus_status'] === 'linked' &&
        $answer['connection']['reflector'] === ($i % 2 ? 'REF051' : 'REF050'));
    if ($i < 12499) {
        $answer = dplusTimelineLookup($large,1000+$i*60+30,'B');
        check('between snapshots '.$i, $answer['dplus_status'] === 'linked' &&
            $answer['connection']['reflector'] === ($i % 2 ? 'REF051' : 'REF050'));
    }
}
foreach (array(999,1000+12499*60+0.01) as $epoch) {
    $answer = dplusTimelineLookup($large,$epoch,'B');
    check('outside coverage stays unknown '.$epoch, $answer['reason'] === 'outside_snapshot_coverage');
}
$small = $large;
$small['snapshots'] = array_slice($large['snapshots'],0,3);
$small['snapshots'][1]['timeline_usable'] = false;
$small['snapshot_index'] = dplusTimelineSnapshotIndex($small['snapshots']);
$answer = dplusTimelineLookup($small,1060,'B');
check('unusable snapshot excluded', $answer['connection']['reflector'] === 'REF050');
$small['snapshots'][1]['timeline_usable'] = true;
$small['snapshots'][1]['anchor'] = $small['snapshots'][0]['anchor'];
$small['snapshot_index'] = dplusTimelineSnapshotIndex($small['snapshots']);
$answer = dplusTimelineLookup($small,1000,'B');
check('equal epochs retain last log-order baseline', $answer['connection']['reflector'] === 'REF051');
$small['replay']['transitions'][] = array('line_number'=>2,'affected_modules'=>array('B'),
    'lower_epoch'=>1050,'upper_epoch'=>1051,'state_after'=>array('B'=>array(
        'dplus_status'=>'unlinked','connection'=>null,'overall_connection'=>'unknown')));
foreach (array(1050,1050.5,1051) as $epoch) {
    $answer = dplusTimelineLookup($small,$epoch,'B');
    check('transition uncertainty preserved '.$epoch, $answer['reason'] === 'transition_window');
}
$small['lookup_enabled'] = false;
$answer = dplusTimelineLookup($small,1060,'B');
check('unsafe timeline stays unknown', $answer['reason'] === 'unsafe_timeline');
$small['lookup_enabled'] = true;
$small['snapshots'][2]['anchor']['upper_epoch'] = 900;
$small['snapshot_index'] = dplusTimelineSnapshotIndex($small['snapshots']);
$answer = dplusTimelineLookup($small,1060,'B');
check('regressing snapshot order fails closed', $answer['reason'] === 'unsafe_snapshot_order');
unset($large,$small);
