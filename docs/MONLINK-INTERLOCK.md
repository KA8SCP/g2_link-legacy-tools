# MonLink/g2_link module ownership interlock

## Validated operator sequence

1. Module C g2_link linked to DCS049 C and created `blocklinking-c`.
2. Patched MonLink suppressed its default REF049 C while the block existed.
3. g2_link unlink removed the block.
4. MonLink restored default REF049 C.

The supplied patch derives the marker from $DPLUSTMP and the lowercase local module; its comment names /dstar/tmp/blocklinking-<module>. The supplied live source sets $DPLUSTMP to /dstar/tmp and identifies version 1.0 02/04/17, author Ken Adkisson WB4FAY. Licensing, marker ownership and test timing remain unrecorded. The operator-supplied patch and original/result hashes are documented in ../monlink/patch/README.md.

## Distribution and application

Distribute only a reviewed unified diff against an identified upstream file, with upstream URL, version, original SHA-256 and licensing notices. The diff must cover the validated default-link decision path. Do not claim this protects every manual/admin/RF linking path without separate evidence.

Before applying the supplied patch, back up the exact local MonLink file and configuration outside the web root, compare its hash with the patch baseline, and run a dry application using `patch --dry-run --fuzz=0` from the documented working directory. Stop on mismatch. Apply only the documented patch, run the appropriate syntax check, and restart using the gateway's established service procedure. Staged patch commands and hash gates are documented in ../monlink/patch/README.md. Production replacement/restart commands still depend on the local service layout.

Record when a block was created and removed and whether MonLink attempted a default link during each interval. Investigate stale blocks through established g2_link procedures; do not automatically delete them or change application ownership.

## Patch inspection

The six hunks guard reconnect and connect processing, two default-link decision points, and a network connection decision, then add the shared marker check. The helper uses file existence and does not create or delete markers. These are observations from the diff; full call-path coverage, race behavior and restart handling are not established by the WB1GOF default-link test. The check and link action are separate operations, so this patch should not be described as an atomic ownership lock.

## Updater persistence

The operator-supplied post-update file reverted to the exact pre-interlock baseline. The existing patch restores the validated bytes. See [updater findings](UPDATER.md); upstream checksum-based update detection can mistake a local patch for a newer upstream release. Review the updater and scheduler before deployment so protection persists across updates.
