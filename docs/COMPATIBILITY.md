# Compatibility and qualification

v0.5.1 adds the [first-install PHP security prerequisite](PHP-SECURITY.md). PHP 5.4 syntax compatibility alone does not qualify an unpatched runtime. Platform/operator evidence below comes from earlier deployments; no v0.5.1 live installation is claimed. Current automated qualification includes the new security gate.

Dashboard v0.5.1 is the stable production release for existing configured DPlus/g2_link gateways in the environments below. Runtime syntax remains compatible with PHP 5.4+; tooling requires Bash 4+ and the utilities in INSTALL.md. This compatibility statement does not provide upstream OS/PHP maintenance or certify gateway binaries.

| Site/platform | Evidence | Boundary |
| --- | --- | --- |
| WB1GOF, CentOS 7, Apache/PHP 5.4, DPlus 2.2t, g2_link 4.00 | Candidate updater HTTP-verified, saved settings/ownership retained, transcript exit 0; fresh RF/DCS association confirmed | Not every intentional failure case was run on the live gateway |
| W4CRL, AlmaLinux 9.5 baseline, nginx/PHP 8.2.33 baseline, RS-RP3C v3.20 baseline | Candidate updated page inspected; local and REF030 C remote data confirmed; nginx:nginx ownership | Exact current package revisions were not re-inventoried |
| K2BWK, AlmaLinux 9.7, nginx/PHP-FPM | Earlier dashboard updates and append logging confirmed; apache:nginx ownership | Latest updater-specific run not independently reported |
| VE3TTT, CentOS 7, Apache/PHP 5.4 | First installation HTTP-verified; displayed revision/activity and subsequent DCS traffic confirmed | Latest updater-specific run not independently reported |
| iPhone, Android tablet, PC browsers | Operator reported successful candidate display testing | Exact browser versions not supplied |
| Gateway without g2_link/nonempty status | First installer rejects missing prerequisites | No supported DPlus-only installation mode |

PHP CLI and web PHP must both work. Apache module PHP and nginx/PHP-FPM are separate supported deployment patterns; PHP-FPM is not a universal Icom prerequisite. New PHP module installation may require an administrator-managed full Apache restart before HTTP testing, as observed on VE3TTT.

Unknown historical associations are deliberate where evidence is insufficient. See RELEASE-READINESS.md for detailed evidence, automation scope and operational limits. MonLink qualification and licensing remain separate; the dashboard never deploys that patch.
