# Dashboard behavior and evidence

## Tables

Module Status keeps DPlus and g2_link states separate. Software & Sources reports versions, availability and source freshness. RF Command Reference sits directly below it, above Remote Users.

Remote Users represents DPlus connected clients and g2_link Software Clients. g2_link's source table named Remote Users is last-heard activity, not a connected-client list, and is not placed in this table. DPlus last-transmission time is filled only when callsign/module matches are unambiguous.

Unified Last Heard shows the newest 30 records, with All Activity, Local RF and Remote filters. Local HTML reports merge only for the same callsign, module and second; source labels identify reporting applications. Exact local DPlus headers are enriched only with one matching logged forwarding start. Different callsigns/modules/times and duplicate candidates are not guessed.

Remote DPlus rows require a unique historical link association and matching reflector/module between logged connection and RPT2. Non-applicable REF049B traffic on a REF049C association is excluded. Module labels are associations, not proof of RF delivery. RPT1/RPT2 preserve logged header fields.

## Historical state

DPlus reads the newest dated archive and active log. Startup sessions remain independent. Untimestamped link/unlink/timeout events retain log order and surrounding timestamp bounds; no exact event time is fabricated. Transition windows, inconsistent snapshots, unresolved timeouts, regressions and uncovered times remain unknown. Complete snapshots may end at the next snapshot header; an incomplete EOF snapshot is excluded.

g2_link DCS history reads all retained uncompressed `log-YYYYMMDD` archives and the active log. This matches WB1GOF's daily copytruncate rule. Link requests and explicit linked announcements establish associations; unlink, restart, timeout, non-DCS replacement, clock-regression and missing archive-day evidence clear them conservatively. Initial unknown state is not inferred from current HTML. Exact overlapping records are deduplicated and inconsistent duplicates are excluded. Distinct stream IDs/times remain distinct, even for the same callsign. Only DCS START records with a unique supported association are included; XRF historical parsing is not implemented.

The parser recognizes the documented legacy log formats. A restart without a recognizable marker, a missing log section, or a gateway using a different rotation scheme requires site review. Retention limits history; this is not a permanent event database.

g2_link rows show Associated DPlus Link as **Not assessed**. A DPlus unlink never proves overall disconnection because g2_link may own the module.

## Suffixes and RF commands

DMR, NXDN, P25, M17 and other incoming g2_link callsign suffixes are preserved. `DPLS` displays as `DPlus`. Suffixes are reported header text, not independently verified protocol provenance or User Message. No transmitted message is invented from a suffix.

URCALL uses eight characters: DPlus L/U/I and g2_link O/T/W in character eight. Link examples: `REF050CL`, `DCS049CO`, `XRF049CO`; unlink/status use seven spaces followed by U/I/T/W. DTMF 73 unlinks g2_link, 99 reports status, #nnnmm links XRF and Dnnnmm links DCS; modules 01=A through 26=Z. DTMF requires the separately running g2_link_dtmf.sh. Its supplied executable logic does not implement the stale comment's *REF example.
