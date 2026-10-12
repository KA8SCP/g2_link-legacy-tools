# g2_link legacy tools

Unified D-STAR Dashboard **v0.4.2**, maintained at [KA8SCP/g2_link-legacy-tools](https://github.com/KA8SCP/g2_link-legacy-tools). This package supports existing gateways; it does not install or replace DPlus, g2_link, or MonLink.

The dashboard combines module status, connected clients and chronological activity. It reads existing status pages and logs, separates connected software clients from stations heard through reflectors, and keeps independent transmissions with their logged headers and callsign suffixes. It refreshes every 60 seconds and includes a collapsible RF command reference.

## Install on an existing gateway

Apache with PHP integration, PHP CLI 5.4+, Bash 4+, Git, curl and coreutils must already be available. PHP's effective web user needs read access to the configured status pages and logs. No database or linking service is installed.

```bash
git clone https://github.com/KA8SCP/g2_link-legacy-tools.git
cd g2_link-legacy-tools
sudo bash scripts/install-dashboard-interactive.sh
```

The installer prompts for repository/ref, callsign, timezone, input paths, web directory, file ownership, verification URL and backup directory. It stages and verifies code/configuration before asking to install. Existing dashboards are backed up outside the served tree. A failed file or HTTP verification triggers rollback. Use a reviewed release tag once target testing is complete; `main` is the development branch.

For an already downloaded, reviewed package:

```bash
sudo bash scripts/install-dashboard-interactive.sh --package-dir "$PWD"
```

See [installation](docs/INSTALL.md), [dashboard behavior](docs/DASHBOARD.md), [milestones](CHANGELOG.md), [compatibility](docs/COMPATIBILITY.md) and [testing](docs/TESTING.md).

## Status and milestones

- **October 3:** v0.4 baseline packaged.
- **October 10:** WB1GOF deployed event-aware DPlus history, connected-client timestamps, separate link associations and remote applicability filtering.
- **October 11:** WB1GOF deployed DPlus archive recovery and exact local RPT1/RPT2 enrichment.
- **October 12:** WB1GOF deployed g2_link DCS stream history across retained daily rotations, independent suffix/header records, corrected connected-user grouping, and the RF reference panel.
- **Portable package:** site configuration and prompted install/upgrade procedure added. W4CRL v0.4 upgrade is planned; no W4CRL deployment or fresh-gateway installer qualification is claimed yet.

The separate MonLink review materials remain under their existing qualification/licensing status. Installing the dashboard does not install or apply the MonLink patch. This repository does not contain the complete upstream MonLink program or gateway binaries.
