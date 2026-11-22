# OOF achievement engine: plan

Written 16 November 2026, with the owner's answers of the same day worked in.
Companion to `docs/achievements.md` (the v1 spec) and `docs/discovery-round-2.md`.
Where this plan and the v1 spec differ on names, tiers or definitions, this plan
follows the newer OOF! Concept doc.

Each phase is small, leaves the hourly run working and ends with the tests green.
A later session can pick up any phase from this file: read the phase, sections 3
and 4 for the design, and "Working notes" at the end.

## Phase status

| Phase | What | Status |
|---|---|---|
| 0 | Safety net: golden master and comparison command | Done, 16 November 2026 |
| 1 | Catalogue alignment, points in config, final-after wait per session type | Done, 16 November 2026 |
| 2 | Surprise rule (rolling team form) and team lineage table | Done, 17 November 2026 |
| 3 | Award schema | Done, 17 November 2026 |
| 4 | Sensitivity | Done, 17 November 2026 |
| 5 | OOFscore | Done, 17 November 2026 |
| 6 | Facts and triggers | Done, 17 November 2026 |
| 7 | Scopes, runs and the Oofies | Done, 18 November 2026 |
| 8 | Recipients | Done, 18 November 2026 |
| 9 | Nicknames and evolution events | Done, 18 November 2026 |
| 10 | 2027 pack | Done, 18 November 2026 |
| 11a | Historic data: Jolpica lap times, constructor merge | Done, 19 November 2026 |
| 11b | Historic backfill: achievements for 1950 to 2022 | Done, 19 November 2026 (curated Clean Sweeps before 1996 approved 19 November) |
| 12a | Rename Early Bird (false start) to Itchy Feet | Done, 21 November 2026 |
| 12b | Engine extensions for the new achievements | Done, 21 November 2026 |
| 12c | Race-weekend glory: Early Bird (pole), Early Chick, Fun Size, Purple Patch, Encore, In Tandem, Hometown Hero | Done, 21 November 2026 (then: official poles, one definition of pole, 22 November) |
| 12d | OOF: Bubble Burst, Dropped the Baton | Done, 22 November 2026 |
| 12e | Fourteen season Oofies | Done, 22 November 2026 (then: Food coverage per race) |
| 12f | Stamp framing service, CMS preview, preview command | Done, 22 November 2026 (then: equal visual weight, background removal) |
| 12g | Backfill and report | Done, 22 November 2026 |

## 0. Where the code stood on 16 November 2026

Engine v1 (`app/Achievements`) runs per session: detectors -> Fairness -> CrossCheck
-> Cascade -> Stacking -> late detectors -> Finality -> store. It is idempotent
(unique on session + achievement + instance key) and never overwrites a person's
decision. 55 detectors, 4,126 awards over 450 sessions of 2023 to 2026.

| Requirement | v1 | Gap |
|---|---|---|
| Per practice / qualifying / race | Yes, `session_types` per achievement | Practice awards are per session; the concept wants one per weekend |
| Season-level awards (Oofies) | Only Frequent Flyer, bolted onto the final race | No season scope; awards require a session |
| Recipients | Driver, or "no driver" for the Safety Car | No team recipient, no explicit recipient type |
| Declarative rules | 6 abstract detector families, one class per achievement, a hardcoded list, a create-only seeder, tier changes by migration | A new achievement touches 3 to 4 places |
| Tier, shelf, editions on the award | Tier and shelf only on the achievement; `variant_name` on the award | No snapshot, so a tier change rewrites history |
| Nickname evolution events | Nothing: no nicknames in the database, no Laravel events | All of it |
| OOFscore, points in config | Points hardcoded in `AchievementTier::points()`, copied to `achievements.points` | No config, no leaderboard |
| Incremental and backfill | Hourly `--changed`; `first_season` 2023 blocks anything earlier | No per-rule data gating, no run log, no date lookup |
| Sensitive weekends | Awards wait for review; a heavy crash sends OOF awards to review | A reviewer can still confirm a crash award; `blocked` is ignored; historic sensitive wins get auto-suppressed |
| Data source and year per achievement | One global `first_season` | Not recorded per achievement |

Catalogue drift, code -> concept (renamed in Phase 1):
`mirror_signal_manoeuvre` -> Blue Carpet, `colouring_outside_the_lines` -> Outside the Lines,
`note_sent_home_to_parents` -> Teacher's Stare (now any reason, not only track limits),
`recalculating` -> Shortcut Tax, `lane_discipline` -> Line Dancing, `road_hog` -> Extra Wide,
`didnt_see_the_sign` -> Selective Vision, `family_feud` -> Mirror Match, `so_close` -> Deleted Scene,
`standing_room_only` -> split into Silver Lining (P2) and Solid Bronze (P3),
`lottery_ticket` -> Giant Slayer, `hogged_the_lot` -> Clean Sweep, `maths_is_over` -> Class Dismissed,
`spare_tyre` -> To the Rescue, `called_in_again` -> Second Helping.
Tier drift (fixed in Phase 1): Meatball Sub and Forgot to Indicate were Silver in code,
Platinum in the concept; Did the Thing was Bronze in code, Gold in the concept.

## 1. Decisions

Decided by the owner on 16 November 2026:

1. Zoomies and Built Different are part of the 2027 pack.
2. The Oofies start with three: most-collected per achievement, OOFscore champion,
   rarest award. Adding another must be a catalogue entry, not new plumbing.
3. No team achievements and no team leaderboard for now. Team recipients stay in the engine.
4. A blocked round stores nothing.
5. Nickname evolution happens in the off-season only. The engine proposes after a
   breakthrough (first podium, first win, or a strong first season); a person approves.
6. Only final awards count on the leaderboard.
7. The final-after wait is configurable per session type, short enough that practice
   and qualifying awards are final by Saturday evening.
8. Mechanics (trigger, scope, recipient, data needs) live in config; editorial fields
   (name, tier, supersedes, enabled) live in the CMS.
9. The Jolpica lap-time import (about 14.5 hours) will be run.
10. FIA stewards' documents are for later, not in this plan.
11. The surprise rule is replaced by a rolling look-back on team form (section 3).

Assumptions that still hold:

- v1 is kept and evolved. Its tests and the golden master are the safety net.
- A sensitivity reaches its whole round, as in the excavators.
- OOF shelf and Safety Car count from 2023, glory from 1950. Earlier OOF awards are
  computed where data allows (OOF Classics, This week in OOF history) but score 0
  and stay out of stamp books.
- Points are snapshotted on the award when created and frozen once a season is closed.
- Sprints keep today's behavior: each achievement lists its own session types.
- An award's calendar date is the session date as stored (Jolpica, UTC), falling
  back to the round's race date.
- The concept's "events" layer stays in memory (typed facts built per run), not a table.
- Rules read the core model, never other awards. Only declared "late" triggers
  (Nothing to See Here, the Oofies) read awards.

## 2. Further decisions

Proposed as defaults and accepted by the owner on 16 November 2026. Each is applied
in the phase named. The final-after waits in section 3 were accepted with them.

| # | Question | Decision | Phase |
|---|---|---|---|
| 1 | "Strong first season" for a nickname evolution | Top 10 of the drivers' championship in the first full season, as a config threshold | 9 |
| 2 | Most-collected Oofie: ties and minimum | Tied drivers share it; at least 2 awards | 7 |
| 3 | Rarest-award Oofie: definition | The final OOF awards of the achievement unlocked least often that season; a tie between achievements goes to the higher tier | 7 |
| 4 | Shelf of the Oofies | A fourth shelf, `oofies`, without tiers or points | 7 |
| 5 | Team form: do sprints count in the window? | No, Grands Prix only | 2 |
| 6 | Team form: Indianapolis 500 rounds of 1950 to 1960 | Left out of the window | 2 |
| 7 | Economy Class "top-four team" | The same rolling team form as the surprise rule | 10 |
| 8 | Keep the golden master out of git? | Yes: it belongs to one database and regenerates with one command | 0 |
| 9 | Team lineage table | `team_lineages`: the rules of `config/f1.php` moved into a table, editable in the CMS, plus the missing historic renames (the `teams` table already holds the lasting identity) | 2 |

## 3. Architecture

```
core model (Jolpica + OpenF1)
   |
   v
Fact providers      typed facts per session, each with recipient, event key, instance key, evidence
   |                (result, qualifying_result, flag, penalty, penalised_offence, incident_involvement,
   |                 track_limit_deletion, pit_stop, cause, safety_car_period, vsc_period, lap_count ...)
   v
Triggers            declarative selection over facts, from config/achievement_catalogue.php
   |                (match, count, extreme, aggregate, custom)
   v
Settling rules      Fairness -> CrossCheck -> Cascade -> Sensitivity -> Stacking -> late triggers -> Finality
   |                (the classes in App\Achievements\Rules, plus Sensitivity)
   v
Award store         idempotent upsert per context; snapshots tier, shelf, points, edition, date
   |
   +--> Projections     OOFscore standings per season and round
   +--> Domain events   dispatched after commit, only for live seasons
```

**Naming.** `App\Achievements\Rules` already holds the settling rules, so the
declarative part of an achievement is called its *trigger* in code.

**Facts.** The Support classes (IncidentThreads, TrackLimitDeletions, SafetyCarPeriods,
PitStops, QualifyingPhases, Finish, Surprise) become fact providers. The fact carries
the event key, so "one event, one achievement" is decided once: a car's deletions, its
black-and-white flag for track limits and its track-limits penalty all share
`track_limits:{entry}`.

**Predicates.** Named conditions on a fact (`won`, `took_pole`, `set_fastest_lap`,
`led_every_lap`, `is_surprise`, `topped_a_practice_session`, `retired_on_lap_one`).
Each fact and predicate declares the data it needs and from which year. A predicate
can have one provider per era (`led_every_lap`: OpenF1 from 2023, Jolpica lap times
from 1996 once imported).

**Data notes fall out of that.** An achievement's sources and first year are derived
from its facts and predicates, stored on the achievement by a sync command, shown in
the CMS and printed by `oof:achievements:catalogue`. The engine skips a trigger for a
session when the year is too early or the source has no rows for that session.

**Trigger types** (a closed set):

| Type | Does | Covers |
|---|---|---|
| `match` | One detection per fact that meets the conditions | Results, flags, penalties, offences, deletions, causes, most glory |
| `count` | Count or sum facts per recipient against a threshold | Blue Carpet, Took the Lead, Second Helping |
| `extreme` | The one recipient with the most or least, with a minimum and a tie policy | Reverse Gear, Fine Dining, Holding the Door, Overtime, Practice Makes Imperfect, Zoomies |
| `aggregate` | Season totals over facts, results or awards | Frequent Flyer, the Oofies |
| `custom` | A class, for logic that does not fit | Class Dismissed, Nothing to See Here, To the Rescue |

Roughly 45 to 50 of the 55 achievements become pure configuration; the rest need a
predicate or a custom class.

**Scopes.** Session, weekend, season. A weekend trigger names the session after which
it is decided (`decided_after`: last practice, qualifying, race). A season trigger runs
once the final round is final. Run order per round: sessions in weekend order, weekend
triggers, then season triggers, then projections.

**The Oofies.** Season-scope catalogue entries with an `aggregate` trigger: a source
(awards, results or facts), a measure, a grouping, a pick (most or fewest), a minimum
and a tie policy. The first three:

- Most-collected: per OOF achievement, the driver with the most final awards of it that season.
- OOFscore champion: the top of the final standings.
- Rarest award: the final OOF awards of the achievement unlocked least often that season.

Frequent Flyer moves here from the final race. Another Oofie is one more entry.

**Recipients.** `driver`, `team`, `safety_car`. A detection carries a recipient value
(type plus entry or season team) in place of today's nullable entry ID. Fairness keeps
requiring exactly one named car for fault-based awards. No team achievement ships yet.

**Surprise rule (team form).** Used by Plot Twist and Giant Slayer.

- Window: the last 10 races before the session's round (`achievements.surprise.window_races`),
  in calendar order, ignoring season boundaries. Fewer are used when fewer exist.
- Per lasting team: the points its cars scored in the window, divided by the window's
  races it entered. Teams are ranked on that figure within the window, so the points
  system of each era does not matter. Equal figures share the better rank.
- A team outside the top 4 (`achievements.surprise.top_teams`) is a surprise. A team
  with no race in the window has no history and counts as outside the top 4.
- Renamed teams keep their history through the lineage (`season_teams.team_id`).
- The driver part ("no top-five start") is gone; only the team's form decides.
- It works in round 1 (the window reaches into the previous season) and for historic
  seasons. The very first championship race has an empty window: nothing is a surprise.

**Sensitivity** (new settling rule, after Cascade so a withheld offence takes its
absorbed penalty with it):

- Round or driver `blocked`: nothing is stored for that scope.
- Round `sensitive`: achievements marked `withheld_on_sensitive_weekends` are never
  awarded. That covers retirements, DNS, causes, incidents, collisions, the Safety Car
  cameo and Nothing to See Here. An existing row is removed, or force-suppressed with
  a system note if a person had decided it.
- Every other award of the round gets `is_sensitive` and the note. OOF and Safety Car
  awards wait for a person; glory awards are stored final but flagged, so a win stays
  in the record.
- The flag never clears. The publishing layer refuses flagged awards in automatic and
  comedic formats.
- Live weekends: strong heavy-crash signals (red flag plus medical car, or a long
  stoppage) create an engine-proposed sensitivity. It acts as sensitive until a person
  confirms or dismisses it.
- Built Different stays manual, and the CMS action refuses it on a sensitive weekend.

**Finality.** `achievements.final_after_hours` becomes a map per session type.
Defaults: practice 2, sprint qualifying 2, qualifying 2, sprint 6, race 24. With the
hourly import, normalize and achievements runs, a practice or qualifying award is
final about three hours after the flag. An open investigation still holds its own
award back until the session is final.

**Points and OOFscore.** `config/achievements.php` gets `points.tiers` (10, 25, 50, 100),
`points.overrides` (Nothing to See Here: 0), `points.scoring_shelves` (OOF only) and
`shelf_from` (OOF 2023, glory 1950, Safety Car 2023). `AchievementTier::points()` and
`achievements.points` go. The standings projection sums final driver awards per season
and stores a row per driver per round, so climbers, fallers and rivalry triggers are
simple queries. Pending awards never count.

**Nicknames and events.** Nicknames live in the database with their stage and dates.

- Breakthroughs: a driver's first podium or first win (read from the results, when the
  award behind it becomes final), or a strong first season (checked when the season is final).
- For a driver with a planned next-stage nickname, a breakthrough stores a proposal and
  dispatches `NicknameEvolutionProposed`. One proposal per target nickname, so re-runs
  and backfills never emit twice.
- A person approves it in the CMS with an effective date, which must fall between the
  season's last round and the next season's first. `NicknameEvolved` (driver, from, to,
  effective date, trigger award) is dispatched on that date.
- Other events: `AwardFinalized`, `AwardWithdrawn`, `AwardsSettled` (per context, with
  the diff) and `OofScoreStandingsUpdated`. All dispatch after commit and only for
  seasons from `achievements.events_from`.

**Runs.** `oof:achievements --changed` stays hourly. New: an `achievement_runs` log
with a catalogue version hash, `--stale` to re-run contexts computed with an older
version, and `--scope=weekend|season`. Backfill is the same command over earlier
seasons, oldest first, one transaction per round; data gating decides what runs.

**Date lookup.** Each award stores `occurred_on` and a month-day number. A query scope
takes a calendar window (with year wrap) and returns awards across all years; This week
in OOF history reads that together with `historic_moments.date`.

## 4. Tables and models

**achievements** (alter)

| Column | Purpose |
|---|---|
| `recipient_type` | driver, team, safety_car |
| `scope` | session, weekend, season |
| `short_name` | Reserve name for tight layouts (Found It Like That) |
| `withheld_on_sensitive_weekends` | Crash-related, the Safety Car cameo and Nothing to See Here |
| `is_manual` | Built Different: never detected |
| `introduced_season`, `retired_after_season` | Season packs and vintage |
| `data_sources` (json), `available_from` | Derived by the sync command |
| drop `points` | Moves to config |

**achievement_awards** (alter)

| Column | Purpose |
|---|---|
| `season_id`, `round_id` (nullable), `race_session_id` (now nullable) | Weekend awards anchor to their deciding session; season awards have only a season |
| `scope`, `context_key` | `session:12`, `round:7`, `season:3`; unique on context + achievement + instance replaces today's unique index |
| `recipient_type`, `season_team_id`, `team_id` | Beside the existing `driver_entry_id` and `driver_id` |
| `tier`, `shelf`, `points` | Snapshot at award time |
| `achievement_variant_id` | Beside the existing `variant_name` snapshot |
| `occurred_on`, `occurred_md` | Date and month*100+day, indexed |
| `is_sensitive`, `sensitivity_note` | Read by the publishing gate |
| `source` | engine, manual, curated |
| `finalized_at` | Set once; makes the events idempotent |

**achievement_variants** (alter): `label` (art file suffix, `name--label.png`),
`subtitle`, optional season range.

