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

## DPlus header matching correction

Normalize whitespace consistently for logged and HTML MYCALL matching while retaining extensions. Unique exact callsign/module/second matches to valid Remote dongle tx start headers identify directly connected hotspot activity, preserve station RPT1 and gateway RPT2 plus suffix, and use the reported RPT2 module without claiming RF delivery or reflector linking. Classification occurs before Local/Remote filtering. Ambiguous events and mismatched start/MYCALL or other-gateway headers remain unresolved. No external DPlusMon data is fetched by the dashboard.

# Historical Associated Link column

The unified Last Heard column combines source-labelled DPlus history and confirmed
g2_link DCS and XRF/DExtra associations at each transmission's timestamp. For example,
`DPlus: Unlinked; g2_link: DCS049 C` means DPlus was unlinked while g2_link has
positive historical evidence for DCS049 C. Simultaneous positive associations are
both retained; the display does not silently prefer one source.

g2_link local associations reuse the remote-history parser's matching DCS or XRF/DExtra link
request and linked announcement, with the local module preserved. Requests,
unlink announcements, restarts, timeouts and retained-log boundaries clear old
evidence. The lookup requires the whole one-second activity window to be within
retained timestamp coverage and away from a state change. Clock regressions,
read errors, missing confirmation and unsupported protocol evidence stay Unknown.
XRF/DExtra history accepts the observed request(dextra), linked announcement and
START from remote g2 formats. Source reflector/module and IP must uniquely match
the retained request/announcement pair. Incoming RPT headers do not establish RF
delivery. XLX and other unsupported link history remain Unknown; the current
status page is never used to invent a historical association. This does not change the current Module Status display.

Remote g2_link rows use their existing uniquely confirmed source/IP/module
association. DPlus history is not applied to those streams. History collection
uses the existing g2_link log pass, and only the maximum 30 visible rows receive
the added indexed lookup. No gateway configuration or linking commands change.


## XRF activity correction (0.5.2-rc.1)

The WL7CWI XRF405 A fixture now retains KA8SCP/52AP and VE6NS/ID52 remote
streams, their RPT1/RPT2 values and local B link association. The active-log
filter also preserves these records before parsing. Existing DCS tests remain
required. This candidate is not a stable release and needs gateway HTTP/activity
acceptance; installing it changes dashboard files only.

## Remote-client expiry correction (0.5.2-rc.2)

WB1GOF's legacy log records an inbound client expiry as
`call=1NFO     timeout, removing 10.0.0.2, users=0`. This removes an
inbound client, not the DCS reflector link. The previous generic timeout reset
mistakenly discarded confirmed reflector history, hiding subsequent DCS activity.

The candidate exempts only this exact timestamped client-expiry diagnostic from
the link-state reset. Reflector TIMEOUT, unlink, unknown timeout, disconnect,
restart and log-gap handling remain conservative. Expiry cannot create a link
association or restore one already invalidated.

The pinned legacy source emits this diagnostic while erasing an entry from
`inbound_list`; reflector heartbeat expiry separately logs
`Unlinked from [...] mod ..., TIMEOUT...`.
See [legacy source](https://github.com/KA8SCP/g2_link/blob/7d22f6f566c863cd6154b198817e47eece9080e5/g2_link.cpp#L1049).
WB1GOF target-site acceptance remains pending.

The operator identifies `1NFO` as a synthetic query identity normally masked
from displayed output. It is not a radio user or a reflector disconnect. This
correction recognizes the inbound-client expiry event type, rather than hiding
all lines containing that callsign or ignoring every timeout.
