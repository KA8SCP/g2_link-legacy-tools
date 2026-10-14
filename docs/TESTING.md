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

