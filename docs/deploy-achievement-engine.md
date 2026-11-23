# Runbook: deploying the achievement engine to production

Branch `achievement-engine` (head 7c17478 or later) onto `main` (20e46c0). Deploy on
**Monday evening 23 November 2026, Amsterdam time**, the day after the Las Vegas Grand Prix.
Follow the steps in order. Each step gives the command(s), what to check afterwards and how
long it takes. The rollback for each step is in step 12.

## How to run the commands

Everything runs from the Mac's Terminal. Server commands are one-liners:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && <command>"
```

- Chain commands with `&&` inside the quotes, and escape every `$` meant for the server as `\$`.
- Inside the quotes, `"` is written `\"`.
- Site root: `~/oof-o6o894df.on-forge.com`, with zero-downtime releases. `current` is a symlink
  to the live release in `releases/`, and `storage/` and `.env` are shared. The droplet also
  serves another site.
- Every one-liner below was tested locally with the same quoting (zsh on the Mac, bash on the
  other side).
- Mac commands run in `/Volumes/Secondary/Projects/Code/oof`.

## Decisions for this deploy

- **Push to deploy is turned off** before the merge and back on at the very end (step 11).
  A push to `main` then deploys nothing, and the deploy starts when you press "Deploy Now".
  That happens after the backup and with the site down, not at the moment of the push.
- **The migrations are taken out of the deploy script for this one deploy** and run by hand
  over SSH (step 6). Why:
  - Forge kills a deployment after 10 minutes.
  - The 37 migrations include data migrations (the historic constructor merge, the Early Bird
    rename, the award snapshot) that have never been timed on production data.
  - Composer, `npm ci` and `npm run build` already use part of the 10 minutes.
  - A migration killed halfway leaves a half-changed database (MySQL cannot roll back schema
    changes), and Forge would not activate the release.
  - By hand there is no time limit and every migration's output is visible.
  - The `migrate` line goes back into the script right after step 6.
- **Art lives in a shared folder** in the site root, `~/oof-o6o894df.on-forge.com/art/images`,
  linked into every release by one line in the deploy script (permanent).
- **No queue worker is needed** for this deploy (nothing on the branch is queued).
  `$RESTART_QUEUES` stays as it is.
- **Maintenance mode** for the whole window. There is no public site yet. Laravel skips
  scheduled commands while the app is down, so the hourly `oof:achievements --changed --stale`
  cannot start a full run beside the manual one.
- The retired `origami/retired/high-five--duo.png` stays local: only the 101 files the app uses
  go to the server.

## The Forge deploy script changes

Forge, site `oof-o6o894df.on-forge.com`, Deployments, Deploy Script. Keep your existing lines
exactly as they are. Make only these two edits.

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# NEW, permanent: the achievement art, shared across releases (not in git).
ln -sfn /home/oof/oof-o6o894df.on-forge.com/art/images $FORGE_RELEASE_DIRECTORY/resources/images

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( npm ci || npm install ) && npm run build     # your existing npm lines, unchanged

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link

# TEMPORARY, engine deploy 23 November 2026: migrations run by hand (runbook step 6).
# Restore this line after step 6.
# $FORGE_PHP artisan migrate --force

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

1. **Art link (permanent).** Add it directly after `cd $FORGE_RELEASE_DIRECTORY`. It must
   come before `$ACTIVATE_RELEASE()`. The release has no `resources/images` of its own (the art
   is untracked), so the link simply creates it. `/home/oof` must be the home folder of the
   `oof` user: the checklist confirms it.
2. **Migrations (temporary).** Put `# ` before `$FORGE_PHP artisan migrate --force`.
   - Undo: right after step 6 succeeds, remove the `# ` again (and the two comment lines above
     it) and save. The next deploy then runs `migrate`, which finds nothing pending.
   - Push to deploy is switched back on in step 11.

---

## Pre-deploy checklist (before step 1)

Do these on Monday before the window, in this order. Each item is one action plus what to
verify. Items 1 to 6 change nothing the running site uses.

