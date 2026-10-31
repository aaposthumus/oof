# Discovery round 2: events without an achievement

Analysis only: nothing here is built. Companion to `docs/achievements.md`.
Data: 2023–2026 as imported on 31 October 2026 (OpenF1 up to 2026 round 18:
88 races, 88 qualifying sessions, 24 sprints and 24 sprint qualifying
sessions; Jolpica results and full history for career firsts).

**How the catalogue's rarity bands map to counts (test run, 2023–2026):**
Platinum ≈ under 5 awards, Gold ≈ 7–20, Silver ≈ 25–80, Bronze 80+.
Frequencies below use the same four-season window, so they compare directly.

**Fairness and confidence** use the spec's terms: *high* = one car named by an
official source, or a plain result row; *medium* = involvement, or a figure we
derive from timing data; *low* = an inferred cause.

---

## 1. Race control

Race control messages were classified with `MessageClassifier` (25,239
messages). Detectors use these types as a trigger: penalties, black,
black-and-white and black-and-orange flags, blue flags, track-limit
deletions, incident notes, red flags, SC/VSC deployments and DSQ. These are
used only as context (cause links, stages, finality): car stopped, off track,
chequered flag, investigations, no further action, warning, reprimand,
penalty served, grid penalty and pit lane start.

### 1a. Message types no achievement uses

| Type | Total | 2023 | 2024 | 2025 | 2026 | Race | Sprint | Quali | SQ | Practice |
|---|---|---|---|---|---|---|---|---|---|---|
| clear_flag | 3890 | 712 | 629 | 1116 | 1433 | 1145 | 275 | 794 | 207 | 1469 |
| yellow_flag | 2071 | 345 | 298 | 559 | 869 | 554 | 128 | 387 | 87 | 915 |
| double_yellow_flag | 2035 | 252 | 293 | 622 | 868 | 516 | 194 | 431 | 149 | 745 |
| session_status | 2028 | 524 | 540 | 559 | 405 | 225 | 56 | 783 | 179 | 785 |
| drs | 1296 | 380 | 405 | 511 | 0 | 359 | 77 | 193 | 46 | 621 |
| green_flag | 916 | 228 | 255 | 253 | 180 | 167 | 46 | 309 | 73 | 321 |
| first_car_to_take_the_flag | 517 | 119 | 147 | 139 | 112 | 0 | 0 | 253 | 66 | 198 |
| slippery_surface | 425 | 120 | 87 | 125 | 93 | 154 | 43 | 54 | 12 | 162 |
| risk_of_rain | 264 | 93 | 67 | 64 | 40 | 85 | 24 | 33 | 7 | 115 |
| notice | 192 | 75 | 47 | 41 | 29 | 46 | 13 | 18 | 8 | 107 |
| time_deleted_other | 156 | 11 | 15 | 12 | 118 | 0 | 0 | 56 | 13 | 87 |
| pit_exit | 122 | 27 | 33 | 30 | 32 | 92 | 26 | 1 | 0 | 3 |
| overtake_mode | 102 | 0 | 0 | 0 | 102 | 54 | 12 | 5 | 4 | 27 |
| vsc_ending | 89 | 17 | 8 | 24 | 40 | 49 | 1 | 0 | 0 | 39 |
| grip_conditions | 89 | 19 | 26 | 8 | 36 | 23 | 4 | 12 | 2 | 48 |
| start_procedure | 69 | 11 | 10 | 16 | 32 | 54 | 14 | 0 | 0 | 1 |
| marshals_on_track | 62 | 0 | 0 | 0 | 62 | 44 | 4 | 1 | 0 | 13 |
| wet_conditions | 34 | 15 | 13 | 3 | 3 | 2 | 2 | 9 | 3 | 18 |
| pit_lane_access | 32 | 0 | 0 | 14 | 18 | 32 | 0 | 0 | 0 | 0 |
| safety_car_procedure | 31 | 6 | 5 | 4 | 16 | 26 | 5 | 0 | 0 | 0 |
| medical_car | 29 | 0 | 18 | 3 | 8 | 4 | 0 | 9 | 0 | 16 |
| pit_entry | 27 | 5 | 6 | 6 | 10 | 18 | 1 | 0 | 0 | 8 |
| time_reinstated | 26 | 3 | 5 | 6 | 12 | 7 | 3 | 4 | 2 | 10 |
| temperature | 12 | 2 | 4 | 4 | 2 | 1 | 0 | 2 | 1 | 8 |
| lapped_cars | 12 | 0 | 2 | 5 | 5 | 12 | 0 | 0 | 0 | 0 |
| unmatched | 4 | 3 | 0 | 1 | 0 | 3 | 0 | 1 | 0 | 0 |

What is in them:

- **Flags, DRS, overtake mode, pit exit/entry, session status, start
  procedure, notices, grip/rain/temperature**: procedural or track-wide, never
  tied to one driver. No achievement material (session status is still
  useful: it gives the qualifying phase, see 3.11).
- **time_deleted_other**: *all 156 are double-yellow deletions*
  ("CAR 4 (NOR) TIME 1:47.298 DELETED - DOUBLE YELLOW AT TURN 12"). One car
  per message. Qualifying: 11 / 15 / 12 / 31 per season; practice only from
  2026 (87). They come in batches (up to 10 cars in one session, 2024 São
  Paulo Q). → candidate 3.8.
