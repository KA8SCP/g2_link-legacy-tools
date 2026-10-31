# Dashboard production release qualification

Current release: **v0.5.2**, October 31, 2026. See [v0.5.2 notes](releases/v0.5.2.md). It promotes the activity fixes below and includes the isolated-tested fresh-site web-readiness gate. Live fresh-install acceptance of that new gate remains pending; VE3RXR is planned, not a passed test. Historical v0.5.0/v0.5.1 evidence below remains separately scoped.

Release date: October 27, 2026. Package version 0.5.0; stable tag v0.5.0. Production scope is the read-only dashboard and its install/update tooling for existing configured gateways. It is not a release of g2_link, DPlus, Icom software, MonLink, or an OS image.

## Operator evidence

| Environment | Reported/observed result |
| --- | --- |
| WB1GOF: CentOS 7, Apache/PHP 5.4 | Sparse-log fix returned HTTP 200 in 10.086 seconds; fresh Local RF event correctly associated DCS049 C and RPT1/RPT2. v0.5 candidate updater preserved callsign, timezone, paths and apache:apache ownership; hashes, PHP syntax, HTTP verification and transcript completed with exit 0. |
| W4CRL: AlmaLinux 9, nginx/PHP-FPM | Candidate updater installed the expected revision. Live page inspection confirmed W4CRL settings, W4CRL local records and incoming REF030 C activity. Operator confirmed the apparent cross-gateway data issue was legitimate shared reflector traffic. Existing ownership is nginx:nginx. |
| K2BWK: AlmaLinux 9.7, nginx/PHP-FPM | Prior dashboard updates were reported complete; g2_link logging append mode was verified. Existing ownership apache:nginx is retained by the updater. Latest updater-specific acceptance is not separately claimed. |
| VE3TTT: CentOS 7, Apache/PHP 5.4 | First dashboard installation HTTP-verified after PHP installation and a full Apache restart. Correct footer/activity was confirmed. QSO_DETAILS was enabled by the administrator, DCS405 A activity verified, append logging corrected, rotation rule debug-validated. Latest updater-specific acceptance is not separately claimed. |
| iPhone, Android tablet, PC browsers | Operator reported successful display testing of the candidate dashboard, including the clarified activity presentation; light/dark display was also positively reviewed. Individual browser/OS versions were not supplied. |

Production reports apply to the tested candidate functionality. The stable promotion changes the package identifier and release-source selection; no production gateway is changed by publication. It does not imply every failure case was exercised on every live gateway.

## Automated and preview evidence

- PHP fixture checks cover site configuration, headers, source associations, archive/clock boundaries, sparse logs, invalid metadata and rendered page structure.
- Isolated Linux updater tests cover cancellation, simulated HTTP failure/rollback, saved-config byte preservation, existing ownership, installed manifest and unsafe input rejection. Intentional failure tests use temporary fixtures, not production gateways.
- Transcript tests cover stdout/stderr, private file mode, early prerequisite failure, exit status and private FIFO cleanup.
- Theme tests cover default light display, toggling, persistence, tab synchronization and blocked-storage fallback. Browser previews checked selected filters and light/dark appearance, including a phone-sized layout.
- Stable release selection rejects draft/prerelease/malformed metadata, records the selected source commit, and permits explicit tag/branch overrides.

## Remaining operational limits

- Real HTTP behavior still depends on the site's Apache/nginx/PHP routes, SELinux and source readability. The isolated updater HTTP failure test uses a substitute boundary.
- New installation requires existing g2_link/DPlus and a nonempty status feed. QSO_DETAILS=Y is needed for detailed g2_link history. The updater does not edit g2_link.cfg, cron or logrotate.
- Historical association coverage is conservative; missing/unsupported/ambiguous evidence remains Unknown, and a historical link is not proof of RF delivery. The stable v0.5.1 baseline supports confirmed DCS associations; the candidate below additionally supports the observed XRF/DExtra formats.
- External certificate renewal may overwrite nginx routes or install an incomplete chain. That independent script is not repaired by this release.
- The roughly ten-second WB1GOF page build is functional but remains a performance opportunity on legacy hardware/logs.
- Backup retention and transcript retention are administered locally. OS/PHP maintenance is outside this package.

Use TESTING.md for repeatable acceptance steps and UPDATING.md for deployment instructions. Future changes require their own regression and operator qualification rather than inheriting an unlimited production-ready claim.

## Activity-fix candidate qualification

Candidate 0.5.2-rc.2 includes XRF/DExtra history and a correction preventing
inbound-client expiry from invalidating reflector history. These activity fixes are promoted in stable v0.5.2; the reports below identify the exact candidate tested at each site.

| Site | Deployment evidence | Operator activity acceptance |
| --- | --- | --- |
| WL7CWI, CentOS/Apache PHP 5.4 | rc.1, commit 9fd743fa69ba; hashes/syntax/HTTP verified, exit 0 | XRF405 A missing activity resolved. rc.2 at this site not reported. |
| WB1GOF, CentOS/Apache PHP 5.4 | rc.2, commit 9dc673999be4; hashes/syntax/HTTP verified, exit 0 | DCS049 C history restored despite 1NFO client expiry; no reflector relink required. |
| K2BWK, AlmaLinux/nginx PHP-FPM | rc.2, same code commit; settings and apache:nginx ownership retained; exit 0 | Correct dashboard/activity confirmed. Individual transport coverage not separately supplied. |
| VE3TTT, CentOS/Apache | rc.2, same code commit; settings and apache:apache ownership retained; exit 0 | Correct dashboard/activity confirmed. Individual transport coverage not separately supplied. |
| VE7VIC, AlmaLinux 9.8/nginx PHP-FPM | rc.2, same code commit; America/Vancouver and apache:apache retained; exit 0 | Operator reports working XRF405 A on local C after enabling QSO_DETAILS=Y and restarting g2_link. New detailed headers observed. |

VE7VIC's initial 403 came from absent Nginx index/PHP routing; a scoped
dashboard route fixed it. Its native systemd g2_link unit already used append
for both output streams: no append-mode correction was needed. QSO_DETAILS was N
and was enabled by the administrator. Preserve this distinction from the
previous CentOS append-mode issue. The first post-restart stream preceded its
link announcement and may correctly remain excluded.

Local automated candidate results: 125 dashboard assertions passed on PHP
8.4.26, including DCS/XRF fixtures, wrong-source/IP rejection, missing/ambiguous
confirmation, archives, duplicates, real-timeout resets, inbound-client expiry
and rendered output. Runtime hashes and PHP syntax passed; GitHub CI passed on
the candidate before documentation finalization. Gateway syntax/HTTP results
are distinct from a full automated PHP 5.4 suite.

No claim is made that every case was exercised at every site. None of these
changes modifies g2_link.cfg, MonLink, DPlus or the Icom applications. Future
stable release/promotion and any remaining protocol coverage need explicit
tracking.

## v0.5.2 fresh-install gate

The complete Linux CI release suite passes for the new readiness tools, including rejection of raw PHP, wrong content, 403/502, redirects, network failures and CLI/web version mismatch, plus cleanup and legacy-file preservation. These are isolated boundaries, not a live new gateway installation. The gate leaves existing index.php files untouched and does not substitute for final deployed HTTP/RF checks. VE3RXR is the proposed next live first installation; record its outcome independently.