1. **The Las Vegas data is in.** Forge's scheduler has imported the 22 November race.
   Verify: `ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:status"`
   shows 2026 R21 Las Vegas with results.
2. **The home folder for the art link.**
   `ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && echo \$HOME && readlink ~/oof-o6o894df.on-forge.com/current"`
   - The first line must be `/home/oof`. If not, use the printed path in the deploy script's
     `ln` line.
   - Note the release the second line prints (the old release, for the rollback):
     `releases/______________`.
3. **The local branch is clean and final.**
   `git status --short` shows only `?? resources/images/`, `git log --oneline -1` shows the
   runbook commit, and `git rev-list --count main..achievement-engine` is 76 or more.
4. **Optional: S3 backups** set up in Forge for this site. Not required: step 2's dump is the
   safety net.
5. **The art is on the server.** Do step 4 now. It only adds a folder that the old code never
   reads, and saves 5 to 15 minutes in the window.
6. **A bypass secret for maintenance mode.** Generate one and keep it at hand:
   `openssl rand -hex 16`. It is written `<secret>` below.
7. **Push to deploy OFF.** Forge, site, Deployments: switch off "Push to Deploy" (Quick
   Deploy). Verify that the toggle shows off.
8. **Deploy script edited** as in "The Forge deploy script changes" above (art line added,
   `migrate` commented out). Save. Verify by reading it back: one `ln -sfn` line after the
   `cd`, and the `migrate` line starts with `#`.
9. **Forge open** in the browser on the site's Deployments page, and this runbook open.
10. **Time:** start after dinner, with about 2.5 hours free. No race session is running on
    Monday, so no new data arrives mid-deploy.

---

## Step 1. Pre-flight, about 5 minutes

The answers from 22 November: PHP 8.5.13 CLI, `gd` and `zip` loaded, GD PNG support yes,
CLI `memory_limit` 512M (framing peaked at 92 MB locally), 29 GB free of 48 GB, database about
0.5 GB, 0 awards decided by hand, 4,236 awards from the older engine, lap_times 0.

Repeat the quick checks on the evening itself:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php -v | head -1 && php -r 'var_dump(gd_info()[\"PNG Support\"]);' && df -h ~ | tail -1 && git log -1 --oneline && php artisan migrate:status | tail -3 && php artisan oof:status | tail -25"
```

Check:

- PHP 8.5.x and `bool(true)`.
- More than 2 GB free.
- `20e46c0` is the live commit.
- The last migration is `2026_10_30_183902_add_handed_back_at_to_achievement_awards_table`.
- `oof:status` shows 2026 R21 with results.

---

## Step 2. Database backup, about 2 minutes

A local test of the same command on the local copy of the database (566 MB with indexes) gave
a 40 MB gzip in 5 seconds.

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && set -o pipefail && mkdir -p ~/backups && DB=\$(grep ^DB_DATABASE= .env | cut -d= -f2- | tr -d '\"') && U=\$(grep ^DB_USERNAME= .env | cut -d= -f2- | tr -d '\"') && P=\$(grep ^DB_PASSWORD= .env | cut -d= -f2- | tr -d '\"') && H=\$(grep ^DB_HOST= .env | cut -d= -f2- | tr -d '\"') && MYSQL_PWD=\"\$P\" mysqldump --single-transaction --no-tablespaces -h \"\${H:-127.0.0.1}\" -u \"\$U\" \"\$DB\" | gzip -c > ~/backups/oof-before-engine-2026-11-23.sql.gz && gzip -t ~/backups/oof-before-engine-2026-11-23.sql.gz && cp .env ~/backups/env-before-engine-2026-11-23 && ls -lh ~/backups/"
```

The credentials are read from the server's `.env` and never leave the server. Then keep a copy
on the Mac:

```bash
mkdir -p ~/oof-backups && scp oof@142.93.134.177:backups/oof-before-engine-2026-11-23.sql.gz ~/oof-backups/ && ls -lh ~/oof-backups/
```

Check: the command ends with the `ls` listing (`gzip -t` passed). The file is tens of MB, and
the copy on the Mac has the same size.

