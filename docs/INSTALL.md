# Prompted dashboard installation and upgrade

This installs the dashboard on a gateway with an existing Apache/PHP installation. It does not bootstrap a gateway, add g2_link, change reflector links, install the DTMF script, alter MonLink, edit logrotate/cron, or restart gateway services.

## Prerequisites

- Bash 4+, PHP CLI 5.4+ and working Apache PHP integration.
- Git, curl, sha256sum, tar, install, mktemp, realpath, getent and normal coreutils.
- Existing DPlus status/log inputs and, for g2_link activity, its status/log inputs. The installer reports missing inputs and asks whether a deliberately reduced installation is acceptable.
- The correct PHP worker user/group and readable source paths. Unix permissions are checked with `runuser` when available. HTTP verification additionally checks the actual PHP/web deployment. SELinux stays enabled; `restorecon` is used when available.

## Procedure

1. Clone the GitHub repository and review the package/ref.
2. Run `sudo bash scripts/install-dashboard-interactive.sh`, or `sudo bash scripts/install-dashboard-interactive.sh --package-dir "$PWD"` for the reviewed local checkout.
3. Enter callsign (without module), log timezone, document root, destination, DPlus/g2_link HTML and log paths, web user/group, local HTTP URL and backup location. Paths can differ from WB1GOF's defaults. The verification URL must point to this site's Apache virtual host; adjust its loopback port where necessary.
4. Review the concrete installation summary and approve installation. The installer verifies all release hashes and PHP syntax and validates the generated configuration before this final prompt.
5. Existing files are backed up outside the web tree. Dependencies/configuration are copied before index.php. Existing unrelated files are retained; release-file symlinks are refused. The installer restores SELinux labels, checks deployed hashes and fetches the page through the web server. Failed deployment verification reverts the dashboard and retains failure files for review.
6. Perform RF and remote-transmission acceptance tests. An HTTP check verifies rendering, not complete radio behavior or every log format.

No existing `/dashboard/` is needed for a fresh installation. For a v0.4 upgrade, use the existing destination and retain the reported rollback archive. The same configurable package is intended for W4CRL after its actual platform and input paths are verified.

## Rollback

The installer records repository commit/ref, deployment path, checksums and an archive under the chosen backup directory. On an automatic rollback it moves the failed installation into that directory and extracts the previous dashboard at its original location. For manual rollback, first preserve the current directory outside the web tree, then extract the recorded archive under the dashboard's parent directory, restore labels, and verify the HTTP page. A new installation has no previous archive to restore; its new-install marker records that fact.

## Staging only

`bash scripts/install-dashboard.sh /absolute/new/candidate-directory` copies the checksum-verified runtime files into a new directory and refuses overwrite. Configure `dashboard.local.php` before using that candidate at another site; apply local ownership/labels and test it before switching live.
