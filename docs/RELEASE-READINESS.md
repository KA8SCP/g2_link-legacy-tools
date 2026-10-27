# Dashboard 0.5.0 release readiness

Current package identity: 0.5.0-rc.2. No stable release/tag is claimed.

## Observed gateway results

- WB1GOF: CentOS 7, Apache/PHP 5.4; sparse-log update installed and HTTP-verified, subsequent request HTTP 200 in 10.086 seconds. Fresh Module C local RF activity showed DCS049 C, RPT1 and RPT2. Administrator corrected startup redirection to append and verified both output descriptors.
- W4CRL and K2BWK: AlmaLinux/nginx; administrator reported both sparse-log upgrades completed. Both running g2_link processes already use append output with copytruncate rotation.
- Those deployments predate the new transcript/footer feature. Do not treat them as qualification of that feature.

## Before VE3TTT's first installation

1. Record OS, PHP CLI version, web server and actual PHP worker user. Confirm DPlus and g2_link are already installed and operating, the actual executable path, and a nonempty readable fs.html. The dashboard installer does not install those prerequisites.
2. Confirm the intended dashboard URL already has working PHP routing. For nginx, use the existing documented dashboard-specific routes; do not enable PHP globally or replace a working Icom web configuration.
3. Use an updated, reviewed copy of the interactive installer and matching repository package. Document root and dashboard directory now precede the callsign prompt. A new site has no saved callsign: enter VE3TTT explicitly. Existing sites offer the saved literal callsign from that selected directory; Enter retains it and typing another valid callsign overrides it.
4. Keep the transcript path printed at startup. Check HTTP verification, footer version/revision, status source readability, mobile display, then local RF and remote activity. Compare installed commit with the install record. Record actual outcomes without assuming success.

## Outstanding qualification

- New transcript/footer and saved-default behavior on CentOS/PHP 5.4 and AlmaLinux: new installation, upgrade, cancellation and HTTP rollback. Automated isolated tests cover portions, not the entire production acceptance matrix.
- Additional tests in TESTING.md: source readability/permissions, failed hash, invalid paths/symlinks, backup restore, mobile presentation, remote activity variants and rotation boundaries. Exercise intentional failure cases on an isolated system, not a working gateway.
- Certificate-renewal resilience remains separate: the external updater previously replaced nginx routes and supplied a leaf-only certificate. The dashboard installer does not repair or modify that updater, nginx configuration or certificate chain.
- WB1GOF's ten-second page build is functional but remains a performance improvement opportunity.

Promote to a stable 0.5.0 release only after recording the remaining relevant acceptance results. A footer version is a package identifier; it does not itself publish a GitHub tag or release.

## October 27 updater and theme qualification

The 0.5.0-rc.2 updater/theme changes have isolated automated coverage and local browser preview checks. Real CentOS 7/PHP 5.4 and AlmaLinux updater verification remains pending. Prior first-install and activity results do not qualify this new updater. See UPDATING.md for source selection and preserved settings.