**Restore** (only from step 12). The dump drops and recreates every table it holds. The seven
tables created by the new migrations are not in it, so they are dropped first. This was tested
locally on a scratch database.

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && set -o pipefail && DB=\$(grep ^DB_DATABASE= .env | cut -d= -f2- | tr -d '\"') && U=\$(grep ^DB_USERNAME= .env | cut -d= -f2- | tr -d '\"') && P=\$(grep ^DB_PASSWORD= .env | cut -d= -f2- | tr -d '\"') && H=\$(grep ^DB_HOST= .env | cut -d= -f2- | tr -d '\"') && MYSQL_PWD=\"\$P\" mysql -h \"\${H:-127.0.0.1}\" -u \"\$U\" \"\$DB\" -e 'SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS team_lineages, oofscore_standings, achievement_runs, driver_nicknames, nickname_evolutions, practice_results, nationality_countries' && gunzip -c ~/backups/oof-before-engine-2026-11-23.sql.gz | MYSQL_PWD=\"\$P\" mysql -h \"\${H:-127.0.0.1}\" -u \"\$U\" \"\$DB\" && echo RESTORED"
```

---

## Step 3. Maintenance mode, 1 minute

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan down --secret=<secret> --retry=60"
```

- The CMS stays reachable: open `https://<site domain>/<secret>` once, which sets a bypass
  cookie, then `/_nimda`.
- The down file lives in the shared `storage/framework`, so the new release stays down too.

Check: the site answers 503. After the bypass URL, `/_nimda` opens.

---

## Step 4. Art onto the server, 5 to 15 minutes (upload speed for 149 MB)

Best done before the window (checklist item 5). It is safe at any time.

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && mkdir -p ~/oof-o6o894df.on-forge.com/art/images"
rsync -avn --checksum --exclude='.DS_Store' --exclude='origami/retired/' resources/images/ oof@142.93.134.177:oof-o6o894df.on-forge.com/art/images/
rsync -av  --checksum --exclude='.DS_Store' --exclude='origami/retired/' resources/images/ oof@142.93.134.177:oof-o6o894df.on-forge.com/art/images/
```

- The first `rsync` is a dry run: it must list 101 PNG files under `origami/achievements/`.
- The trailing slashes matter.
- `resources/images` is only read.

Checksums: the masks in git are named by each art file's sha1, so a changed byte means a lost
mask.

```bash
(cd resources/images && find ./origami/achievements -name '*.png' -print0 | sort -z | xargs -0 shasum -a 1) > /tmp/oof-art.sha1 && wc -l < /tmp/oof-art.sha1 && scp /tmp/oof-art.sha1 oof@142.93.134.177:/tmp/oof-art.sha1
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/art/images && sha1sum -c --quiet /tmp/oof-art.sha1 && find . -name '*.png' | wc -l && echo ALL OK"
```

Check: `101` on the Mac, then `101` and `ALL OK` on the server. The resolve and mask checks
need the new code, so they come in step 7.

---

## Step 5. Merge, push and deploy, about 5 minutes

Push to deploy is off (checklist item 7), so the push deploys nothing.

```bash
git checkout main && git pull --ff-only origin main && git merge --ff-only achievement-engine && git log --oneline -1 && git push origin main && git push origin achievement-engine
```

- `main` has no commits of its own since the branch started, so this is a fast-forward with no
  merge commit.
- Afterwards, `git checkout achievement-engine` if you keep working there.

Then press **Deploy Now** in Forge.

- The script runs composer, npm and `optimize`, without migrations. There are no composer or
  npm changes on the branch, so it is no slower than usual: well under the 10-minute limit.
- The new code then runs on the old database schema. That is harmless while the site is down,
  and step 6 follows at once.

Check:

- The Forge deploy log ends without errors.
- Then:

  ```bash
  ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && git log -1 --oneline && ls -la resources/ | grep images && ls resources/images/origami/achievements"
  ```

  The new head commit, `images -> /home/oof/oof-o6o894df.on-forge.com/art/images`, and the
  folders `glory oof safety-car`.

---

## Step 6. Migrations, practice results and the catalogue sync, 3 to 8 minutes (estimate)

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan migrate --force && php artisan migrate:status | tail -40"
```

