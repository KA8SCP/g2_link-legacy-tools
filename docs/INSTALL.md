# Prompted dashboard installation and upgrade

This installs the dashboard on a gateway with an existing web server with working PHP integration. It does not bootstrap a gateway, add g2_link, change reflector links, install the DTMF script, alter MonLink, edit logrotate/cron, or restart gateway services.

## Prerequisites

- Bash 4+, PHP CLI 5.4+ and working web PHP integration (Apache/PHP or Nginx with a configured PHP backend).
- Git, curl, sha256sum, tar, install, mktemp, realpath, getent and normal coreutils.
- An installed executable g2_link and readable, nonempty g2_link status HTML are mandatory. These are checked before PHP requirements, downloads, backups or deployment; there is no reduced-mode override. Existing DPlus inputs are also required as appropriate. Missing optional logs retain their explicit confirmation.
- The correct PHP worker user/group and readable source paths. Unix permissions are checked with `runuser` when available. HTTP verification additionally checks the actual PHP/web deployment. SELinux stays enabled; `restorecon` is used when available.

## Procedure

1. Clone the GitHub repository and review the package/ref.
2. Run `sudo bash scripts/install-dashboard-interactive.sh`, or `sudo bash scripts/install-dashboard-interactive.sh --package-dir "$PWD"` for the reviewed local checkout.
3. Enter callsign (without module), log timezone, document root, destination, DPlus/g2_link HTML and log paths, web user/group, local HTTP URL and backup location. Paths can differ from WB1GOF's defaults. The verification URL must point to this site's web virtual host; adjust its loopback port where necessary.
4. Review the concrete installation summary and approve installation. The installer verifies all release hashes and PHP syntax and validates the generated configuration before this final prompt.
5. Existing files are backed up outside the web tree. Dependencies/configuration are copied before index.php. Existing unrelated files are retained; release-file symlinks are refused. The installer restores SELinux labels, checks deployed hashes and fetches the page through the web server. Failed deployment verification reverts the dashboard and retains failure files for review.
6. Perform RF and remote-transmission acceptance tests. An HTTP check verifies rendering, not complete radio behavior or every log format.

No existing `/dashboard/` is needed for a fresh installation. For a v0.4 upgrade, use the existing destination and retain the reported rollback archive. The same configurable package is intended for W4CRL after its actual platform and input paths are verified.

## Rollback

The installer records repository commit/ref, deployment path, checksums and an archive under the chosen backup directory. On an automatic rollback it moves the failed installation into that directory and extracts the previous dashboard at its original location. For manual rollback, first preserve the current directory outside the web tree, then extract the recorded archive under the dashboard's parent directory, restore labels, and verify the HTTP page. A new installation has no previous archive to restore; its new-install marker records that fact.

## Staging only

`bash scripts/install-dashboard.sh /absolute/new/candidate-directory` copies the checksum-verified runtime files into a new directory and refuses overwrite. Configure `dashboard.local.php` before using that candidate at another site; apply local ownership/labels and test it before switching live.


## K2BWK qualification — October 14, 2026

The reported AlmaLinux 9.7 system had populated DPlus inputs, no g2_link executable found in the searched locations or active process, and zero-byte fs.html/g2_link.log placeholders. Both dashboard attempts failed HTTP verification and rolled back; no dashboard remained and the records identified new installations with no previous deployment. Archive cleanup was advised; completion was not independently verified.

Nginx was serving Icom WebApp through ports 5000/5001, while PHP CLI was installed and PHP-FPM inactive. The HTTP 403 was a directory-index failure, separate from missing g2_link. PHP CLI syntax success does not qualify web PHP execution. PHP-FPM is dashboard-specific on that Nginx arrangement, not a stock Icom prerequisite or universal installer requirement. This installer does not enable it, edit Nginx, or alter Icom/DPlus services.

The new gate rejects missing/non-executable binaries and missing/empty/unreadable status feeds. It does not execute the supplied binary, require a service-name convention, or prove live/fresh/semantically valid status; verify those during site acceptance. Do not create placeholder files to pass qualification.