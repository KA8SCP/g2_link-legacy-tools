# PHP2Shell prerequisite for first installations

Starting with v0.5.1, the installer checks for fixes to CVE-2026-109618 and CVE-2026-109619 after the existing g2_link prerequisites and before downloading/staging/deploying a dashboard. A rejected check is recorded in the private installer transcript. No dashboard files are installed, although transcript and temporary working files are created. There is no bypass switch.

Use the v0.5.1 release tools or a newer reviewed checkout for this check. The immutable v0.5.0 release installer predates it; downloading that tag does not obtain the new gate. Existing-dashboard updates are outside this initial-install gate and do not certify PHP security. Updating the dashboard never patches the PHP runtime.

## What is checked

- The PHP command must belong to the system RPM `php-cli` package, and its running version must match the package version.
- Installed `php-common`, `php-cli`, and each installed `php` (mod_php) / `php-fpm` package must be recognized fixed builds and match one another. A patched CLI alone is insufficient. At least one packaged web runtime is required. This does not require PHP-FPM on an Apache/mod_php gateway.
- Additional PHP collection CLI/common/FPM packages are rejected for separate qualification. Unknown vendor backports fail closed; missing changelog entries alone are not used to determine vulnerability.
- Running Apache/FPM processes are checked for deleted executables or replaced PHP mappings that indicate a required service restart. The installer never restarts them.

Recognized minimums (policy reviewed October 27, 2026):

| Branch / vendor | Accepted fixed builds |
| --- | --- |
| PHP upstream 8.2 / 8.3 / 8.4 / 8.5 | 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12 or later within the same branch |
| GetPageSpeed EL7 PHP 5.4 | Version 5.4.45, release 20.el7 or later numeric EL7 release; GetPageSpeed vendor metadata required |
| AlmaLinux EL9 PHP 8.0 | Version 8.0.30, release 8.el9_8.1 or later numeric suffix, or 8.el9_8.alma.1 or later numeric suffix; AlmaLinux vendor metadata required |

Known fixed builds on the four gateways are 5.4.45-23.el7 (WB1GOF/VE3TTT), 8.0.30-8.el9_8.1 (K2BWK), and 8.2.35-1.module_el9.8.0+329+e5668d75 (W4CRL). Other legitimate fixes, including earlier Alma modular backports, require a policy update supported by vendor evidence; they are not guessed safe. Future PHP branches likewise require review.

## Limits and remediation

This is an offline installed-package prerequisite, not an exploit scanner, compromise investigation, signature-integrity audit, or comprehensive discovery of custom PHP runtimes. Package metadata is trusted. Custom binaries, containers, alternate handlers, remote FastCGI backends and unrecognized operating systems need separate administrator qualification; the system RPM check cannot certify their web routing. Administrators must confirm the dashboard is actually served by the checked runtime. Worker replacement detection is a supplementary check, not a substitute for restarting the relevant service after patching.

Patch the affected PHP runtime through a reviewed vendor channel, restart its existing web/PHP workers, verify local web operation, then rerun the installer. The dashboard installer does not add repositories, install updates, change certificates, alter access restrictions or edit gateway services. A read-only PHP application and HTTPS do not remove PHP2Shell exposure. OS support and other PHP vulnerabilities remain separate concerns.

Sources:

- [PHP official advisory and fixed upstream versions](https://www.php.net/archive/2026.php)
- [AlmaLinux EL9 PHP 8.0 security update ALSA-2026:87151](https://errata.almalinux.org/9/ALSA-2026-87151.html)
- [AlmaLinux initial backport package list](https://almalinux.org/blog/2026-10-21-php2shell-cve-2026-109618-cve-2026-109619/)
- [GetPageSpeed EL7 PHP 5.4 backport](https://www.getpagespeed.com/server-setup/php/php2shell-on-centos-7)