These 37 migrations run, in this order:

```
2026_11_16_194131_align_achievements_with_the_concept
2026_11_16_194133_drop_points_from_achievements_table
2026_11_16_195214_add_qualifying_to_teachers_stare
2026_11_17_181459_create_team_lineages_table
2026_11_17_184410_add_three_historic_team_lineages
2026_11_17_185638_split_theodore_before_the_ensign_merger
2026_11_17_190406_add_context_and_snapshot_to_achievement_awards_table
2026_11_17_191524_add_oofscore_closed_at_to_seasons_table
2026_11_17_192240_add_withheld_on_sensitive_weekends_to_achievements_table
2026_11_17_192241_add_sensitivity_to_achievement_awards_table
2026_11_17_192243_add_source_and_decision_to_moment_sensitivities_table
2026_11_17_193516_hold_decisions_and_involved_drivers_for_sensitivity
2026_11_17_194614_create_oofscore_standings_table
2026_11_17_201648_add_score_category_to_achievements_table
2026_11_17_201649_add_score_multiplier_to_achievement_awards_table
2026_11_17_201651_allow_fractional_points_in_oofscore_standings_table
2026_11_17_213222_add_data_needs_to_achievements_table
2026_11_18_173400_add_scope_to_achievements_table
2026_11_18_173401_create_achievement_runs_table
2026_11_18_181236_update_most_collected_description
2026_11_18_182448_move_overtime_to_the_driver_score_category
2026_11_18_183828_add_recipient_type_to_achievements_table
2026_11_18_190011_create_driver_nicknames_table
2026_11_18_190013_create_nickname_evolutions_table
2026_11_18_193651_create_practice_results_table
2026_11_18_193652_add_is_manual_to_achievements_table
2026_11_18_200237_apply_phase_10_decisions_to_achievements
2026_11_19_182751_add_team_name_to_team_lineages_table
2026_11_19_182915_merge_historic_constructors_into_lasting_teams
2026_11_19_193444_update_nearly_famous_description_for_every_era
2026_11_21_203558_rename_early_bird_to_itchy_feet
2026_11_21_211506_add_side_to_achievements_table
2026_11_21_211507_create_nationality_countries_table
2026_11_21_213411_apply_phase_12c_to_achievements
2026_11_22_105004_update_food_award_descriptions
2026_11_22_183835_limit_what_itchy_feet_and_cant_park_there_supersede     (ff1d6de)
2026_11_22_185559_apply_the_penalty_tier_rule_to_offence_achievements     (7dfdf00)
```

Then the practice results (plan deploy step 1) and the catalogue sync (plan deploy step 2),
dry run first:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:normalize --all --source=openf1 --endpoint=session_result && php artisan oof:achievements:sync --dry-run"
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:achievements:sync && php artisan oof:achievements:sync --dry-run"
```

- The first dry run lists the achievements it creates (Can't Park There among them) and the
  mechanics fields it writes on existing rows. Read it before the real sync.
- The last dry run must show 0 created and 0 fields changed.

Spot check:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan tinker --execute 'dump(App\Models\Achievement::whereIn(\"key\", [\"itchy_feet\", \"cant_park_there\", \"bumper_cars\", \"early_bird\"])->get([\"key\", \"shelf\", \"tier\", \"supersedes\"])->map(fn (\$a) => \$a->key.\" \".\$a->shelf->value.\" \".\$a->tier->value.\" \".implode(\",\", \$a->supersedes))->all());'"
```

Expected (as locally):

```
bumper_cars oof silver it_was_like_that_when_i_got_there,high_five,sibling_rivalry
cant_park_there oof gold it_was_like_that_when_i_got_there,sibling_rivalry,high_five,perfect_ten
early_bird glory gold
itchy_feet oof gold it_was_like_that_when_i_got_there,high_five,perfect_ten,sibling_rivalry
```

