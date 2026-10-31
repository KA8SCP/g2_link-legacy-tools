# Prompted dashboard installation and upgrade

This installs the dashboard on a gateway with an existing web server with working PHP integration. It does not bootstrap a gateway, add g2_link, change reflector links, install the DTMF script, alter MonLink, edit logrotate/cron, or restart gateway services.

## Prerequisites

**v0.5.1 first-install prerequisite:** use the v0.5.1 release installer, which runs the offline [PHP2Shell package check](PHP-SECURITY.md) before downloading or deploying a dashboard. Historical v0.5.0-tag commands below predate this check. There is no bypass. Known patched RPM builds are accepted, while unverified builds stop for administrator remediation. This does not automatically patch PHP or certify custom web runtimes. Follow the current commands in README.md and [v0.5.1 release notes](releases/v0.5.1.md).

- Bash 4+, PHP CLI 5.4+ and working web PHP integration (Apache/PHP or Nginx with a configured PHP backend).
- Git, curl, sha256sum, tar, install, mktemp, realpath, getent and normal coreutils.
- The executable prompt defaults to `/root/g2_link/g2_link`. Press Enter for that installation layout, or supply the absolute path to the installed executable if it differs. This is a prerequisite check only: the installer does not execute, relocate or change permissions on g2_link.
- An installed executable g2_link and readable, nonempty g2_link status HTML are mandatory. These are checked before PHP requirements, downloads, backups or deployment; there is no reduced-mode override. Existing DPlus inputs are also required as appropriate. Missing optional logs retain their explicit confirmation.
- Detailed incoming XRF/DCS activity requires **`QSO_DETAILS=Y`** in the running g2_link configuration and append-mode logging to the configured readable log. See [activity logging](#g2_link-activity-logging-and-history-retention); status-page availability alone does not qualify detailed activity.
- Nginx sites must already route the dashboard entry point to PHP-FPM; a running pool alone is insufficient. See [dashboard-only Nginx routing](NGINX-DASHBOARD.md).
- The correct PHP worker user/group and readable source paths. Unix permissions are checked with `runuser` when available. HTTP verification additionally checks the actual PHP/web deployment. SELinux stays enabled; `restorecon` is used when available.

## Web-runtime readiness before installation

Perform this administrator step after installing or updating PHP, including the PHP2Shell fixed packages, and before running the dashboard installer. Package checks and `php -l` do not prove that the running web server executes PHP.

A restart briefly interrupts the affected web/PHP service and may affect other hosted applications. Choose a suitable maintenance window. Identify the existing handler and actual service/configuration names first; do not restart unrelated services or add PHP-FPM to a mod_php installation. A healthy dashboard-only installation or update does not require a routine restart. The dashboard installer does not perform these restarts automatically.

### Apache with mod_php

On the documented CentOS/AlmaLinux `httpd` layout, validate configuration, then fully restart Apache after a PHP module installation/update:

```bash
sudo httpd -t && sudo systemctl restart httpd
sudo systemctl --no-pager -l status httpd
sudo tail -n 30 /var/log/httpd/error_log
```

Do not substitute `systemctl reload httpd` for this post-PHP-update full service restart. Inspect for new errors or worker crashes after the restart; old log entries alone do not indicate a new failure. If validation or restart fails, stop and repair the web runtime before dashboard installation.

### Nginx (or Apache) with PHP-FPM

Identify the PHP-FPM instance/socket that actually serves the dashboard virtual host. For a standard system `php-fpm` installation, validate its configuration and restart that instance after updating PHP:

```bash
sudo php-fpm -t && sudo systemctl restart php-fpm
sudo systemctl --no-pager -l status php-fpm
```

Use the matching binary, configuration and unit for a versioned/custom instance; do not assume the service is named `php-fpm`. Restarting Nginx alone does not replace PHP-FPM workers. For Nginx, also validate its configuration with `sudo nginx -t`. Reload Nginx only if its configuration changed (`sudo nginx -t && sudo systemctl reload nginx`); use the site's maintenance procedure for an Nginx package update. Apply the same PHP-FPM distinction if Apache proxies PHP requests instead of loading mod_php.

### Confirm execution through the actual web route

Before dashboard deployment, run the following in a root Bash shell. Set `document_root` and `web_origin` to the document root and local HTTP origin of the intended site. If the site's route depends on a hostname, use its actual host and loopback mapping (for example curl's `--resolve`); HTTPS also requires the correct hostname/SNI and trusted certificate. Do not bypass certificate verification or access restrictions. Verify the real dashboard route again after installation.

This probe publishes only a random-name PHP file that prints a harmless token, not `phpinfo()`, credentials or dashboard configuration. It removes the probe and private response file on exit. Do not interrupt the shell with SIGKILL; if cleanup fails, remove the reported probe manually before proceeding.

