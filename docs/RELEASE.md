# Release status

Repository: [KA8SCP/g2_link-legacy-tools](https://github.com/KA8SCP/g2_link-legacy-tools). The initial portable dashboard package is based on the verified WB1GOF October 12 v0.4.2 export, with configuration and installation additions. The production export hash is recorded in dashboard/README.md.

Before tagging a qualified installer release, complete the target-platform checks in TESTING.md, especially PHP 5.4, Apache/SELinux, first install, v0.4 upgrade and HTTP-failure rollback. `main` is development until those outcomes are recorded. A local fixture pass is not a fresh-gateway deployment claim.

Runtime SHA256SUMS records the packaged code; generated dashboard.local.php is excluded and preserved only in site backups. No passwords, logs or private site configuration belong in the repository.

MonLink materials retain the separate source/licensing review described in their existing guides. No full MonLink source or compiled gateway binary is included, and the dashboard installer does not apply that patch. Existing gateway programs retain their own notices and licenses. See LICENSE for the package's licensing status.