Then **undo the temporary deploy script change**: remove the `# ` before
`$FORGE_PHP artisan migrate --force` (and the two TEMPORARY comment lines) and save. Push to
deploy stays off until step 11.

Check:

- Every migration shows "Ran".
- The second sync dry run is empty.
- The spot check matches.
- The deploy script has its `migrate` line back.

### The "rename before sync" step

The plan doc ("Still open after Phase 12", item 3) says the Phase 12 migrations must run before
the sync, so the Early Bird rename comes first.

- On `main`, the false start is the OOF achievement `early_bird`. On this branch it is
  `itchy_feet`, and `early_bird` is a new glory achievement (the pole).
- `2026_11_21_203558_rename_early_bird_to_itchy_feet` renames the existing row in place, so its
  awards move with it, and it rewrites every stored `supersedes` list that names the old key.
- If the sync ran first, it would write the pole's mechanics onto the old `early_bird` row and
  turn every false-start award into a pole award.
- Two guards make a wrong order fail loudly:
  - `CatalogueSync::FORMER_KEYS` refuses to sync while an `early_bird` row sits on the OOF shelf.
  - The Phase 12c migration refuses to run before the rename.
- Running `migrate` in full before the sync, as above, satisfies it.
- For the art: the false-start art is `oof/itchy-feet.png` and the pole's is
  `glory/early-bird.png`. The files already carry these names, so nothing needs renaming. Step 7
  checks that every key finds its file.

If a migration fails: read the error, fix the cause, run the same command again (the migrations
check before they change). If the data is damaged, restore (step 12).

---

## Step 7. Art resolves on the server, 2 minutes

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan tinker --execute 'dump(app(App\Achievements\Stamps\StampArt::class)->missing());' && php artisan tinker --execute '\$r = config(\"stamps.art_path\"); \$f = collect(Illuminate\Support\Facades\File::allFiles(\$r))->filter(fn (\$x) => \$x->getExtension() === \"png\"); dump([\"files\" => \$f->count(), \"without_mask\" => \$f->filter(fn (\$x) => App\Achievements\Stamps\BackgroundRemoval::maskPath(\$x->getPathname()) === null)->map->getRelativePathname()->values()->all()]);'"
```

Check: `[]` (every catalogue achievement resolves to its file), then `files => 101` and
`without_mask => []`. Locally the same check gives exactly this.

If it fails: a missing key means the file is missing or misnamed, so rsync again (step 4). A
file without a mask means its checksum differs: copy it again.

---

## Step 8. Laps and nicknames (plan deploy steps 3, 4, 7, 8), 10 to 30 minutes (estimate)

Production has no lap times yet (lap_times 0). Laps led, Led Astray and Clean Sweep need them,
so this comes before the engine run.

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:import:dump"
```

- It downloads the free Jolpica dump (about 14 MB, SHA-256 checked) and writes every race lap
  from 1996 onward: about 636,000 laps.
- It prints the dump's date, about 14 days behind, so a dump from early November misses the
  last two or three races (2026 R19 to R21).
- Not timed on an empty table: estimate 2 to 10 minutes.

The races newer than the dump come from the API, at about 75 seconds per race.
`--only-laps` skips every round whose race already has lap times (from the dump) and is final
(race date plus 3 days), and counts it as skipped, so only the races the dump misses are
fetched. `--refresh` fetches every round again. Before this skip existed (the deploy of
23 November 2026), the same command fetched all 21 rounds of 2026 again: 259 pages, about
35 minutes.

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:import --only-laps --season=2026 && php artisan oof:normalize --laps --source=jolpica --season=2026"
```

Then the nicknames and the dig:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan db:seed --class=DriverNicknameSeeder --force && php artisan oof:nicknames && php artisan oof:dig"
```

Check:

- "Jolpica dump: N laps in M races". Warnings about unmatched laps (shared drives) are
  expected.
- `oof:status` shows lap times from 1996.
- No errors from the seeder, `oof:nicknames` or `oof:dig`.

