<?php
/* WL7CWI XRF405 A evidence; no live files or network access. */
$xrfLog = $fixture . '/xrf.log';
$xrfRequest = "103026 at 11:59:55:sending link request(dextra) from mod B to link with: [XRF405  ] mod A [66.179.209.30]\n";
$xrfAnnouncement = "103026 at 11:59:58:sending File:[/root/g2_link/linked.dat], mod:[B], RADIO_ID=[LINKED XRF405 A     ]\n";
$xrfLink = $xrfRequest . $xrfAnnouncement;
$xrfTx = "103026 at 12:00:14:START from remote g2: streamID=216,126, flags=00:00:00, my=KA8SCP  , sfx=52AP, ur=CQCQCQ  , rpt1=WL7CWI B, rpt2=WL7CWI G, 56 bytes fromIP=66.179.209.30, source=XRF405 A\n" .
    "103026 at 12:03:04:START from remote g2: streamID=152,60, flags=00:00:00, my=VE6NS   , sfx=ID52, ur=CQCQCQ  , rpt1=WL7CWI B, rpt2=WL7CWI G, 56 bytes fromIP=66.179.209.30, source=XRF405 A\n";
file_put_contents($xrfLog, $xrfLink . $xrfTx);
$xrfHistory = g2DashboardHistoryRead($xrfLog);
check('WL7CWI XRF remote streams survive combined history filtering',
    $xrfHistory['error'] === '' && count($xrfHistory['records']) === 2);
$xrfRows = $xrfHistory['records'];
check('XRF callsigns and suffixes retained', count($xrfRows) === 2 &&
    $xrfRows[0]['callsign'] === 'KA8SCP' && $xrfRows[0]['suffix'] === '52AP' &&
    $xrfRows[1]['callsign'] === 'VE6NS' && $xrfRows[1]['suffix'] === 'ID52');
check('XRF local B is distinct from remote A and RF delivery unconfirmed',
    count($xrfRows) === 2 && $xrfRows[0]['linked_local_module'] === 'B' &&
    $xrfRows[0]['via'] === 'XRF405 A' && $xrfRows[0]['rf_delivery'] === 'unconfirmed');
check('XRF gateway headers retained without inventing local RF',
    count($xrfRows) === 2 && $xrfRows[0]['rpt1'] === 'WL7CWI B' &&
    $xrfRows[0]['rpt2'] === 'WL7CWI G' && $xrfRows[0]['activity'] === 'Remote');
$xrfAt = strtotime('2026-10-30 12:00:14');
check('XRF link history annotates local B only',
    g2DashboardLinkAt($xrfHistory, $xrfAt, 'B') === 'XRF405 A' &&
    g2DashboardLinkAt($xrfHistory, $xrfAt, 'A') === 'Unknown');
$xrfAssociated = dashboardAssociatedLinksAnnotate($xrfHistory, $xrfRows);
check('XRF remote associated link is source-labelled',
    count($xrfAssociated) === 2 && $xrfAssociated[0]['associated_link'] === 'g2_link: XRF405 A');
file_put_contents($xrfLog, $xrfRequest . $xrfTx);
$case = g2DashboardHistoryRead($xrfLog);
check('XRF request without linked announcement cannot associate remote audio', count($case['records']) === 0);
file_put_contents($xrfLog, $xrfAnnouncement . $xrfTx);
$case = g2DashboardHistoryRead($xrfLog);
check('XRF announcement without request/IP cannot associate remote audio', count($case['records']) === 0);
file_put_contents($xrfLog, str_replace('(dextra)', '', $xrfLink) . $xrfTx);
$case = g2DashboardHistoryRead($xrfLog);
check('unqualified XRF transport request does not establish evidence', count($case['records']) === 0);
file_put_contents($xrfLog, $xrfLink . str_replace('fromIP=66.179.209.30', 'fromIP=192.0.2.1', $xrfTx));
$case = g2DashboardHistoryRead($xrfLog);
check('XRF source IP mismatch excluded', count($case['records']) === 0);
file_put_contents($xrfLog, $xrfLink . str_replace('source=XRF405 A', 'source=XRF405 B', $xrfTx));
$case = g2DashboardHistoryRead($xrfLog);
check('XRF remote module mismatch excluded', count($case['records']) === 0);
file_put_contents($xrfLog, $xrfLink . str_replace('source=XRF405 A', 'source=DCS405 A', $xrfTx));
$case = g2DashboardHistoryRead($xrfLog);
check('remote-g2 format does not silently become DCS traffic', count($case['records']) === 0);
foreach (array(
    "103026 at 12:00:00:Unlinking from [XRF405  ] mod A\n",
    "103026 at 12:00:00:sending File:[/root/g2_link/unlinked.dat], mod:[B]\n",
    "103026 at 12:00:00:startup\n",
    "103026 at 12:00:00:disconnect\n",
    "103026 at 12:00:00:sending link request(dextra) from mod B to link with: [XRF406  ] mod A [192.0.2.2]\n"
) as $reset) {
    file_put_contents($xrfLog, $xrfLink . $reset . $xrfTx);
    $case = g2DashboardHistoryRead($xrfLog);
    check('XRF stale association cleared: ' . trim(substr($reset, 19)), count($case['records']) === 0);
}
file_put_contents($xrfLog, $xrfLink .
    str_replace(array('mod B ', 'mod:[B]', '11:59:55', '11:59:58'),
        array('mod C ', 'mod:[C]', '11:59:59', '12:00:00'), $xrfLink) . $xrfTx);
$case = g2DashboardHistoryRead($xrfLog);
check('same XRF destination on two modules remains ambiguous', count($case['records']) === 0);
file_put_contents($xrfLog . '-20261030', $xrfLink);
file_put_contents($xrfLog, $xrfTx);
$case = g2DashboardHistoryRead($xrfLog);
check('XRF rotated request/announcement evidence retained', count($case['records']) === 2);
file_put_contents($xrfLog, $xrfLink . $xrfTx);
$case = g2DashboardHistoryRead($xrfLog);
check('XRF copytruncate overlap deduplicated', count($case['records']) === 2 && $case['duplicate_records'] === 0);
// The duplicate link events regress time at the file boundary; instead exercise
// duplicate transmissions after a retained link with a separate same-day copy.
file_put_contents($xrfLog . '-20261030', $xrfLink . $xrfTx);
file_put_contents($xrfLog, $xrfTx);
$case = g2DashboardHistoryRead($xrfLog);
check('XRF archive clock regression clears subsequent stale association',
    count($case['records']) === 2 && $case['clock_regressions'] === 1 &&
    !$case['link_history']['ordered']);
file_put_contents($xrfLog . '-20261030', $xrfLink);
$xrfFirstTx = substr($xrfTx, 0, strpos($xrfTx, "\n") + 1);
file_put_contents($xrfLog, $xrfFirstTx . $xrfFirstTx);
$case = g2DashboardHistoryRead($xrfLog);
check('XRF duplicate stream records are not displayed twice',
    count($case['records']) === 1 && $case['duplicate_records'] === 1);
// Leave an isolated XRF page fixture for the existing end-to-end render test.
unlink($g2log . '-20261011');
file_put_contents($g2log, $xrfLink . $xrfTx);
