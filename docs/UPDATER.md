# MonLink updater overwrite finding — 2026-10-03

The operator reported an automated update and supplied the resulting MonLink file. Its SHA-256 is `3e782af12a82ee81f5827a34f628a1c69ffd40d45025550e42948303d488fa30`, exactly the original pre-interlock baseline. It identifies version `1.0 02/04/17` and contains neither the helper nor the added marker checks. The supplied result therefore reverted the local interlock; it is not a different upstream source revision.

Applying the existing patch to a private copy succeeded and produced `f5636f454b9db8c69dfd58c42b5ed25d6045b12f3e3951a5668bb622dda03920`, exactly the validated patched file. Text normalization was disabled for byte verification. No gateway was changed or program executed.

## Update detection

The source's startup code downloads the upstream MD5 manifest, compares it with installed files, and prints instructions to run `sh /dstar/util/monlink-check.sh` when the comparison fails. A local interlock edit changes the checksum even when the upstream version has not changed, so this check can report NEW VERSION OF MonLink IS AVAILABLE solely because of the patch.

The inspected startup section reports the program mismatch; it does not itself replace the MonLink program there. The operator's reported automated replacement needs investigation of `/dstar/util/monlink-check.sh` and its scheduler/service invocation before changing update policy. The wrapper has now been supplied and reviewed below; the downloaded installer has also been supplied and is reviewed below; scheduling details remain unavailable. The startup section separately performs announcement-file updates; do not conflate these with program replacement.

## Maintenance procedure

Treat every upstream update as requiring a post-update interlock check. Preserve a backup, check the downloaded source hash/version, stage and test a compatible patch, and verify the resulting hash before production activation. This patch is qualified by byte identity only against the recorded baseline; do not force it onto changed upstream source. Record local patch status separately from upstream version.

Restoring the current validated patch is possible for this exact uploaded baseline. Follow the staged application steps in ../monlink/patch/README.md and local syntax/service/testing procedures. Any automatic update-policy change requires review of the actual updater and scheduler first. Do not substitute the patched checksum into an upstream manifest to conceal the difference.

## Updater wrapper inspection

The operator supplied `/dstar/util/monlink-check.sh` on 2026-10-03. Independently measured SHA-256: `e4d543fea45a805d9a6eb16ce69c76b9164f7343112cd0de73a68c52a886ed9a`. The script was read, not executed, and is not redistributed in this package.

Control flow:

1. Requires root, then changes to `/tmp`.
2. If `/dstar/util/monlink` exists and there are no arguments, downloads the upstream `monlink.md5sum` and runs `md5sum -c` from `/dstar/util`.
3. A successful checksum check exits with “monlink is current.” A local source patch changes the digest, so the patched file normally takes the mismatch branch.
4. On mismatch, missing local program, or any supplied argument, downloads `g3-ml-dpr-install.sh` from the upstream `.g3_util` path and runs `sh ./g3-ml-dpr-install.sh ml`.

The wrapper contains no AlmaLinux/CentOS detection and no interlock preservation, backup or post-install verification logic. The downloaded installer determines the actual installation/replacement and any platform-specific behavior. This supports the mechanism of a local patched checksum triggering installation, but does not establish the scheduler, exact downloaded installer revision, or all actions taken during the reported update.

The wrapper also does not gate execution on a successful installer download. The installer review below confirms replacement without patch preservation; the invocation schedule still needs review before changing update policy. Do not run this wrapper merely to diagnose the gateway: it can invoke an installation.

The installer has now been supplied. Remaining evidence: the cron/timer/service entry that invokes the wrapper and event logs. A fresh upstream download may differ from the version used during the event; preserve local evidence first.

## Installer inspection

The supplied `g3-ml-dpr-install.sh` has SHA-256 `581dda9d814051af3096342505afbc2022b88981544cb6490f2c9174ddaa33ca`. It identifies itself as installer version 3.01 with 2017 history notes mentioning CentOS 7 and ICOM G3. No AlmaLinux detection or platform-specific branch appears in this supplied file. This does not establish the contents of other upstream revisions.

When invoked with `ml`, the script performs common prerequisite operations and then runs install_monlink. That function stops MonLink, deletes any existing `monlink.last`, renames the current program to `monlink.last`, downloads an upstream replacement, sets executable permissions, and replaces the MD5 manifest and updater wrapper. It preserves an existing monlink.conf and downloads monlink.conf.new for review. It also installs an init script, registers it with chkconfig, replaces the dstar logrotate file, extracts announcement assets and starts MonLink. The function contains no interlock reapplication or post-download hash/syntax gate before restart.

