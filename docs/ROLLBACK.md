# Rollback

Record original paths, hashes, owner/group, mode and SELinux labels before deployment.

Dashboard: restore the preserved live directory using the site's normal deployment procedure, verify original hashes and open the restored URL. A candidate directory can remain unserved for investigation. Dashboard rollback does not require changing link managers.

MonLink: restore the exact pre-patch file and configuration, run the applicable syntax check, and restart using the recorded local service procedure. Confirm the prior default-link behavior. Reverting removes the protection supplied by the patch; select a maintenance state appropriate to the gateway before testing. Do not remove a g2_link-owned marker merely to force a default link.

Keep operator timestamps and redacted results for both deployment and rollback.
