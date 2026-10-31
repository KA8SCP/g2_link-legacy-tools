# Changelog

## v0.5.2 — stable activity and installation-readiness release, 2026-10-31

- Promote the operator-tested XRF/DExtra activity and inbound-client expiry fixes from rc.1/rc.2.
- Add fresh-site PHP execution/capability/version readiness checks before dashboard deployment, while preserving existing entry points and legacy pages.
- Document QSO_DETAILS=Y, append logging, site-specific PHP/web routing, runtime restarts after PHP changes and VE7VIC's dashboard-only Nginx recipe.
- Update package identity/default install tag and retain stable-release updater selection, saved configuration, ownership, transcripts, backup and rollback.
- Automated release checks pass; live activity reports cover the candidate fixes. Live fresh-install acceptance of the new preflight, including planned VE3RXR, remains pending.


## Unreleased tools — application-readiness preflight, 2026-10-31

- Test fresh-site PHP execution/capabilities on the actual selected dashboard route after approval and before dashboard deployment; reject raw source, wrong content and HTTP failures with configuration guidance.
- Clean temporary token probes on exit, preserve existing entry points/assets and retain final verification/rollback. No automatic server/gateway configuration changes or restarts.
- Add isolated execution, failure-status, cleanup, symlink and existing-file regression tests. Live first-install qualification pending; old tagged installers remain unchanged.


## Operational guidance — October 30, 2026

- Document QSO_DETAILS=Y as a detailed-activity prerequisite, activation/restart precautions, append-mode logging and preserved 1NFO authentication.
- Add the dashboard-only Nginx/PHP-FPM routing recipe used at VE7VIC, preserving Icom proxy routes. Installer does not edit web/gateway services.
- Record multi-site candidate deployment/activity acceptance separately from automated checks.

## 0.5.2-rc.2 — inbound-client timeout correction, 2026-10-30

- Preserve confirmed reflector history when a separately tracked inbound client expires. Fixes WB1GOF DCS activity disappearing after 1NFO client timeouts.
- Keep real reflector/unknown timeouts, disconnects, unlinks, restarts and log gaps invalidating stale evidence.
- Add WB1GOF DCS and XRF client-expiry, retained-archive and negative fixtures. WB1GOF DCS recovery confirmed; rc.2 display/activity confirmed at K2BWK, VE3TTT and VE7VIC; rc.1 XRF display confirmed at WL7CWI.


## 0.5.2-rc.1 — XRF activity correction, 2026-10-30

- Parse XRF/DExtra remote-g2 stream headers, including flags and callsign suffixes.
- Preserve these events in retained-log filtering and confirm XRF request/announcement source-IP associations.
- Add WL7CWI regression fixtures, mismatch/reset/ambiguity and rendered-page checks. RF delivery remains unconfirmed.
- Candidate functionality has operator validation as recorded in RELEASE-READINESS.md. Stable v0.5.1 remains unchanged.


## Dashboard v0.4.2 portable package � 2026-10-12

- Imported the operator-verified WB1GOF production export (source archive hash in dashboard/README.md).
- Added prompted GitHub/local-package install and v0.4 upgrade, preflight/configuration review, outside-web-root backups, deployed checksums, HTTP checks and automatic rollback on verification failure.
- Centralized callsign, timezone, document root, HTML inputs and log paths; added W4CRL configuration fixtures and site example.
- Added release checks and GitHub CI. Portable installer acceptance and W4CRL deployment remain pending.

### Production milestones inherited from WB1GOF

- October 10: PHP 5.4-compatible bounded DPlus event timeline, conservative untimestamped transitions, snapshot reconciliation, connected-client timestamps, local-source merging and remote header applicability filtering.
- October 11: DPlus archive recovery with independent restart sessions, complete snapshot boundary correction and exact local RPT1/RPT2 enrichment.
- October 12: separated g2_link Software Clients from reflector-heard users; retained independent DCS transmissions and logged RPT1/RPT2/suffixes; added retained-copytruncate archive history, overlap deduplication and conservative association clearing.
- October 12: clarified g2_link DPlus state as Not assessed; deployed collapsible RF command reference below Software & Sources with DPlus L/U/I, g2_link O/T/W and verified DTMF syntax.

## Unreleased — packaging draft, 2026-10-03