- **time_reinstated**: 26 single-car messages ("CAR 77 (BOT) TIME 1:38.366
  WILL BE REINSTATED"; 2026 United States FP1 HUL had an 8:42.988
  lap reinstated). → candidate 3.21.
- **lapped_cars**: "LAPPED CARS MAY NOW OVERTAKE THE SAFETY CAR: 6, 63, 41…"
  lists the cars that were lapped when the safety car came in (14 cars at 2026
  Monaco). Good extra evidence for Lapped Dog; not a new event.
- **medical_car, marshals_on_track (2026 only), car_stopped "RECOVERY VEHICLE"**:
  context for red flags and safety cars; no driver.
- **Unmatched (4)**: "RESTART PROCEDURE NOTED/UNDER INVESTIGATION/NO FURTHER
  ACTION" (2023 Australia, whole field), two 2023 Monaco "CAR 18 (STR) TRACK
  LIMITS TURN 10 LAP 1" (old wording), "LAP TIME OF CAR 87 (BEA) - UNDER
  REVIEW" (2025 Imola Q).

### 1b. Stewards' stages ignored on purpose

| Stage | Total | 2023 | 2024 | 2025 | 2026 | Race | Sprint | Quali | SQ | Practice |
|---|---|---|---|---|---|---|---|---|---|---|
| no_further_action | 560 | 69 | 130 | 192 | 169 | 255 | 23 | 159 | 48 | 75 |
| investigated_after_session | 527 | 72 | 138 | 148 | 169 | 116 | 36 | 213 | 57 | 105 |
| under_investigation | 318 | 80 | 78 | 82 | 78 | 272 | 32 | 3 | 1 | 10 |
| warning | 3 | 0 | 0 | 0 | 3 | 1 | 1 | 0 | 0 | 1 |
| reprimand | 2 | 0 | 0 | 2 | 0 | 2 | 0 | 0 | 0 | 0 |

- **Reprimands and warnings barely exist in race control** (5 in four
  seasons, e.g. "REPRIMAND (DRIVING) FOR CAR 55 (SAI) - … ESCAPE ROAD
  INSTRUCTIONS", 2025 Monza; "WARNING FOR CAR 5 (BOR) - MOVING UNDER BRAKING",
  2026 Spain). Reprimands live in the stewards' documents. Not viable from
  this data.
- **No further action** by offence (top): maximum time 136 (almost all
  qualifying), impeding 60, leaving the track 52, causing a collision 52,
  yellow flag 42, forcing off 34, race director's instructions 26, unsafe
  release 20. In races and sprints 112 NFAs name one car and 153 name two.
  An NFA is an acquittal, so an award would tease a driver for being cleared.
  That breaks "involvement is not fault". Not recommended (see 3.24).
- **Investigations without an outcome in race control**: as in round 1,
  post-session outcomes are mostly missing. Nothing new to award.
- **"Place was given back"** appears twice in NFA reasons (2023 Monaco, 2023
  Canada): too rare.

### 1c. Offences no achievement uses

| Offence · stage | Total | 2023 | 2024 | 2025 | 2026 | Race | Sprint | Quali | SQ | Practice |
|---|---|---|---|---|---|---|---|---|---|---|
| impeding · incident_noted | 135 | 40 | 33 | 36 | 26 | 1 | 1 | 65 | 16 | 52 |
| impeding · investigated_after_session | 73 | 29 | 16 | 11 | 17 | 0 | 1 | 44 | 5 | 23 |
| impeding · no_further_action | 60 | 9 | 16 | 25 | 10 | 0 | 0 | 20 | 11 | 29 |
| impeding · black_and_white_flag | 3 | 2 | 0 | 0 | 1 | 0 | 0 | 0 | 0 | 3 |
| impeding · time_penalty | 1 | 1 | 0 | 0 | 0 | 1 | 0 | 0 | 0 | 0 |
| starting_procedure · incident_noted | 21 | 5 | 3 | 3 | 10 | 18 | 3 | 0 | 0 | 0 |
| starting_procedure · under_investigation | 11 | 4 | 1 | 1 | 5 | 9 | 2 | 0 | 0 | 0 |
| starting_procedure · investigated_after_session | 7 | 0 | 2 | 0 | 5 | 7 | 0 | 0 | 0 | 0 |
| starting_procedure · no_further_action | 5 | 1 | 0 | 1 | 3 | 4 | 1 | 0 | 0 | 0 |
| starting_procedure · time_penalty / stop-go | 3 / 1 | 3 / 0 | 0 | 0 | 0 / 1 | 4 | 0 | 0 | 0 | 0 |
| unsafe_condition · incident_noted | 20 | 2 | 5 | 7 | 6 | 10 | 2 | 4 | 1 | 3 |
| unsafe_condition · investigated_after_session | 15 | 1 | 3 | 6 | 5 | 7 | 2 | 4 | 0 | 2 |
| unsafe_condition · stop_go_penalty | 2 | 0 | 1 | 1 | 0 | 2 | 0 | 0 | 0 | 0 |
| unsafe_rejoin · incident_noted | 13 | 3 | 5 | 4 | 1 | 8 | 0 | 2 | 0 | 3 |
| unsafe_rejoin · time_penalty / B&W flag | 1 / 1 | 1 / 0 | 0 | 0 / 1 | 0 | 2 | 0 | 0 | 0 | 0 |
| serving_a_penalty_incorrectly · incident_noted | 9 | 4 | 2 | 2 | 1 | 9 | 0 | 0 | 0 | 0 |
| serving_a_penalty_incorrectly · time_penalty | 5 | 3 | 1 | 1 | 0 | 5 | 0 | 0 | 0 | 0 |
| serving_a_penalty_incorrectly · drive_through | 1 | 0 | 0 | 0 | 1 | 1 | 0 | 0 | 0 | 0 |
| driving_slowly · black_and_white_flag | 10 | 0 | 0 | 0 | 10 | 0 | 0 | 9 | 0 | 1 |
| driving_slowly · incident_noted | 6 | 1 | 3 | 0 | 2 | 2 | 0 | 1 | 0 | 3 |
| on_foot · incident_noted | 3 | 1 | 2 | 0 | 0 | 1 | 1 | 1 | 0 | 0 |

