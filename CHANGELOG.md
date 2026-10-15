# Changelog

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