- Added legacy-support documentation and checksum-gated staging/verification tools.
- Recorded WB1GOF dashboard v0.4 hashes and reported interlock sequence.
- Added release requirements for missing source, patch provenance, licensing and target-platform testing.
- Imported supplied dashboard index.php and style.css unchanged; both hashes independently match the WB1GOF v0.4 baseline. MonLink patch and package qualification remain pending.

- Added operator-supplied six-hunk MonLink patch with baseline/result hashes and staged application instructions; independent application and license review remain pending.

- Verified supplied patched MonLink hash and exact reverse/forward patch round trip against both recorded hashes; documented source version and author. Complete program remains excluded.

- Confirmed the supplied post-update MonLink file is identical to the original baseline and lacks the interlock. Existing patch restores the validated hash. Added updater persistence findings; updater script review pending.

- Reviewed supplied monlink-check.sh: a local checksum mismatch invokes the downloaded installer; any argument bypasses the current-file checksum check. No platform-specific or patch-preservation logic exists in the wrapper. Installer/scheduler review remains pending.

- Reviewed installer version 3.01: confirms program/wrapper replacement, rotating monlink.last backup and service restart without interlock reapplication. Supplied installer has no AlmaLinux-specific branch. Added backup recovery and update-persistence requirements.

- Recorded WB1GOF cron/timer/init-script findings: no direct updater trigger found; startup script has no updater invocation. Operator confirmed preserved patched backup hash. Documented restoration and actual-process status checks.

- Operator resolved update trigger as manual invocation after checksum warning and confirmed patched live hash plus successful MonLink restart. Post-restoration linking regression remains pending.

- Operator reports post-restoration Module C suppression holds beyond MonLink reconnect checks with DCS049 C remaining active; marker confirmed. Post-restoration unlink/default restoration remains pending.

- Completed operator-observed post-restoration interlock sequence: RF T unlink, marker removal and MonLink REF049 C restoration confirmed with logs; module B retained REF050 C in supplied samples.

- K1HRO no-g2_link CentOS 7.9 test confirms MonLink-generated B default recovery to REF050 C, correlated with DPlus response; C retains REF030 C in supplied samples. Scheduled/AlmaLinux cases remain pending.

## Unreleased — October 14, 2026

- Require an installed executable g2_link and nonempty readable status HTML before dashboard PHP checks, downloads or deployment; remove reduced-mode override for this prerequisite.
- Document K2BWK failed-install rollback, empty placeholders, and distinction between stock Icom services and dashboard web PHP requirements. No automatic web/service changes.


- Correct padded callsign matching and enrich uniquely matched DPlus hotspot starts with actual RPT1/RPT2 and suffix. Classify before Local/Remote filtering; preserve ambiguity and unconfirmed RF delivery.


- Restore expensive DPlus timeline enrichment to the visible 30 rows; keep lightweight hotspot/header classification before filtering. WB1GOF PHP 5.4 timed out with all-history enrichment; target performance confirmation pending.
- Use a subshell instead of git -C for older CentOS 7 Git compatibility.


## October 15 timeline lookup performance

- Build a compact usable-snapshot index once per history session and use binary search for baseline selection. Preserve equal-time log order, coverage limits and transition uncertainty; fail closed on regressing index order.
- Compute per-session coverage once per annotation pass instead of scanning snapshots per row. Add synthetic 12,500-snapshot regression cases. WB1GOF target timing remains pending.


## October 15: unified historical link associations

- Populated Associated Link from DPlus and confirmed g2_link DCS history at transmission time, with source labels and both associations retained when present.
- Reused the existing g2_link log pass and indexed lookups for displayed rows only.
- Added regression fixtures for local/remote DCS, simultaneous evidence, confirmation boundaries, unlink/restart, read errors and clock ambiguity.
- XRF/XLX historical formats remain unqualified (Unknown); current module status is never substituted for historical evidence. Target display and performance verification remain pending.
## October 15: reduce repeated g2_link history processing

- WB1GOF reported a 21.98-second g2_link history scan across eight sources and a 30-second PHP timeout during full-page rendering.
- Validate all timestamps on the initial archive pass, reusing consecutive identical timestamp conversions. Carry only recognized state events, DCS headers and first/last coverage markers into subsequent scans.
- Reuse the timezone object instead of creating it per log line. Keep clock-regression resets and fail-closed timestamp/read handling.
- Added a 10,000-line diagnostic fixture, coverage-boundary checks, and regression/invalid timestamp checks on filtered lines. Target timing remains pending; no PHP timeout or gateway service changes.
## October 15: sparse g2_link log handling

