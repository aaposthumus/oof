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
| 7 | Scopes, runs and the Oofies | Not started |
| 8 | Recipients | Not started |
| 9 | Nicknames and evolution events | Not started |
| 10 | 2027 pack | Not started |
| 11 | Historic backfill | Not started |

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
| Bumper Cars, Early Bird, Selective Hearing, Shortcut Tax, Line Dancing, Smile for the Camera, Extra Wide, Forgot to Indicate, Selective Vision, Queue Jumper, Failed the Driving Test, Released Into the Wild, Double Detention | OpenF1 race control, penalty naming one car and an offence | 2023 | In-session penalties only |
| Sunday Driver | OpenF1 race control, maximum delta time noted | 2023 | |
| High Five, Perfect Ten, Drive-Thru, Red Light Green Light | OpenF1 race control, penalties | 2023 | Post-session penalties are missing |
| Called the Tow Truck, Whole Class Detention | OpenF1 race control: deployment plus a single-car message just before | 2023 | Inferred, always reviewed |
| Table for One, Fine Dining | OpenF1 pit, stationary time | 2023 nominally | Stationary time is filled for only a small share of visits; "penalty served" exists only from 2025 |
| Holding the Door, By a Whisker, Mirror Match | Jolpica qualifying times; OpenF1 session results for sprint qualifying | 2006 (knockout format, check) | |
| Economy Class | Same, plus team form before the round | 2006 (check) | |
| Deleted Scene | OpenF1 deletions with a time, plus the Q3 time | 2023 | From 2024 most deletions carry no time, so it undercounts |
| Overtime | OpenF1 laps | 2023 | |
| Practice Makes Imperfect | OpenF1 deletions in practice | 2026 | Not published before 2026 |
| Fool's Gold | Practice classification and qualifying | 2023 | Practice session results are raw only today |
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
2. **Laps led before 2023.** Jolpica `lap_times` is empty until the import runs; there
   is nothing at all before 1996.
3. **Pit stationary time** is sparse. Check coverage per season before trusting Table
   for One and Fine Dining; Jolpica's pit data is pit-lane time, not stationary time.
4. **Track limits.** Practice deletions exist only from 2026; from 2024 most deletions
   have no lap time.
5. **Practice classification and starting grid** are only in `raw_responses`.
6. **Retirement reasons** are generic from 2025, so a DNS or DNF through illness or
   injury relies on a person suppressing it.
7. **No live sensitivity source.** The seeded weekends are historic; nothing flags a
   2027 weekend unless a person or the heavy-crash proposal does.
8. **Nicknames, mottos, props, reserves** are not in the database; the 2027 line-ups
   are not confirmed.
9. **Team lineage** covers today's teams and, since Phase 2, the main historic renames.
   Jolpica's chassis-engine constructors of the 1950s to 1970s (Lotus-Climax beside Team
   Lotus, Cooper-Climax, Brabham-Repco, McLaren-Ford and so on) are still separate teams,
   which splits their form; the owner decided to merge them in Phase 11.
10. **Session dates.** Old seasons have only the race date, in UTC; a night race can
    fall on the next calendar day locally.
11. **Jolpica quirks.** No scheduled race distance, a few wrong 2025 lap counts, grid 0
    no longer used after 2024.
12. **No signal in the data.** Black-and-orange flags never occur; team radio has no
    transcripts and follows the broadcast; stints are noisy.
13. **Licences.** OpenF1's free tier is personal use; Jolpica is non-commercial. Both
    need settling before launch.

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
and flagged awards never leave the CMS. A publishing change that reads awards another
way is not done.

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
  Reverse Gear, Overtime, Nearly Famous, Mirror Match, All Dressed Up Nowhere to Go),
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

**Phase 7: scopes, runs and the Oofies.**
Weekend scope (combined practice awards, Fine Dining, Nothing to See Here), season scope
(Frequent Flyer and the three Oofies), the run log and `--stale`. Apply decisions 2, 3
and 4 of section 2.
Done when practice awards appear once per weekend, the 2025 Oofies can be computed, and
a catalogue change marks old contexts stale.

**Phase 8: recipients.**
Recipient value on detections, team columns in use, Safety Car explicit; trophy queries
per recipient.
Done when a test team achievement can be awarded end to end without engine changes.

**Phase 9: nicknames and evolution events.**
Tables, seed the 22 nicknames and the legends, CMS screens, the breakthrough check,
both events, the off-season rule for the effective date, nickname-based edition
subtitles. Apply decision 1 of section 2.
Done when a fixture podium for Lindblad creates one proposal and one event, a re-run
creates none, and an effective date inside the season is refused.

**Phase 10: 2027 pack.**
The nine new achievements, Zoomies, manual Built Different; normalise practice session
results for Fool's Gold. Re-run the report on 2023 to 2026 and set final tiers.
Done when each new achievement is a config entry plus, at most, one predicate.
Built Different must keep `withheld_on_sensitive_weekends` (it is already in the
seeder's list), so the award guard keeps it off sensitive weekends.

**Phase 11: historic backfill.**
Import Jolpica lap times (independent of the other phases, so it can start any time),
lift `first_season`, run 1950 to 2022 oldest first, add a curated list for Clean Sweeps
before the lap data, wire the This week in OOF history query.
Done when a second full backfill changes nothing and any calendar week returns awards
with sensitive ones flagged.

Decided for this phase on 17 November 2026:

- Merge Jolpica's chassis-engine constructors into lasting teams in `team_lineages`
  (Lotus-Climax and the other Lotus variants into Team Lotus, Cooper-Climax and the
  other Cooper variants into Cooper, likewise Brabham, McLaren, March, Eagle, BRM,
  Matra, Shadow, LDS and De Tomaso), so their form is not split. Do it before the
  backfill runs. Then run `oof:dig`, which re-digs the surprise-winner excavator
  (version 3, on team form); it has not been run since that change.
- Open question: should the Indianapolis 500 rounds of 1950 to 1960 count for any
  achievement at all? Today they are only out of the surprise rule.

## Working notes

- The work lives on the branch `achievement-engine`, one commit per phase. `main` stays
  untouched. A phase starts only when the owner says so.
- PHP runs only inside Sail: `vendor/bin/sail artisan ...`, `vendor/bin/sail bin pint --dirty --format agent`.
- When another Sail project holds the default ports, start this one beside it:
  `APP_PORT=8087 FORWARD_DB_PORT=33087 VITE_PORT=5187 vendor/bin/sail up -d`
  (and keep those variables set for later `sail` calls).
- A full engine dry run over 2023 to 2026 takes about 30 seconds.

## Deploy steps

When the `achievement-engine` branch is deployed to production (decided 17 November 2026):

1. `php artisan migrate` (lineages, award schema and snapshot, sensitivity columns,
   data needs).
2. `php artisan oof:achievements:sync --dry-run`, then, if it lists no differences in
   sessions or stacks the owner wants kept, `php artisan oof:achievements:sync` (writes
   the catalogue's mechanics and the derived data needs).
3. `php artisan oof:achievements --season=2023 --season=2024 --season=2025 --season=2026`,
   to bring the stored awards up to date, as was done locally. Decisions made by people
   stay as they are.
4. `php artisan oof:oofscore`, to build the standings of every season (step 3 already
   rebuilds the seasons it ran; this makes sure).
5. Do not run `oof:dig` yet; it waits for the chassis-engine merge in Phase 11.
6. Do not close any season until the owner says so.