```bash
(
set -euo pipefail
umask 077
document_root=/var/www/html
web_origin=http://127.0.0.1
[[ $EUID == 0 && -d "$document_root" && "$document_root" != / ]] || exit 1
probe=''
response=''
cleanup() {
    [[ -z "$probe" ]] || rm -f -- "$probe"
    [[ -z "$response" ]] || rm -f -- "$response"
}
trap cleanup EXIT
response=$(mktemp /var/tmp/dashboard-php-response.XXXXXXXX)
probe=$(mktemp --suffix=.php "$document_root/dashboard-php-check.XXXXXXXX")
printf 'Temporary probe: %s\n' "$probe"
expected="dashboard-php-ok-$(date +%s)-$"
printf '<?php header("Content-Type: text/plain"); echo "%s";\n' "$expected" > "$probe"
chmod 0644 "$probe"
if command -v restorecon >/dev/null; then restorecon "$probe"; fi
status=$(curl --silent --show-error --max-time 30 \
    --output "$response" --write-out '%{http_code}' \
    "${web_origin%/}/${probe##*/}")
[[ $status == 200 && $(cat "$response") == "$expected" ]] || {
    echo 'FAIL: HTTP PHP execution not verified; do not deploy the dashboard.' >&2
    exit 1
}
echo 'PASS: HTTP 200 and exact PHP-generated token; probe removed on exit.'
)
```

HTTP 200 by itself is insufficient: directory listings, PHP source and unrelated pages must fail the exact-token comparison. A successful probe shows execution on that route at that time, not security qualification or complete gateway behavior. If source is returned, keep dashboard files outside the web root and investigate the handler/runtime before retrying. After this check passes, continue with the installer, retain the PHP package prerequisite and final dashboard HTTP verification, and perform the RF/remote acceptance tests.

### WL7CWI observed result — October 30, 2026

The v0.5.1 package prerequisite and CLI rendering passed, but HTTP returned raw PHP and the installer rolled back. A graceful Apache reload was followed by worker segmentation faults. After a full Apache service restart, the sampled HTTP response rendered the dashboard correctly with no new crashes in the supplied log; installation then completed with HTTP verification and exit status 0. These operator results support checking the running web runtime after PHP updates. They do not diagnose a defect in the PHP2Shell patch or guarantee that restarting resolves every PHP/web failure.