WB1GOF's active log had an apparent size of 12,468,196 bytes but only 4,096 allocated bytes, with six text records after a NUL-filled gap. The SysV launch command redirected output using > rather than >> while logrotate used copytruncate. The administrator changed the launch redirection to append, restarted g2_link, and verified append flags 0102001 on both output descriptors. QSO_DETAILS=Y and rotation settings were retained. Existing gaps were not removed.

Dashboard history now normalizes NUL runs in 64 KiB blocks into explicit evidence-reset boundaries before line parsing. This avoids PHP 5.4 reading a multi-megabyte zero-filled line, preserves subsequent text, and prevents link associations from crossing missing data without fresh confirmation. Original logs remain read-only. Fixtures cover a 12 MiB sparse prefix, block-boundary recovery, and internal gaps. Actual WB1GOF page timing after installation remains pending.

## October 16: installation diagnostics and release identity

Version 0.5.0-rc.1 adds automatic private installation transcripts, including early prerequisite failures, stdout/stderr, prompts and exit status; installation records link to the transcript. Typed responses are not echoed. The dashboard footer now displays package version and, for installations made with the updated Git-backed installer, source revision. The sparse-log fix was installed and HTTP-verified by the administrator on WB1GOF, W4CRL and K2BWK. WB1GOF returned HTTP 200 in 10.086 seconds and a fresh local RF transmission displayed its confirmed DCS049 C association. Both AlmaLinux systems already had append-mode log output; WB1GOF was corrected separately and restarted by its administrator.

This is a release candidate version identifier, not a claim that a GitHub tag or stable release has been published. New transcript functionality still requires real gateway installation qualification, and the broader acceptance matrix remains incomplete. Automated checks do not establish PHP 5.4 execution of the new feature. No production system was modified by this repository update.

## October 16: saved gateway callsign default

Upgrade prompts now offer the valid saved callsign from the chosen dashboard directory. New sites require an explicit callsign. The existing PHP configuration is tokenized without execution, with tests for missing/invalid/duplicate values and executable content. Added docs/RELEASE-READINESS.md to distinguish reported three-site deployment results from outstanding transcript/footer and first-install qualification. Version remains 0.5.0-rc.1; no production gateway changes or stable tag are included.

## October 16: CentOS transcript completion correction

VE3TTT's first installation passed hashes and PHP 5.4 syntax but HTTP verification returned curl 52 (empty server reply), triggering rollback. The transcript exposed a separate Bash compatibility error when waiting for a process-substitution PID. Transcript capture now uses a private FIFO and an explicit background tee child, then waits and removes the FIFO directory. Tests assert clean completion and no leftover pipe directory. The Apache empty response remains under investigation; no retry or successful VE3TTT deployment is claimed.

## October 27: 0.5.0-rc.2 updater and dark display

Added scripts/update-dashboard.sh with an existing-site mode in the interactive installer: strict nonexecuting saved-config parsing, byte-preserved settings, retained file ownership, single review confirmation, standard private transcript, backup and HTTP rollback. Custom destination/ref/verification/backup options are documented. The default source remains main because no GitHub release or tag is published. Added a keyboard-accessible Dark display toggle with local browser persistence, refresh/filter continuity, blocked-storage fallback, dark status/table colors and versioned asset URLs. PHP and theme tests plus isolated updater cancellation/rollback/deployment fixtures were added. Target CentOS/AlmaLinux updater qualification remains pending; production gateways were not modified.

## October 27: 0.5.0-rc.3 Last Heard clarity

Made activity filters larger with a visible selected state and added distinct Local RF/Remote row badges. A short explanation clarifies that remote reflector traffic can be heard on multiple linked gateways. Classification and history evidence rules are unchanged. Light/dark palettes and keyboard navigation remain supported. This is a presentation update; no gateway settings or live deployments were modified.

## October 27: v0.5.0 stable production release

Promoted the tested candidate dashboard to 0.5.0. Recorded operator results for WB1GOF and W4CRL updates, earlier K2BWK deployment and VE3TTT first installation, plus iPhone, Android tablet and PC display testing. Updated compatibility and qualification records without claiming every failure case was run on each live site. The updater now discovers the latest published stable GitHub Release and clones its tag; explicit --ref overrides remain available and discovery failure stops without a development fallback. First installation defaults to v0.5.0. Existing authorship/history, private transcripts, conservative Unknown link handling, backups/rollback and gateway independence are retained. External certificate updater resilience and legacy-platform maintenance remain documented operational limits.
