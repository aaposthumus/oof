# OOF! Achievements spec

Source of truth for the achievements engine. Written for Claude Code.
Last updated: 30 October 2026.

## Concept in short

OOF! is a stats-based F1 parody account. Drivers "unlock" tongue-in-cheek
achievements every session, presented like a video game ("Achievement
unlocked"), and collect them all season.

- **OOF shelf**: funny, mildly embarrassing achievements. They earn points;
  the sum is the season's **OOFscore** leaderboard.
- **Glory shelf**: positive achievements (wins, podiums). They show a tier
  but earn **no** points.
- Tiers and points: Bronze 10, Silver 25, Gold 50, Platinum 100.
  The tiers below are first estimates; the final tiers come from a test run
  on 2023–2026 data (how often each achievement actually unlocks).

## Achievements

Rarity = first estimate. "Stacks" = awarded once per instance; otherwise
once per driver per session.

### Race (OOF shelf)

| Achievement | Trigger | Rarity |
|---|---|---|
| Lapped Dog | Lapped by the leader (finished 1 lap down) | Bronze |
| Good Boy | Lapped twice or more | Silver |
| Mirror, Signal, Manoeuvre | Shown blue flags several times in one race (threshold from test run) | Bronze |
| Table for One | Pit stop longer than 5 s stationary | Bronze |
| Fine Dining | Slowest pit stop of the weekend | Bronze |
| Meatball Sub | Black-and-orange flag (stacks) | Silver |
| Colouring Outside the Lines | Lap time deleted for track limits | Bronze |
| Note Sent Home to Parents | Black-and-white flag for track limits | Silver |
| It Was Like That When I Got There | Involved in an incident noted by race control | Bronze |
| Bumper Cars | Penalty for causing a collision | Silver |
| Early Bird | False start (offence) with a penalty | Gold |
| Selective Hearing | Failing to follow race director's instructions, with a penalty | Silver |
| Recalculating | Leaving the track and gaining an advantage, with a penalty | Bronze/Silver |
| Lane Discipline | Crossing the pit entry or exit line, with a penalty | Silver |
| Smile for the Camera | Speeding in the pit lane, with a penalty | Bronze |
| Road Hog | Forcing another driver off the track, with a penalty | Silver |
| Forgot to Indicate | Moving under braking, with a penalty | Silver |
| Didn't See the Sign | Ignoring a flag (yellow, blue, red), with a penalty | Silver |
| Queue Jumper | Overtaking or going too fast under SC/VSC, with a penalty | Silver |
| Sunday Driver | Maximum delta time offence (too slow, e.g. under SC or on a qualifying in-lap) | Silver |
| Failed the Driving Test | Erratic or dangerous driving, with a penalty | Gold |
| Called the Tow Truck | Caused a safety car | Silver |
| Released Into the Wild | Unsafe release, with a penalty | Gold |
| Forgot the Keys | Did not start (DNS) | Gold |
| Early Lunch | Retired on lap 1 | Silver |
| Home Before Dinner | Retired later in the race (DNF) | Bronze |
| Sent to Your Room | Disqualified after the race (DSQ) | Gold |
| Expelled | Black flag during the race | Platinum |
| Reverse Gear | Most places lost compared with the grid | Bronze |
| All Dressed Up, Nowhere to Go | Fastest lap but finished outside the points | Silver |
| Turned Off the Lights | Last classified finisher | Bronze |
| Nothing to See Here | Unlocked nothing all weekend ("The quiet ones") | Bronze |

Sent to Your Room can carry a reason subtitle (Skipped Breakfast =
underweight, Scraped the Bottom = plank wear, Running on Fumes = fuel
sample, Bendy Bits = bodywork). Reasons are often only in the stewards'
documents, so they can be filled in via the CMS.

### Any session (OOF shelf)

| Achievement | Trigger | Rarity |
|---|---|---|
| Whole Class Detention | Caused a red flag (practice, qualifying or race) | Gold |

Red flags and safety cars only count when one driver caused them. Weather,
debris or track problems give no achievement. Link them to an incident or
"car stopped" message just before; without a clear link, award nothing and
send the case to the CMS to confirm or skip.

### Penalties (OOF shelf, stack per instance)

| Achievement | Trigger | Rarity |
|---|---|---|
| High Five | 5-second time penalty | Bronze |
| Perfect Ten | 10-second time penalty | Silver |
| Drive-Thru | Drive-through penalty | Gold |
| Red Light, Green Light | Stop-go penalty | Platinum |

### Qualifying (OOF shelf)

| Achievement | Trigger | Rarity |
|---|---|---|
| Family Feud | Knocked out by your own teammate | Bronze |
| By a Whisker | Knocked out by less than 0.05 s | Silver |
| So Close | Best Q3 lap deleted | Silver |
| Holding the Door | Slowest in Q1 (unlocks every weekend) | Bronze |

### Practice (OOF shelf, light "Friday Report")

| Achievement | Trigger | Rarity |
|---|---|---|
| Overtime | Most laps driven | Bronze |
| Practice Makes Imperfect | Most deleted laps | Bronze |

Practice achievements only count for race drivers (not FP1 rookies or
practice-only drivers).

### Glory shelf (trophy cabinet, no points)

| Achievement | Trigger | Rarity |
|---|---|---|
| Did the Thing | Win | Bronze |
| Standing Room Only | Podium | Bronze |
| Plot Twist | Surprise pole | Gold |
| Lottery Ticket | Surprise win | Platinum |
| Took the Scenic Route | Win from P10 or lower on the grid | Gold |
| Hogged the Lot | Grand Chelem: pole, win, fastest lap, led every lap | Platinum |
| Maths Is Over | Championship clinched | Gold |
| Spare Tyre | Called up to race as a replacement driver | Gold |

"Surprise" (open, first proposal): the team is outside the constructors'
top 4, or the driver hasn't started in the top 5 earlier that season.

### Safety Car (separate, never in the OOFscore)

The Safety Car is a cameo character with its own small stats corner.

| Achievement | Trigger |
|---|---|
| Took the Lead | The safety car led more laps than some of the drivers |
| Called In Again | Several safety car deployments in one race |
| Frequent Flyer | Season count of deployments (for the end-of-season awards) |
| Virtually There | A VSC deployment |

## Rules

**Cascading: one event, one achievement.** When several achievements come
from the same event, only the most specific counts.
- Bumper Cars absorbs the incident note (It Was Like That When I Got There)
  and its time penalty; shown as detail, e.g. "Bumper Cars · 10 s".
- Every offence achievement absorbs its own penalty (e.g. "Released Into
  the Wild · 5 s").
- Good Boy replaces Lapped Dog; Fine Dining replaces Table for One;
  Note Sent Home to Parents replaces Colouring Outside the Lines; a High Five
  for track limits replaces both track-limit achievements; Expelled replaces
  Sent to Your Room.
- Separate events still stack (a crash on lap 30 and being lapped both count).
- Implementation: a "supersedes" relation between achievements; after
  calculating, anything covered by a more specific achievement from the same
  event is removed.

**Thresholds** (blue flags, deleted laps) unlock once per race.

**Driver-specific variants**: an optional variant name per achievement and
driver, counted as the same achievement (e.g. Overstepper's Colouring
Outside the Lines becomes "Off Course").

**Achievements belong to the person, not the seat**: stand-ins keep what
they unlock.

## Fairness and trust (important)

- **Involvement is not fault.** An "incident noted" message lists the cars
  involved, not who caused it. Involvement achievements (It Was Like That
  When I Got There) never imply blame, including in captions. Fault-based
  achievements (Bumper Cars and other offences) only come from penalties,
  which always name exactly one car.
- **Evidence on every award**: store the source (the original race control
  message text, or the result row) with each awarded achievement, so a
  reviewer can verify it without knowing the race.
- **Confidence per award**: high (a penalty or flag naming one car),
  medium (involvement in an incident), low (anything inferred, e.g. who
  caused a safety car or red flag). Low always goes to manual review.
- **Wait for finality**: achievements tied to an open investigation wait for
  its outcome. A session is only "final" once stewards are done.
- **Cross-check sources**: penalties in race control vs Jolpica's final
  results; disagreements are flagged, not awarded.
- **CMS manual override**: suppress any award when the reason isn't funny,
  e.g. a DNS because of illness or injury, anything on a sensitive weekend.

## What the data showed (OpenF1 race control 2023–2026)

- Unsafe release, false start, leaving the track, speeding in the pit lane
  etc. are **offences** attached to stewards' messages, typed by stage
  (noted, investigation, outcome). Offence achievements trigger on
  offence + penalty outcome.
- **Post-session penalties are not in race control.** Of 527 "investigated
  after the session" messages, only 149 have an outcome there (148 "no
  further action"). Post-race penalties, grid penalties and DSQs must come
  from Jolpica (or later the stewards' documents).
- **Multi-car incidents** (1,339 messages) name several cars in
  `car_numbers` but aren't linked to a driver entry. Linking every car
  involved is needed for It Was Like That When I Got There.
- **Black-and-orange flags never occur** in 2023–2026; one black flag
  (Hülkenberg, 2024 Brazil).
- **"Penalty served"** only exists from 2025.
- **Track-limit deletions**: practice deletions only published from 2026,
  so compare seasons on qualifying, sprint and race only; 115 deletions are
  re-issued for the same lap, so dedupe on session + car + deleted time.
- **Time penalties** are 5 s or 10 s only.
- **Red flags, SC and VSC** occur in practice too (including SC/VSC test
  deployments): filter on race and sprint for SC/VSC. A VSC that turns into
  an SC has no "VSC ending". "SAFETY CAR LIGHTS ON/OFF" is procedural.
- **Maximum delta time** is the most common offence (476 messages).
- **Era-bound types**: DRS ends after 2025, overtake mode starts in 2026;
  "off track/spun/missed the apex" only in 2023–24; "marshals on track" only
  in 2026.
- Six qualifying entries were created from OpenF1 for drivers without a
  qualifying time (no qualifying result row).

## Still open

- Sprints: reuse the race achievements, or a light set of their own.
- Final thresholds and rarity tiers (from the test run).
- Collections (completing a family of achievements unlocks a meta badge).