References: [Apache service control](https://httpd.apache.org/docs/2.4/stopping.html), [Nginx configuration reload behavior](https://nginx.org/en/docs/control.html), and [PHP-FPM configuration](https://www.php.net/manual/en/install.fpm.configuration.php).

## g2_link activity logging and history retention

Before activity acceptance, verify the configuration actually used by the running
g2_link process. Detailed remote activity requires:

```ini
QSO_DETAILS=Y
```

The inspected legacy source guards both `START from remote g2:` (XRF/DExtra)
and `START from dcs:` records with this setting. A current link can appear in
Module Status while those detailed activity records are absent. Setting this to
Y enables future records after the running process adopts the setting; it cannot
reconstruct transmissions that were never logged. Editing the file alone does
not prove that the existing process has reloaded it. Do not restart blindly:
first preserve the log and inspect the launcher/rotation setup.

Verify that stdout/stderr reach the configured dashboard log and that service
startup preserves existing history. For a shell launcher, `>> /var/log/g2_link.log 2>&1` appends whereas a single `>` can truncate at startup. Removing redirection
entirely can send output elsewhere. A native systemd unit needs its own output
configuration; `>` or `>>` in ExecStart is not shell redirection unless an
explicit shell is used. Inspect the real unit and any invoked script rather than
applying a CentOS init-script edit to every AlmaLinux installation.

Read-only inventory examples (the service name may differ):

```bash
pgrep -af '[g]2_link'
systemctl --no-pager cat g2_link.service
grep -n 'QSO_DETAILS' /root/g2_link/g2_link.cfg
```

On VE7VIC (AlmaLinux 9.8), the native unit already contained:

```ini
StandardOutput=append:/var/log/g2_link.log
StandardError=append:/var/log/g2_link.log
```

No unit edit was required. The administrator enabled QSO_DETAILS and restarted
g2_link after preserving the log; subsequent XRF headers were observed. Do not
create a legacy init script for such a native unit. These append directives are
an observed AlmaLinux example, not an instruction for older systemd versions.

If the unit invokes `/etc/rc.d/init.d/g2_link` or another wrapper, inspect that
file before editing. Confirm the process's open log destination as well as its
startup append mode. Open descriptors can still target a renamed/deleted file
after rotation even when the configured pathname exists.

Rotation must remain locally administered and bounded: enabling QSO_DETAILS
increases log volume. Preserve supported uncompressed `g2_link.log-YYYYMMDD`
archives and the active log. WB1GOF uses copytruncate; do not apply that rule to
another gateway without reviewing its existing rotation and file-opening
behavior. Append mode avoids sparse-hole problems when a long-running writer
continues after copytruncate. Do not truncate a live log or restart a gateway
merely as an installation check.

After configuration is active, generate a known transmission and verify that a
new detailed record appears in the intended log, is readable by the PHP worker,
and appears on the dashboard with retained link evidence. Keep
`ADMIN=1NFO` and `ONLY_ADMIN_LOGIN=Y` where configured for the existing dashboard
identity; do not remove or relax them to address logging. The dashboard installer
does not edit g2_link configuration, launchers or rotation policies.

Source evidence: [legacy QSO_DETAILS guards](https://github.com/KA8SCP/g2_link/blob/7d22f6f566c863cd6154b198817e47eece9080e5/g2_link.cpp#L3804)
and [DCS guard](https://github.com/KA8SCP/g2_link/blob/7d22f6f566c863cd6154b198817e47eece9080e5/g2_link.cpp#L5590).
These are source-level findings; verify the deployed executable and actual
configuration at each site.

## Procedure

1. Complete [web-runtime readiness](#web-runtime-readiness-before-installation), including the appropriate post-PHP-update restart and HTTP execution test. Then clone the GitHub repository and review the package/ref.
2. Run `sudo bash scripts/install-dashboard-interactive.sh`, or `sudo bash scripts/install-dashboard-interactive.sh --package-dir "$PWD"` for the reviewed local checkout.
3. Enter callsign (without module), log timezone, document root, destination, DPlus/g2_link HTML and log paths, web user/group, local HTTP URL and backup location. Paths can differ from WB1GOF's defaults. The verification URL must point to this site's web virtual host; adjust its loopback port where necessary.
4. Review the concrete installation summary and approve installation. The installer verifies all release hashes and PHP syntax and validates the generated configuration before this final prompt.
5. Existing files are backed up outside the web tree. Dependencies/configuration are copied before index.php. Existing unrelated files are retained; release-file symlinks are refused. The installer restores SELinux labels, checks deployed hashes and fetches the page through the web server. Failed deployment verification reverts the dashboard and retains failure files for review.
6. Perform RF and remote-transmission acceptance tests. An HTTP check verifies rendering, not complete radio behavior or every log format.

No existing `/dashboard/` is needed for a fresh installation. For a v0.4 upgrade, use the existing destination and retain the reported rollback archive. W4CRL candidate updates and VE3TTT first installation have since been confirmed; see RELEASE-READINESS.md for the exact evidence.

## Rollback

The installer records repository commit/ref, deployment path, checksums and an archive under the chosen backup directory. On an automatic rollback it moves the failed installation into that directory and extracts the previous dashboard at its original location. For manual rollback, first preserve the current directory outside the web tree, then extract the recorded archive under the dashboard's parent directory, restore labels, and verify the HTTP page. A new installation has no previous archive to restore; its new-install marker records that fact.

## Staging only

`bash scripts/install-dashboard.sh /absolute/new/candidate-directory` copies the checksum-verified runtime files into a new directory and refuses overwrite. Configure `dashboard.local.php` before using that candidate at another site; apply local ownership/labels and test it before switching live.


## K2BWK qualification — October 14, 2026

The reported AlmaLinux 9.7 system had populated DPlus inputs, no g2_link executable found in the searched locations or active process, and zero-byte fs.html/g2_link.log placeholders. Both dashboard attempts failed HTTP verification and rolled back; no dashboard remained and the records identified new installations with no previous deployment. Archive cleanup was advised; completion was not independently verified.

Nginx was serving Icom WebApp through ports 5000/5001, while PHP CLI was installed and PHP-FPM inactive. The HTTP 403 was a directory-index failure, separate from missing g2_link. PHP CLI syntax success does not qualify web PHP execution. PHP-FPM is dashboard-specific on that Nginx arrangement, not a stock Icom prerequisite or universal installer requirement. This installer does not enable it, edit Nginx, or alter Icom/DPlus services.

The new gate rejects missing/non-executable binaries and missing/empty/unreadable status feeds. It does not execute the supplied binary, require a service-name convention, or prove live/fresh/semantically valid status; verify those during site acceptance. Do not create placeholder files to pass qualification.
## Automatic installation transcripts (0.5.0-rc.1)

Use the updated scripts/install-dashboard-interactive.sh from this package. An older bootstrap script does not gain transcript support merely because it downloads a newer dashboard. Update the installer itself from a reviewed checkout first.

After the root check, the installer creates a private transcript in /var/log/g2-dashboard-install/install-<UTC timestamp>.<unique suffix>.log, before g2_link/PHP prerequisites or downloads. The directory must be root-owned mode 0700; each transcript is mode 0600 and remains outside the web root. Standard output, standard error, prompts, start/end times and final exit status are captured while stdin remains interactive. Typed responses/keystrokes are not echoed; the reviewed configuration, package version, Git revision and installation result are printed normally. Do not enter credentials or paste secrets into prompts. Review and redact paths, hostnames or other private information before sharing a transcript.

Prerequisite failures, cancellation and HTTP rollback retain the transcript. Once backup records are created, install-<timestamp>.txt includes its path. Fatal failures before transcript initialization (such as missing tee or an unsafe directory) can only report to the console. Logs have no automatic retention/deletion policy; administrators manage retention. Transcript completion/tee failures are reported with nonzero status, and the original installer failure status is preserved. No service configuration or gateway restart is added.

The page footer identifies Unified D-STAR Dashboard v0.5.0-rc.1 and a shortened source commit for Git-backed installs. Local package installs without a verified Git revision show the package version only. dashboard-build.json contains only the source revision and is generated by this installer; no private configuration paths are published there. Existing deployments gain the footer on upgrade with the matching runtime manifest.

## Saved callsign default on upgrades

With the updated installer, select the document root and dashboard directory before the callsign prompt. The installer reads the saved literal callsign from that directory's dashboard.local.php without executing its PHP code. Press Enter to retain it, or enter a different valid callsign. Missing, unreadable, invalid or duplicate values leave the default blank; a first installation such as VE3TTT requires an explicit callsign. Other configuration fields retain their existing prompt behavior. Update the installer itself and use the matching package to obtain this behavior.

See RELEASE-READINESS.md for observed deployment results, VE3TTT preflight and remaining stable-release qualification. The new transcript/footer and saved-default changes still need target installation qualification.

## Existing dashboard upgrades (0.5.0-rc.2)

Use scripts/update-dashboard.sh from a current tools checkout rather than re-entering site settings. It preserves the complete saved configuration and existing file owner/group. First installations continue to use the interactive installer. See [UPDATING.md](UPDATING.md). The header now offers a browser-local Dark display toggle.

## Stable v0.5.0 release

Use the v0.5.0 tag for reproducible first installation. The interactive installer offers that stable tag by default. Existing sites use update-dashboard.sh, which now selects the latest published stable GitHub Release unless an explicit --ref is supplied. See UPDATING.md and RELEASE-READINESS.md. Earlier candidate notes above are historical, not current qualification blockers.

## Automatic fresh-site web readiness

The v0.5.2 tools add an application check distinct from the PHP2Shell
package prerequisite. After you approve the reviewed installation, but before
dashboard PHP, site configuration or backup/deployment files are copied, a
fresh target without index.php is tested at the selected local verification URL.

The installer briefly creates a token-only index.php at the intended destination.
It checks PHP 5.4+, date support, the basic functions used by the dashboard, and
writable PHP temporary storage. It requires HTTP 200 with the exact runtime token.
Raw PHP source, directory listings, other pages, redirects, 403 and 502 responses,
and failed PHP execution stop installation. The probe is removed on success or
failure; a newly created empty directory is also removed. Existing directories
and unrelated assets are preserved.

A target with an existing index.php is not overwritten by this check. It reports
that the fresh-site probe was skipped; final deployed-dashboard HTTP verification
and rollback remain required for updates and legacy dashboards. The probe is a
route/capability check, not a web-runtime security certification or proof that
the route uses a particular vendor RPM build.

Failures point administrators to the dashboard-only Nginx/PHP-FPM recipe or
Apache handler/restart guidance. The installer does not edit server configuration,
restart services, disable SELinux or alter the Icom proxy. Use the site's actual
worker identity/socket, HTTP/HTTPS route, callsign, timezone, status feeds and logs.
Check both public HTTPS and local verification routes afterward.

Use the complete v0.5.2 tools checkout: a v0.5.1-tagged installer does not gain
this preflight merely by downloading newer dashboard files. This new gate has
isolated test coverage; live fresh-install acceptance for it remains pending.

The VE7VIC prerequisites checklist also includes active QSO_DETAILS=Y, append-mode
logging in the actual launcher, retained/readable detailed logs, preserved 1NFO
authentication, and a known-transmission display check. The installer does not
set those gateway-owned values automatically.

The preflight reports CLI PHP and, on a fresh site, the PHP version that actually
handles the selected web route. A CLI/web version mismatch blocks deployment for
runtime review. It does not infer the PHP version from the OS or certify unknown
vendor fixes. Existing legacy status pages and Icom routes remain in service;
the probe is confined to the chosen unified-dashboard target. Existing dashboard
entry points are not probed or overwritten; final HTTP verification still applies.
