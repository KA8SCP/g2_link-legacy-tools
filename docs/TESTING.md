# Release and gateway acceptance tests

v0.5.1 release checks also run `tests/php-security-test.php` and `tests/php-security-test.sh`: fixed/vulnerable package boundaries, vendor/mixed/alternate runtime rejection, CLI ownership, stale worker detection, and initial-install failure before deployment. Existing updates deliberately skip the initial-install gate. Test custom web runtime routing separately; package fixtures do not establish it.

For stable v0.5.1, current production/operator evidence is in RELEASE-READINESS.md. Earlier qualification notes below are historical; they do not override that record.

Run `bash scripts/check-release.sh` in a release checkout. It verifies shell syntax, runtime hashes, PHP syntax/fixtures, transcript lifecycle, updater cancellation/rollback/configuration preservation and theme behavior. Node.js is a development-test dependency only. Fixtures use temporary paths and never change production gateways. The updater test substitutes its HTTP boundary, so passing automation alone does not qualify every real web setup.

The latest-release selector is tested to accept a published stable tag and reject drafts, prereleases and incomplete metadata. Optional --ref overrides select a reviewed tag or branch. Verify actual release discovery after publication without disabling TLS verification.

## Repeatable site acceptance

1. Record OS, PHP CLI/web versions, web worker/group, source paths, source commit and gateway callsign.
2. Confirm g2_link/DPlus already operate, QSO_DETAILS is enabled where detailed history is needed, status feeds are nonempty/readable, and the chosen URL renders PHP.
3. For an upgrade confirm the saved callsign/timezone/paths and ownership are preserved. For a new site enter its identity explicitly.
4. Review the summary; keep the transcript and rollback record. Verify HTTP success, footer version/revision and no unexpected gateway-service changes.
5. Compare a fresh Local RF transmission and remote/reflector activity with the native feeds/logs. Check RPT1/RPT2, suffix, source label and conservative Unknown associations.
6. Check filters/selected state, dark/light persistence, RF-reference panel and desktop/mobile presentation.
7. Exercise intentional cancellation, missing prerequisites, hash failure, unreadable inputs, unsafe paths/symlinks, failed HTTP rollback and backup restoration on an isolated test system rather than a working gateway.
8. Check archive continuity, clock/restart boundaries, sparse logs and copytruncate/append interaction. Actual rotation scheduling remains locally administered.

## Historical qualification notes
# Release and gateway acceptance tests

Run `bash scripts/check-release.sh` in a release checkout. It checks Bash syntax, all runtime checksums, PHP syntax and isolated PHP fixtures. The fixtures verify W4CRL-specific local header parsing, timezone conversion, duplicate ambiguity, archive continuity, restart clearing, independent suffixes, M17 and DPlus header applicability. They do not change live files or read real gateway logs.

Before qualifying an installer release on CentOS 7/PHP 5.4 and AlmaLinux 9:

1. Verify first installation into a new directory and v0.4 upgrade with an outside-web-root rollback archive.
2. Confirm web-user input readability, SELinux labeling and PHP rendering through the actual web server and PHP worker configuration.
3. Check missing-input handling, cancelled approval, hash failure, unreadable input, existing destination symlink and failed HTTP rollback.
4. Confirm local RF and reflector activity, DPlus connected hotspot, g2_link Software Clients and last-heard separation.
5. Confirm NXDN/P25/DMR/M17 suffixes preserve independent streams; transmitted messages remain separate.
6. Check midnight DPlus transition uncertainty, DPlus restart sessions and weekly archive recovery.
7. Check g2_link daily copytruncate, overlapping archives, missing archive days, re-link/restart and non-DCS replacement. Unknown associations must not be guessed.
8. Expand RF Command Reference in desktop/mobile layouts and verify its position above Remote Users.
9. Record site, OS, PHP CLI/web versions, checked commit, input/logrotate layout and test outcomes. Verify the backup restores the previous site.

WB1GOF production tests before portability are recorded in CHANGELOG.md. New installer acceptance and W4CRL deployment remain pending until observed on the gateways.

Portable-package checks, October 12: all PHP files pass PHP 8.4.26 CLI syntax checks; 15 isolated fixtures pass, including W4CRL page-title/RF-reference rendering. Bash 5.2 syntax validation passes for all scripts. WB1GOF also passed the full release checksum, syntax and 15-fixture check on PHP 5.4. Linux installer/rollback acceptance remains pending. W4CRL reports AlmaLinux 9.5, PHP 8.2.33 and PHP-FPM workers running as nginx; its upgrade remains pending.

## Mandatory prerequisite gate

On an isolated Linux test system, verify missing/non-executable g2_link, missing/empty status HTML and an alternate valid executable/status path. Failed cases must exit before PHP checks, Git downloads, backup creation and web deployment. For a qualifying case confirm subsequent PHP prerequisites are reached and the binary is never executed. Confirm no gateway or web service configuration changes. These new behavioral cases await Linux qualification; local Bash is unavailable in the Windows workspace.


## 0.5.0-rc.1 qualification

Automated checks now exercise transcript stdout/stderr, mode 0600, success/failure exit status and the actual early g2_link prerequisite failure in an isolated directory, plus footer version and invalid/missing build metadata. Before a stable release, confirm transcript capture on CentOS 7/Bash 4/PHP 5.4 and AlmaLinux, including cancellation, completed installation and HTTP rollback. Verify footer revision matches install records and no transcript is web-served. The administrator reported sparse-log dashboard upgrades on all three gateways and a fresh WB1GOF local RF association test; this does not complete every acceptance case above.
