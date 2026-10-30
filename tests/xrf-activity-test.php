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

// WB1GOF: inbound client 1NFO expired while the DCS049 C link stayed up.
$expiryLog = $fixture . '/client-expiry.log';
$dcsExpiryLink = "102926 at 00:00:01:sending link request from mod C to link with: [DCS049  ] mod C [54.237.10.173]\n" .
    "102926 at 00:00:04:sending File:[/root/g2_link/linked.dat], mod:[C], RADIO_ID=[LINKED DCS049 C     ]\n";
$clientExpiry = "102926 at 01:24:53:call=1NFO     timeout, removing 10.0.0.2, users=0\n";
$dcsExpiryTx = "103026 at 13:53:05:START from dcs: streamID=101,80, my=KA8SCP  , sfx=DMR , ur=CQCQCQ  , rpt1=DCS049 C, rpt2=XLX978 G, 100 bytes fromIP=54.237.10.173, source=DCS049 C\n" .
    "103026 at 14:30:01:START from dcs: streamID=191,57, my=W1KFR   , sfx=DPLS, ur=CQCQCQ  , rpt1=DCS049 C, rpt2=KB1SBJ G, 100 bytes fromIP=54.237.10.173, source=DCS049 C\n";
file_put_contents($expiryLog, $dcsExpiryLink . $clientExpiry . $dcsExpiryTx);
$expiryHistory = g2DashboardHistoryRead($expiryLog);
check('WB1GOF client timeout does not erase confirmed DCS history',
    $expiryHistory['error'] === '' && count($expiryHistory['records']) === 2 &&
    $expiryHistory['excluded'] === 0);
check('WB1GOF DCS historical association survives client expiry',
    g2DashboardLinkAt($expiryHistory, strtotime('2026-10-30 13:53:05'), 'C') === 'DCS049 C');
check('DCS ingress headers and suffix retained after unrelated expiry',
    count($expiryHistory['records']) === 2 &&
    $expiryHistory['records'][0]['rpt2'] === 'XLX978 G' &&
    $expiryHistory['records'][0]['suffix'] === 'DMR' &&
    $expiryHistory['records'][0]['rf_delivery'] === 'unconfirmed');
foreach (array(
    "102926 at 01:24:53:Unlinked from [DCS049  ] mod C, TIMEOUT...\n",
    "102926 at 01:24:53:Unlinking from [DCS049  ] mod C\n",
    "102926 at 01:24:53:unknown transport timeout\n",
    "102926 at 01:24:53:call=1NFO     timeout, removing 10.0.0.2, users=invalid\n",
    "102926 at 01:24:53:disconnect\n",
    "102926 at 01:24:53:startup\n"
) as $reset) {
    file_put_contents($expiryLog, $dcsExpiryLink . $reset . $dcsExpiryTx);
    $case = g2DashboardHistoryRead($expiryLog);
    check('real or unqualified reset still clears DCS history: ' . trim(substr($reset, 19)),
        count($case['records']) === 0);
}
file_put_contents($expiryLog, $clientExpiry . $dcsExpiryTx);
$case = g2DashboardHistoryRead($expiryLog);
check('client expiry cannot create a missing DCS association', count($case['records']) === 0);
file_put_contents($expiryLog . '-20261030', $dcsExpiryLink . $clientExpiry);
file_put_contents($expiryLog, $dcsExpiryTx);
$case = g2DashboardHistoryRead($expiryLog);
check('DCS client-expiry correction survives retained archive boundary',
    count($case['records']) === 2 && $case['excluded'] === 0);
file_put_contents($xrfLog, $xrfLink .
    "103026 at 12:00:00:call=1NFO     timeout, removing 10.0.0.2, users=0\n" . $xrfTx);
unlink($xrfLog . '-20261030');
$case = g2DashboardHistoryRead($xrfLog);
check('unrelated client expiry also preserves confirmed XRF history', count($case['records']) === 2);
file_put_contents($expiryLog, $dcsExpiryLink .
    "102926 at 01:24:53:Unlinked from [DCS049  ] mod C, TIMEOUT...\n" .
    "102926 at 01:24:54:call=1NFO     timeout, removing 10.0.0.2, users=0\n" . $dcsExpiryTx);
unlink($expiryLog . '-20261030');
$case = g2DashboardHistoryRead($expiryLog);
check('client expiry never restores evidence cleared by real link timeout', count($case['records']) === 0);