The common prerequisite section uses yum, downloads Tail.pm if absent, and may rename rpmforge.repo. These operations run even for the `ml` argument. Thus this script performs more than a single program-file update; qualification of the dashboard/interlock package must not be inferred from its presence on an AlmaLinux gateway.

The observed chain is consistent with the reported loss of the patch: local patched checksum mismatch -> wrapper invokes installer -> installer backs up patched program and downloads baseline -> installer restarts baseline without marker protection. Scheduling and event logs remain needed to confirm the exact triggering invocation.

### Recovering the prior patched program

Inspect `/dstar/util/monlink.last` before another installer run. If its SHA-256 is `f5636f454b9db8c69dfd58c42b5ed25d6045b12f3e3951a5668bb622dda03920`, it is byte-identical to the validated interlock program. Copy it to a separately named private backup before any further update, retaining permissions and recording the hash. Do not assume monlink.last remains the patched file after multiple updater runs: each run deletes the previous backup.

### Release requirement

Document a locally managed update procedure that stages upstream downloads, checks their identity and patch compatibility, runs required syntax/tests, then activates the patched result using the gateway's established service procedure. Preserve timestamped backups outside the installer's monlink.last slot. Review the scheduler before disabling or replacing any update job. Do not simply edit monlink-check.sh and assume persistence: this installer replaces that wrapper too. No updater, service, or scheduler has been changed by this packaging task.

## Scheduler and startup evidence supplied by WB1GOF

The operator's timer listing shows only tmpfiles-clean and readahead-done. Searches of cron directories/spools, systemd unit directories and /root/my_scripts found no direct monlink-check or installer invocation. The wider search found a commented-out check_dplus_g2link cron entry. Supplied root crontab has no direct MonLink updater call. These bounded checks do not exclude an indirect or external invocation.

The supplied generated monlink.service invokes `/etc/rc.d/init.d/monlink`. The operator supplied that init script (version 1.1): it queries `monlink -v`, checks input/config availability, starts the installed executable with nohup redirected to /var/log/monlink.log, and stops it with kill against pidof results. It contains no updater/download invocation. Service status should be supplemented with `/sbin/service monlink status`, because the generated unit uses RemainAfterExit and does not track a main PID reliably.

The operator confirmed both monlink.last and the independently preserved `/dstar/util/monlink.g2-interlock-validated-20261003` match the validated patched SHA-256. Restoration can use that preserved copy during a maintenance window, retaining a separate backup of the current live file. Verify the preserved hash and Perl syntax, stop the service and confirm its process has exited, copy with attributes preserved, verify the live hash, start the service, inspect actual process status/logs, and repeat the documented Module C acceptance sequence. Do not manipulate ownership marker files as part of file restoration.

The installer mechanism and lost interlock are established by source and hash evidence; an automatic schedule or exact invoking actor is not established. Do not disable unrelated gateway cron jobs based on these findings. Patch persistence qualification remains pending even after restoration.

## Trigger resolved and restoration confirmed

The operator clarified that the updater was invoked manually after seeing the “new version available” message, to check release consistency. There is no evidence from this incident of an automatic scheduled updater. No scheduling change is needed on the basis of this event.

On 2026-10-03 the operator reported successful Perl syntax validation of the preserved patched file, stopped MonLink and confirmed it was not running, and backed up the current live program. The initial interactive copy did not produce the expected live hash; the reason was not established. An explicit `/bin/cp -a -f` from the preserved copy then produced the validated live SHA-256 `f5636f454b9db8c69dfd58c42b5ed25d6045b12f3e3951a5668bb622dda03920`. MonLink was started and its application status reported running, PID 21811, version 1.0 02/04/17.

These are operator-supplied restoration results, distinct from local patch round-trip verification. The post-restoration Module C functional sequence and startup-log inspection have not yet been reported. Updater-trigger diagnosis is complete for this event; future publication still needs explicit documentation that local patches cause upstream checksum warnings and that invoking the upstream installer removes the interlock. Running status alone does not prove linking behavior.

### Post-restoration functional sequence completed

The operator confirmed RF T unlink acknowledgement, absence of /dstar/tmp/blocklinking-c, and MonLink restoration of REF049 C. Supplied logs show the restored C link at 12:22:05–12:24:15 EDT on 2026-10-03 and a module C link announcement; module B stays on REF050 C. The marker/suppression/release/default-restoration sequence now has post-restoration operator evidence. Package platform qualification and licensing remain separate outstanding release requirements.