Plan deploy step 3 (awards decided by hand on Overtime and so on) is settled: there are 0 on
production.

If it fails: all of it is safe to re-run. If the download fails, copy the local dump over and
use `--file=`:

```bash
scp storage/app/private/jolpica/dumps/jolpica-csv-2026-11-02.zip oof@142.93.134.177:/tmp/
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:import:dump --file=/tmp/jolpica-csv-2026-11-02.zip"
```

That older dump leaves more races for the API.

---

## Step 9. Re-run the engine, 8 to 18 minutes (estimate)

Measured locally on 22 November 2026, with nothing left to change (pure run time), followed by
the golden master with 0 differences:

| Run | Contexts | Local |
|---|---|---|
| Live seasons 2023 to 2026 | 455 sessions, 91 weekends, 3 seasons | 53 s |
| One historic season (1975) | 14 sessions, 14 weekends, 1 season | 2 s |
| Every season 1950 to 2026, one command | 1,988 sessions, 1,170 weekends, 76 seasons | 285 s |
| `--stale` with 1950 to 2023 stale | 1,636 sessions, 1,094 weekends, 73 seasons | 244 s |
| Golden master afterwards | 33,080 awards | 280 s, no differences |

On production, `--stale` alone covers only what ran before, or seasons from `live_from` (2023).
1950 to 2022 have never run there, so they are backfilled season by season (plan deploy step 9).

**9a. The live seasons**, about 1.5 to 3 minutes:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:achievements --season=2023 --season=2024 --season=2025 --season=2026 2>&1 | tee -a storage/logs/deploy-engine.log | tail -5"
```

**9b. The historic backfill, oldest first**, about 5 to 12 minutes. It runs in the background on
the server, so a dropped connection does not stop it:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && nohup bash -c 'for y in \$(seq 1950 2022); do echo \"== \$y\"; php artisan oof:achievements --season=\$y || exit 1; done; echo BACKFILL DONE' > storage/logs/deploy-backfill.log 2>&1 < /dev/null &"
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && tail -3 storage/logs/deploy-backfill.log"
```

- `< /dev/null` matters: without it the job keeps the connection's stdin and the SSH session
  hangs until the job ends. Any command sent to the background over SSH needs it.
- Repeat the second line every minute or two until it shows `BACKFILL DONE`. If it stops at
  a season instead, the last `== year` names it.

**9c. Proof and standings:**

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:achievements --stale 2>&1 | grep INFO && php artisan oof:oofscore"
```

Check: 9c says `0 sessions, 0 weekends, 0 seasons`, and no run logged an exception.

About chunks:

- Every run is idempotent and keeps decisions made by people. Split by `--season=`,
  `--round=`, `--session=` or `--scope=` as needed, and re-run any season that broke off.
- Never run two engine runs on the same season at once.
- 512M CLI memory is fine for framing. The engine's peak was not measured: if a run dies with
  "Allowed memory size", add `-d memory_limit=1G` after `php`.

---

## Step 10. Warm the stamp cache, 4 to 9 minutes (estimate)

The CMS frames a stamp on first view at about 1.7 s each, which is too slow for a list. Warm it
from the command line:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan oof:stamps:preview | tail -3"
```

- Measured locally into an empty cache: 101 files in 174 s, slowest
  `oof/released-into-the-wild.png` at 2.1 s, then 0.4 s for all 101 warm.
- 131 MB of cache in the shared `storage/app/private/stamps/framed`, plus 34 MB of preview copies.
- The cache survives deploys: only changed art or settings are framed again.

Check: "Every catalogue achievement has art." In the browser (bypass cookie, logged in):

- `/_nimda/achievements`: a thumbnail on every row.
- `/_nimda/stamps/cant_park_there.png`: the car and the warden on the grey slab, with no white rim.
- `/_nimda/stamps/itchy_feet.png` and `/_nimda/stamps/early_bird.png`.
- One variant: an achievement page with variants, adding `?variant=<label>`.

---

## Step 11. Smoke test, `up`, Push to deploy back on, about 15 minutes