Messages with **no recognised offence** (no reason after the dash, or "RACE
CONTROL TEST", "TYRE USAGE", "DRS INFRINGEMENT", "UNSPORTSMANLIKE BEHAVIOUR",
"FAILING TO STOP FOR WEIGHING PROCEDURE"): 133 incident notes, 77 NFAs, 4 time
penalties, 3 black-and-white flags, 1 black flag (Hülkenberg, covered by
Expelled).

Notes:

- **Impeding** is the biggest unused offence (272 messages, almost all
  qualifying and practice). The message names *both* cars ("INCIDENT
  INVOLVING CARS 22 (TSU) AND 4 (NOR) NOTED - IMPEDING") and does not say who
  impeded whom. The outcome (grid penalty or reprimand) is in the stewards'
  documents, not here. **Cannot be tied fairly to one driver** with this
  data. Revisit once stewards' documents are imported.
- **Serving a penalty incorrectly** (5 time penalties, 1 drive-through) is
  very OOF (getting a penalty for serving your penalty wrong: ALO 2023
  Jeddah, OCO 2023 Bahrain, HUL 2023 Monaco, RIC 2024 Monza, TSU 2025
  São Paulo). Single car, high confidence. → candidate 3.16.
- **Driving unnecessarily slowly** is a 2026 story: 10 black-and-white flags
  in 2026 qualifying (HAM ×2, VER ×2, HUL, RUS, ALO, GAS, BEA; SAI in FP3).
  Sunday Driver only looks at maximum delta time. → folded into 3.7.
- **Starting procedure** (out of position, wrong grid slot): 4 penalties,
  1 warning, 1 reprimand. Too rare on its own; could join Early Bird as a
  variant.
- **Unsafe condition, unsafe rejoin, on foot**: under 5 penalties each over
  four seasons. Not worth separate achievements.

### 1d. Black-and-white flags for other reasons

Note Sent Home to Parents only counts track limits. Black-and-white flags for
other reasons, all naming one car:

| Offence | Total | 2023 | 2024 | 2025 | 2026 | Race | Quali | Practice |
|---|---|---|---|---|---|---|---|---|
| pit_lane_infringement (crossing the pit entry line) | 26 | 8 | 9 | 0 | 9 | 0 | 1 | 25 |
| race_directors_instructions | 11 | 4 | 0 | 2 | 5 | 1 | 4 | 6 |
| driving_slowly | 10 | 0 | 0 | 0 | 10 | 0 | 9 | 1 |
| erratic_driving (weaving) | 6 | 2 | 1 | 1 | 2 | 4 | 0 | 2 |
| moving_under_braking | 3 | 1 | 0 | 1 | 1 | 3 | 0 | 0 |
| impeding | 3 | 2 | 0 | 0 | 1 | 0 | 0 | 3 |
| forcing_off_the_track | 3 | 2 | 0 | 0 | 1 | 2 | 0 | 1 |
| no reason given | 3 | 0 | 1 | 1 | 1 | 2 | 0 | 1 |
| causing_a_collision | 2 | 2 | 0 | 0 | 0 | 2 | 0 | 0 |
| unsafe_rejoin, ignoring blue flags | 1 + 1 | | | | | 2 | | |

69 in total (21 / 11 / 6 / 31 per season). Example: four cars flagged in one
session for crossing the pit entry line (2023 Las Vegas FP2: GAS, SAR, HUL,
BOT), and TSU in FP3. → candidate 3.7.

---

## 2. Everything else in the database

### 2a. Team radio (17,443 clips, audio URL only, no transcript)

| Season | Race clips | Avg per driver per race | Max in one race |
|---|---|---|---|
| 2023 | 2,744 | 6.4 | 26 (SAI, Monaco) |
| 2024 | 2,620 | 5.8 | 32 (NOR, Hungary) |
| 2025 | 765 | 3.1 | 16 |
| 2026 | 437 | 2.8 | 12 |

- Volume fell by about 70% from 2025: OpenF1 only has what the broadcast
  airs.
- From 2025 it's heavily skewed to front-runners: NOR 160 race clips in 2025,
  STR 2, COL 3. 2026: ANT 80, STR 1.
- Outliers are real stories (NOR 32 clips at 2024 Hungary = the team-orders
  saga; Monaco 2023 rain: SAI 26, RUS 22).
- **Verdict:** "most radio" measures the broadcaster's choices, not the
  driver. Unfair and drifting. Only usable as a fun fact on a slide, not as an
  award. Without transcripts there's nothing to say about content.

### 2b. Stints and tyres (33,782 stints)

- Stints per driver per race: mostly 2–3; 6–8 only in rain or red-flag races
  (2023 Zandvoort: RUS, LAW, STR 8 stints; 2026 Monaco BOR 8; 2026 Bahrain PER
  8).
- **Data is noisy:** overlapping laps (1-1, 1-2), stints that end before they
  start (11 rows), 365 one-lap stints in races (red flags, data splits), null/UNKNOWN compounds
  (9). Red-flag tyre changes are *not* recorded as new stints: at 2024 Monaco
  eight drivers show a single 1–78 stint despite the lap-1 red-flag change.
- **No classified finisher ran a race on one stint.** Every single-stint
  driver retired.
- **Wet → slick → wet flip-flops**: nearly always the whole field
  (2023 Zandvoort, 2025 Melbourne). Individual cases are rare: OCO 2024
  Silverstone (S-I-M-I-M), STR 2025 Silverstone (I-S-I-S), ANT 2025
  Silverstone. Too rare and too noisy.
- **Longest stints**: circuit-driven (Monaco, Jeddah, Baku hard-tyre runs of
  49–78 laps). Longest soft stint: HAD 61 laps, 2026 Singapore. Strategy, not
  achievement.
- **Verdict:** nothing strong. A possible "Tyre Whisperer" (longest stint of
  the race) is circuit-driven and suffers from the red-flag gap.

### 2c. Laps (240,623 laps; race laps 99.5% complete)

- **Solo slow laps**: a lap ≥25% slower than the field median for that lap.
  Lap 1, pit in/out laps and neutralised laps (field median >8% over normal)
  are excluded. 61 laps, 56 driver-races in 88 races (12 / 5 / 7 / 37 per
  season; the 2026 spike looks like energy management or harvesting). Top:
  HAD 2025 Qatar L55 (+86%), DEV 2023 Montréal L35 (+79%), NOR 2024 Austria
  L64 (+68%, puncture after the Verstappen contact). → candidate 3.14.
- **Lap 1** (positions from line-crossing times vs the Jolpica grid):
  - Gained ≥5 places: 56 driver-races; ≥8: 13. Best per race ≥6 places in
    13 of 88 races. Top: MAG 2023 Austria P19→P8, LEC 2024 Abu Dhabi P19→P8.
  - Lost ≥5: 58; ≥8: 20. Top: PIA 2024 Abu Dhabi P2→P19 (hit by Verstappen),
    PIA 2026 Montréal P4→P21, RUS 2023 Qatar P2→P18 (contact with Hamilton).
  - Most big losses come from contact caused by someone else, so the award
    has to be involvement-style, not blame.
- **Fastest laps**: covered by All Dressed Up, Nowhere to Go.
- **Pit-out laps**: about 850 per season in races; nothing notable beyond
  Table for One and Fine Dining.
- **Speed trap**: race top speed averages 333 km/h, max 364. A per-race top
  speed award would unlock every race for a low-drag car; no story.

### 2d. Positions (derived per lap from line-crossing times)

- **Lead changes per race**: 0 in 19 races, 2 in 22, 5 in 12, 8 max (2025
  Montréal, 2026 Zandvoort). Race-level, no driver: only fits the Safety Car
  stats corner style.
- **Lost the lead in the final 3 laps**: 5 cases (NOR→PIA 2024 Hungary, team
  orders; VER→NOR 2025 Monaco, last-lap mandatory stop; PIA↔NOR sprint
  swaps). Mostly team orders or strategy. Unfair and too rare.
- **Led the most laps, didn't win**: 11 races (0 / 4 / 3 / 4). VER 2024
  Austria led 62 laps and finished P5. → candidate 3.9.
- **Retired while in the top 3**: 7 (LEC 2023 Bahrain P3, VER 2024 Melbourne
  P2, LEC 2025 São Paulo P3, NOR 2025 Las Vegas P2 then DSQ, RUS 2026
  Montréal from the lead, LEC 2026 Monaco P3, PIA 2026 Hungary P2). →
  candidate 3.12.
- **Midfield team led a lap** (team outside that season's top 4 on points):
  5 (ALO 2023 Jeddah, TSU 2023 Abu Dhabi, OCO 2024 São Paulo 14 laps, ALB
  2025 Shanghai, GAS 2026 Monza). → candidate 3.22.
- **Most position changes** (sum of lap-to-lap moves): LAW 2026 Suzuka 73,
  ZHO 2023 Qatar 68. Driven by pit cycles and SC; no story.

### 2e. Pit lane visits (29,507)

- Race visits per driver: 1 (613), 2 (652), 3 (188), 4 (69), 5 (41), 6 (11),
  7 (5). 6–7 only in rain or chaos races (2023 Zandvoort, 2026 Monaco, 2025
  Montréal ALO 6).
- Practice and qualifying visits (avg 4–5, max 11) are normal running. No
  story.
- Stationary time is only filled for 1,038 visits. Lane time includes garage
  time in practice (up to 72 minutes).
- **Verdict:** "most pit visits in a dry race" is possible but overlaps
  penalties, damage and Table for One. Low priority.

### 2f. Weather (43,307 samples)

Sessions with rain in ≥25% of samples: races 0 / 3 / 2 / 1, sprints
2 / 0 / 1 / 0, qualifying 1 / 3 / 1 / 0. Races where most drivers ran
intermediates: 13 in four seasons (Monaco 2023, Zandvoort 2023, Montréal
2024, Silverstone 2024 and 2025, São Paulo 2024, Melbourne 2025, Spa 2025,
Bahrain 2026, plus sprints).

What happens in them: SC in most, up to 6–8 non-finishers (Melbourne 2025,
Singapore 2026 sprint), red flags in Zandvoort 2023, São Paulo 2024, Spa 2025 and Miami 2025 sprint.

**Verdict:** weather hits every driver, so it's not an event. It works as a
*modifier*: a "wet" badge on existing awards (Home Before Dinner in the rain,
Did the Thing in the rain) or a variant name. Also useful context to suppress
Lapped Dog and Reverse Gear on chaos days.

### 2g. Session results and starting grid (raw, unused)

Only in `raw_responses`, never normalised:

- `session_result` (8,940 rows): position, laps, duration and gap per driver
  for **every session including practice**, plus dnf/dns/dsq flags per
  session (practice never sets them).
  - Practice classification → **practice leader** (candidate 3.4, 3.23) and
    **no time set** (60 rows; 25 are individual, excluding sessions where 3+
    cars set no time: 5 / 10 / 2 / 8). → candidate 3.13.
  - Qualifying and sprint qualifying: 51 `dnf` and 5 `dns` flags. They mix
    crashes (SAR 2023 Suzuka, PER 2023 Melbourne: no time at all) with
    drivers who reached Q3 and set no time (21 such cases in qualifying).
    Only "no time at all" (12) is clean, and it overlaps
    Holding the Door and Whole Class Detention.
- `starting_grid` (2,262 rows): grid slot and the lap time it came from.
  Duplicates Jolpica's grid. No new event.

### 2h. Results and qualifying patterns not covered yet

| Pattern | Count (2023 / 24 / 25 / 26) | Notes |
|---|---|---|
| P11, first car out of the points | 88 (every race) | HUL 13 times, ALO and ALB 8, BOR 7 |
| Top-4 team driver out in Q1 | 14 / 13 / 17 / 3 | HAM 2024 China P18, VER 2025 São Paulo P16 |
| Pole (grid 1), no podium | 2 / 6 / 4 / 3 | 5 of them DNF or DSQ; ANT 2026 Silverstone P15 |
| Topped a practice session, qualified outside the top 10 | 3 / 4 / 1 / 0 (10 sessions) | VER 2023 Jeddah (topped all three, Q15) |
| Finished < 0.15 s behind the car ahead (both on the lead lap) | about 20 in four seasons | RUS 0.009 s behind OCO, 2023 Austria sprint |
| Biggest gain of the race grid→finish ≥ 10 | 32 of 88 races | HUL 2025 Silverstone P19→P3 |
| Points from grid P18 or lower | 8 / 6 / 13 / 9 | LIN 2026 Bahrain P22→P10 |
| Started ≥5 places behind qualifying position | 8 / 11 / 5 / 12 | VER 2024 Spa Q1→G11 (engine) |
| Career first points / podium / win / grid-1 | 9 / 4 / 3 / 3 since 2023 | HUL first podium 2025 Silverstone |
| Outside 107% in Q1 | 4 | Too rare |
| Pit lane start (grid 0) | 4 (2023–24 only) | Jolpica stopped using grid 0 |
| Deleted lap in Q1/Q2 that beat the cut-off, knocked out | 6 (time known) | Undercounts: from 2024 most deletions say "LAP DELETED" with no time |

---

## 3. Candidates

Working titles only. Shelf, frequency, fairness and confidence per
candidate. "Per season" = average over 2023–2025, with 2026 to round 18 noted
where it differs.

### OOF shelf

**3.1 Economy Class: a top-4 team driver knocked out in Q1**
- Trigger: driver of a team in the constructors' top 4 before the round
  (same rule as "surprise") eliminated in Q1.
- Frequency: 14 / 13 / 17 per season, ~0.6 per weekend. Silver.
- Fairness: high. A plain qualifying result, one driver.
- Examples: HAM 2024 China P18, PER 2024 Monaco P16 (one of six for PER in
  2024), TSU 2025 Imola P20, VER 2025 São Paulo P16.
- Tone: very OOF, a big name in the cheap seats. Cascade with Holding the
  Door (slowest in Q1 supersedes, or both stack: separate facts).

**3.2 Friendly Fire: incident with your own teammate**
- Trigger: an incident note in a race or sprint whose cars are exactly the
  two team-mates.
- Frequency: 20 in four seasons (17 race + 3 sprint), ~5 per season.
  Silver/Gold.
- Fairness: medium. Involvement, so **both drivers get it** and the caption
  never assigns blame. A Bumper Cars penalty still goes to whoever was
  penalised.
- Examples: GAS/OCO 2024 Monaco, NOR/PIA 2025 Montréal (start/finish
  straight), HAM/RUS 2023 Qatar lap 1, PER/BOT 2026 Shanghai (Cadillac),
  LAW/LIN 2026 three times (Shanghai, Spa, Baku).
- Tone: top-tier OOF. Pairs nicely with Family Feud (qualifying).

**3.3 Peaked Too Soon: started on pole, no podium**
- Trigger: grid 1 and finished P4 or lower, retired or DSQ.
- Frequency: 2 / 6 / 4 / 3, ~4 per season. Gold.
- Fairness: high (result rows). Mechanical DNFs (VER 2024 Melbourne brake
  fire) → CMS can suppress, like any DNF.
- Examples: LEC 2023 Austin (DSQ), VER 2024 Austria P5, NOR 2024 Austin P4,
  ANT 2026 Silverstone P15.

**3.4 Fool's Gold: fastest in practice, outside the top 10 in qualifying**
- Trigger: P1 in any practice session that weekend, qualified P11 or worse.
- Frequency: 10 sessions / 8 drivers in four seasons, ~2.5 per season.
  Gold. New data: needs the practice classification from `session_result`.
- Fairness: high. Two official classifications, one driver.
- Examples: STR 2024 China (FP1 top, Q11), PIA 2024 Mexico (FP3 top, Q17),
  LEC 2024 Abu Dhabi (FP1 top, Q14), VER 2023 Jeddah
  (topped all three, Q15: driveshaft failure, a CMS judgement call).
- Tone: lovely Friday-to-Saturday arc for the weekend slides.

**3.5 Nearly Famous: finished P11**
- Trigger: classified P11 in a race (first non-scoring place).
- Frequency: exactly one per race (88). Bronze.
- Fairness: high.
- Examples: HUL 13 times since 2023 (a running gag on its own), ALO and ALB
  8 each, BOR 7.
- Tone: gentle OOF; "unlocks every weekend" like Holding the Door.

**3.6 Lost by a Nose: finished less than 0.15 s behind the car ahead**
- Trigger: both cars on the lead lap; gap from Jolpica race times
  < 150 ms. Sprints included.
- Frequency: about 20 in four seasons, ~5 per season. Silver.
- Fairness: high, but needs care: post-race time penalties reorder
  positions (negative gaps showed up for 2023 Baku and Qatar sprints), so
  only use positive gaps after penalties.
- Examples: RUS 0.009 s behind OCO (2023 Austria sprint), NOR 0.023 s
  behind HAM (2023 Zandvoort), OCO 0.030 s behind LIN (2026 Baku), BOR
  0.042 s behind DOO (2025 Jeddah).
- Tone: OOF for the loser; the mirror image of By a Whisker.

**3.7 Note Sent Home, any subject: black-and-white flag for a reason other
than track limits**
- Trigger: B&W flag with any other offence. Proposed as an extension or
  variant of Note Sent Home to Parents, with the reason as subtitle
  ("Crossing the pit entry line", "Driving unnecessarily slowly",
  "Weaving").
- Frequency: 69 in four seasons (21 / 11 / 6 / 31). Silver.
- Fairness: high (one car, an official flag).
- Examples: HAM, HUL, RUS 2026 Barcelona Q (driving slowly, the 2026
  energy-harvesting rule); SAI and HAM 2023 Jeddah (weaving); GAS, SAR, HUL,
  BOT 2023 Las Vegas FP2 (crossing the pit entry line).
- Note: practice entries break the "practice achievements only for race
  drivers" rule unless filtered; 25 of the 26 pit-entry flags are practice.

**3.8 Wrong Place, Wrong Time: qualifying lap deleted for double yellows**
- Trigger: "TIME … DELETED - DOUBLE YELLOW" in qualifying or sprint
  qualifying (practice from 2026 if wanted).
- Frequency: qualifying 11 / 15 / 12 / 31 messages. Silver.
- Fairness: high (one car named). No fault implied: it's bad luck, the
  caption should say so.
- Examples: NOR 2024 São Paulo Q (one of ten that session), ANT 2025 Las
  Vegas Q, HAM 2026 Austria Q, LIN 2026 Baku Q.
- Cascade: So Close should supersede it when it was the best Q3 lap.

**3.9 Front-Runner's Remorse: led the most laps, didn't win**
- Trigger: most laps led (from per-lap positions) but not P1 at the flag.
- Frequency: 0 / 4 / 3 / 4, ~3 per season. Gold.
- Fairness: medium-high. Laps led are derived from line-crossing times
  (OpenF1 laps); Jolpica's lap_times table is empty, so no second source yet.
- Examples: VER 2024 Austria (62 laps, P5), PIA 2025 Silverstone (36, P2),
  RUS 2026 Monza (40, P2).

**3.10 Lap One Freefall: lost 8+ places on lap 1**
- Trigger: grid position minus position after lap 1 ≤ −8, driver
  classified or retired later than lap 1 (Early Lunch covers lap-1 DNFs).
- Frequency: 20 driver-races in 88 races, ~5 per season. Silver.
- Fairness: **medium. Usually somebody else's fault.** Must be phrased like
  Lapped Dog (it happened to you), never as blame.
- Examples: PIA 2024 Abu Dhabi P2→P19, RUS 2023 Qatar P2→P18, PER 2023
  Mexico P5→P20, LEC 2024 Austria P6→P18.

**3.11 Would Have Made It: knocked out with a deleted lap that beat the
cut-off**
- Trigger: knocked out in Q1 or Q2; a lap deleted in that phase was faster
  than the slowest driver who went through. Phase from session-status
  messages (the `QualifyingPhases` helper So Close already uses).
- Frequency: 6 known in four seasons. Gold/Platinum. Undercounts: from 2024
  most deletions are "LAP DELETED" without the time, so fill it in from the
  matching OpenF1 lap.
- Fairness: high.
- Examples: PER 2023 Austria Q2 (two deleted laps, both fast enough),
  SAR 2023 Jeddah Q1, HAM 2023 Austria SQ1, STR 2025 Austin Q1.
- Simplest as an extension of So Close to Q1 and Q2.

**3.12 So Near, So Far: retired while in the top 3**
- Trigger: not classified, and P1–P3 on the last lap they completed (lap 2
  onwards).
- Frequency: 7 in four seasons, ~2 per season. Gold.
- Fairness: high for the fact; the cause is usually mechanical, so CMS
  review like Home Before Dinner. Supersedes Home Before Dinner.
- Examples: RUS 2026 Montréal (from the lead, lap 29), PIA 2026 Hungary
  (P2, lap 55), LEC 2026 Monaco (P3, lap 64), LEC 2023 Bahrain (P3).

**3.13 Garage Day: no time set in a practice session**
- Trigger: practice classification with no time, while at most one other
  car also set no time (filters washouts and red-flagged FP1s).
- Frequency: 25 in four seasons, ~6 per season. Bronze.
- Fairness: medium. Usually mechanical (the team), sometimes a crash on the
  out-lap. Race drivers only.
- Examples: PER 2026 Melbourne FP2, Red Bull Ring FP2 and Hungary FP3
  (Cadillac); OCO 2024 Baku FP1 and FP3; MAG 2023 Zandvoort FP3.

**3.14 Scenic Lap: one lap far slower than everyone else**
- Trigger: a racing lap ≥25% slower than the field median for that lap
  (not lap 1, not pit in/out, not under SC/VSC/red).
- Frequency: 56 driver-races in 88 races. 2026 has three times more (37):
  check whether that's energy management before trusting it. Bronze.
- Fairness: medium. Spin, damage, puncture, mechanical: could be anyone's
  fault. Best linked to an off-track or incident message for evidence.
- Examples: NOR 2024 Austria L64 (puncture), DEV 2023 Montréal L35,
  HAD 2025 Qatar L55.

**3.15 Spare Parts: started 5+ places behind the qualifying position**
- Trigger: Jolpica grid ≥ qualifying position + 5 (excludes pit lane
  starts).
- Frequency: 8 / 11 / 5 / 12, ~9 per season. Bronze/Silver.
- Fairness: **low-medium.** The cause (component penalty for the team, or a
  driving penalty carried over) isn't in the data. Without the stewards'
  documents it can't say why.
- Examples: VER 2024 Spa Q1→G11, HAM 2024 Baku Q7→G19, NOR 2026 Spa
  Q3→G13.

**3.16 Do It Again, Properly: penalty for serving a penalty incorrectly**
- Trigger: time or drive-through penalty with offence "serving a penalty
  incorrectly".
- Frequency: 6 in four seasons. Platinum.
- Fairness: high (one car, penalty).
- Examples: ALO 2023 Jeddah, OCO 2023 Bahrain, HUL 2023 Monaco, RIC 2024
  Monza, TSU 2025 São Paulo (10 s each).
- Tone: peak OOF, a penalty for the penalty. Supersedes its own penalty
  achievement like every offence.

### Glory shelf

**3.17 First Time for Everything: career first points, podium, win or pole**
- Trigger: first points / podium / win / pole in the driver's career (full
  Jolpica history since 1950 is in the database).
- Frequency since 2023: first points 9, podium 4, win 3, pole (grid 1) 3;
  ~4–5 per season across all four. Gold (win: Platinum).
- Fairness: high.
- Examples: HUL first podium 2025 Silverstone (after 239 starts), PIA first
  win 2024 Hungary, ANT first win 2026 Shanghai, LIN first points 2026
  Melbourne on debut.
- Tone: big glory moments; Hülkenberg's podium is a story by itself.

**3.18 Charging Rhino: 10+ places gained grid→finish**
- Trigger: classified, grid − position ≥ 10. The glory mirror of Reverse
  Gear.
- Frequency: the race's biggest gain was ≥10 in 32 of 88 races, ~8 per
  season. Silver.
- Fairness: high (rain or chaos races inflate it; acceptable).
- Examples: HUL 2025 Silverstone P19→P3, VER 2024 São Paulo P17→P1, LEC
  2024 Abu Dhabi P19→P3. Took the Scenic Route supersedes it for a win.

**3.19 Back of the Bus: points from grid P18 or lower**
- Frequency: 8 / 6 / 13 / 9, ~9 per season. Bronze/Silver.
- Overlaps 3.18; pick one or cascade (Charging Rhino supersedes).
- Example: LIN 2026 Bahrain P22→P10, ALO 2026 Monaco P21→P10.

**3.20 Lap One Hero: 6+ places gained on lap 1**
- Frequency: best-of-race ≥6 in 13 of 88 races; 56 driver-races ≥5.
  Silver at ≥6.
- Fairness: medium (gains often come from others' crashes; still positive,
  no blame issue).
- Examples: MAG 2023 Austria P19→P8, STR 2023 Las Vegas P19→P9, ALO 2026
  Melbourne P17→P7.

**3.21 Benefit of the Doubt: deleted time reinstated**
- Frequency: 26 in four seasons (3 / 5 / 6 / 12). Bronze. Any session.
- Fairness: high (one car named).
- Examples: HUL 2026 United States FP1 (an 8:42.988 lap reinstated), PIA
  2024 Abu Dhabi Q, SAI 2024 Singapore race "LAP 1 WILL BE REINSTATED".
- Glory-ish, or a cheeky OOF counterpart to Colouring Outside the Lines.

**3.22 View from the Front: a midfield driver leads a race lap**
- Frequency: 5 in four seasons. Gold.
- Fairness: medium-high (derived laps led; often a pit-cycle lead).
- Examples: OCO 2024 São Paulo (14 laps), ALO 2023 Jeddah, TSU 2023 Abu
  Dhabi, GAS 2026 Monza.

**3.23 Friday Hero: a midfield driver tops a practice session**
- Frequency: 11 sessions, 5 drivers in four seasons (mostly 2023–24
  Aston Martin). Silver.
- Example: BOT 2023 Montréal FP1, GAS 2024 and 2025, RIC 2024 Bahrain FP1.
- Shares data with 3.4; Fool's Gold is the funnier half.

### Not recommended (and why)

| Event | Why not |
|---|---|
| 3.24 "Walked Free": investigated, no further action | Teases a driver for being cleared; breaks "involvement is not fault". |
| Impeding noted | Names both cars, not who impeded. Revisit with stewards' documents. |
| Most team radio | Measures broadcaster choice; collapsed and skewed after 2024. |
| Tyre flip-flops, most stints, longest stint | Whole-field rain events, noisy stint data, red-flag changes missing. |
| Lost the lead in the final laps | 5 cases, mostly team orders or strategy. |
| Most pit visits | Rain and chaos races; overlaps penalties and damage. |
| Weather-only events | Hits everyone; use as a modifier instead. |
| Reprimands, warnings | 5 messages in four seasons; the rest are in stewards' documents. |
| Lead changes, position churn | Race-level, no driver (lead changes could join the Safety Car corner). |
| Outside 107%, pit lane starts | Too rare / format changed. |

---

## 4. Top 15 by how well they'd work as an achievement

Ranked on: a frequency that fits a tier, can be pinned on one driver fairly,
reliable data, and the OOF or glory story.

| # | Candidate | Shelf | Frequency | Fairness | Example |
|---|---|---|---|---|---|
| 1 | Economy Class (top-4 team, out in Q1) | OOF | ~14/season | High | HAM 2024 China, P18 |
| 2 | Friendly Fire (incident with teammate) | OOF | ~5/season | Medium (both) | NOR/PIA 2025 Montréal |
| 3 | Peaked Too Soon (pole, no podium) | OOF | ~4/season | High | VER 2024 Austria, pole → P5 |
| 4 | Fool's Gold (topped practice, Q11+) | OOF | ~2.5/season | High | STR 2024 China, FP1 P1 → Q11 |
| 5 | First Time for Everything (career firsts) | Glory | ~4–5/season | High | HUL first podium, 2025 Silverstone |
| 6 | Nearly Famous (P11) | OOF | every race | High | HUL, 13 P11s since 2023 |
| 7 | Note Sent Home, any subject (B&W, not track limits) | OOF | ~17/season | High | HAM 2026 Barcelona Q, driving slowly |
| 8 | Lost by a Nose (< 0.15 s behind at the flag) | OOF | ~5/season | High | RUS 0.009 s behind OCO, 2023 Austria sprint |
| 9 | Do It Again, Properly (penalty for serving a penalty wrong) | OOF | ~1.5/season | High | HUL 2023 Monaco, 10 s |
| 10 | Wrong Place, Wrong Time (double-yellow deletion, quali) | OOF | ~12–30/season | High, no fault | NOR 2024 São Paulo Q |
| 11 | Charging Rhino (+10 places grid→finish) | Glory | ~8/season | High | HUL 2025 Silverstone, P19 → P3 |
| 12 | Front-Runner's Remorse (led most laps, no win) | OOF | ~3/season | Medium-high | VER 2024 Austria, 62 laps → P5 |
| 13 | So Near, So Far (retired while top 3) | OOF | ~2/season | High (CMS for mechanical) | RUS 2026 Montréal, from the lead |
| 14 | Would Have Made It (Q1/Q2 out, deleted lap beat cut-off) | OOF | ~1.5/season (undercount) | High | PER 2023 Austria Q2 |
| 15 | Lap One Freefall (−8 places on lap 1) | OOF | ~5/season | Medium (rarely their fault) | PIA 2024 Abu Dhabi, P2 → P19 |

Runners-up: Garage Day, Benefit of the Doubt, Lap One Hero, Back of the Bus,
Scenic Lap, Spare Parts, View from the Front, Friday Hero.

---

## Method notes

- Race control: every message run through `MessageClassifier` (offences via
  `Offence::fromReason`), counted by season and session type.
- Lap positions: order of line-crossing times per lap from `session_laps`
  (the start of the next lap, or start + duration). Lapped cars cross later,
  so they sort correctly. Jolpica `lap_times` is empty, so there's no
  cross-check.
- "Top-4 team" in this report = top 4 on that season's final points.
  Detectors should use the standings before the round, like the surprise
  rule.
- Session results and starting grid came straight from `raw_responses`
  (JSON), since they are not normalised.
- One-off analysis scripts were not committed; every figure can be
  reproduced with the definitions above.
