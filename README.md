# g2_link legacy tools

Unified D-STAR Dashboard **v0.5.1**, maintained at [KA8SCP/g2_link-legacy-tools](https://github.com/KA8SCP/g2_link-legacy-tools). Stable production release for existing, configured DPlus/g2_link gateways on the documented platforms.

The dashboard combines module status, connected clients and chronological activity. It reads existing status pages and logs, distinguishes Local RF from incoming remote/reflector activity, retains available RPT1/RPT2 and callsign suffixes, and refreshes every 60 seconds. Light/dark display is selected per visitor and remembered in that browser. Remote reflector traffic can appear on multiple linked gateways.

## Install on an existing gateway

DPlus and g2_link must already operate, with a nonempty readable g2_link status feed and working web PHP integration. PHP CLI 5.4+, Bash 4+, Git, curl and the utilities listed in [INSTALL.md](docs/INSTALL.md) are required. PHP-FPM is needed only where the site's web configuration uses it. The dashboard does not install or replace gateway applications, linking services or MonLink.

```bash
git clone --branch v0.5.1 https://github.com/KA8SCP/g2_link-legacy-tools.git
cd g2_link-legacy-tools
sudo bash scripts/install-dashboard-interactive.sh --package-dir "$PWD"
```

Starting with v0.5.1, initial installations must pass the [PHP2Shell fixed-package prerequisite](docs/PHP-SECURITY.md). Vulnerable, mixed or unrecognized PHP builds stop installation before deployment. The check does not patch PHP or restart services. The older v0.5.0 installer does not contain this prerequisite. See the [v0.5.1 release notes](docs/releases/v0.5.1.md).

Review the prompted paths, callsign, timezone, ownership and verification URL. Private transcripts, outside-web-root backups, checksums, PHP syntax checks and HTTP verification are included. Deployment/verification failure triggers rollback.

## Update an installed dashboard

From a current, reviewed tools checkout:

```bash
sudo bash scripts/update-dashboard.sh
```

The updater selects the latest published **stable GitHub Release**, preserves saved site configuration and installed file ownership, and asks for one review confirmation. Use `--ref v0.5.1` to pin this release; `--ref main` explicitly selects development code. It stops on failed release discovery rather than silently selecting development code. See [UPDATING.md](docs/UPDATING.md).

## Production qualification and limits

Operator results cover WB1GOF (CentOS 7/Apache), W4CRL and K2BWK (AlmaLinux/nginx), and VE3TTT's CentOS first installation. The v0.5 candidate updater was HTTP-verified on WB1GOF and W4CRL. Display testing was reported on iPhone, Android tablet and PC browsers. Automated tests cover history fixtures, configuration preservation, cancellation/rollback, theme persistence and stable-release selection. These are recorded separately in [release qualification](docs/RELEASE-READINESS.md) and [compatibility](docs/COMPATIBILITY.md).

Unsupported or incomplete historical link evidence remains Unknown. The external certificate-renewal updater can overwrite web routes or certificate-chain settings; repairing it is outside this dashboard release. Gateway OS/PHP maintenance remains the administrator's responsibility. See [dashboard behavior](docs/DASHBOARD.md), [testing](docs/TESTING.md) and [milestones](CHANGELOG.md).

## Attribution and history

The legacy g2_link and DPlus applications remain independent. Existing authorship, provenance and historical milestones are retained in the source materials, LICENSE and CHANGELOG.md. Separate MonLink review materials retain their existing qualification/licensing status; installing the dashboard does not apply that patch. This repository does not contain the complete upstream MonLink program or gateway binaries.