Production has one round more than the local copy (2026 R21 Las Vegas), so **2026 values will
differ**. 1950 to 2025 must match the local values exactly.

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan tinker --execute 'dump(App\Models\AchievementAward::whereHas(\"achievement\", fn (\$q) => \$q->where(\"key\", \"oofscore_champion\"))->with([\"driver\", \"season\"])->get()->map(fn (\$a) => [\$a->season->year, \$a->driver?->family_name, \$a->detail])->sortBy(0)->values()->all());'"
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan tinker --execute 'dump(App\Models\AchievementAward::whereHas(\"achievement\", fn (\$q) => \$q->whereIn(\"key\", [\"itchy_feet\", \"cant_park_there\"]))->with([\"achievement\", \"season\"])->get()->countBy(fn (\$a) => \$a->achievement->key.\" \".\$a->season->year)->sortKeys()->all());'"
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan tinker --execute 'dump(App\Models\AchievementAward::whereHas(\"season\", fn (\$q) => \$q->where(\"year\", \"<=\", 2025))->count());'"
```

The expected values below include the post-deploy fixes of 23 November 2026 (branch
`post-deploy-fixes`): Outside the Lines needs 2 or more deleted lap times in a session, and a
team without form is no surprise. The values of the deploy itself were: champions Piastri 785,
Hamilton 1015 and Tsunoda 1365, 31,752 awards in 1950 to 2025, and 1,100, 1,225 and 1,301
awards in 2023, 2024 and 2025.

| Check | Expected |
|---|---|
| OOFscore Champion 2023 | Piastri, 745 points |
| OOFscore Champion 2024 | **Magnussen, 902.5 points** |
| OOFscore Champion 2025 | **Tsunoda, 1295 points** |
| Itchy Feet per season | 2024: 4, 2025: 5, 2026: 1 or more |
| Can't Park There per season | 2023: 3, 2024: 2, 2025: 1, 2026: 1 or more |
| Outside the Lines per season | 2023: 70, 2024: 84, 2025: 64 |
| Nothing to See Here per season | 2023: 82, 2024: 66, 2025: 71 |
| Awards in seasons 1950 to 2025 | 31,430 |
| Awards per season | 2023: 996; 2024: 1,118; 2025: 1,190 |

In the CMS:

- OOFscore standings 2025 (final): TSU 1295, ANT 1270, BEA 885, SAI 762.5, LAW 720.
- OOFscore standings 2024: MAG 902.5, HAM 885, PIA 795, ALB 762.5, PER 750.
- OOFscore standings 2023: PIA 745, RUS 670, SAR 600, STR 590, PER 585.
- Rounds, 2026 R6 Monaco, race: Perez has Can't Park There and Drive-Thru; Russell has Double
  Detention and Drive-Thru.
- Rounds, 2024 R20 Mexico City: Perez has Can't Park There (5 s), not Itchy Feet.
- Rounds, 2026 R1 Australia: Colapinto has Red Light, Green Light and no Can't Park There.
- Rounds, 2026 R21 Las Vegas (new on production): awards present, nothing pending that looks
  wrong.
- One historic round (e.g. 1975): glory awards present, OOF awards have 0 points and no
  OOFscore weighting (–).
- No new errors in the log:
  `ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && tail -20 storage/logs/laravel.log"`.