**New tables**

| Table | Columns |
|---|---|
| `team_lineages` | constructor ID, from year, to year, team; replaces `f1.team_lineage` in config and feeds `season_teams.team_id` |
| `achievement_runs` | scope, context_key, season, catalogue_version, options, stats, is_dry_run, started and finished |
| `oofscore_standings` | season, round, driver, points, awards_count, position, position_change; unique per season + round + driver |
| `driver_nicknames` | driver, nickname, motto, stage, evolves_from, status (planned, active, retired), active_from, active_to, evolution_trigger (json) |
| `nickname_evolutions` | driver, from, to, trigger_award, reason, evidence, status (proposed, approved, applied, dismissed), decided_by, effective_on; unique on the target nickname |

**moment_sensitivities** (alter): `source` (seed, manual, engine) and `confirmed_at`.

**seasons** (alter): `oofscore_closed_at`, which freezes that season's snapshots.

**Config**: `config/achievement_catalogue.php` (data arrays only, safe for config
caching); `config/achievements.php` gains points, shelf_from, events_from, the
final-after map and the surprise window.

## 5. Five achievements as rules

Sketches from before Phase 6; the syntax as built (templates in braces, thresholds in
the entry) is in `config/achievement_catalogue.php`.

Grand Chelem. Its availability is derived: `won` and `took_pole` 1950,
`set_fastest_lap` 2004, `led_every_lap` 2023, so 2023 today and 2004 after the lap import.

```php
'clean_sweep' => [
    'name' => 'Clean Sweep', 'shelf' => 'glory', 'tier' => 'platinum',
    'recipient' => 'driver', 'scope' => 'session', 'sessions' => ['race'],
    'trigger' => ['type' => 'match', 'fact' => 'result',
        'where' => ['won', 'took_pole', 'set_fastest_lap', 'led_every_lap']],
    'supersedes' => ['did_the_thing'],
],
```

Track limits. Both facts carry the car's `track_limits` event, so the stare replaces
the deletions only when it is about track limits; a stare for another reason stands alone.

```php
'outside_the_lines' => [
    'name' => 'Outside the Lines', 'shelf' => 'oof', 'tier' => 'bronze',
    'sessions' => ['race', 'sprint'],
    'trigger' => ['type' => 'match', 'fact' => 'track_limit_deletion'],
    'stacks' => false,   // one award per session, detail "×3"
],
'teachers_stare' => [
    'name' => "Teacher's Stare", 'shelf' => 'oof', 'tier' => 'silver',
    'sessions' => ['race', 'sprint'],
    'trigger' => ['type' => 'match', 'fact' => 'flag',
        'where' => [['flag', '=', 'black_and_white']]],
    'supersedes' => ['outside_the_lines'],
],
```

Safety Car.

```php
'took_the_lead' => [
    'name' => 'Took the Lead', 'shelf' => 'safety_car', 'tier' => null,
    'recipient' => 'safety_car', 'sessions' => ['race', 'sprint'],
    'trigger' => ['type' => 'count', 'fact' => 'safety_car_period', 'sum' => 'laps',
        'at_least' => 'config:achievements.took_the_lead_laps', 'detail' => ':total laps'],
    'withheld_on_sensitive_weekends' => true,
],
```

An offence that absorbs its penalty ("Bumper Cars · 10 s").

```php
'bumper_cars' => [
    'name' => 'Bumper Cars', 'shelf' => 'oof', 'tier' => 'silver', 'section' => 'offence',
    'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
    'trigger' => ['type' => 'match', 'fact' => 'penalised_offence',
        'where' => [['offence', 'in', ['causing_a_collision']]]],
    'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten',
        'drive_thru', 'red_light_green_light'],
    'withheld_on_sensitive_weekends' => true,
],
```

A weekend-scope achievement from the 2027 pack.

```php
'fools_gold' => [
    'name' => "Fool's Gold", 'shelf' => 'oof', 'tier' => 'gold',
    'scope' => 'weekend', 'decided_after' => 'qualifying', 'introduced_season' => 2027,
    'trigger' => ['type' => 'match', 'fact' => 'qualifying_result',
        'where' => ['topped_a_practice_session', ['position', '>=', 11]]],
],
```

## 6. Data needed per achievement

"From" is the first year the data supports it. The OOF shelf counts from 2023 by
policy even where the data goes back further. Jolpica coverage years marked "check"
come from general knowledge of the Ergast data, not from this database.

| Achievements | Needs | From | Notes |
|---|---|---|---|
| Lapped Dog, Good Boy | Jolpica results (status "+N Laps") | 1950 | |
| Forgot the Keys, Early Lunch, Home Before Dinner, Sent to Your Room, Reverse Gear, Turned Off the Lights, Peaked Too Soon | Jolpica results and grid | 1950 | From 2025 the retirement reason is only "Retired", so illness and injury cannot be filtered automatically; grid 0 meant a pit lane start until 2024 |
| All Dressed Up, Nowhere to Go | Jolpica fastest lap rank | 2004 (check) | |
| Nearly Famous | Jolpica results, points system per era | 2010 for P11 | Earlier eras need the first non-scoring position per year in config |
| Blue Carpet | OpenF1 race control, blue flags | 2023 | |
| Outside the Lines | OpenF1 race control, deletions | 2023 | Re-issued deletions are deduplicated on car and time |
| Teacher's Stare, Meatball Sub, Expelled | OpenF1 race control, flags (Expelled also Jolpica DSQ) | 2023 | Meatball never occurred in 2023 to 2026; one black flag |
| It Was Like That When I Got There, Sibling Rivalry | OpenF1 race control, incident notes with every car linked | 2023 | Involvement only, never blame |
| Bumper Cars, Itchy Feet (Early Bird until Phase 12a), Selective Hearing, Shortcut Tax, Line Dancing, Smile for the Camera, Extra Wide, Forgot to Indicate, Selective Vision, Queue Jumper, Failed the Driving Test, Released Into the Wild, Double Detention | OpenF1 race control, penalty naming one car and an offence | 2023 | In-session penalties only |
| Sunday Driver | OpenF1 race control, maximum delta time noted | 2023 | |
| High Five, Perfect Ten, Drive-Thru, Red Light Green Light | OpenF1 race control, penalties | 2023 | Post-session penalties are missing |
| Called the Tow Truck, Whole Class Detention | OpenF1 race control: deployment plus a single-car message just before | 2023 | Inferred, always reviewed |
| Table for One, Fine Dining | OpenF1 pit, stationary time | 2023 nominally | Stationary time is filled for only a small share of visits; "penalty served" exists only from 2025 |
| Holding the Door, By a Whisker, Mirror Match | Jolpica qualifying times; OpenF1 session results for sprint qualifying | 2006 (knockout format, check) | |
| Economy Class | Same, plus team form before the round | 2006 | Q1 exits need the knockout format |
| Deleted Scene | OpenF1 deletions with a time, plus the Q3 time | 2023 | From 2024 most deletions carry no time, so it undercounts |
| Overtime | OpenF1 laps | 2023 | |
| Practice Makes Imperfect | OpenF1 deletions in practice | 2026 | Not published before 2026 |
| Fool's Gold | OpenF1 practice classification (`practice_results`, since Phase 10) and qualifying | 2023 | |
| Nothing to See Here | The weekend's awards | 2023 | |
| Did the Thing, Silver Lining, Solid Bronze, Took the Scenic Route, Charging Rhino, First Time for Everything | Jolpica results and grid | 1950 | Shared drives in the 1950s need a rule |
| Plot Twist, Giant Slayer | Pole or win, plus team form: results of the last 10 races and the team lineage | 1950 | Pole from qualifying where present, otherwise grid 1 |
| Clean Sweep | Results, pole, fastest lap, laps led | 2023 now; 2004 after the lap import | Earlier ones need a curated list |
| Led Astray | Laps led | 2023 now; 1996 after the lap import | |
| Class Dismissed | Driver standings per round, maximum points per era | 1991 reliably | Dropped-score seasons before that break the clinch maths |
| To the Rescue | Results and entries per team | 1950 data | Only meaningful with fixed line-ups; seat swaps are reviewed |
| Zoomies | OpenF1 laps, speed trap | 2023 | |
| Took the Lead, Second Helping, Virtually There, Frequent Flyer | OpenF1 race control, SC and VSC with lap numbers | 2023 | Races and sprints only; a VSC that becomes an SC has no "VSC ending" |
| Built Different | Manual | n/a | |

## 7. Data gaps

1. **Post-session decisions.** Of 527 "investigated after the session" messages only
   149 have an outcome in race control. Post-race time penalties, grid penalties and
   DSQ reasons need Jolpica's final result or, later, the FIA documents. A grid-penalty
   achievement can only be approximated from qualifying position against grid.
2. **Laps led before 2023.** Jolpica lap times are imported for 1996 on (Phase 11a); the
   engine does not read them yet (11b). There is nothing at all before 1996.
3. **Pit stationary time** is sparse. Check coverage per season before trusting Table
   for One and Fine Dining; Jolpica's pit data is pit-lane time, not stationary time.
4. **Track limits.** Practice deletions exist only from 2026; from 2024 most deletions
   have no lap time.
5. **Starting grid** is only in `raw_responses`. The practice classification is
   normalized into `practice_results` since Phase 10.
6. **Retirement reasons** are generic from 2025, so a DNS or DNF through illness or
   injury relies on a person suppressing it.
7. **No live sensitivity source.** The seeded weekends are historic; nothing flags a
   2027 weekend unless a person or the heavy-crash proposal does.
8. **Props and reserves** are not in the database (nicknames and mottos are, as drafts,
   since Phase 9); the 2027 line-ups are not confirmed.
9. **Team lineage** covers today's teams, the main historic renames (Phase 2) and, since
   Phase 11a, Jolpica's chassis-engine constructors of about 1959 to 1975, teams listed
   under another ID, and two IDs shared by unrelated teams. Jolpica has no entrant, so a
   constructor ID used by several teams in one season (Tyrrell's March cars of 1970,
   private Lotuses) stays with the chassis maker.
10. **Session dates.** Old seasons have only the race date, in UTC; a night race can
    fall on the next calendar day locally.
11. **Jolpica quirks.** No scheduled race distance, a few wrong 2025 lap counts, grid 0
    no longer used after 2024.
12. **No signal in the data.** Black-and-orange flags never occur; team radio has no
    transcripts and follows the broadcast; stints are noisy.
13. **Licences.** OpenF1's free tier is personal use; Jolpica is non-commercial, both the
    API and the free database dump. Both need settling before launch: if OOF ever becomes
    commercial, the data sources' terms must be checked first (owner, 19 November 2026).

## 8. Build plan

**Phase 0: safety net. Done.**

- `oof:achievements:golden --export` runs the engine as a dry run and writes what it
  gives to `storage/app/private/achievements/golden-master.jsonl` (not in git). One
  line per award: session, achievement, instance key, driver, car, detail, variant,
  status, confidence, review reason, event key and a hash of the evidence.
- `oof:achievements:golden` runs the engine as a dry run again and compares. It stores
  no awards, exits with a failure when anything differs, and lists what went missing,
  was added or changed, per achievement. `--season=2025` limits it; `--limit=0` lists
  every difference.
- Exported on 16 November 2026 from the local database: 4,126 awards in 450 sessions
  (2023 to 2026 round 19). Two re-runs showed no differences. Re-exported after Phase 1.
- Fixed on the way: Overtime listed drivers with equal lap counts in an arbitrary
  order in its evidence, so 124 awards counted as "updated" on every run. Equal counts
  now go by entry ID. Stored awards keep the old order until their session next runs.

How to use it in the later phases:

1. Before a change, run `oof:achievements:golden` and expect "No differences".
2. Make the change, run it again and read the differences.
3. When they are exactly the intended ones, replace the master with
   `oof:achievements:golden --export --force`. A pure refactor (Phase 6) must never need this.

It belongs to the database it was exported from: instance keys carry that database's
IDs. Sessions first run after the export are reported and not compared. A session
younger than its final-after wait changes from pending to final by itself.

**Phase 1: catalogue alignment and points in config. Done.**

- 15 keys and names renamed, with their detector classes, in code, seeder, tests and
  (by migration) the stored catalogue and every `supersedes` list.
- Tiers: Meatball Sub and Forgot to Indicate to Platinum, Did the Thing to Gold.
- The podium is split: Silver Lining (second, Silver) and Solid Bronze (third, Bronze).
  The stored podium awards moved by place and lost their "P2"/"P3" detail; a person's
  decisions stay on the rows.
- Teacher's Stare counts a black-and-white flag for any reason. One for track limits
  still shares the car's track-limits event; any other stands on its own.
- Descriptions follow the concept. The migration replaces a description only where it
  still equals the seeded text, so a CMS edit is kept.
- Points: `achievements.points` in config (tiers, overrides, scoring shelves), read by
  `Achievement::points()`. `AchievementTier::points()` and the `achievements.points`
  column are gone; the CMS shows the points and no longer asks for them.
- `achievements.final_after_hours` is a map per session type (practice and qualifying
  2, sprint 6, race 24), read through `SessionData::finalAt()`.
- Checked on the local database: the migrated catalogue equals a fresh seed (56
  achievements), and against the Phase 0 golden master, after mapping the renames and
  the podium split, nothing is missing or changed. The only additions are 16 Teacher's
  Stare awards for reasons other than track limits, all in races. The golden master
  was re-exported.
- Decided by the owner afterwards: Teacher's Stare also counts in qualifying and sprint
  qualifying, not in practice. That added 15 awards, all in qualifying, and took one
  Nothing to See Here away (Leclerc, 2023 round 8, who now has a stare that weekend).
  The golden master was re-exported again: 4,156 awards in 450 sessions.
- Timing note: Jolpica's qualifying results for the 2026 Mexico City round were in the
  database about 1.5 hours after the session ended, so qualifying awards can be final
  on Saturday evening. That is one observation, not a guarantee.

**Phase 2: surprise rule and team lineage table. Done.**

- Checked first: Leclerc's black-and-white flag in 2023 round 8 (Canada) qualifying.
  He went out in Q2 (P11), so Deleted Scene, which needs a deleted Q3 lap, could not
  apply. No car in 2023 to 2026 has both Teacher's Stare and Deleted Scene in one session.
