# Compatibility and qualification

Target existing CentOS 7 and AlmaLinux 9 RS-RP3C gateways with a web server and PHP, including Apache and nginx/PHP-FPM configurations. Dashboard syntax is written for PHP 5.4+. Installer requires Bash 4+ and the tools listed in INSTALL.md. Existing gateway applications retain their own installation and compatibility requirements.

| Site/platform | Evidence | Remaining acceptance |
| --- | --- | --- |
| WB1GOF, CentOS 7, DPlus 2.2t, g2_link 4.00 | Operator verified v0.4.2 production features; portable release hashes, syntax and 15 fixtures passed on PHP 5.4 | Installer/rollback acceptance; configuration migration |
| W4CRL, AlmaLinux 9.5, PHP 8.2.33, RS-RP3C v3.20 | Dashboard v0.4; PHP-FPM workers use nginx; expected input paths exist | Release fixtures, web configuration, portable upgrade and radio tests; upgrade not performed |
| Gateway without g2_link | Installer allows unavailable inputs after explicit operator acknowledgement | Verify DPlus-only rendering; no claim that it installs g2_link |

The Windows release-development tests do not qualify SELinux, Apache, file permissions, rollback or Linux service integration. W4CRL must be validated using its actual environment. Do not infer dashboard acceptance from the legacy gateway binary's operation.

Older MonLink test-site records remain separate from this dashboard qualification. The dashboard installer never deploys that patch.
