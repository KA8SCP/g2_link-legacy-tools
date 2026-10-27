# Dashboard production release qualification

Current release: v0.5.1. See [v0.5.1 changes and qualification](releases/v0.5.1.md). It adds an isolated-tested first-install PHP2Shell package gate and changes the footer/package identity; it does not claim a new live installation on the gateways. The evidence below records the v0.5.0 baseline. All four gateways subsequently completed v0.5.0 installation and separate PHP security remediation with local HTTP verification, as recorded in the v0.5.1 notes.

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
- Historical association coverage is conservative; missing/unsupported/ambiguous evidence remains Unknown, and a historical link is not proof of RF delivery. Confirmed DCS associations are supported; additional protocol/event formats require separate qualification.
- External certificate renewal may overwrite nginx routes or install an incomplete chain. That independent script is not repaired by this release.
- The roughly ten-second WB1GOF page build is functional but remains a performance opportunity on legacy hardware/logs.
- Backup retention and transcript retention are administered locally. OS/PHP maintenance is outside this package.

Use TESTING.md for repeatable acceptance steps and UPDATING.md for deployment instructions. Future changes require their own regression and operator qualification rather than inheriting an unlimited production-ready claim.