- `TeamForm` (`App\Achievements\Support`): the last `achievements.surprise.window_races`
  (10) Grands Prix before the round, across seasons, without sprints and without the
  Indianapolis 500. Points per race entered, per lasting team; equal figures share the
  better rank. `Surprise::of()` returns the detail ("Team P6 on form over the last 10
  races", "No race for the team in the last 10 races") and an evidence note with the
  window and the top four. Giant Slayer now has that note too. `top_grid` and
  `early_season_rounds` are gone from the config.
- `team_lineages` (constructor ID, from year, to year, team slug, note) replaces
  `f1.team_lineage`; `TeamResolver` reads it. Seeded by `TeamLineageSeeder` (create-only)
  from the migration, with the historic renames added: Footwork to Arrows, Ligier to
  Prost, Leyton House to March, Osella to Fondmetal, Lotus Racing to Caterham, Virgin
  and Marussia to Manor. A lasting team takes the slug of its latest name.
- `TeamLineageSync` re-points `season_teams.team_id` to what the rules give, renames a
  team to its latest season's name and removes a team left without seasons. It runs in
  the migration and after every create, edit or delete on the CMS screen (Team lineage),
  in one transaction; a rule that would merge an unmapped constructor is refused and
  rolled back. Overlapping ranges for one constructor are refused by the form.
- On the local database the migration moved the season teams of those 7 constructors
  and removed their 7 empty teams (188 to 181).
- Read by hand: every pole and win of 2023 to 2026 with the winning team's form rank.
  Only Gasly's pole (2026 round 13, Alpine P6 on form) is a surprise, as under the old
  rule; the closest calls were teams at rank 4. Golden master: only that award's detail
  and evidence changed. Re-exported: 4,156 awards in 450 sessions.
- For comparison (not awarded yet, the engine starts in 2023), over 1990 to 2022 the
  old and new rules agree on 31 poles and wins; the new rule adds 13 (Renault early 2005,
  Mercedes 2013 rounds 5 and 6) and drops 22 (15 of them McLaren 2007, which the old
  rule read as P11 after its exclusion from the constructors' standings).
- Note: a session's surprise awards now depend on the ten races before it, so a late
  result change in an earlier race does not re-run them under `--changed`. Decided
  by the owner: left to Phase 7 and `--stale`.

Follow-ups decided by the owner on 17 November 2026, done the same day:

- The Indianapolis 500 rounds are never a surprise (`Surprise::forTeam()`), besides
  staying out of the window.
- Three more lineages, by migration and in the seeder: Coloni to Andrea Moda (`moda`),
  Ensign to Theodore (`theodore`), Onyx to Monteverdi (`monteverdi`). The Monteverdi
  team is named "Onyx", Jolpica's name for 1990 (decided: fine, no CMS name field).
  Local database: teams 181 to 179.
- Ensign and Theodore (decided afterwards): both entered cars in 1978, 1981 and 1982,
  so they stay separate until 1983, when Ensign merged into Theodore. The lasting
  team `theodore` holds Ensign's seasons and Theodore's from 1983; Theodore's own
  entries of 1978 to 1982 are the team `theodore_1978`, as with `aston_martin_1950s`.
  Local database: teams 179 to 180.
- 2026 round 13 qualifying re-run: Gasly's stored Plot Twist has the new detail and
  evidence, still final.
- The surprise-winner excavator (version 3) uses the same rule: a team hit is a win
  that `Surprise::forTeam()` calls a surprise, scored 100 × how far down the form the
  team stood (no race in the window counts as last), plus the only-career-win bonus.
  `min_position`, `min_relative`, `early_season_rounds` and `excluded_teams` are gone.
  Its evidence holds the form rank, the teams and races in the window and the points
  per race; summaries of candidates dug before version 3 still read. On the local
  database it would give 108 candidates against the 65 stored (58 kept, 50 added,
  7 dropped), 44 of the added from before 1990, many through the chassis-engine
  constructors that have no history of their own (see Phase 11). The stored
  candidates change on the next `oof:dig`, which runs only after the chassis-engine
  merge in Phase 11 (decided by the owner).
- Golden master: no differences (all of it is before 2023 or Indianapolis);
  re-exported, 4,156 awards in 450 sessions.

**Phase 3: award schema. Done.**

- `achievement_awards` gained `season_id` (required), `round_id`, `scope`, `context_key`
  (`session:12`), `recipient_type`, `season_team_id`, `team_id`, `tier`, `shelf`,
  `points`, `achievement_variant_id`, `occurred_on`, `occurred_md`, `source` and
  `finalized_at`; `race_session_id` is now nullable. The unique index is context key +
  achievement + instance key. Enums: `AwardScope`, `RecipientType`, `AwardSource`.
- Saving an award fills what follows from the rest (`AchievementAward::fillDerived()`):
  the context and calendar date from the session (scheduled date, else the round's
  date), season team and team from the driver entry (so a person picking another car
  moves the team too), source `engine`, and `finalized_at` the first time it is final,
  never cleared. Tier, shelf and points are snapshotted only when the award is created.
  The engine finds a session's awards by context key and stores the variant's ID
  beside its name.
- Recipient until Phase 8: `Achievement::recipientType()` gives the Safety Car for its
  own shelf and a driver otherwise.
- Calendar window: `AchievementAward::query()->inCalendarWindow($from, $to)` returns
  awards of every year whose month and day fall in the window, wrapping at New Year.
- Backfill checked on the local database: all 4,126 stored awards have a season, round,
  context, date, shelf, points and source; tier is empty only on the 90 Safety Car
  awards, which have no tier; every final award has `finalized_at` (its decision time,
  else its last update); 4,036 driver and 90 Safety Car recipients. The migration was
  rolled back and applied again without loss. Not covered by an automated test, because
  the schema change cannot run inside the test transaction.
- Golden master: no differences; not re-exported.

Follow-ups decided by the owner on 17 November 2026, done the same day:

- Points freeze per season: a tier or shelf change on an achievement (the CMS) updates
  the tier, shelf and points of its awards in every season that is not closed
  (`Achievement::updateOpenSeasonAwards()`); a closed season keeps its snapshot, and an
  award created later in a closed season takes the snapshot already there, so within
  one season an achievement is always worth the same. `seasons.oofscore_closed_at`
  (planned for Phase 5) was added for this; nothing closes a season yet, so every
  season is open until Phase 5 does. A change to the points in config does not reach
  stored awards by itself.
- Stored awards brought up to date with `oof:achievements` over 2023 to 2026: 4,126 to
  4,156. Created 31 Teacher's Stare (16 races, 15 qualifying), removed 1 Nothing to See
  Here (Leclerc, 2023 round 8), updated the evidence of 1 Overtime; nothing else
  changed. No award had been decided by a person; the 132 historic suppressions are
  unchanged. Golden master: no differences.

**Phase 4: sensitivity. Done.**

- `achievements.withheld_on_sensitive_weekends` (migration, and `AchievementSeeder::WITHHELD_ON_SENSITIVE_WEEKENDS`):
  Early Lunch, Home Before Dinner, Forgot the Keys, Called the Tow Truck, Whole Class
  Detention, It Was Like That When I Got There, Bumper Cars, the four Safety Car
  achievements, Nothing to See Here, and `built_different` for when it is added. A
  safety setting: shown in the CMS, not editable there.
- `achievement_awards.is_sensitive` and `sensitivity_note`. `moment_sensitivities.source`
  (seed, manual, engine; the existing rows are `seed`), `confirmed_at`, `dismissed_at`.
- `App\Achievements\Rules\Sensitivity`, after Cascade and again after the late detectors:
  every sensitivity that is not a dismissed proposal makes its whole round sensitive,
  with or without a driver. Withheld achievements are left out on a sensitive round (a
  withheld offence takes its absorbed penalty with it); a block leaves out everything
  in its scope (round, or driver). The store step deletes such rows; a withheld row a
  person had decided is force-suppressed instead (status suppressed, no decider, note
  "Withheld: sensitive weekend"), and a block deletes even decided rows. Everything left
  in a sensitive round is flagged with the notes; the flag never clears (model guard).
- Finality: on a sensitive round OOF and Safety Car awards wait for a person
  (`sensitive_weekend`); glory is final and flagged.
- Guard: saving an award that a sensitive or blocked weekend does not allow (unless it
  is suppressed) throws `WithheldOnSensitiveWeekend`; the CMS confirm and hand-back
  actions show "Not awarded" instead. This is what keeps Built Different off sensitive
  weekends once it exists (Phase 10).
- Engine proposals (`achievements.sensitivity`): from `propose_from` (2027-01-01, the
  live seasons), a race or sprint in which a car retired with a red flag and the medical
  car, or a red-flag stoppage of at least `long_stoppage_minutes` (20), creates an engine
  sensitivity (level sensitive, severity injury, the driver named) once per driver and
  round. It acts as sensitive at once; the sensitivities screen confirms or dismisses
  it, and a dismissed one is never proposed again and is ignored by the engine and the
  excavators.
- Any change to a sensitivity clears `achievements_checked_at` for its round, so
  `--changed` re-runs the whole weekend.
- The golden master now records the flag (only when set).
- Golden master: no differences (no 2023 to 2026 sensitivities; proposals start in
  2027). Read-only check of the proposal rule on 2023 to 2026: it would have proposed
  2023 Australia (Ocon, Gasly, de Vries, Sargeant: red flag, 32 minutes) and 2023
  Netherlands (Zhou: red flag in the rain, 42 minutes); none had the medical car.

Follow-ups decided by the owner on 17 November 2026, done the same day:

- A block, like a withheld achievement, force-suppresses awards a person had decided
  (system note "Withheld: sensitive or blocked weekend"); what the engine made itself is
  removed. The person's decision is kept in `achievement_awards.held_decision`.
- After a dismissal or a deletion, the held decisions come back wherever the round's
  remaining sensitivities allow the award (`AchievementAward::releaseRound()`): a win
  suppressed by a driver block returns, flagged, while an ordinary sensitivity stays; a
  withheld retirement does not. The flag stays.
- One engine proposal per round: no driver, covering the round, with every driver
  involved in `moment_sensitivities.involved_driver_ids` and named in the note; a later
  crash that weekend is added while it is still a proposal. The excavators' span rule
  reads the listed drivers like a named one. Long stoppage stays at 20 minutes.
- Also withheld: Released Into the Wild, Failed the Driving Test, Selective Vision and
  Queue Jumper. Reverse Gear, Sent to Your Room and Expelled are not.
- `AchievementAward::query()->publishable()`: final and not flagged sensitive.

**Hard requirement for any publishing work** (feeds, automatic posts, comedic formats,
the public site, exports): read awards only through `publishable()`. Suppressed, pending
and flagged awards never leave the CMS. OOF and Safety Car awards before 2023 are never
published automatically (decided 19 November 2026, Phase 11b Q6): `publishable()` leaves
them out, a person picks them for OOF Classics and This week in OOF history, and stamp
books and trophy cabinets read `countingOnTheirShelf()`. A publishing change that reads awards another
way is not done. Likewise, publishing refuses draft nicknames (`driver_nicknames.is_draft`,
decided 18 November 2026): a draft nickname, its motto and an edition subtitle built from
it never leave the CMS.

**Phase 5: OOFscore. Done.**

- `oofscore_standings` (season, round, driver, points, awards_count, position,
  position_change; unique per season + round + driver) and `App\Achievements\OofScoreStandings`.
  Per season: the points snapshot of each driver's final awards on the scoring shelves,
  summed round by round; a row per driver per round from their first award on, for every
  round that has awards. Pending and suppressed awards never count; Nothing to See Here
  counts as an award with 0 points. Equal points share a place; `position_change` is the
  places gained since the round before (null for a newcomer).
- Rebuilt from scratch every time, in one transaction: by `oof:achievements` for each
  season it ran, after every decision in the CMS (confirm, suppress, hand back, bulk
  suppress), by `oof:oofscore [--season=]`, and by the Rebuild action on the OOFscore page.
- `OofScoreStandingsUpdated` (season, changed round IDs), dispatched after the commit
  when a rebuild changed something, only for seasons from `achievements.events_from` (2027).
- Closing a season: `oof:oofscore:close {year}` (asks first; `--force` does not) or the
  "Close season" action on the OOFscore page. It refuses while the last race has not been
  run or is not final, while any award of the season waits for review, or when the
  season is closed already. Otherwise it rebuilds the standings a last time and sets
  `seasons.oofscore_closed_at`. From then on the season is never rebuilt, a tier change
  no longer reaches its awards, and a new award there takes the season's snapshot.
  No season has been closed (the owner decides when).
- Decided afterwards: final awards of sensitive weekends count. Equal points are split
  like a medal table: more Platinum, then Gold, Silver, Bronze, then more awards; only
  drivers equal on all of that share a place. Achievements worth 0 by design (Nothing to
  See Here: no OOFs that weekend) count in neither the medals nor the award count of the
  tie-break, so they never help in a tie; they still show in `awards_count`.
- Reopening, owner only: `oof:oofscore:reopen {year}`, no CMS action; it asks for the
  year to be typed (`--force` does not). Its awards take the catalogue's current tier,
  shelf and points again and the standings are rebuilt.
- Multipliers (decided 17 November 2026, after a read-only simulation of two sets):
  every achievement has a `score_category`, editable in the CMS: `driver` (×1: penalties,
  offences, track limits, flags, collisions, Sunday Driver, By a Whisker, It Was Like
  That When I Got There, Called the Tow Truck, Whole Class Detention; also the default),
  `pace` (Lapped Dog, Good Boy, Blue Carpet, Holding the Door, Turned Off the Lights,
  Reverse Gear, Nearly Famous, Mirror Match, All Dressed Up Nowhere to Go; Overtime until
  18 November 2026, now driver),
  `reliability` (Forgot the Keys, Early Lunch, Home Before Dinner, Sent to Your Room,
  Table for One, Fine Dining, Released Into the Wild, Meatball Sub) and `surprise` (×1:
  Economy Class, Peaked Too Soon, Led Astray, when added). `achievements.points.multipliers`
  by the team's form rank before the round (Phase 2 rule; no race in the window counts
  as the last band): pace 1-4 ×2, 5-7 ×1, 8+ ×0.5; reliability 1-4 ×1, 5-7 ×1, 8+ ×0.5.
  Nothing to See Here stays 0.
- The multiplier counts only in the OOFscore: the award keeps its tier and base points.
  The rebuild of an open season stores `team_form_rank` and `score_multiplier` on each
  of its OOF awards (an award without a known car is ×1) and sums points × multiplier,
  so standings hold halves; a closed season keeps the stored values. A change of an
  achievement's tier, shelf or score category rebuilds the open seasons it appears in.
  The tie-break is unchanged: medals on tiers, then award count.
- Decided: pending awards get a rank and multiplier stored too (they count once final);
  an award without a known car is ×1; a CMS change of tier, shelf or score category
  rebuilds the open seasons immediately. If that gets slow, move the rebuild to a
  background job.
- CMS: an OOFscore page with the leaderboard after a chosen round (latest by default),
  change arrows, and the Rebuild and Close season actions.
- Checked on the local database: 2026 rebuilt from scratch (19 rounds with data, 425 rows),
  a second rebuild changed nothing; Pérez (72 awards, 1,035) and Hülkenberg (50 awards,
  745) match their final OOF awards by hand. 2023 to 2025 rebuilt as well; all three
  could be closed. Golden master: no differences.

**Phase 6: facts and triggers. Done.**

- `config/achievement_catalogue.php`: the 56 achievements in run order. Editorial
  defaults (name, section, shelf, tier, score category, description, supersedes) seed a
  new row once and belong to the CMS after that. Mechanics (sessions, stacks, recipient,
  scope, withheld on sensitive weekends, trigger) belong to the file. `planned` keeps
  the settings decided for the 2027 pack (Built Different withheld; Economy Class,
  Peaked Too Soon and Led Astray in the surprise score category). It replaces the
  seeder's lists and `AchievementEngine::DETECTORS`; the two migrations that read the
  seeder's lists have them written out.
- Facts (`App\Achievements\Facts`): one provider per fact type (penalised_offence,
  penalty, offence_noted, incident_involvement, flag, track_limit_deletion, result,
  qualifying_result, qualifying_knockout, pit_stop, lap_count, safety_car_period,
  deployment, cause), built once per session. A fact carries the recipient, instance and
  event keys, evidence and attributes (lazy where costly, such as `surprise`).
  Predicates: did_not_start, retired, disqualified, not_disqualified,
  classified_finisher, last_classified_finisher, is_surprise, took_pole, led_every_lap.
- Triggers (`App\Achievements\Triggers`): `match`, `count`, `extreme` and `custom`.
  Conditions are a predicate name, `[attribute, operator, value]` or `['any' => [...]]`.
  Templates use `{attribute}`, `{count}`, `{total}`, `{value}` and `{count:one|other}`;
  an empty value renders as nothing. Unknown trigger options are refused. `aggregate`
  waits for Phase 7.
- 52 achievements are config. Custom (`Triggers\Custom`, moved unchanged): Class
  Dismissed, To the Rescue, Frequent Flyer (until Phase 7) and Nothing to See Here
  (late). `app/Achievements/Detectors` is gone.
- Per-achievement thresholds moved from `config/achievements.php` into their entries
  (`at_least`, or a condition): Blue Carpet 8, Took the Lead 8 laps, Second Helping 2,
  Table for One 5 s, By a Whisker 15 ms, Took the Scenic Route P10, Practice Makes
  Imperfect 3. Global settings (surprise, multipliers, cause window, heavy crash,
  sensitivity, max points) stay there.
- Data needs (`Requirements`) are derived from the provider, the predicates and the
  attributes a trigger's conditions read (fastest lap rank from 2004), or declared by a
  custom class. The engine skips an achievement in a session before its first year, or
  when the session has no rows in a source its facts are built from.
  `oof:achievements:catalogue [--each]` prints them (section 6, from code).
- `oof:achievements:sync [--dry-run]` creates missing achievements and writes the
  mechanics and the derived `data_sources` and `available_from` (new columns) over the
  row; rows outside the catalogue are reported, never deleted. The seeder creates rows
  the same way. The CMS shows sessions, stacks and the data needs read-only (decided by
  the owner).
- Years follow the local data, which differs from section 6 in places: measured pit
  stationary time starts in 2024; Jolpica qualifying in 1994 (Holding the Door from
  1994, the knockout achievements from 2006); practice deletions are sparse before 2026
  (one session in 2023, one in 2024, five in 2025), so Practice Makes Imperfect is
  declared from 2023, which keeps its one 2023 award.
- Sync dry run on the local database before the switch: no differences in sessions,
  stacks or withheld; only the 112 new derived fields, which the sync then wrote.
- Golden master: no differences after every step (13 commits); not re-exported.

Decided by the owner on 17 November 2026:

- Practice Makes Imperfect stays available from 2023, so its 2023 award is kept.
- The years taken from the local data stand (pit stationary time 2024, Jolpica
  qualifying 1994, knockout achievements 2006).
- Deploy: sync dry run first, then the sync (deploy step 2). Nobody has edited sessions
  or stacks in the production CMS, so the sync overwrites nothing a person set.

**Phase 7: scopes, runs and the Oofies. Done.**

- `achievements.scope` (session, weekend, season) and `decided_after` (`practice` for the
  last practice session, or a session type), mechanics written by the sync; the migration
  sets them for the five achievements that move. A weekend or season entry's `sessions`
  are the sessions it reads. The factory refuses a weekend entry without `decided_after`
  and an aggregate trigger outside the season scope (and the reverse).
- Weekend scope: Overtime and Practice Makes Imperfect (after the last practice, totals
  per driver over every practice session: extreme option `per: driver`), Fine Dining (after
  the race, the slowest stop of race and sprint; `from` and `only_own_session` are gone)
  and Nothing to See Here (after the race, from the stored session awards and the
  weekend's other detections). Descriptions now say "the whole weekend" (replaced only
  where they still equal the seeded text). Stored in context `round:{id}`, anchored
  (`race_session_id`) to the deciding session, on the car the driver drove in it. An
  achievement whose deciding session has not ended is undecided: nothing is found and its
  stored awards stay. A weekend achievement that supersedes a session one (Fine Dining over
  Table for One) joins that session's cascade, so the session never stores what the
  weekend replaces. CrossCheck does not run on the weekend (no fault-based weekend
  achievement exists).
- Season scope and the `aggregate` trigger: a source (facts of the season's sessions, the
  season's final session and weekend awards, or the OOFscore standings after the last
  round), `per` (achievement), `group` (season, driver, achievement), `measure`, `pick`
  (all, max, min), `at_least`, `ties` (share, none, higher_tier) and `to` (group or
  holders). Stored in context `season:{id}` with only a season, dated on the final round,
  final unless inferred. A season is decided once its final race has been run and is
  final. Frequent Flyer moved onto it (facts: safety car deployments of races and sprints);
  the custom class is gone.
- The Oofies (decisions 2, 3, 4): a fourth shelf and section `oofies`, no tier, 0 points.
  Most Collected (per OOF achievement, the driver with the most final awards; at least 2;
  ties shared), OOFscore Champion (position 1 after the last round; ties shared), Rarest
  Award (the OOF achievement unlocked least often, at least once; a tie goes to the higher
  tier, equal tiers share; every holder gets it). Created by the sync; names are CMS fields.
- Run order per round: its sessions in weekend order, then its weekend. Then the OOFscore
  of the seasons touched, then the seasons. The standings come before the seasons (not
  after, as section 3 had it) because the champion reads them; the Oofies earn no points.
- Run log: `achievement_runs`, one row per context decided, with `Catalogue::version()`: a
  hash of the catalogue file, the engine settings of `config/achievements.php` (not points,
  not events) including the new `engine_version` (raise it when a code change alters what
  the engine finds), and what the engine reads from the stored achievements (enabled,
  supersedes, mechanics). Dry runs are logged too, marked, after the rollback; a season
  that is not final is not logged.
- `oof:achievements --stale`: rounds with a session or weekend whose last real run was on
  another version or is not in the log (what never ran is for `--changed`), a session whose
  achievements read earlier races (`ReadsEarlierRaces`, the surprise rule) when a result,
  entry or season team in its team-form window changed after its last run (the Phase 2
  note), and seasons decided on another version. Whole rounds re-run. `--changed` now also
  decides a season whose session or weekend awards changed since it was last decided (a
  CMS decision can change the Oofies), and re-runs a round when a weekend award anchored to
  one of its sessions is pending and that session is final now. `--scope=session|weekend|season`
  limits the run; `--session` narrows only the session scope.
- Golden master: a weekend award is keyed by its deciding session, a season award by
  `2025-season`, covered once that season is in the run log.
- CMS: the awards list groups by context ("2025 R5 Miami Grand Prix · Weekend (after
  FP3)", "2025 · Season"), filters on the award's season and round, and the session filter
  has Weekend and Season. The achievement form shows what it is decided over (read-only).
  The test-run report counts by season, so weekend and season awards count. No CMS screen
  for the run log.
- Checked on the local database (migrate, sync: the 3 Oofies created, no other drift;
  rollback and re-apply work): Overtime equals an independent SQL total of practice laps
  per race driver on all 90 weekends (67 awards; 22 weekends tie on the top total, 1 has no
  laps). Every Nothing to See Here change comes from Overtime moving. Frequent Flyer gives
  the old counts (2023 21, 2024 14, 2025 21). `--stale` after a full run takes under a
  second and finds nothing; after a catalogue change it re-runs everything.
- Golden master: 282 differences, all intended. Overtime 147 missing, 67 added (once per
  weekend over the practice totals); Practice Makes Imperfect 24 missing, 15 added (likewise;
  2026 rounds 11 and 14 tie on the total); Nothing to See Here 5 missing, 15 added (drivers
  who gained or lost their only award through Overtime); Fine Dining 3 added and Table for
  One 3 missing (the slowest stop was in a sprint: 2025 rounds 6 and 19, 2026 round 4);
  Frequent Flyer 3 moved to the season contexts. New and not compared: the season contexts
  of 2023 to 2025 (3 Frequent Flyer, 91 Most Collected, 3 champions, 4 Rarest Award).
  Re-exported: 4,175 awards in 453 contexts (450 sessions, 3 seasons).
- 2025 Oofies on the local data: OOFscore Champion Antonelli (1,210); Rarest Award
  Bearman (Red Light, Green Light, once, over Queue Jumper on tier); 32 Most Collected,
  for example Antonelli Outside the Lines ×15, Ocon Nothing to See Here ×9.
- Stored awards on the local database were not brought up to date (only dry runs); deploy
  step 4 does that.

Decided by the owner on 18 November 2026, done the same day:

- Ties on a weekend total are shared: everyone on the top total gets Overtime or Practice
  Makes Imperfect (extreme trigger `ties: share`).
- Most Collected leaves Nothing to See Here out (aggregate option `except`); its
  description says so (migration, only where it still equals the seeded text).
- The hourly schedule runs `oof:achievements --changed --stale`.
- Live weekends: a session runs once it has ended (OpenF1's end time, else its scheduled
  start plus three hours, `RaceSession::endsAt()`), no longer only from the round's race
  date. Practice and qualifying are decided on Friday and Saturday and become final after
  their own final-after hours through `--changed`; a weekend achievement still waits for
  its deciding session.
- The production check of decided awards stays deploy step 3.
- Golden master: 86 differences, all intended. Overtime 55 added (shared on the 22 tied
  weekends); Practice Makes Imperfect 6 added (2026 rounds 1, 11, 14); Nothing to See Here
  8 missing (drivers who now share Overtime); Most Collected 9 missing, 3 added, 1 changed
  (Nothing to See Here out: 5; Overtime counts moved with the sharing, e.g. 2023 Bottas
  Overtime ×5 alone instead of three drivers on ×2); OOFscore Champion 1 missing, 1 added,
  2 changed (the shared Overtime points: 2023 Piastri 685 to 725, 2025 Antonelli 1,210 to
  1,230, and 2024 goes from Albon 727.5 to Hamilton 745). Re-exported: 4,222 awards in 453
  contexts.
- Overtime moved from the pace to the driver score category (×1 for every team), decided
  the same day: catalogue default and, by migration, the stored row (only where it was
  still pace). OOFscore rebuilt for 2023 to 2026. Champions: 2023 Piastri 725 to 715, 2024
  Hamilton 745 to Magnussen 742.5, 2025 Antonelli 1,230 to 1,180. 2024 top 5: Magnussen
  742.5, Albon 727.5, Hamilton 715, Gasly 635, Stroll 620. Golden master: only OOFscore
  Champion differs (2023 and 2025 points, 2024 the driver); re-exported.

**Phase 8: recipients. Done.**

- `App\Achievements\Recipient` (driver with its car, which may be unknown; team with its
  season team; Safety Car) replaces the nullable entry ID on `Detection`. Its key (`e12`,
  `t5`, none for the Safety Car or an unknown car) prefixes the award's instance key, so a
  driver award's key is unchanged, and groups detections in Cascade, Stacking and the
  `count` and `extreme` triggers. Fairness still needs a message naming exactly one car,
  and the award goes to that car's driver.
- `achievements.recipient_type` (migration; the four Safety Car achievements written out)
  is a mechanic: the catalogue's `recipient` is written by the sync and is part of the
  catalogue version. `Achievement::recipientType()` reads it; it no longer follows the
  shelf. The Phase 3 migration that filled `achievement_awards.recipient_type` now has the
  shelf rule written out, so a fresh migrate gives the same result.
- Triggers: a declarative trigger gives each fact's detection the entry's recipient (the
  driver of the fact's car, that car's season team, or the Safety Car); for a team
  achievement a fact without a known team is left out. `count` counts `per: recipient`
  (default; Blue Carpet renamed from `per: entry`) or `per: session`. The aggregate
  trigger gives the Safety Car or a driver. The factory refuses a team achievement with a
  custom or aggregate trigger.
- Stored: a team award has `recipient_type` team, `season_team_id` and the lasting
  `team_id` (filled from the season team), no driver or car; a driver award still records
  its car's team. The OOFscore and the Oofies read driver awards only (decision 3).
- Trophy queries: `AchievementAward::query()->forDriver()`, `forTeam()` (the lasting team,
  every season and name), `forSeasonTeam()`, `forSafetyCar()`; `Driver::awards()`,
  `Team::awards()`, `SeasonTeam::awards()`, each the recipient's own awards only (a team's
  list does not hold its drivers' awards).
- CMS: the awards list, award page and round page show a Recipient column (driver, team
  name or "Safety Car"); picking a car on confirm is asked only for driver awards; the
  achievement form shows "Awarded to" read-only.
- Golden master: records a team award's team (only when set), so team awards will be
  compared once one exists.
- Proven with a team achievement that exists only in the test (`TeamRecipientTest`,
  "Double Podium": a `count` trigger over result facts, `recipient: team`), added as
  config and synced, then run: one award for the team, re-run creates nothing, the queries
  find it, the OOFscore ignores it. No team achievement ships (decision 3).
- Checked on the local database: migrate, sync dry run (no drift; 55 driver, 4 Safety Car
  achievements), rollback and re-apply. Golden master: no differences; not re-exported.

Decided by the owner on 18 November 2026:

- Requirement for the first team achievement (not built now, as none exists): a driver
  block on a round also holds back the team awards of that driver's team in that round,
  as a round block does for every award.
- Season-level team awards (an aggregate trigger grouped by team) come later, if ever;
  until then a team achievement takes a match, count or extreme trigger.

**Phase 9: nicknames and evolution events. Done.**

- Source: `docs/oof-concept.md`, committed on its own as a reference snapshot (13 November
  2026 nicknames). Requirement from the owner: the nicknames are not final, so everything
  must stay editable, and nothing stores a nickname as text.
- `driver_nicknames`: one row per stage (driver, nickname, motto, stage, `evolves_from_id`,
  status planned, active or retired, `is_draft`, `active_from`, `active_to`,
  `evolution_trigger`: the breakthroughs that may propose a planned stage, any when empty,
  and a note). One active nickname per driver (model guard). `nickname_evolutions`: driver,
  from and to nickname, season of the breakthrough, trigger award, reason, evidence, status
  (proposed, approved, applied, dismissed), decided by and at, `effective_on`, `applied_at`;
  unique on the target nickname.
- Seeded by `DriverNicknameSeeder` (also in `DatabaseSeeder`), all as drafts: the 22 drivers
  of the grid table and the four legends, with mottos (legends have none), and the four
  planned evolutions (Outlawson to LawFirm, which keeps the motto as the concept says;
  LindBud to LindBloom, RafaCut to Rafined and Gabbles to Gabblefly, without a motto yet).
  Create-only per driver and stage: an existing row is never touched. A driver not in the
  database (matched on the Jolpica ID, else the name) is skipped and reported: Câmara
  locally. Props are not seeded.
- Nothing stores the text: `Driver::nicknameOn($date)` finds the stage in use on a date;
  `AchievementAward::nickname()` reads it for the award's date, so an award keeps the stage
  it was won under after an evolution; `editionSubtitle()` builds "Outside the Lines ·
  MaxSettings edition" for an award with a driver variant. The award's own `subtitle`
  stays a person's free text. Evolutions refer to the rows.
- Correction and evolution are kept apart. A correction edits the row in place (CMS
  Nicknames, "Correct"): every award, subtitle and evolution follows at once, with no new
  stage and no event; status and dates are not editable there. An evolution is a new row:
  planned in the CMS ("Next stage of"), made active only through an approved evolution.
- Breakthroughs (`App\Achievements\Nicknames\Breakthroughs`), for each planned stage whose
  previous stage is active and that has no evolution yet: first podium or first win, from
  the Grand Prix results (sprints do not count), once the award behind it (Did the Thing
  or what supersedes it, Silver Lining, Solid Bronze) is final; a first podium that is a
  win is a first win. Strong first season (decision 1 of section 2): once the season is
  final, `nicknames.strong_first_season_top` (10, in its own `config/nicknames.php`, so the
  catalogue version is unchanged) or better in the final drivers' standings of the
  driver's first full season (see the decisions below). One proposal per target
  nickname, so a re-run, a backfill or a dismissal never proposes again.
- Runs at the end of `oof:achievements` for the seasons touched (not on a dry run), and
  as `oof:nicknames [--season=]`.
- Approval (CMS Nickname evolutions): a person approves with an effective date, which must
  fall after the breakthrough season's last round and before the next season's first
  round (`OffSeasonOnly` otherwise; also refused while the next season's calendar is not
  in the database), or dismisses it. `oof:nicknames:evolve`, daily at 00:10, applies the
  approved evolutions whose date has come: the old stage retires the day before, the new
  one is active from the date.
- Events, after the commit and only for seasons from `achievements.events_from` (2027):
  `NicknameEvolutionProposed` and `NicknameEvolved` (the evolution: driver, from, to,
  effective date, trigger award).
- CMS: Nicknames (list with filters, Correct, add a first nickname or plan a next stage,
  delete only a stage nothing builds on) and Nickname evolutions (approve, dismiss). The
  award page shows the nickname on the day and the edition subtitle.
- Checked on the local database: migrate, seed (28 rows: 25 drivers and 3 planned stages,
  all drafts; Câmara skipped), seed again (nothing changes), rollback and re-apply. Read
  without storing: no proposal over 2023 to 2026, which is right: Lawson, Lindblad and
  Bortoleto have no Grand Prix podium (best P5, P6, P6), Lawson's and Bortoleto's first
  full seasons (2025) ended P14 and P19, and 2026 is not final yet (Lindblad P11 after
  round 19).
- Golden master: no differences; not re-exported.

Decided by the owner on 18 November 2026, done the same day:

- A first full season: the driver drove in the season's first round and missed at most
  two rounds (`nicknames.full_season_missed_rounds`); a mid-season replacement has none
  that season.
- Approval stays refused while the next season's calendar is not in the database.
- Unwanted proposals (for example Lawson's, meant for "once established") are dismissed
  by hand; `evolution_trigger` stays empty.
- Câmara: 2026 round 20 (Brazil, his Haas debut) imported locally from Jolpica and
  OpenF1 and normalized. His Jolpica ID is `rafael_camara` (the seeder had `camara`;
  corrected). The seeder then gave him RafaCut, with Rafined planned (30 rows).
- Golden master after the import: 79 added, nothing missing or changed, all new data. 66
  in round 20 (its sessions and weekend, run for the first time), and 13 in the 2026
  round 19 race: 12 Table for One and 1 Fine Dining (Verstappen, 32.4 s) from pit stops
  that OpenF1 published after the earlier import. Bottas's one is suppressed: his
  retirement had heavy-crash signals, so his OOF awards wait for review, and before
  `review_from` that means suppressed, as with his stored Home Before Dinner. Re-exported:
  4,301 awards. Stored awards were not brought up to date (no real engine run).

To do (nicknames):

- Mottos for LindBloom, Rafined and Gabblefly.
- A motto template that counts titles, for Kimissimo ("One scoop and counting", "Two
  scoops and counting" after a second title); stored as plain text until then.

**Phase 10: 2027 pack. Done.**

- Eleven catalogue entries; the `planned` block of the catalogue is gone (its settings are
  in the entries). As built:

  | Achievement | Shelf, tier | Score category | Definition as built |
  |---|---|---|---|
  | Nearly Famous | OOF, Bronze | pace | Grand Prix: classified P11. Sprints do not count |
  | Economy Class | OOF, Silver | surprise | Qualifying and sprint qualifying: out in Q1 or SQ1 (by classified position) with a lap time in it, while the lasting team is in the top 4 on form before the round (Phase 2 rule) |
  | Peaked Too Soon | OOF, Gold | surprise | Grand Prix: took pole (`took_pole`), then not on the podium (P4 or lower, unclassified, DNS or disqualified) |
  | Led Astray | OOF, Gold | surprise | Grand Prix: led the most laps (shared on a tie, at least one) and did not win. Laps led from OpenF1 as for Clean Sweep: P1 at the end of a lap (the last lap is not counted for anyone) |
  | Double Detention | OOF, Gold | driver | Offence `serving_a_penalty_incorrectly` with a penalty, in races, sprints and both qualifyings; absorbs the penalty like the other offences ("Double Detention · 10 s") |
  | Fool's Gold | OOF, Gold | surprise | Weekend, decided after qualifying: P1 in at least one practice session of the round, then qualified P11 or lower; once per weekend ("topped FP1 and FP3 · P12") |
  | Sibling Rivalry | OOF, Silver | driver | Race or sprint: named in an incident race control noted together with a teammate; both get it; supersedes It Was Like That When I Got There for that incident, and is superseded for a car penalised for it |
  | First Time for Everything | Glory, Gold | n/a | Career first in a Grand Prix: win, else podium, else points (one per car, the most specific); in Grand Prix qualifying, the first pole (an earlier pole is P1 in qualifying, else grid 1). History from 1950; sprints do not count |
  | Charging Rhino | Glory, Silver | n/a | Grand Prix: classified and gained 10 or more places; a pit lane start counts as behind every car that started |
  | Zoomies | Glory, Bronze | n/a | Grand Prix: highest speed-trap reading (OpenF1 `st_speed`, whole km/h); a tie is shared |
  | Built Different | Glory, Platinum | n/a | Manual only (trigger `manual`, `achievements.is_manual`), every session type, withheld on sensitive weekends |

- Withheld on sensitive weekends: Sibling Rivalry and Built Different, as decided, plus
  Peaked Too Soon and Led Astray (both often come from a retirement or a crash).
- Data: `practice_results` (place, best lap, laps per car) from OpenF1's practice session
  results, by `SessionResultNormalizer`, which already stored sprint qualifying. Locally
  4,427 rows; every practice session run so far has a P1. `oof:normalize` gained
  `--endpoint` (`--all --source=openf1 --endpoint=session_result` re-reads them).
- Engine: fact providers `speed_trap` and `career_first`; attributes `places_gained`,
  `finish` and `laps_led` (results), `knocked_out_in` (from 2006), `team_form_rank` and
  `topped_practice` (qualifying), `teammate` (incident involvement); predicates
  `led_most_laps`, `top_team_on_form` (reads earlier races, for `--stale`) and
  `topped_a_practice_session`; `GatesSessions` for a provider that reads results in a race
  and qualifying in qualifying. Trigger type `manual` finds nothing.
- Built Different: `AchievementAward::awardByHand()` stores it final, source `manual`,
  decided by the person (so the engine keeps it), once per car and session; refused for a
  detected achievement and, through the Phase 4 guard, on a sensitive or blocked weekend.
  A sensitivity added later suppresses it. CMS: "Award by hand" on the awards list
  (achievement, session, car, a required note); "Hand back to the engine" is hidden for it.
- Frequency on 2023 to 2026 (90 race weekends, through 2026 round 20; suppressed awards
  not counted): Nearly Famous 90 (1.00 per weekend, Bronze), Economy Class 54 (0.60,
  Silver; 44 of them in Grand Prix qualifying), Peaked Too Soon 15 (0.17, Gold), Led Astray
  12 (0.13, Gold; none in 2023, when the top lap leader won every race), Double Detention 6
  (0.07, Gold), Fool's Gold 9 (0.10, Gold), Sibling Rivalry 48 (0.53, Silver by the rule),
  First Time for Everything 20 (0.22, Gold), Charging Rhino 47 (0.52, Silver), Zoomies 110
  (1.22, Bronze; 15 of 90 races share the top reading). The rule agrees with the concept
  doc's proposals except Sibling Rivalry (rule Silver, doc Gold); the catalogue keeps the
  doc's Gold until the owner decides. Built Different has no frequency; Platinum is a
  placeholder (the doc gives no tier).
- Golden master: 555 differences, all intended. Added: the ten detected achievements (413).
  Knock-on: It Was Like That When I Got There 47 missing, 13 added, 1 changed (incidents with
  a teammate are Sibling Rivalry now; merged ×2 awards split again); Nothing to See Here 44
  missing (drivers with a new award); Perfect Ten 5 and Drive-Thru 1 missing (absorbed by
  Double Detention); Most Collected 21 added, 3 changed; Rarest Award 3 evidence changes
  (same holders); OOFscore Champion: 2023 Piastri 715 to 810, 2024 Magnussen 742.5 to
  Hamilton 815, 2025 Antonelli 1,180 to 1,300. Re-exported: 4,651 awards in 453 contexts.
- Stored awards on the local database were not brought up to date (only dry runs).

Decided by the owner on 18 November 2026, done the same day:

1. Sibling Rivalry is Silver, by the frequency rule.
2. Built Different keeps Platinum.
3. Fool's Gold is in the surprise score category; Peaked Too Soon and Led Astray stay
   withheld on sensitive weekends.
4. Economy Class keeps sprint qualifying but leaves out Q1 and SQ1 exits without a lap
   time: a crash or a failure is not the joke.
5. Nearly Famous stays Grand Prix only.
6. First Time for Everything and Did the Thing both stand on a first win.
7. One event, one achievement: a teammate penalised for the incident gets the offence
   (Bumper Cars) only; the other keeps Sibling Rivalry. Built as: every achievement that
   supersedes It Was Like That When I Got There (the 13 offences, Sunday Driver and the
   four penalties) also supersedes Sibling Rivalry. Confirmed by the owner the same day:
   any offence or penalty on the incident replaces Sibling Rivalry for that car, not
   only Bumper Cars.
8. Peaked Too Soon and Led Astray may both stand on one result.

- 1, 4 and 7 in the catalogue and, for existing rows, by migration (tier only while still
  Gold, the Economy Class description only while it equals the seeded text, Sibling
  Rivalry added to the supersedes lists that hold It Was Like That When I Got There).
  Rebuild the OOFscore of open seasons after deploying (`oof:oofscore`).
- Frequency now: Economy Class 50 (0.56 per weekend; 40 qualifying, 10 sprint
  qualifying), Sibling Rivalry 46 (0.51).
- Golden master: 14 differences, all intended. Economy Class 4 missing (Q1 exits without a
  time: Pérez 2023 R3, Tsunoda 2025 R7, Hadjar 2026 R4 and R17); Sibling Rivalry 3
  missing and 1 added (Ocon 2024 R8, Sainz 2026 R12 and Colapinto 2026 R15 had Bumper Cars
  for the incident; Colapinto's other incident with Gasly stays, now as a single award);
  Most Collected 4 changed (Tsunoda Economy Class ×10 to ×9, evidence of three others);
  OOFscore Champion 2023 Piastri 810 to 785, 2025 evidence only (Antonelli 1,300); 2024
  Hamilton 815 unchanged. Re-exported: 4,645 awards in 453 contexts.

**Phase 11a: historic data. Done.**

Data preparation only: no achievements were run for 1950 to 2022 and `first_season` is
still 2023.

- Lap times from Jolpica's free CSV database dump instead of the paced API (decision 9
  stands, but the dump takes seconds instead of about 14 hours). `oof:import:dump`
  fetches the free dump (14 days behind the paid one), checks its SHA-256, keeps it in
  `storage/app/private/jolpica/dumps` (not in git) and writes every race lap onto the
  driver's race entry, one race per transaction, safe to re-run. Sprint, qualifying and
  practice laps are left out, like the API's laps endpoint. `--file=` imports a dump on
  disk, `--season=` limits it. The API import (`oof:import --only-laps`) stays as the
  fallback and for races newer than the dump.
- Local database: the dump of 2 November 2026 gave 637,858 laps in 587 races (1996 to
  2026 round 19, every race, nothing unmatched); 2026 round 20 (Brazil) came from the API.
  The API's laps of all 20 rounds of 2026 (249 requests, about 30 minutes) match the
  dump's on every lap of rounds 1 to 19 (22,104 laps, no difference in position or time).
  In all: 639,293 laps in 588 races. Lap responses are normalized only with
  `oof:normalize --laps`, so the hourly run never writes them.
- Laps led, Jolpica against OpenF1 on the 89 races of 2023 to 2026 round 19, both read as
  "P1 at the end of a lap, the last lap not counted": the top lap leader is the same in all
  89 (so Led Astray would not change) and led every lap agrees in 85. 65 races match lap
  for lap; in most of the other 24 OpenF1 gives the leader one lap more, because its laps
  run past the flag, and 2025 round 6 has a gap in OpenF1's laps. The engine keeps OpenF1
  from 2023; Jolpica laps are for the seasons before.
- Team lineage (decisions D3 to D6 below): 36 chassis-engine constructor IDs join their
  chassis maker (Team Lotus, Cooper, Brabham, McLaren, March, Eagle, BRM, Shadow, LDS, De
  Tomaso, Matra), teams listed under another ID join their lasting team, and Alfa Romeo
  and ATS are split by seasons (`TeamLineageSeeder`: `CHASSIS_ENGINE_RULES`,
  `SAME_TEAM_RULES`, `SPLIT_RULES`; written out in the migration). A rule can fix the
  lasting team's name (`team_lineages.team_name`, CMS "Fixed team name"), which wins over
  the name of its latest season, in the sync and on import: Cooper (not
  "Cooper-Maserati"), Eagle, Embassy Hill, Scuderia Italia, Alfa Special. Local database:
  teams 180 to 145, rules 50 to 116; rollback and re-apply give the same teams.
- `oof:dig` run after the merge: surprise winners 65 to 97 (57 kept, 40 added, 8 dropped).
  Phase 2 had estimated 108 under the version 3 rule with the teams still split; the
  merge takes 11 of them away (for example Clark's 1967 Dutch win in the first Lotus-Ford:
  Team Lotus now has its Lotus-BRM and Lotus-Climax history). Every other excavator is
  unchanged but one start milestone (2026 round 20, new data). No candidate had been
  reviewed.
- Golden master 2023 to 2026: no differences after each step; not re-exported.

Decided by the owner on 19 November 2026:

- D1. Lap times from the free dump, 2023 to 2026 included for the cross-check; newer races
  from the API; the API importer stays as the fallback.
- D2. `first_season` stays 2023 in 11a. Lifting it is the switch for the backfill: the
  hourly `--changed --stale` would start 1950 to 2022 on its own, and the catalogue
  version changes. 11b first builds the "OOF counts from 2023" rule (OOF and Safety Car
  awards before 2023 score 0 and stay out of the OOFscore, the Oofies, Close season and
  the stamp books; glory counts from 1950), then lifts `first_season` and runs the
  backfill by hand, season by season.
- D3. Matra-Ford (Ken Tyrrell's Matra team, 1968 and 1969) merges into Matra, as in the
  record books, not into Tyrrell. Neither choice changes a surprise award.
- D4. Frank Williams' first team (Politoys 1972, Iso-Marlboro 1973 and 1974, Williams 1975
  and 1976) merges into Wolf, which bought it in 1976; today's Williams starts in 1977.
- D5. Seasons in a chassis no other team used that year merge into the team: Embassy
  Hill's Lola (1974 and 1975), RAM's March (1981 and 1982), Larrousse's Lola (1991) and
  Scuderia Italia's Lola (1993, after its Dallara seasons). Also routine: Simca (1950 to
  1953) into Gordini; Alfa Romeo split into the 1950 and 1951 works team, de Klerk's Alfa
  Special (1963, 1965) and the 1979 to 1985 works team; ATS split into the Italian team
  of 1963 and the German team of 1978 to 1984.
- D6. An optional fixed name per lineage rule (built).
- D12. `oof:dig` stays in the daily schedule (04:50); the lineage migration must run before
  the first dig after the deploy (deploy step 1).

Decided for Phase 11b (not built in 11a, which is data only):

- D7. Indianapolis 500 (1950 to 1960): results and standings stay as Jolpica has them.
  Only the glory achievements that restate the official record (Did the Thing, Silver
  Lining, Solid Bronze, First Time for Everything); no OOF achievements, no surprise and no
  comparative glory such as Charging Rhino. The excavators keep leaving it out. 103
  drivers raced only there; 6 of its 11 rounds are seeded sensitive (fatal).
- D8. Shared drives follow the official record: every driver credited with a shared car
  gets its glory (Fagioli's only win, 1951 France, shared with Fangio). A driver who drove
  two cars in one race (40 races up to 1964) is judged on his best car; the car he left
  gets no retirement award. 125 shared cars in 60 races, nearly all up to 1960; later
  "shared" car numbers are mostly a withdrawn entry whose number went to a reserve.
- D9. No Forgot the Keys for withdrawn entries (`W`) before 2023 (347 of them): without
  practice data a car that never arrived cannot be told from one that failed on the grid.
- D10. Where a round has no qualifying data (every round before 1994 and most of 1996 to
  2002), grid 1 of the race is the pole, so Plot Twist and the first pole of First Time
  for Everything reach back to 1950. Today they run only on qualifying sessions.
- D11. Nearly Famous before 2010: the first place outside the points of that era (P6 in
  the 1950s, P7 to 2002, P9 to 2009), not P11.
- Also for 11b: Led Astray from 1996 and Clean Sweep from 2004 on Jolpica laps; the
  curated Clean Sweeps before that; the This week in OOF history query. Done when a
  second full backfill changes nothing and any calendar week returns awards with the
  sensitive ones flagged.

**Phase 11b: historic backfill. Done.**

Decided by the owner on 19 November 2026 (step 1 report): Q1 to Q7 below, with D2 and
D7 to D11 of Phase 11a; Spain 1977 (Lauda) and Brazil 1998 (Herbert), whose injury
statuses already give those drivers nothing, stay without a sensitivity.

- Q1, laps led: Jolpica's lap times are the source of truth, from 1996 and for every
  race since 2023, counted the standard way (P1 at the end of a lap, the last lap
  included; `LapsLed`). OpenF1 stands in until a race's Jolpica laps are in, the
  winner's last lap counted. An achievement that reads Jolpica laps (Led Astray, Clean
  Sweep) waits with the review reason "Waiting for lap times" (`Finality`, from 1996)
  and becomes final on the next `--changed` after they arrive (`lap_times` is a change
  source). The hourly import fetches the current season's race laps
  (`oof:import --recent-laps`) and the hourly normalize writes them
  (`oof:normalize --laps`). Led Astray from 1996; Clean Sweep from 1996 through
  `fastest_lap_and_every_lap_led` (fastest-lap rank from 2004, before that the fastest
  Jolpica lap). On 2023 to 2026 Jolpica and OpenF1 give the same 9 Clean Sweeps and the
  same Led Astray but one: Leclerc in 2026 round 19 (Mexico), whom OpenF1's old rule
  tied with Verstappen; Verstappen led 28 laps, not 27. That award is gone. Miami 2025
  has a gap in OpenF1's laps; Jolpica is right and no award differs.
- D2, OOF counts from 2023: `achievements.shelf_from` (OOF, Safety Car and Oofies 2023,
  glory 1950). An award of a season before its shelf counts earns 0 points, also after
  a tier change or a reopen; no OOFscore standings, Close season or Oofies before 2023.
  `first_season` is 1950. `live_from` (2023): the hourly `--changed --stale` never
  starts an earlier season by itself; a season run by hand is kept up to date by both.
  Nickname breakthroughs from `live_from`. `first_season`, `shelf_from` and `live_from`
  are not part of the catalogue version.
- Q6: `AchievementAward::countingOnTheirShelf()`, and `publishable()` requires it (see
  the hard requirement above).
- D7: the Indianapolis 500 gives only Did the Thing, Silver Lining, Solid Bronze and
  First Time for Everything (`achievements.indianapolis_500_achievements`).
- D8: a driver in more than one car in a race is judged on the best one; the car left
  gets nothing (`SessionData::setAsideEntryIds()`). Both drivers of a shared car keep its
  result.
- D9 and Q2: before 2023 an entry that only "Withdrew" takes part in nothing; a
  withdrawal whose status names a failure is a non-start (Forgot the Keys)
  (`achievements.plain_withdrawals_from`). Q3: a classified car is never a non-start,
  whatever its status (Button 2013, Magnussen 2020, one in 1996).
- D10: without qualifying data the grid is the qualifying order, grid 1 the pole
  (fact `qualifying_order`): Plot Twist runs in races too and finds a pole there only
  when the round has no qualifying data; First Time for Everything gives a first pole
  from the grid (with a first win the same day: one award, "×2 · First win · First
  pole").
- D11: Nearly Famous is the first place outside the points of each era with no points
  (`achievements.first_place_outside_the_points`: P6 to 1959, P7 to 2002, P9 to 2009,
  P11 since 2010); description by migration where it still equals the seeded text.
- Q4: To the Rescue from 1981.
- Q5: `config/curated_clean_sweeps.php` holds the 44 published Grand Chelems of 1950 to
  1995 (Wikipedia, "Grand Slam (Formula One)"), each checked against our data (the
  driver won from pole: all 44 match). Nothing before 1996 is awarded while `approved`
  is false; after the owner's review, set it to true and run 1950 to 1995 again.
- This week in OOF history: `ThisWeekInOofHistory` (final awards of every year and shelf
  in a calendar week, wrapping at New Year, each with its sensitive flag; historic
  moments of the week, flagged when their round is sensitive) and `oof:history {date?}`.
- `engine_version` 3. Golden master 2023 to 2026: only Leclerc's Led Astray differs (step
  Q1); re-exported, 4,644 awards. No differences after the later steps and after the
  backfill. Re-exported after the backfill so it covers 1950 to 2026: 30,639 awards in
  2,059 contexts.
- Backfill on the local database, by hand, oldest first, one season per command
  (`oof:achievements --season=YEAR`), after `oof:achievements:sync` (8 fields on 5
  achievements: Plot Twist's sessions, and the data needs and first years of Led Astray,
  Clean Sweep, Plot Twist, To the Rescue and Built Different). Per decade, checked: 0
  OOF points, no OOFscore rows, no Oofies, no season closed, one win award for each
  credited winner in every race, nothing withheld stored on a sensitive weekend and
  every award there flagged (OOF suppressed), nothing but the four glory achievements at
  Indianapolis, no Forgot the Keys on a plain withdrawal or a classified car, no driver
  with awards on two cars in one race, no To the Rescue before 1981, no Clean Sweep
  before 1996, nothing pending; a second run of each season created, updated and
  removed nothing.

  | Decade | Races | Awards | Glory final | OOF final | Suppressed | Run |
  |---|---|---|---|---|---|---|
  | 1950s | 84 | 1,945 | 522 | 1,359 | 64 | 14 s |
  | 1960s | 100 | 2,174 | 505 | 1,584 | 85 | 16 s |
  | 1970s | 144 | 3,840 | 935 | 2,758 | 147 | 23 s |
  | 1980s | 156 | 4,713 | 1,107 | 3,451 | 155 | 28 s |
  | 1990s | 162 | 4,461 | 984 | 3,385 | 92 | 30 s |
  | 2000s | 174 | 3,724 | 802 | 2,827 | 95 | 34 s |
  | 2010s | 198 | 4,028 | 807 | 3,123 | 98 | 41 s |
  | 2020 to 2022 | 61 | 1,110 | 262 | 816 | 32 | 14 s |
  | All | 1,079 | 25,995 | 5,924 | 19,303 | 768 | 3.3 min |

  1,270 awards are flagged sensitive; the suppressed ones are OOF on sensitive weekends
  and To the Rescue seat swaps (both wait for a person, and before 2027 nobody reviews).
  Clean Sweeps from the lap data: 22 for 1996 to 2022, the 21 published ones plus
  Verstappen at Spa 2021 (the race run only behind the safety car); see the open points.
- The hourly `--changed --stale` after the backfill picks up no historic session (it
  picked the 450 sessions of 2023 to 2026 locally, whose stored awards predate the
  branch; deploy step 5 brings them up to date).
- This week in OOF history on the local data: 50 of the 53 weeks of 2026 return awards
  (for example 7 to 13 September: 1,433 awards in 57 seasons, 25 flagged). 16 to 22
  February and 14 to 27 December never had a championship race.

Decided by the owner on 19 November 2026, after the report:

- Spa 2021: the owner suppresses Verstappen's Clean Sweep by hand in the CMS (the race
  was run only behind the safety car; most records do not count it). The engine keeps a
  person's decision, so it stays suppressed.
- Weeks without any race: This week in OOF history falls back to the nearest week with
  awards or a historic moment, the earlier one on a tie (`ThisWeekInOofHistory::weekOf()`,
  `is_fallback`; `oof:history` says so). 16 to 22 February shows 9 to 15 February, 14 to
  20 December shows 7 to 13 December, 21 to 27 December shows 28 December to 3 January.

- Curated Clean Sweeps: reviewed and approved by the owner (19 November 2026);
  `approved` is true, and the list is part of the catalogue version, so a later edit
  makes `--stale` re-run. 1950 to 1995 re-run season by season on 20 November 2026: 44
  Clean Sweeps created and 40 Did the Thing removed, nothing else (golden master over
  1950 to 2026: exactly those 84 differences, none changed). In 4 of the 44 races the
  win was already a Giant Slayer (a team outside the top four on form), which also
  supersedes Did the Thing and stands beside the Clean Sweep: Siffert 1971 Austria,
  Ickx 1972 Germany, Piquet 1980 Long Beach, Berger 1987 Australia. No such pair after
  1995.
- Decided by the owner on 20 November 2026: Clean Sweep and Giant Slayer both stand on
  the same win; they tell different stories (how he won versus who won). The win check
  is now: Did the Thing never stands beside another win award (Clean Sweep, Giant
  Slayer, Took the Scenic Route), and every race has at least one win award;
  combinations of those three are allowed. Result: Did the Thing never beside another,
  10 winners with a combination, and every race of 1950 to 2026 has a win award in the
  golden master. Locally the stored awards of 2026 round 20 (Brazil) are empty, because
  the stored 2023 to 2026 awards predate the branch (deploy step 5). Golden master
  re-exported over 1950 to 2026.

**Phase 12: new achievements, season Oofies, stamp framing.**

Planned on 21 November 2026 (step 1 report, approved by the owner the same day). New
race-weekend achievements whose art is final, fourteen season awards, and a framing
step for the art. The plan as approved, with the owner's decisions:

- Race weekend, glory (no points). Tiers:
  - Early Bird: pole for the Grand Prix, `qualifying_order` P1 as Plot Twist; Gold.
  - Early Chick: sprint qualifying P1, from 2023 only (2021 and 2022 had no sprint
    qualifying of their own); Silver.
  - Fun Size Win, Silver and Bronze: sprint P1 to P3, from 2021; Gold, Silver and Bronze.
    Files with a `--label` suffix are variants, not achievements.
  - Purple Patch: Grand Prix fastest lap (`set_fastest_lap`, 1996) with points; Bronze.
    All Dressed Up, Nowhere to Go moves to the same predicate, so the two are exact
    complements. Clean Sweep supersedes Purple Patch.
  - Encore: won the sprint and the Grand Prix of one weekend; Gold.
  - In Tandem: a 1-2 finish (distinct positions), recipient the team; Silver.
  - Hometown Hero: won at home, the driver's nationality mapped to the circuit's country
    through a new editable table; Gold. It supersedes Did the Thing.
  - Plot Twist supersedes Early Bird.
  - Did the Thing, Silver Lining and Solid Bronze drop the sprint (decided): a sprint
    podium is only a Fun Size award.
- Race weekend, OOF:
  - Itchy Feet: the false start, renamed from Early Bird (12a).
  - Bubble Burst: retired while leading (P1 at the end of his last completed lap or the
    one before it, at least one lap completed). Grand Prix only, from 1996 on Jolpica
    laps, waits for lap times. Supersedes Home Before Dinner. Gold (decided: losing a win
    to a breakdown should not also earn the heaviest OOF points), reliability, withheld
    on sensitive weekends.
  - Dropped the Baton: the team held P1 and P2 at the end of one of the 5 laps before the
    leader's last and not at the end of the last lap, judged on the road from the lap
    positions (decided), so post-race penalties and disqualifications do not count.
    Recipient the team, Grand Prix only, from 1996; Platinum (its points never reach the
    OOFscore, which reads driver awards), withheld on sensitive weekends.
- The Oofies (decided): all fourteen on the `oofies` shelf with a new `side` (glory or
  oof) for the art folder and theme; glory side from the data year, OOF side from 2023.
  - Glory: Creature of Habit, Morning Person, Room with a View, Follow the Leader (1996),
    Stampede, Fastest Food (team), Perfect Attendance, Like a Metronome.
  - OOF: Homebody, Usual Suspect, Backpedal, Slow Food (team), Best in Show, Always the
    Bridesmaid.
  - Grands Prix only, except Usual Suspect (race, sprint and both qualifyings) and the
    Food awards (race and sprint).
  - Ties are shared, at least 1. Always the Bridesmaid follows the championship order.
    Best in Show (decided): the number of races finished lapped, with total laps down as
    the tie-break.
  - Fastest Food and Slow Food (decided): OpenF1 stationary time through
    `PitStops::countable`, leaving out stops the car never rejoined from and values under
    1.5 s. Awarded only in a season where every race has stationary times for at least 80%
    of its pit visits; on the local data only 2025 passes.
  - Places gained and lost: positive per race, classified finishers only, summed.
  - Homebody leaves out retirements on sensitive rounds.
- These are the first team achievements that ship (decision 3 is replaced). A driver
  block on a round also holds back that driver's team awards (the Phase 8 requirement).
- Stamp framing (decided): a framing service, a CMS preview and a preview command only.
  Each image is trimmed to its visible content and scaled to sit centred in the 4:5
  window with an even empty margin of about 15 percent, at render time, on white. The
  source files are never touched. Backgrounds are not made transparent yet. **When that
  comes, it must use a flood fill from the image edges, not a global near-white threshold,
  so cream and white parts inside characters survive.**

**Phase 12a: Early Bird to Itchy Feet. Done.**

- Migration `rename_early_bird_to_itchy_feet`: the row with key `early_bird` on the OOF
  shelf takes key `itchy_feet`, and the name "Itchy Feet" where it was still "Early Bird"
  (a CMS name is kept). Awards point to the row, so every award and every decision on it
  moves with it. Every stored `supersedes` list that names the old key is rewritten. An
  `early_bird` on the glory shelf is left alone. An empty `itchy_feet` row (a sync run
  before the migration) is replaced; one that holds awards stops the migration.
  Rollback puts key and name back.
- Catalogue entry and the detector test renamed. Old migrations are unchanged.
- Order matters on deploy: migrate before sync (deploy steps 1 and 2), and the pole Early
  Bird (12c) lands only after this rename.
- Local database: the 14 false-start awards (none decided by a person) are now Itchy
  Feet; the sync dry run shows no drift.
- Golden master: before the change one difference, not from this phase: Verstappen's
  Clean Sweep at Spa 2021 is suppressed (the owner's decision of Phase 11b, made after the
  last export). After the change: 14 `early_bird` missing, 14 `itchy_feet` added,
  identical in every field once the key is mapped, and nothing else. Re-exported, 30,643
  awards in 2,063 contexts.

**Phase 12b: engine extensions. Done.**

No new achievement: only what 12c to 12e need, each proven by a feature test with an
achievement that exists only in the test.

- Count trigger:
  - `per: driver` counts a driver's facts across the sessions a weekend entry reads
    (Encore). The award goes on the car of the last session.
  - `distinct: <attribute>` counts different values, so a shared car of the 1950s is one
    place (In Tandem).
  - Other groups still stand for their first fact, so existing instance keys are
    unchanged.
- Sensitivity: a driver block also holds back the team awards of that driver's team in
  the round (`blocked_season_teams`, the Phase 8 requirement). The rule, the engine's
  store step, the release after a dismissal and the save guard all read it
  (`Sensitivity::allowsAward()`, `blocks()`). The teammate's own awards stay.
- Aggregate trigger:
  - `group: team` with a team recipient; the factory now allows a team aggregate. A team
    award must be grouped by team, and a team group must go to a team.
  - `reduce` (sum, min or max) for an attribute measure.
  - `tie_break`: an attribute summed per group that decides between groups level on the
    measure (Best in Show).
  - `in_every: race` (Perfect Attendance, Like a Metronome).
  - `skip_sensitive_rounds` (Homebody).
  - `coverage: [in, at_least]`: nothing unless every session of that type is measured
    well enough. Read from a provider that implements `MeasuresCoverage`; pit stops give
    the share of visits with a stationary time (decision 3: Fastest Food and Slow Food).
  - Source `championship`: the final drivers' standings with position, points, wins and
    Grand Prix podiums (`SeasonData::championship()`; the Indianapolis 500 podiums count,
    as in the record) (Always the Bridesmaid).
  - The facts source now leaves the Indianapolis 500 out of every season count.
  - The fact-only options are refused on other sources.
- Pit stop facts: attribute `rejoined` (the car completed a lap after the stop's lap),
  and coverage.
- Oofie side: `achievements.side` (migration; the three Oofies so far are `oof`), a
  mechanic written by the sync and part of the catalogue version, shown read-only in the
  CMS.
  - `Achievement::firstSeason()`: a glory-side Oofie counts like glory (1950), an
    OOF-side one like the Oofies (2023).
  - `countingOnTheirShelf()` follows the side.
  - `Requirements` decides an Oofie no earlier than its side counts, whatever its data
    allows.
  - A test checks that every Oofie names its side and nothing else has one.
- `nationality_countries` (nationality, country, note): created and filled by its
  migration with all 40 nationalities in the data (the step 1 report said 41: a
  miscount). Countries without a circuit keep their own name. East German maps to
  Germany; Rhodesian to Rhodesia, with a note.
  - CMS screen Nationalities (list, add, edit, delete).
  - Its rows are part of the catalogue version.
- Predicates:
  - `at_home`: the driver's nationality is at home in the circuit's country.
  - `retired_while_leading`: retired after at least one lap, P1 at the end of the last
    completed lap or the one before. From 1996; it reads Jolpica laps, so it waits for
    them.
- `Support/RunningOrder`: position per car at the end of each lap, from Jolpica's lap
  times, OpenF1 standing in.
- Local database: migrated; the sync dry run shows no drift. The catalogue version
  changed (side, nationality table), so the next `--stale` re-runs every context; it
  should find the same awards.
- Golden master: no differences (30,643 awards); not re-exported.
- Deploy: the two migrations go with step 1; nothing else.

**Phase 12c: race-weekend glory. Done.**

- Nine catalogue entries, glory shelf, as approved:
  - Fun Size Win, Silver and Bronze (sprint P1 to P3; Gold, Silver, Bronze).
  - Early Bird (Grand Prix pole from `qualifying_order`, event `pole:{entry}`; Gold).
  - Early Chick (sprint qualifying P1; Silver; sprint qualifying exists only from 2023).
  - Purple Patch (`set_fastest_lap` with points, not disqualified; Bronze).
  - Encore (weekend scope after the race, count per driver of sprint and Grand Prix
    wins; Gold).
  - In Tandem (team, count of distinct positions 1 and 2; Silver).
  - Hometown Hero (Grand Prix win and `at_home`; Gold; supersedes Did the Thing).
- Did the Thing, Silver Lining and Solid Bronze: Grands Prix only (sessions written by
  the sync). Their descriptions say "Grand Prix" where they still equal the seeded text.
- All Dressed Up, Nowhere to Go on `set_fastest_lap`, so from 1996.
- Migration `apply_phase_12c_to_achievements`: Plot Twist supersedes Early Bird, Clean
  Sweep supersedes Purple Patch, plus the podium descriptions. It refuses to run while the
  false-start row still has the key `early_bird`. `CatalogueSync` refuses the same through
  `FORMER_KEYS`, so a sync without the 12a rename cannot turn false-start awards into pole
  awards.
- Fixed on the way (found by the golden master): the lap-time wait (`Finality`) now applies
  to Grands Prix only. Jolpica has no sprint laps, so All Dressed Up in a sprint (it now
  reads lap times through `set_fastest_lap`) waited forever: 2025 round 23 sprint went
  from final to suppressed.
- Golden master: 2,277 differences, all intended or explained below; none changed.

  | Achievement | Added | Missing | Why |
  |---|---|---|---|
  | Early Bird | 1,070 | | Every pole that is not a Plot Twist (Plot Twist 87) |
  | Early Chick | 24 | | Every sprint qualifying, 2023 to 2026 |
  | Fun Size Win, Silver, Bronze | 30 each | | Every sprint, 2021 to 2026 |
  | Did the Thing | | 101 | 30 sprint wins, 71 home wins (84 home wins, 13 of them already another win award) |
  | Silver Lining, Solid Bronze | | 30 each | The sprint podium |
  | Encore | 11 | | 11 of 30 sprint weekends |
  | In Tandem | 321 | | Every one-two outside the Indianapolis 500 (SQL: 321) |
  | Hometown Hero | 84 | | Every home win outside the Indianapolis 500 (SQL: 84) |
  | Purple Patch | 491 | | Fastest lap in the points, 1996 to 2026, without Clean Sweeps |
  | All Dressed Up | 22 | | 1996 to 2003, from the lap times |
  | Nothing to See Here | | 3 | Pérez 2023 R8, Russell 2024 R7, Norris 2024 R22: their only award that weekend is now Purple Patch |

  Checked in the new master: Did the Thing never beside another win award, Purple Patch
  never beside Clean Sweep, Early Bird never beside Plot Twist, no Did the Thing, Silver
  Lining or Solid Bronze in a sprint. Re-exported.
- Not predicted by the plan, left as is for the owner:
  - Nothing to See Here gives way to a glory award, as it already did to a win (the 3
    above).
  - Seven rounds of 1950 to 1960 have no pole award: the pole sitter later took over
    another car, and D8 (judged on the best car; the car left gets nothing) sets the pole
    car aside. Plot Twist works the same way. (Fixed afterwards; see "Official poles"
    below.)
  - Five rounds have more than one: Jolpica credits grid 1 to several drivers of a shared
    car (1952 R8, 1953 R7 and R8, 1955 R1 three, 1964 R9). (Fixed afterwards.)
- Local database: migrated and synced (9 created; podium sessions; All Dressed Up's data
  needs). Stored awards not brought up to date (dry runs only).

Decided by the owner on 21 November 2026, after the report: Nothing to See Here stands as
it is. The pole follows the official record.

**Official poles. Done.**

- The pole is earned by one driver in qualifying, so a car swap in the race does not take
  it away. A shared car gets exactly one pole, for the driver the record credits.
- `SessionData::setAsideEntryIds()` is now the union of `leftCarEntryIds()` (D8) and
  `withdrawnEntryIds()` (D9). A fact provider that implements `KeepsLeftCars` (the
  qualifying order) loses only the withdrawals, both in `Facts` and in the engine's
  set-aside filter. Every other achievement still ignores a left car (no retirement
  award for it).
- `QualifyingOrderFacts::gridPoleEntryId()`: without qualifying data, the one car
  credited with pole.
  - When one driver is on grid 1, that driver's car on grid 1 (the car they started in,
    even if they left it).
  - When several drivers are, the driver named in `config/official_poles.php` (season,
    round, Jolpica driver; part of the catalogue version). An unlisted tie gives no pole.
  - The other drivers on grid 1 get no qualifying order, so Early Bird and Plot Twist
    both follow it.
- The list holds the 9 rounds of the local data with several drivers on grid 1: 1951 R4
  (Fangio), 1952 R8 (Ascari; Moss also on grid 1), 1953 R7 (Ascari), 1953 R8 (Fangio),
  1955 R1 (González), 1956 R2 and R8 (Fangio; his pole car later driven by Castellotti),
  1957 R5 (Moss), 1964 R9 (Clark). The pole sitters are the published ones (my knowledge
  of the race records, not checked against a source in this session). Confirmed by the
  owner against the official record (formula1.com qualifying results and race reports)
  on 22 November 2026.
- `took_pole` (Clean Sweep, Peaked Too Soon) and the first pole of First Time for
  Everything have their own pole logic and are unchanged. (Changed afterwards: see "One
  definition of pole" below.)
- Golden master: 22 differences, all Early Bird; Plot Twist has none (no surprise pole in
  these rounds).
  - 9 added for the official pole sitter: Fangio 1950 R7, 1951 R4, 1953 R4, 1956 R1, R2
    and R8; Farina 1952 R1; Moss 1957 R5 and 1960 R1.
  - 8 removed from drivers who were not on pole: Moss 1952 R8, Villoresi 1953 R7, Bonetto
    1953 R8, Farina and Trintignant 1955 R1, Castellotti 1956 R2 and R8, Spence 1964 R9.
  - 5 kept with a new evidence note ("several drivers are on grid 1; the pole follows the
    official record"): Ascari 1952 R8 and 1953 R7, Fangio 1953 R8, González 1955 R1,
    Clark 1964 R9.
  - Every round of 1950 to 2026 with a race now has exactly one pole award (Early Bird or
    Plot Twist), except the 11 Indianapolis 500s. Re-exported: 32,593 awards.

**One definition of pole. Done.**

Decided by the owner on 22 November 2026: `took_pole` and First Time for Everything follow
the same official pole as Early Bird.

- `App\Achievements\Support\Poles` is the one definition: qualifying P1 where the round
  has qualifying data, otherwise the official grid pole (left cars count, plain
  withdrawals do not, `config/official_poles.php` settles several drivers on grid 1).
  - The qualifying order (Early Bird, Plot Twist) reads `Poles::gridPoleEntryId()`.
  - `took_pole` (Clean Sweep, Peaked Too Soon) compares the driver with
    `Poles::driverOf()`, so it holds on every car the pole sitter drove that day and
    on no co-driver of a shared car.
  - First Time for Everything takes its grid first pole for the pole sitter only, on
    the car they are judged on (D8), and reads earlier poles by the same rules
    (`Poles::hadPoleBefore()`, once per round date).
- A test runs every kind of round (qualifying with a grid penalty, grid only, a car swap,
  a shared car, an unlisted tie). `took_pole` holds for exactly the pole award's driver,
  and Clean Sweep and the first pole follow it.
- Golden master: 17 differences, only First Time for Everything and Peaked Too Soon.
  - First poles removed for co-drivers the record does not credit (5): Moss 1952 R8,
    Villoresi 1953 R7, Bonetto 1953 R8, Trintignant 1955 R1, Spence 1964 R9.
  - First poles moved to the real first pole (2): Moss 1955 R6, now "×2 · First win ·
    First pole" (his grid 1 at Monza 1952 was the grid error); Brooks 1958 R2 (his grid 1
    of 1957 R5 was Moss's pole in their shared car).
  - Peaked Too Soon removed for co-drivers (6): Moss 1952 R8, Villoresi 1953 R7, Bonetto
    1953 R8, Castellotti 1956 R2 and R8, Spence 1964 R9.
  - Peaked Too Soon added for pole sitters who took over another car and still missed
    the podium (3): Fangio 1950 R7 and 1953 R4, Farina 1952 R1 (OOF before 2023, so 0
    points).
  - Clean Sweep: no change. Every curated Clean Sweep already named the official pole
    sitter.
  - In the new master every Clean Sweep, Peaked Too Soon and first pole names the round's
    pole-award driver, except the first poles of the 11 Indianapolis 500s, where First
    Time for Everything restates the record (D7) and Early Bird is not given.
  - Re-exported: 32,586 awards (2026 round 21 race, run for the first time, has no
    awards yet).

**Phase 12d: Bubble Burst and Dropped the Baton. Done.**

- Bubble Burst: OOF, Gold, reliability. Grand Prix: `retired_while_leading` (Phase 12b).
  It supersedes Home Before Dinner, is withheld on sensitive weekends and waits for
  Jolpica lap times. Detail "lap 27 · Turbo".
- Dropped the Baton: OOF, Platinum, recipient team, withheld on sensitive weekends. New
  fact provider `lost_one_two` (`LostOneTwoFacts`), on `RunningOrder`: the team's two
  cars ran P1 and P2 at the end of one of the 5 laps before the leader's last lap, and
  not at the end of that lap.
  - Judged on the road, so post-race penalties and disqualifications do not count, and
    teammates swapping places still finish one-two.
  - One fact per team. It waits for lap times. Its points are snapshotted, but as a team
    award it never reaches the OOFscore.
  - Detail "1-2 on lap 49 of 50 · P1 and P3".
- Tests (`LapOofTest`, 12): Bubble Burst
  - on a car that slowed on its last lap, replacing Home Before Dinner
  - not for a leader passed before retiring
  - not for a lap-1 retirement (Early Lunch only)
  - withheld on a sensitive weekend
  - waits for lap times

  Dropped the Baton:
  - lost on the last lap, for the team
  - not when lost before the final laps
  - not for teammates swapping
  - not for a disqualification after the race
  - withheld on a sensitive weekend
  - held back by a driver block on either car
  - waits for lap times
- Golden master: 140 differences, nothing changed. 65 Bubble Bursts, each replacing a
  Home Before Dinner (65 missing), and 10 Dropped the Batons.
  - The step 1 SQL said about 63 and 14. Bubble Burst finds 2 more because `retired` also
    counts cars classified at 90% distance.
  - Dropped the Baton leaves out the 3 post-race decisions (Silverstone 2016,
    Austria 2020, Spa 2024) and Brazil 2003, a seeded sensitive weekend.

  | Era | Races | Bubble Burst | per race | Dropped the Baton | per race |
  |---|---|---|---|---|---|
  | 1996 to 1999 | 65 | 18 | 0.28 | 0 | 0 |
  | 2000s | 174 | 27 | 0.16 | 3 | 0.017 |
  | 2010s | 198 | 15 | 0.08 | 4 | 0.020 |
  | 2020 to 2026 | 151 | 5 | 0.03 | 3 | 0.020 |
  | All | 588 | 65 | 0.11 | 10 | 0.017 |

  In the scoring seasons (2023 to 2026): 1 Bubble Burst (Russell 2026 R5) and 2 Dropped
  the Batons (2023 R21, 2026 R9).
- Bubble Burst counts a lead during a pit cycle, as Jolpica records it at the end of the
  lap. Hamilton at Monza 2021 (P1 at the end of laps 24 and 25, after the cars ahead had
  pitted) and Webber at the 2003 United States Grand Prix (P1 on laps 20 and 21) come
  from such laps. Decided by the owner on 22 November 2026: they stand. "Leading" means
  the same as for laps led (Led Astray, Clean Sweep, Follow the Leader): P1 at the end of
  the lap, pit-cycle laps included.
- Re-exported: 32,596 awards.

**Phase 12e: the fourteen season Oofies. Done.**

- Fourteen catalogue entries on the Oofies shelf (no tier, 0 points), season scope,
  aggregate triggers over facts. Grands Prix only, except where noted; ties shared.
  - Glory side, each from its data year:
    - Creature of Habit (wins), Morning Person (poles, the one definition of pole),
      Room with a View (podiums): from 1950.
    - Follow the Leader (laps led): from 1996 (`laps_led` is dated 1996 in
      `ResultFacts`).
    - Stampede (positive places gained of classified finishers, summed): from 1950.
    - Fastest Food (team; races and sprints; fastest single stop of at least 1.5 s
      after which the car rejoined; coverage gate): from 2024.
    - Perfect Attendance (points in every Grand Prix): from 1950.
    - Like a Metronome (new predicate `saw_the_flag`: running at the flag and not
      disqualified in every Grand Prix, so a retirement classified at 90% breaks it):
      from 1950.
  - OOF side, from 2023:
    - Homebody (retirements, sensitive rounds left out).
    - Usual Suspect (penalties in races, sprints and both qualifyings).
    - Backpedal (positive places lost of classified finishers, summed).
    - Slow Food (team; slowest single stop on the same rules as Fastest Food).
    - Best in Show (races finished lapped, laps down as the tie-break).
    - Always the Bridesmaid (best championship position with points and no Grand Prix
      podium).
- Engine: aggregate option `value_format: seconds` ("2.1 s" in the Food details).
- Tests (`SeasonOofiesTest`, 14): one tiny season per award. They cover:
  - ties shared and minimums
  - every-Grand-Prix rules with a missed round
  - the coverage gate and the rejoined rule
  - Always the Bridesmaid by the standings
  - Homebody without a sensitive round
  - the OOF side absent in 2022 and the glory side present in 1990
- Golden master: 429 differences, all added in season contexts, nothing missing or
  changed elsewhere. Seasons awarded:

  | Oofie | Seasons |
  |---|---|
  | Creature of Habit, Morning Person, Room with a View, Stampede | 76 (1950 to 2025) |
  | Follow the Leader | 30 (1996 to 2025) |
  | Like a Metronome | 16 (first 1953) |
  | Perfect Attendance | 6 (first 1954) |
  | the six OOF-side ones | 3 (2023 to 2025) |
  | Fastest Food, Slow Food | 0 |

  2026 is not final yet.
- Fastest Food and Slow Food give nothing on the local data. The coverage gate wants
  every race at 80% of pit visits measured. 2025 is fully measured but for Australia
  (34 of 82 visits) and Canada (33 of 82); 2024 and 2026 are far below. (Changed
  afterwards; see "Food coverage per race" below.)
- Re-exported: 33,025 awards.

**Food coverage per race. Done.**

Decided by the owner on 22 November 2026: keep the 80% bar, but per race.

- A race with less than 80% of its pit visits timed is left out of Fastest Food and
  Slow Food entirely, for every team.
- A season is awarded when at least 90% of its races pass.
- The detail says when races were left out ("2.1 s · 2 races without full timing
  data"), and the evidence note names them.

Implementation:
- The aggregate `coverage` option takes `season_at_least` and `detail`.
- The left-out sessions are removed from the season's sessions before anything is
  counted (`AggregateTrigger::isCovered()`, `seasonSessions()`).
- Descriptions by migration `update_food_award_descriptions`, only where still seeded.
- Tests: a poorly timed race with the season's quickest and slowest stops is left out
  for every team, with the detail and the note. Two of ten races poorly timed (80%) give
  nothing.
- Golden master: 2 added, nothing else.
  - 2025 Fastest Food: McLaren, 1.8 s (Piastri, Qatar R23, lap 42).
  - 2025 Slow Food: Haas, 29.0 s (Bearman, Emilia Romagna R7, lap 31).
  - Both "· 2 races without full timing data" (Australia and Canada).
  - 2024 stays without (6 of 24 races timed). 2023 is before the timing data (the Food
    awards start in 2024). 2026 is not final yet.

**Phase 12f: stamp framing. Done.**

- `App\Achievements\Stamps\StampArt` (GD, no new dependency), settings in
  `config/stamps.php`, kept out of `config/achievements.php` so the catalogue version
  does not change.
  - Art path: `<art_path>/<folder>/<key in kebab case>[--label].png`. The folder is the
    shelf (glory, oof, safety-car) or, for an Oofie, its side.
  - Framing, at render time only: trim to the visible content (a pixel counts when any
    channel is below 245 and it is not transparent; a row or column needs 3 such pixels,
    so stray specks do not count). The content is scaled to fit the 800 x 1000 window
    (4:5) with a 15% margin, centred, on white. The limiting side gets exactly 15%; the
    other side gets more, as the aspect ratio is kept. (Changed afterwards: see "Equal
    visual weight" below.)
  - Cached under `storage/app/private/stamps/framed`, keyed by the source's hash and the
    settings. The sources are never written.
  - `missing()` lists catalogue achievements without art.
- Background removal was not part of this phase (decision 8). It came afterwards: see
  "Background removal" below.
- CMS: an Art column on the achievements list and a large framed image on the achievement
  page. Both are served by `stamps.show` (`/_nimda/stamps/{key}.png`, admins only, 404
  without art, `?variant=` for a variant).
- `oof:stamps:preview [--only=] [--out=]`: frames every art file into
  `storage/app/private/stamps/preview` and reports the catalogue achievements without
  art.
- Tests:
  - `StampArtTest` (8): tall and wide objects (15% on the limiting side, centred), a
    stray speck, a transparent source, the cache and the source left unchanged, the
    paths per shelf, side and variant, the missing-art report, the command.
  - `Admin/StampArtPreviewTest` (3): an admin gets the PNG; guests and non-admins get
    403 and missing art 404; the list column and the page show the thumbnail.
- Run on the local art: 97 files framed in about 28 seconds, the source hashes unchanged.
  - Without art: Most Collected, OOFscore Champion, Rarest Award.
  - Every art file belongs to a catalogue achievement (5 variants).
  - Contact sheets in `~/oof-contact-sheets/framed/` (outside the repo).
- Seen on the contact sheets: the art's background is not flat white near the objects
  (soft shading just above the threshold), so the trimmed rectangle shows as a faint box
  on the pure white canvas for some objects. Background removal (above) will take it
  away. Until then a lower threshold or a feathered edge could soften it; for the owner.
- Golden master: no differences.

**Equal visual weight. Done.**

Decided by the owner on 22 November 2026: wide compositions came out much smaller than
tall and square ones.

- `StampArt::scale()` now scales every trimmed object so its bounding box covers the same
  share of the window: `area_share` 0.392, which is what a square object got with the 15%
  margin (560 x 560 of 800 x 1000), so a square object is unchanged.
- No edge comes closer than `min_margin` 6% to the window; when the area target would
  break that, the margin wins. Still centred. Both are in `config/stamps.php`; `margin`
  is gone.
- Tests:
  - a square object is unchanged (15% left and right, 22% top and bottom)
  - a wide object covers the same area and gets more width than before
  - a very wide one stops at exactly 6%, with less area
  - a tall one covers the same area and stays inside the minimum margin
- On the local art (97 files): every object covers 0.366 to 0.393 of the window.
  - Two stop at the 6% margin: backpedal (0.366) and meatball-sub--submarine (0.374).
  - The wide ones the owner named now cover 0.392: creature-of-habit, fastest-food,
    room-with-a-view, homebody, itchy-feet, stampede.
  - Tall objects also grew a little (they get the same area too).
  - Contact sheets regenerated in `~/oof-contact-sheets/framed/`.

**Background removal. Done.**

Asked for by the owner on 22 November 2026. `App\Achievements\Stamps\BackgroundRemoval`,
settings under `background` in `config/stamps.php`.

- The background colour is the median of the image's edge pixels (254, 254, 254 on nearly
  all art; 253 on three). Distance: the largest channel difference from it, 0 to 255.
- Flood fill from every edge pixel, 4-connected, taking in pixels within `tolerance` 16.
  There is no global threshold, so cream and white parts inside characters survive (the
  polar bear, the bunny, bellies). Measured on the bunny: at 28 the fill eats its toes,
  and a fill limited by the step between neighbours (tolerance 90, step 4) runs through
  the whole bunny. 16 leaves every character whole on the contact sheets.
- Colour to alpha inside the flooded region: transparent up to `transparent_within` 2,
  then opacity `((d - 2) / 14) ^ opacity_curve` (2), reaching 1 at the tolerance, with the
  colour unmixed from the background. Over the old background each pixel looks as before.
  The curve turns the faint shading near the objects into a dark, nearly transparent
  shadow; a straight ramp (curve 1) left it as a dotted light fringe on navy.
- Outline: `edge_width` 2 pixels on either side of it. Each pixel that lies on the line
  between the background and the nearest solid object colour gets that colour, with the
  mixing share as alpha. Without this a dark anti-aliased disc keeps 628 light edge
  pixels; with it, none.
- Enclosed background regions (not connected to the edge): each one from
  `enclosed_min_pixels` 400 is reported with its size, box, a seed point (the pixel
  nearest the centre of its box) and its mean distance. Smaller ones are always kept.
  First version: kept unless a seed removed them. Changed by the owner the same day to a
  rule with a keep-list (see "Enclosed regions: rule and keep-list" below).
- The transparent copy and its report (JSON) are cached under
  `storage/app/private/stamps/framed/cutout`, keyed by the source, the removal settings
  and that file's seeds. The framed copy is keyed on top of that. The sources are never
  written.
- Framing: as before, from the transparent copy. The trim counts a pixel from
  `trim_min_opacity` 0.1 (it replaces `white_threshold`), so the faint box is gone. The
  window is transparent, not white.
- `oof:stamps:preview` now also prints the enclosed regions per file and any unused seeds.
- Tests: `BackgroundRemovalTest` (8):
  - a cream object, a white belly and a near-white cream patch survive
  - a soft shadow becomes partly transparent, fading out, darker than the background, and
    looks as before over the old background
  - a dark anti-aliased edge keeps partial alpha and has no light halo
  - an enclosed hole is kept and reported with a seed inside it
  - a seeded hole is removed, a stray seed is reported, and seeds apply to their own file only
  - the source is never changed, and the copies are cached
  - the trim ignores faint shading, and the window is transparent
  - the preview command reports the regions
  - `StampArtTest`: the transparent-source test now expects a transparent window.
- Run on the local art: 97 files in 2 minutes 38 seconds (about 1.5 s each, cached after),
  the source hashes unchanged.
  - Area shares unchanged: 0.366 to 0.393. The same two stop at the 6% margin.
  - 210 enclosed regions in 68 files, none removed yet (no seeds).
  - Contact sheets on dusty teal (#5F8F8C) and deep navy (#14213D) in
    `~/oof-contact-sheets/transparent/` (outside the repo).
- Seen on the contact sheets:
  - No character loses a part, and dark outlines show no halo.
  - The cores of the floor shadows are darker than the tolerance, so they stay as soft
    light ovals under most characters on a dark stamp. The fill cannot take them without
    eating light fur, because the shadow and the feet meet without an edge.
  - The enclosed regions show as white patches: gaps between legs closed by a shadow,
    holes in handles and rings, cage bars, the film frame in deleted-scene.

**Enclosed regions: rule and keep-list. Done.**

Decided by the owner on 22 November 2026.

- An enclosed region from 400 pixels is removed when its mean distance is 6 or less
  (`remove_enclosed_up_to`), and kept above that (a light surface).
- `keep_enclosed` lists regions never to remove, per art file, by a point inside each.
  A keep-list point that matches no region is reported with the reason. It is empty:
  the four first entries (early-bird, early-chick, morning-person, perfect-ten) turned
  out on the review sheets to be gaps, not white surfaces. The clock faces and the
  scorecard are cream, beyond the tolerance, so they are never enclosed regions.
- oof/deleted-scene: the blank middle film frame is removed on purpose. It is the cut-out
  scene, and the stamp background shows through it.
- The report gives each region removed or kept, and why.
- Review sheets (teal, magenta = removed, cyan = kept) in `~/oof-contact-sheets/regions/`.
- Floor-shadow ovals: first kept for now, to be judged again on the real theme
  backgrounds. Changed by the owner the same day to "remove now": see "Object masks and
  a synthetic shadow" below.

**Object masks and a synthetic shadow. Done.**

Decided by the owner on 22 November 2026: the white floor-shadow ovals and rims read as
white residue on any non-white background (line-dancing, extra-wide,
it-was-like-that-when-i-got-there, by-a-whisker), so they go now.

- `bin/stamp-masks.py`: a preprocessing step outside the Laravel runtime. rembg 2.0.61 in
  a venv at `~/.venvs/oof-rembg` (Python 3.9, CPU only), models cached in `~/.u2net`.
  One greyscale mask per art file, in
  `resources/stamp-masks/<model>/<sha1 of the source>.png` (first in
  `storage/app/private/stamps/masks/`, moved when the deploy was decided, see below).
  - The CPU provider is forced: onnxruntime's CoreML provider hangs while compiling
    birefnet-general.
- BackgroundRemoval: where a mask exists, the final opacity is the flood-fill and
  colour-to-alpha result times the mask's opacity. The mask's opacity is 0 up to 32 and
  1 from 128 (`background.mask`). The mask is part of the cache key.
- StampArt: one synthetic shadow (`shadow`, off by default): a soft ellipse behind the
  object, centred under its footprint (the visible width of its bottom 12%), 1.15 times
  that width, height 0.14 of its width, opacity 0.28, edge fading over 70% of the radius.
- Tested on 8 files (the hardest cases): bunny, polar bear, line-dancing, extra-wide,
  it-was-like-that-when-i-got-there, by-a-whisker, released-into-the-wild, itchy-feet.
  - birefnet-general (about 7 s per file on the CPU): no residue. Keeps the blue track mat
    in itchy-feet and the vase shards.
  - isnet-general-use (under 0.5 s per file): no residue either, but it drops the blue
    track mat in itchy-feet and cuts the cream face off the large vase shard.
  - Sheet: `~/oof-contact-sheets/shadow-test/shadow-test.png`.
- Approved by the owner the same day: birefnet-general, the mask multiplied into the
  flood-fill alpha, the footprint-based shadow. Masks made for all 97 art files and the
  shadow switched on (`shadow.enabled` true).
- Run on all 97 files: about 7 s per mask, every cut-out made with its mask, framed
  copies and contact sheets regenerated. No white residue left.
  - The mask cut off real parts in five files:
    - outside-the-lines: the drawing paper under the panda
    - holding-the-door: the right post of the door frame
    - charging-rhino: the dust cloud behind it
    - encore: part of the dark red back curtain
    - meatball-sub--submarine: a few small bubbles
  - Decided by the owner the same day, per file in `background.mask.files`:
    - `mask_keep`: boxes in source pixels inside which the mask removes nothing (the
      flood-fill alpha applies there).
    - `'mask' => false`: flood fill only.
    - outside-the-lines (the paper), holding-the-door (the post) and charging-rhino (the
      dust clouds) get keep boxes, approved with their small leftovers below.
      meatball-sub--submarine is left as is.
    - encore: first mask off, but that brought back its white floor-shadow rim under the
      stage. Instead the mask stays on, with keep boxes over the curtain in 20 px
      columns, from the top of the image down to the stage's top edge (+ 3 px), or to
      the lowest curtain pixel the mask removed (+ 2 px) where that is lower. Checked:
      no curtain pixel is removed and no white rim remains under the stage.
  - The boxes follow the parts' measured outlines: the lowest or rightmost pixel with
    saturation 40 or more (the parts are 43 or more, the shadows 34 or less), in 20 px
    slices.
  - Floor shadow that comes back inside the boxes, all small:
    - outside-the-lines: light teeth of 2 to 4 px along the paper's slanted right edge,
      and a thin band under its bottom edge
    - holding-the-door: a light wedge of about 20 x 40 px left of the base's bottom bevel
    - charging-rhino: thin slivers under the left puffs, and a smear of about 15 x 8 px
      at the tip of the dust. The pale puff in the clouds shows half transparent with a
      dark crescent (the flood fill takes its near-white parts as background).
  - Sheet: `~/oof-contact-sheets/shadow-test/mask-fixes.png`.
- Deploy, decided by the owner on 22 November 2026: the masks are built locally and
  committed. Production does not get the rembg setup (about 1 GB of model, Python, CPU);
  it only reads the masks.
  - The masks live in `resources/stamp-masks/birefnet-general/`, one PNG per art file,
    named by the sha1 of that file. The 97 masks take 4.35 MB.
  - After new or changed art, run `bin/stamp-masks.py` locally
    (`~/.venvs/oof-rembg/bin/python -I bin/stamp-masks.py`; it skips masks that exist)
    and commit the new masks with the art.
  - A changed art file has a new sha1, so it needs a new mask. The old mask then matches
    nothing and can be deleted.
  - Without a mask, an art file falls back to the flood fill alone, floor shadow
    included, while the synthetic shadow is still added.

**Phase 12g: backfill and report. Done.**

- `oof:achievements:sync`: 0 created, 0 fields changed (each step had synced already).
- `oof:achievements --stale` over 1950 to 2026: 1,983 sessions, 1,169 weekends and 76
  seasons in 291 seconds, OOFscore rebuilt for 2023 to 2026.
  - Found 33,027, created 3,599, updated 1, removed 727, 0 pending.
  - This brought the stored awards up to date with every Phase 12 change.
- A second `--stale`: 0 sessions, 0 weekends, 0 seasons, nothing changed.
- The stored awards now equal the golden master exactly: 33,027 (32,121 final, 906
  suppressed).
- Frequency of the new achievements: final awards per eligible Grand Prix (Indianapolis
  500 left out), sprint, sprint qualifying or season, from each one's first year,
  through 2026 round 20.

  | Achievement | Per | From | All years | 2023 to 2026 |
  |---|---|---|---|---|
  | Early Bird | Grand Prix | 1950 | 1,071 / 1,158 = 0.92 (the rest are Plot Twists) | 89 / 90 = 0.99 |
  | Early Chick | sprint qualifying | 2023 | 24 / 24 = 1.00 | 24 / 24 = 1.00 |
  | Fun Size Win, Silver, Bronze | sprint | 2021 | 30 / 30 = 1.00 each | 24 / 24 = 1.00 each |
  | Purple Patch | Grand Prix | 1996 | 491 / 588 = 0.84 | 72 / 90 = 0.80 |
  | Encore | sprint weekend | 2021 | 11 / 30 = 0.37 | 9 / 24 = 0.38 |
  | In Tandem | Grand Prix | 1950 | 321 / 1,158 = 0.28 | 23 / 90 = 0.26 |
  | Hometown Hero | Grand Prix | 1950 | 84 / 1,158 = 0.07 | 5 / 90 = 0.06 |
  | Itchy Feet | Grand Prix weekend | 2023 | 14 / 90 = 0.16 | 14 / 90 = 0.16 |
  | Bubble Burst | Grand Prix | 1996 | 65 / 588 = 0.11 | 1 / 90 = 0.01 |
  | Dropped the Baton | Grand Prix | 1996 | 10 / 588 = 0.017 | 2 / 90 = 0.022 |

  | Oofie | From | Awards / seasons decided (to 2025) | Seasons with an award | 2023 to 2025 |
  |---|---|---|---|---|
  | Creature of Habit | 1950 | 92 / 76 | 76 | 3 |
  | Morning Person | 1950 | 87 / 76 | 76 | 3 |
  | Room with a View | 1950 | 100 / 76 | 76 | 3 |
  | Follow the Leader | 1996 | 30 / 30 | 30 | 3 |
  | Stampede | 1950 | 79 / 76 | 76 | 3 |
  | Fastest Food | 2024 | 1 / 2 | 1 (2025) | 1 |
  | Perfect Attendance | 1950 | 6 / 76 | 6 | 1 |
  | Like a Metronome | 1950 | 17 / 76 | 16 | 4 |
  | Homebody | 2023 | 6 / 3 | 3 | 6 |
  | Usual Suspect | 2023 | 3 / 3 | 3 | 3 |
  | Backpedal | 2023 | 3 / 3 | 3 | 3 |
  | Slow Food | 2024 | 1 / 2 | 1 (2025) | 1 |
  | Best in Show | 2023 | 3 / 3 | 3 | 3 |
  | Always the Bridesmaid | 2023 | 3 / 3 | 3 | 3 |

  Awards above seasons are shared ties. 2026 is not final yet, so it has no season
  awards.

Still open after Phase 12:

1. Art for the three original Oofies: `oof/most-collected.png`,
   `oof/oofscore-champion.png`, `oof/rarest-award.png`.
2. Variant thumbnails in the CMS: the route serves a variant (`?variant=`), but the
   variants list on the achievement page shows none.
3. Production deploy: the Phase 12 migrations go with deploy step 1 (the Early Bird
   rename before the sync of step 2). Then `oof:achievements --stale` brings production
   up to date, as here.
4. Food awards: 2024 stays without (6 of 24 races timed); a season qualifies only with 90%
   of its races at 80% of pit visits timed.

**Itchy Feet is a jump start only (22 November 2026, after Phase 12g).**

- Itchy Feet is a jump start: moving before the start signal (FIA Sporting Regulations
  Art 48.1 a). A car out of position on the grid (Art 48.1 c) is not one.
- Out of position is the starting procedure offence (`starting_procedure`), which has no
  achievement. "FALSE START - OUT OF POSITION" routes there by its text
  (`app/RaceControl/Offence.php`, ordered before `false_start`). Bare "FALSE START",
  "JUMP START" and "FALSE START - MOVING BEFORE SIGNAL" stay `false_start`.
- The bare 2024 messages do not say which it was, so `config/starting_position_rulings.php`
  curates the ones the stewards ruled out of position, each checked against the FIA
  decision document named in the entry: Perez 2024 R20 (Document 42) and Piastri 2024 R22
  (Document 36). The other four bare 2024 awards (Ricciardo, Magnussen, Hamilton, Zhou)
  were checked against their decisions and are real jump starts. The list is part of the
  catalogue version.
- `engine_version` is now 4 (was 3), so `oof:achievements --stale` re-runs older decisions.
- Four awards retracted by re-running their races (Perez 2024 R20, Piastri 2024 R22,
  Norris 2025 R4, Perez 2026 R6): High Five x3 and Drive Thru x1 took their place. Itchy
  Feet totals 10 (the 14 in the Phase 12g table is before this). Golden master: only these
  eight differences plus the 2024 OOFscore champion's evidence (Perez's total moved);
  re-exported, 33,027 awards.

## Working notes

- The work lives on the branch `achievement-engine`, one commit per phase. `main` stays
  untouched. A phase starts only when the owner says so.
- PHP runs only inside Sail: `vendor/bin/sail artisan ...`, `vendor/bin/sail bin pint --dirty --format agent`.
- When another Sail project holds the default ports, start this one beside it:
  `APP_PORT=8087 FORWARD_DB_PORT=33087 VITE_PORT=5187 vendor/bin/sail up -d`
  (and keep those variables set for later `sail` calls).
- A full engine dry run over 2023 to 2026 takes about 30 seconds; the golden master, which
  now also dry-runs 1950 to 2022, about 3.5 minutes. The backfill of 1950 to 2022 by hand
  takes about 3.5 minutes.
- Read-only checks: run them in a transaction that is rolled back, or as a script under
  `storage/app/private` via `tinker --execute 'require ...'` (a file argument makes tinker
  wait for input).

## Deploy steps

When the `achievement-engine` branch is deployed to production (decided 17 November 2026):

1. `php artisan migrate` (lineages, award schema and snapshot, sensitivity columns,
   data needs, scope and the run log, recipient type, nicknames and evolutions, practice
   results and `is_manual`, the lineage's fixed names and the historic constructor
   merge, and the rename of Early Bird to Itchy Feet, which must run before the sync of
   step 2), then `php artisan oof:normalize --all --source=openf1
   --endpoint=session_result` to fill `practice_results` from the stored responses.
   Deploy and migrate before 04:50: the scheduled `oof:dig` must not run on the old
   lineage (decision D12 of Phase 11a).
2. `php artisan oof:achievements:sync --dry-run`, then, if it lists no differences in
   sessions or stacks the owner wants kept, `php artisan oof:achievements:sync` (writes
   the catalogue's mechanics and the derived data needs, and creates the three Oofies and
   the eleven achievements of the 2027 pack).
3. Before the next step, check for awards a person decided on Overtime, Practice Makes
   Imperfect, Fine Dining, Nothing to See Here or Frequent Flyer: the engine leaves such a
   decided award in its old session context, beside the new weekend or season one. None
   existed locally.
4. `php artisan oof:import:dump` (the free Jolpica dump: race lap times 1996 on, in
   seconds), then `php artisan oof:import --only-laps --season=<year>` for the races
   newer than the dump (about 10 paced requests per race; the command says the dump's
   date) and `php artisan oof:normalize --laps --source=jolpica --season=<year>`. Before
   step 5: laps led come from them, and Led Astray and Clean Sweep wait for them. The
   hourly schedule now runs `oof:import --recent-laps` and `oof:normalize --laps`.
5. `php artisan oof:achievements --season=2023 --season=2024 --season=2025 --season=2026`,
   to bring the stored awards up to date, move the weekend and season achievements to
   their new contexts and fill the run log. Decisions made by people stay as they are.
6. `php artisan oof:oofscore`, to build the standings of every season (step 5 already
   rebuilds the seasons it ran; this makes sure).
7. `php artisan db:seed --class=DriverNicknameSeeder --force` (create-only; run it again
   once Câmara is imported), then `php artisan oof:nicknames` to check for breakthroughs.
8. `php artisan oof:dig`, which re-digs on the merged lineage (it also runs daily at
   04:50).
9. The historic backfill, by hand, oldest first, one season per command:
   `php artisan oof:achievements --season=1950`, then 1951 and so on to 2022 (about 3.5
   minutes in all). The hourly run never starts these seasons itself; once run, it keeps
   them up to date.
10. Do not close any season until the owner says so.
11. After any later catalogue or engine change: `php artisan oof:achievements --stale`.
