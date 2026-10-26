# Monitoring Dinas Pendidikan

User approved: kepala sekolah grants finance access per school; Dinas updates only its own profile.

Extend the existing Dinas dashboard: aggregate active schools by default, optional school_id GET filter, same population and six-month attendance charts at either scope. Filtering does not set active_school_id or confer operational permissions. Inactive/suspended/unknown schools cannot be selected.

Finance access is disabled by default per school. Only an active kepala sekolah (including the established super_admin membership alias) of the selected school can grant/revoke it. A dedicated school setting and navigation entry expose the control. The school flag is not mass assignable. Dinas cannot grant access itself.

The BOS expense chart sums approved BOS and BOS subtype expenses from eligible schools that granted permission. Pending/rejected/non-BOS expenses are excluded. Without any permission, no BOS series is serialized. A coverage label states how many monitored schools granted finance access. Revocation applies on the next request.

Reuse /profile for self-service updates; accept no target user ID or role changes. Do not add account deletion or administration. Preserve unrelated finance changes and Yayasan behavior.

Approach: existing dashboard and school setting, one additive boolean migration, a focused dashboard service. Reusing operational school switching would incorrectly mix monitoring with membership; giving finance access through generic role permissions would obscure school consent.

UI follows existing Blade/Tailwind components: labeled school selector and submit button, aggregate reset link, responsive existing statistics, explicit denied/empty finance state, and a checkbox with save feedback for kepala sekolah.
