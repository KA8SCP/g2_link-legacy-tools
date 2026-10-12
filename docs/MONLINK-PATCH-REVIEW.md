# Proposed MonLink module-ownership interlock

Technical review for Ken Adkisson, WB4FAY

Prepared for KA8SCP • 2026-10-03 • Proposal for review, not an upstream release

## Purpose

Prevent MonLink from initiating a DPlus reflector connection on a local module while an independent linker owns that module. WB1GOF uses legacy g2_link for DCS/XRF connections and MonLink for DPlus default linking. Without coordination, MonLink's default-link logic can reclaim a module that g2_link is using.

The proposed patch recognizes the existing per-module marker without coupling the programs or changing their network protocols. It does not add a g2_link dependency or require g2_link to be installed.

## Baseline and attachment

Reviewed MonLink source identifies version `1.0 02/04/17`, author Ken Adkisson WB4FAY. The proposed unified diff is attached separately as `monlink-g2-link-interlock.patch`.

| Artifact | SHA-256 |
| --- | --- |
| Original MonLink | `3e782af12a82ee81f5827a34f628a1c69ffd40d45025550e42948303d488fa30` |
| Patched MonLink | `f5636f454b9db8c69dfd58c42b5ed25d6045b12f3e3951a5668bb622dda03920` |
| Supplied patch | `4a961b429c0f26cb4ef74d9417ec1bd04073e42273a5c82bff8e7c068573ea13` |

The six-hunk diff adds 49 lines and removes 3. Its reversal reproduced the original SHA-256, and forward application reproduced the supplied patched file byte-for-byte. The full MonLink program is not included in the proposed distribution pending licensing/permission review.

## Marker contract

The helper `check_g2link_block` tests:

```perl
sprintf( "%s/blocklinking-%1s", $DPLUSTMP, lc( $LOCALMOD[$N] ) )
```

In the supplied source, `$DPLUSTMP` is `/dstar/tmp`; local C therefore checks `/dstar/tmp/blocklinking-c`. Perl `-e` returns true if the marker exists. The helper returns 1 when present and 0 otherwise. It does not read marker contents or create/delete markers. g2_link owns that lifecycle. WB1GOF's C marker was zero bytes, root-owned, mode 0600.

## Changes by code path

Line numbers refer to the original baseline, before insertions.

| Location | Proposed change | Reason |
| --- | --- | --- |
| `reconnect`, around 2404 | Return early when the module marker exists; optionally log deferral | Avoid reconnecting a DPlus link while the module is externally owned |
| `connect`, around 2454 | Return early before connection-progress state changes and request creation | Guard the common connection routine |
| `check_defaults`, around 2904 | Add a no-marker condition to the delayed default-link decision | Avoid setting default connection state before a blocked connect |
| `check_defaults`, around 2948 | Require no marker before replacing the current destination with the specified default | Avoid disconnect/relink side effects from that default-enforcement branch |
| `check_subscriptions`, around 4074 | Require no marker before the final selected-net connection branch | Defer that net connection and its associated state updates/sleep |
| Helper, around 4334 | Add the per-module existence check | Share the same rule across the guarded paths |

The two common-routine debug messages identify connect/reconnect deferral and require `$DEBUG && $PROC`. Other guarded decisions can skip silently. Earlier scheduled-net decisions can still execute before the final guard; this patch is not claimed to suspend all net processing or all disconnect paths.

## Behavior on systems without g2_link

When no matching marker exists, the helper returns 0. The early-return guards are not taken, and each added `!check_g2link_block` condition is true. For the default destination comparison, the original expression `(call differs OR module differs)` is preserved inside parentheses before the new condition.

**Consequently, the inspected link-control branches retain their original decisions when the marker is absent.** Existing configuration, transport, DPlus commands and timing values are unchanged. No new Perl module or service is required. The incremental work is a filesystem existence check at the guarded decisions; timing/performance has not been benchmarked.

This is a code-level compatibility argument, not proof that all MonLink operation is unaffected. A dedicated original-versus-patched regression on a system without g2_link remains required. A stale marker left behind after removing g2_link would still defer linking; “g2_link absent” must include verifying that its marker is absent.

## WB1GOF results

Operator-reported production testing, repeated after restoration of the hash-verified patch on 2026-10-03:

1. REF049 C was unlinked via RF; `DCS049CO` linked g2_link to DCS049 C.
2. `blocklinking-c` existed; MonLink left DPlus C unlinked while DCS049 C remained active beyond the reported reconnect interval.
3. RF `T` unlink was acknowledged by g2_link; the marker disappeared.
4. MonLink restored REF049 C. Logs confirm the restored C link through 12:24:15 EDT and its link announcement.
5. Module B remained on REF050 C in the supplied status samples.

The operator also reported `perl -c` syntax success and successful service restart. These observations validate the tested C default-link sequence, not all reconnect/net/failure cases or AlmaLinux package compatibility. Exact unlink-to-restoration latency was not measured.

## Proposed compatibility acceptance tests

Compare the original and patched versions with identical configuration and controlled inputs. With no markers present and no g2_link installed, verify default link establishment, ordinary connect/disconnect, failed-link reconnect/retry limits, configured default delays, forced/optional net transitions and announcements, status/log processing, configuration reload and service restart. Compare generated DPlus requests and relevant state transitions; include historical-log replay (`$PROC == 0`). Expected result: no changed link decisions, requests, configured timing rules or announcements attributable to the patch.

Then test C blocked with B unblocked, marker removal/default recovery, and marker present at startup/replay. Test stale markers, permissions/stat failures and marker creation/removal races. The existence check and link action are separate operations; the patch is not an atomic ownership lock and fails open if existence cannot be established. Define the intended recovery policy before extending qualification.

## Updater interaction

The patched source differs from the upstream MD5 manifest, causing a “new version available” warning even though the downloaded baseline is the same release. At WB1GOF the operator manually invoked the updater after that warning. The supplied installer rotated the patched program to `monlink.last`, downloaded the original baseline and restarted it without the interlock. Restoration and the full C test subsequently succeeded.

An upstream-reviewed integration would avoid treating this local patch as an out-of-date upstream file. Until then, documentation must require staged updates, patch/hash verification and independently preserved backups. The installer also replaces the updater wrapper, so merely editing that wrapper does not ensure persistence.

## Requested review

Please review the marker convention and placement of all six changes, especially reconnect bookkeeping, startup replay and net scheduling. We request confirmation of the intended no-marker behavior and applicable permission/license terms for publishing the small patch. We would welcome upstream incorporation after the agreed compatibility tests. No complete MonLink redistribution or claim of universal compatibility is proposed.

## Available compatibility test sites

KA8SCP reports sudo/root access to candidate non-g2_link gateways K1HRO and K1MRA (CentOS 7.x) and W1MRA (AlmaLinux 9.x). These provide candidates for testing both platform families. Inventory, matching source baselines, marker absence, maintenance windows and original-versus-patched results remain pending; no compatibility result is claimed from access alone.

### Site-specific compatibility coverage

K1MRA has only local C/VHF, default REF050 C; W1MRA has only local B/UHF, default REF050 C; K1HRO has B/UHF default REF050 C and C/VHF default REF030 C. This covers single-module and independent dual-module operation, including different local/remote module letters. The existence check uses the local letter. Regression must also cover configured scheduled reflector transitions and the configured event-exit/default policy, since the proposed patch guards selected-net connections as well as defaults. Exact schedules and test results remain pending; see TESTING.md.