Then:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && php artisan up"
```

Forge: switch **Push to Deploy back on**. Check that the deploy script has the art line and
the `migrate` line (uncommented).

An hour later, the scheduler's run at :50 should find little or nothing:

```bash
ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && tail -20 storage/logs/achievements.log"
```

---

## Step 12. Rollback, per step

Old commit: `20e46c0`. Old release: noted in checklist item 2.

| Step that fails | What to do |
|---|---|
| Checklist, 1 Pre-flight | Nothing changed. Fix or postpone. Undo the deploy script edits and switch Push to deploy back on if you postpone. |
| 2 Backup | Nothing changed. Do not continue without a good dump. |
| 3 Maintenance | `php artisan up`. |
| 4 Art | Rsync again. It never touches data. |
| 5 Merge/deploy | A failed Forge deploy keeps the old release active. Nothing ran against the database yet: `php artisan up`, and put `main` back with `git push --force-with-lease origin 20e46c0:main` (Push to deploy is off, so nothing deploys). A deploy that succeeded but must go: redeploy 20e46c0 (after the force push), or point `current` at the old release: `ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com && ln -sfn releases/<old> current"`. |
| 6 Migrations/sync | Fix and re-run. If the data is damaged: restore (step 2), put `main` back to 20e46c0 and redeploy (with the `migrate` line back, it finds nothing to run), then `up`. |
| 7 Art resolve | Fix the files (step 4). No data involved. |
| 8 Laps/nicknames | Safe to re-run. If it cannot finish: continue without it. Led Astray and Clean Sweep wait for laps, and the hourly import fills them later. |
| 9 Engine | Re-run the failed season. If the results look wrong in step 11: keep the site down and compare with a local `--dry-run`, or restore (step 2) and go back to 20e46c0. |
| 10 Stamps | Delete `storage/app/private/stamps/framed` and run `oof:stamps:preview` again. |
| 11 Smoke test | A wrong 2023 to 2025 value: keep the site down, compare with the local copy, and decide between a fix and a full restore. |

Full restore in short: `down`, restore the dump (step 2), `git push --force-with-lease origin
20e46c0:main`, Deploy Now, `up`. The shared art folder and the framed cache can stay; the old
code ignores them. Keep the art line in the deploy script; it is harmless.

---

## Step 13. Afterwards

- Delete the preview copies (34 MB):
  `ssh oof@142.93.134.177 "cd ~/oof-o6o894df.on-forge.com/current && rm -rf storage/app/private/stamps/preview"`.
- Keep `~/backups/oof-before-engine-2026-11-23.sql.gz` (server and Mac) for a week. Set up the
  S3 backups if not done yet.
- Do not close any season until the owner says so (plan deploy step 10). When closing one over
  SSH, pass `--force`: `php artisan oof:oofscore:close` asks for confirmation, and over SSH
  without a terminal `confirm()` reads nothing and aborts. The alternative is `ssh -t`, which
  gives the command a terminal so you can answer the question.
- After any later catalogue or engine change: `php artisan oof:achievements --stale` (the
  hourly schedule also does this).

---

## Time estimate

Before the window, on Monday (checklist): about 30 minutes, including the art upload (5 to 15
minutes, depending on upload speed).

The window itself:

| Step | Estimate | Certainty |
|---|---|---|
| 1 Pre-flight | 5 min | known |
| 2 Backup and copy to the Mac | 2 to 5 min | local test: 5 s for the dump; the copy depends on the connection |
| 3 Maintenance | 1 min | known |
| 5 Merge, push, Deploy Now | 5 min | the usual deploy (composer and npm, no changes) |
| 6 Migrations, practice results, sync, deploy script undo | 5 to 10 min | uncertain: the constructor merge and the normalize were never timed on production data; no time limit by hand |
| 7 Art resolve | 2 min | known |
| 8 Laps, nicknames, dig | 10 to 30 min | uncertain: the dump import into an empty lap table, 2 or 3 races from the API at 75 s each, and `oof:dig` |
| 9 Engine: live seasons, backfill, proof | 8 to 18 min | measured locally (53 s + about 3.5 min + seconds), times 1.5 to 3 for a shared droplet |
| 10 Stamp cache | 4 to 9 min | measured locally (174 s), times 1.5 to 3 |
| 11 Smoke test, `up`, Push to deploy on | 15 min | known |
| **Total** | **about 1 hour to 1 h 40 min** | plan a 2-hour window |

The uncertain parts:

- The droplet's speed, shared with another site: all the "times 1.5 to 3" figures.
- The first import of 636,000 laps.
- How many races are newer than the Jolpica dump.
- The data migrations and `oof:dig` on production data.

If step 8 runs long, the window can stretch to 2.5 hours without risk: the site is down and
nothing runs beside it.
