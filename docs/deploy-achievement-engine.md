# Runbook: deploying the achievement engine to production

Branch `achievement-engine` (75 commits on top of `main`, head 7dfdf00), written 22 November
2026. Follow the steps in order. Each step gives the command(s), what to check afterwards,
how long it takes, and what to do if it fails (the full rollback table is in step 11).

Placeholders used throughout:

- `<host>`: the SSH host of the production server. `bin/pull-db.sh` uses the alias `oof`.
- `<site-root>`: the Forge site directory. `bin/pull-db.sh` uses
  `~/oof-o6o894df.on-forge.com`, with the live release under `<site-root>/current`. That
  layout means Forge zero-downtime deployments: every deploy is a fresh release directory,
  while `storage/` and `.env` are shared.
- All `php artisan` commands run on the server in `<site-root>/current`, unless a step says
  otherwise.

Times are measured locally (Apple silicon Mac, Sail/Docker, PHP 8.5, a copy of the
production database plus the branch's backfill) unless marked as an estimate. For production
I assume 1.5 to 3 times slower, because the server's CPU is unknown.

---

## Open questions (please answer before the deploy)

1. **Forge deploy script.** Does it run `php artisan migrate --force` itself? Does it run
   `config:cache` / `optimize`? Is "Quick Deploy" (deploy on every push to `main`) on? The
   order of steps 3 and 4 depends on this. Please paste the script, or confirm it is the Forge
   zero-downtime default.
2. **Zero-downtime layout.** Is `<site-root>/current` a symlink to `<site-root>/releases/<id>`,
   as `bin/pull-db.sh` suggests? If it is, art copied into a release disappears on the next
   deploy, so step 6 puts the art in a shared folder and links it from every release. The
   link needs one line in the deploy script. Can I count on you to add it, or would you rather
   commit the art (149 MB) to git, or make `stamps.art_path` configurable through `.env`? The
   last two are code changes and not part of this runbook.
3. **PHP on the server.** Which version runs CLI and FPM? `composer.json` needs ^8.3; the
   branch is tested on 8.5. Is GD installed with PNG support? StampArt and BackgroundRemoval
   use GD only, no Imagick. What is the CLI `memory_limit`? Framing peaked at 92 MB locally.
   I did not measure the engine run's peak.
4. **What production's data already holds.** Are the 1950 to 2022 results already in the
   production database (they are in the local copy)? Has the Jolpica lap dump ever been
   imported there? `php artisan oof:status` in step 1 answers this. If the 1950 to 2022 results
   are missing, step 8b cannot run and this runbook needs an extra import step first.
5. **Maintenance window.** This runbook takes the site down with `php artisan down` for
   about 1.5 to 2.5 hours, so the scheduler stays quiet (see step 3). The CMS stays reachable
   through a secret bypass URL. Is that acceptable, or should the scheduler be paused in
   Forge instead?
6. **Backups.** Are Forge database backups (or provider snapshots) configured? This runbook
   makes its own dump anyway. How much free disk is on the server? The deploy needs about
   0.5 GB extra; see step 1.
7. **Decided awards.** Did anyone decide awards by hand in the production CMS (approve,
   suppress, hand back)? Decisions stay as they are, but plan deploy step 3 asks to check
   Overtime, Practice Makes Imperfect, Fine Dining, Nothing to See Here and Frequent Flyer.
   The query is in step 1.
8. **Art count.** You mentioned 101 art files. The working copy has 102 PNGs under
   `resources/images`: the 101 under `origami/achievements` (96 achievements and 5 variants)
   and one retired file, `origami/retired/high-five--duo.png`, which the app never reads.
   Should the retired file go to the server too? The rsync below copies the whole folder; add
   `--exclude='retired/'` to leave it out.
9. **Timing.** Plan deploy step 1 says to finish migrating before 04:50, when the scheduled
   `oof:dig` runs. With the site down the scheduler does not run, so this only matters if you
   choose to pause in Forge instead. Which day and time suits you? After the last session of a
   race weekend is best, so no new OpenF1 data arrives mid-deploy.

---

## Step 1. Pre-flight (read-only), about 15 minutes

On the server:

```bash
ssh <host>
cd <site-root>/current
php -v                                  # 8.3 or newer (8.5 tested)
php -m | grep -i -E '^gd$|^pdo_mysql$|^zip$'
php -r 'var_dump(gd_info()["PNG Support"] ?? false);'   # must print bool(true)
php -r 'echo ini_get("memory_limit"), PHP_EOL;'        # CLI: 512M or more, -1 is fine
df -h <site-root> /tmp                  # see the disk budget below
php artisan about | head -40
php artisan migrate:status | tail -5    # last ran on main: 2026_10_30_183902_add_handed_back_at_...
php artisan oof:status                  # what data is there (question 4)
crontab -l                              # the Forge scheduler: php artisan schedule:run every minute
ls -la <site-root>                      # current -> releases/..., shared storage, .env
```

`zip` is needed by `oof:import:dump` (ZipArchive). If it is missing, the dump import fails.
The API fallback still works but takes far longer.

Decided awards (question 7):

```bash
php artisan tinker --execute 'dump(DB::table("achievement_awards")->join("achievements","achievements.id","=","achievement_awards.achievement_id")->whereNotNull("achievement_awards.decided_at")->selectRaw("achievements.`key`, count(*) as n")->groupBy("achievements.key")->pluck("n","key")->all());'
```

Disk budget (local sizes):

| What | Size | Where |
|---|---|---|
| Art (`resources/images`) | 149 MB | shared folder, step 6 |
| Masks (`resources/stamp-masks`, in git) | 4.7 MB | every release |
| Framed and cut-out cache, 101 files | 131 MB | `storage/app/private/stamps/framed` (shared) |
| Preview copies (`oof:stamps:preview`) | 34 MB | `storage/app/private/stamps/preview` (can be deleted afterwards) |
| Jolpica dump | 14 MB | `storage/app/private/jolpica/dumps` |
| Database growth | about 50 MB | awards, run log, standings, lap times (local tables: awards 34 MB, runs 11 MB, lap times 52 MB) |
| Database dump (step 2) | about 100 to 200 MB gzipped | home directory, delete after a week |

Plan on at least 1 GB free.

Not needed: a queue worker. `QUEUE_CONNECTION=database`, but nothing on this branch is queued
(no `ShouldQueue`), so the events (standings updated, nickname proposed) run synchronously. The
branch adds no `.env` keys (`.env.example`, `composer.json` and `package.json` are unchanged
against `main`). Its new settings are all in config files: `config/achievements.php`,
`config/achievement_catalogue.php`, `config/stamps.php`, `config/official_poles.php`,
`config/curated_clean_sweeps.php`, `config/starting_position_rulings.php`,
`config/nicknames.php`.

Check: every line above gives the expected answer. If GD/PNG is missing, stop: install it
first (Forge: PHP extensions; on Ubuntu `php8.x-gd`).

---

## Step 2. Database backup, 2 to 10 minutes (estimate)

On the server. The credentials are read from `.env` and never typed:

```bash
cd <site-root>/current
mkdir -p ~/backups
set -a; . ./.env; set +a
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces --routines \
  -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" "$DB_DATABASE" \
  | gzip -c > ~/backups/oof-before-engine-$(date +%F-%H%M).sql.gz
unset DB_PASSWORD
ls -lh ~/backups/ && gzip -t ~/backups/oof-before-engine-*.sql.gz && echo OK
cp .env ~/backups/env-before-engine-$(date +%F-%H%M)
```

Also take a copy off the server, from the Mac:

```bash
scp <host>:~/backups/oof-before-engine-*.sql.gz ~/oof-backups/
```

Check: `gzip -t` prints OK, and the file is in the expected size range (the local database
is 566 MB uncompressed, including indexes).

Restore, only if a later step goes wrong (step 11):

```bash
cd <site-root>/current
set -a; . ./.env; set +a
gunzip -c ~/backups/oof-before-engine-<stamp>.sql.gz \
  | MYSQL_PWD="$DB_PASSWORD" mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" "$DB_DATABASE"
unset DB_PASSWORD
```

The dump holds `DROP TABLE IF EXISTS` per table, so it restores over the current tables.
Tables created by the new migrations (`team_lineages`, `oofscore_standings`,
`achievement_runs`, `driver_nicknames`, `nickname_evolutions`, `practice_results`,
`nationality_countries`) are not in the dump, so a restore over the live database would leave
them behind. Restore into an emptied database instead: drop and recreate it (`DROP DATABASE`,
`CREATE DATABASE`, with the name from `.env`), then run the import above. Never use
`migrate:fresh` on production. Then redeploy the old `main` commit 20e46c0 (step 11).

---

## Step 3. Maintenance mode, 1 minute

Laravel skips scheduled commands while the app is down. That matters here: the new schedule
runs `oof:achievements --changed --stale` every hour at :50, and after the deploy everything is
stale, so it would start a full run beside the manual one.

```bash
cd <site-root>/current
php artisan down --secret="<a-long-random-string>" --retry=60
```

The CMS stays reachable at `https://<domain>/<a-long-random-string>`, which sets a bypass
cookie. Maintenance mode is a file in the shared `storage/framework`, so it survives the new
release.

Check: the site returns 503, and the bypass URL opens `/_nimda`.

---

## Step 4. Merge and deploy, 5 to 10 minutes

`main` has no commits since the branch started (0 ahead of the merge base), so a fast-forward
merge keeps the history as it is, with no merge commit. On the Mac:

```bash
cd /Volumes/Secondary/Projects/Code/oof
git checkout main
git pull --ff-only origin main
git merge --ff-only achievement-engine
git push origin main
git push origin achievement-engine     # keep the branch on GitHub for reference
```

If "Quick Deploy" is on, this push starts the deploy. Otherwise press "Deploy Now" in Forge.
Before deploying, add the art link line from step 6 to the deploy script, if you chose the
shared-folder approach.

Check: the Forge deploy log ends without errors. `ls -la <site-root>/current` points at the new
release, and `git -C <site-root>/current log -1 --oneline` shows 7dfdf00.

There are no composer or npm changes, so the deploy builds nothing new.

---

## Step 5. Migrations, then the catalogue sync, 3 to 8 minutes (estimate)

Skip the `migrate` line if the deploy script already ran it, but check `migrate:status`.

```bash
cd <site-root>/current
php artisan migrate --force
php artisan migrate:status | tail -40
```

These 37 migrations run, in this order (Laravel orders them by name):

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

Then plan deploy step 1, part 2 (fills `practice_results` from the stored OpenF1 responses):

```bash
php artisan oof:normalize --all --source=openf1 --endpoint=session_result
```

Then the catalogue sync. Read the dry run first: it lists what it would create and every field
it would change.

```bash
php artisan oof:achievements:sync --dry-run
php artisan oof:achievements:sync
php artisan oof:achievements:sync --dry-run     # now: 0 created, 0 fields changed
```

The dry run should show creations only (the new achievements, Can't Park There among them)
and the mechanics fields of existing rows (sessions, recipient, scope, stacks and so on). Stop
if it lists a change to sessions or stacks you want kept (plan deploy step 2).

Check:

- `migrate:status` shows every migration as Ran.
- The second sync dry run shows nothing.
- Spot check:
  `php artisan tinker --execute 'dump(App\Models\Achievement::whereIn("key",["itchy_feet","cant_park_there","bumper_cars","early_bird"])->get(["key","shelf","tier","supersedes"])->toArray());'`
  - itchy_feet and cant_park_there are oof, gold, and supersede neither drive_thru nor red_light_green_light.
  - bumper_cars supersedes only it_was_like_that_when_i_got_there, high_five and sibling_rivalry.
  - early_bird is on the glory shelf (the pole).

### The "rename before sync" step

The plan doc ("Still open after Phase 12", item 3) says the Phase 12 migrations go with deploy
step 1, so that the Early Bird rename comes before the sync of step 2.

- On `main`, the false start is the OOF achievement with key `early_bird`. On this branch it
  is `itchy_feet`, and `early_bird` is a new glory achievement (the pole).
- The migration `2026_11_21_203558_rename_early_bird_to_itchy_feet` renames the existing OOF
  row in place, so its awards and any decisions move with it. It also rewrites every stored
  `supersedes` list that names the old key.
- If the sync ran first, it would find a row called `early_bird` and write the pole's mechanics
  onto it. Every false-start award would then turn into a pole award.
- Two guards prevent that, so a wrong order fails loudly instead of corrupting data:
  - `CatalogueSync::FORMER_KEYS` refuses to sync while an `early_bird` row sits on the OOF shelf.
  - The Phase 12c migration refuses to run before the rename.
- With one `php artisan migrate --force` before the sync (above), the order is right by
  itself. It only matters if the deploy script were to run the sync, or if `migrate` stops
  halfway.
- For the art this means the false-start art is named after the new key, `oof/itchy-feet.png`,
  and `glory/early-bird.png` is the pole's art. The local files already carry these names, so
  nothing needs renaming. Step 6 checks that every catalogue key finds its file.

If a migration fails: read the error. The migrations of this branch are written to be re-run
(they check before they change). Fix the cause, then run `php artisan migrate --force` again.
If that is not possible, restore the backup (step 11).

---

## Step 6. Art onto the server, 5 to 15 minutes (depends on the upload speed for 149 MB)

The art is not in git. Masks are in git (`resources/stamp-masks/birefnet-general/`, one per art
file, named by the art file's sha1), so a single changed byte in an art file means that file
finds no mask. The checksum check below matters.

`config/stamps.php` reads the art from `resource_path('images/origami/achievements')`, inside
the release. Put the art in a shared folder that survives deploys, and link it into each
release.

1. Dry run, then the copy, from the Mac:

   ```bash
   cd /Volumes/Secondary/Projects/Code/oof
   ssh <host> 'mkdir -p <site-root>/art/images'
   rsync -avn --checksum --exclude='.DS_Store' resources/images/ <host>:<site-root>/art/images/
   rsync -av  --checksum --exclude='.DS_Store' resources/images/ <host>:<site-root>/art/images/
   ```

   The trailing slashes matter: the contents of `resources/images` land in `art/images`.
   The command only reads `resources/images` locally.

2. Link it into the live release, and into every future release:

   ```bash
   ssh <host> 'ln -sfn <site-root>/art/images <site-root>/current/resources/images'
   ```

   In the Forge deploy script, after the release is created and before it is activated, add:

   ```bash
   ln -sfn $FORGE_SITE_PATH/art/images $FORGE_RELEASE_DIRECTORY/resources/images
   ```

   Check that these variable names match your deploy script (question 1). Without
   zero-downtime deploys, rsync straight into `<site-root>/resources/images/` instead and skip
   the link.

   Then rebuild the cached config, if the deploy script caches it:
   `php artisan config:cache`.

3. Checksums. On the Mac, write the list:

   ```bash
   cd resources/images && find . -name '*.png' -print0 | sort -z | xargs -0 shasum -a 1 > /tmp/oof-art.sha1 && wc -l /tmp/oof-art.sha1
   scp /tmp/oof-art.sha1 <host>:/tmp/
   ```

   On the server:

   ```bash
   cd <site-root>/art/images && sha1sum -c --quiet /tmp/oof-art.sha1 && echo ALL OK
   ```

4. Every catalogue achievement resolves to its file, and every file finds its mask. On the
   server, in `<site-root>/current`:

   ```bash
   php artisan tinker --execute 'dump(app(App\Achievements\Stamps\StampArt::class)->missing());'
   php artisan tinker --execute '$r = config("stamps.art_path"); $f = collect(Illuminate\Support\Facades\File::allFiles($r))->filter(fn ($x) => $x->getExtension() === "png"); dump(["files" => $f->count(), "without_mask" => $f->filter(fn ($x) => App\Achievements\Stamps\BackgroundRemoval::maskPath($x->getPathname()) === null)->map->getRelativePathname()->values()->all()]);'
   ```

Check:

- `ALL OK` from `sha1sum`, 102 lines (101 without `retired/`).
- `missing()` prints `[]`.
- `files` is 101 and `without_mask` is `[]`. This was measured locally on the same files: 101
  files, 5 variants, missing `[]`, no orphans, every file with its mask.

If it fails: a missing file means rsync again. A file without a mask means its checksum differs:
copy that file again. The art can be fixed at any time without touching the data.

---

## Step 7. Laps and nicknames (plan deploy steps 3, 4, 7, 8), 10 to 30 minutes (estimate)

Lap times feed laps led, Led Astray and Clean Sweep, so they come before the re-run.

```bash
cd <site-root>/current
php artisan oof:import:dump        # downloads the free Jolpica dump (14 MB), checks its SHA-256, imports race laps 1996 on
```

- It prints the dump's date. It is about 14 days behind, so the races after that date come
  from the API.
- Local: "seconds" for the dump itself (Phase 11a); writing 636,000 laps took a few minutes.

For each season with races newer than the dump (normally only the current one):

```bash
php artisan oof:import --only-laps --season=2026        # about 10 paced requests per race, 7.5 s apart: about 75 s per race
php artisan oof:normalize --laps --source=jolpica --season=2026
```

Then:

```bash
php artisan db:seed --class=DriverNicknameSeeder --force    # create-only
php artisan oof:nicknames                                     # checks for breakthroughs
php artisan oof:dig                                           # re-digs on the merged team lineage (also runs daily at 04:50)
```

Check:

- `oof:import:dump` ends with "Jolpica dump: N laps in M races". Warnings about unmatched
  laps (shared drives) are expected.
- `php artisan oof:status` shows lap times for the seasons from 1996.
- The seeder and `oof:nicknames` finish without errors.

If it fails: everything here is safe to re-run. If the dump download fails, `--file=` imports a
dump copied over by hand (the local one is
`storage/app/private/jolpica/dumps/jolpica-csv-2026-11-02.zip`). As a last resort, use the API
import per season, which is slow (hours).

---

## Step 8. Re-run the engine

Measured locally on 22 November 2026, all with nothing left to change (so pure run time),
followed by the golden master with 0 differences:

| Run | Contexts | Local |
|---|---|---|
| `oof:achievements --season=2023 ... --season=2026` | 455 sessions, 91 weekends, 3 seasons (2026 not final) | 53 s |
| One historic season, e.g. `--season=1975` | 14 sessions, 14 weekends, 1 season | 2 s |
| Every season, 1950 to 2026, in one command | 1,988 sessions, 1,170 weekends, 76 seasons | 285 s (4 min 45 s) |
| `oof:achievements --stale` with 1950 to 2023 stale | 1,636 sessions, 1,094 weekends, 73 seasons | 244 s |
| `oof:achievements:golden` (dry run of everything, compare) | 33,080 awards | 280 s, no differences |

On production, `--stale` on its own does not cover everything. It re-runs only what has run
before (`achievements_checked_at` set) or seasons from `live_from` (2023). Production has never
run 1950 to 2022, so those seasons are backfilled by hand, season by season (plan deploy step 9).
That gives three parts:

**8a. The live seasons.** Estimate 1.5 to 3 minutes on production.

```bash
cd <site-root>/current
php artisan oof:achievements --season=2023 --season=2024 --season=2025 --season=2026 \
  2>&1 | tee -a storage/logs/deploy-engine.log
```

**8b. The historic backfill, oldest first.** Estimate 5 to 12 minutes in all (2 to 4 s per
season locally, plus the start-up of each command).

```bash
for y in $(seq 1950 2022); do
  echo "== $y"; php artisan oof:achievements --season=$y || break
done 2>&1 | tee -a storage/logs/deploy-engine.log
```

**8c. Proof that nothing is stale any more.**

```bash
php artisan oof:achievements --stale 2>&1 | tail -5    # expect 0 sessions, 0 weekends, 0 seasons
php artisan oof:oofscore                               # plan deploy step 6; step 8a already rebuilt 2023 to 2026
```

In the background or in chunks:

- Every run is idempotent and keeps decisions made by people, so it can be split anywhere: by
  season (`--season=`), by round (`--round=`), by session type (`--session=`), or by scope
  (`--scope=session|weekend|season`).
- A run that breaks off can simply be started again.
- To keep it running after you log out, use `nohup` (or `tmux`/`screen`):

  ```bash
  nohup bash -c 'for y in $(seq 1950 2022); do php artisan oof:achievements --season=$y || exit 1; done' \
    > storage/logs/deploy-backfill.log 2>&1 &
  tail -f storage/logs/deploy-backfill.log
  ```

- Never run two engine runs on the same season at the same time: they would race on the same
  awards. The scheduler's own run uses `withoutOverlapping`, a manual run does not, which is one
  reason the site stays down until step 8c is done.
- If the CLI `memory_limit` is low, prefix with `php -d memory_limit=1G artisan ...`. The
  engine's peak memory was not measured.

Check:

- Every command ends with "N sessions, N weekends, N seasons" and "OOFscore rebuilt for ...",
  without an exception.
- 8c reports nothing left to do.

If it fails: note the season, fix the cause, and run that season again. Nothing is half-written
in a way a re-run does not repair. If the results look wrong (step 10), restore the backup
(step 11).

---

## Step 9. Framed stamps: warm the cache, estimate 4 to 9 minutes

The CMS frames a stamp on first view (`/_nimda/stamps/{key}.png`). A cold stamp costs about
1.7 s (up to 2.1 s for `oof/released-into-the-wild.png`), so an uncached achievements list
would take minutes and may time out. Warm the cache from the command line:

```bash
cd <site-root>/current
php artisan oof:stamps:preview
```

It frames every art file into the shared cache `storage/app/private/stamps/framed`, writes
preview copies to `storage/app/private/stamps/preview`, and ends with "Every catalogue
achievement has art."

Measured locally into an empty cache: 101 files in 174 s cold (about 1.7 s each), 0.4 s for
all 101 once cached, 131 MB of cache, 92 MB peak memory. The cache is keyed by each art file's
content and the settings, so it survives deploys, and only a changed art file or changed
setting is framed again.

Spot check in the browser (through the bypass URL from step 3, logged in):

- `/_nimda/achievements`: the Art column shows a thumbnail on every row.
- `/_nimda/stamps/cant_park_there.png`: the car on its grey pavement slab, the warden standing
  on it, no white rim.
- `/_nimda/stamps/itchy_feet.png`, `/_nimda/stamps/early_bird.png` (the pole, glory shelf),
  and one variant: open an achievement with variants and add `?variant=<label>`.
- Compare a few with the local copies in `~/oof-contact-sheets/`.

If it fails: an exception names the file. Check its checksum (step 6). A blank or 404 stamp
means `missing()` is not empty.

---

## Step 10. Smoke test, about 15 minutes

Still in maintenance mode, through the bypass URL.

Known values. They hold if production's 2023 to 2025 data equals the local copy; 2026 can
differ when production has newer sessions than the local copy.

```bash
php artisan tinker --execute 'dump(App\Models\AchievementAward::whereHas("achievement", fn ($q) => $q->where("key","oofscore_champion"))->with(["driver","season"])->get()->map(fn ($a) => [$a->season->year, $a->driver?->family_name, $a->detail])->sortBy(0)->values()->all());'
php artisan tinker --execute 'dump(App\Models\AchievementAward::whereHas("achievement", fn ($q) => $q->whereIn("key",["itchy_feet","cant_park_there","perfect_ten","drive_thru","red_light_green_light"]))->with("achievement")->get()->countBy(fn ($a) => $a->achievement->key)->all());'
php artisan tinker --execute 'dump(App\Models\AchievementAward::count());'
```

| Check | Expected (local) |
|---|---|
| OOFscore Champion 2025 | Tsunoda, 1365 points |
| OOFscore Champion 2024 | Hamilton, 1015 points |
| OOFscore Champion 2023 | Piastri, 785 points |
| Itchy Feet | 10 |
| Can't Park There | 7 |
| Awards in all (engine dry run) | 33,080 locally; production differs by whatever data production has that the local copy does not |

In the CMS:

- OOFscore standings: 2025 final top 5 is TSU 1365, ANT 1350, BEA 945, SAI 862.5, LAW 790. 2024
  is HAM 1015, MAG 952.5, PIA 845, NOR 835, ALB 792.5.
- Rounds, 2026 round 6 (Monaco), race: Perez has Can't Park There and Drive-Thru side by side;
  Russell has Double Detention and Drive-Thru.
- Rounds, 2024 round 20 (Mexico City), race: Perez has Can't Park There (5 s), not Itchy Feet.
- Awards list: filter on Itchy Feet. 10 rows, all "5 s", from RIC 2024 R9 to HAM 2026 R9.
- Rounds, 2026 round 1 (Australia): Colapinto has Red Light, Green Light, and no Can't Park There.
- One historic round, e.g. 1975: glory awards present, OOF awards scored 0.
- `storage/logs/laravel.log`: no new errors since the deploy started.

Then bring the site back:

```bash
php artisan up
```

After `up`, the scheduler resumes. At the next :50, `oof:achievements --changed --stale` should
find little or nothing. Check `storage/logs/achievements.log` an hour later.

---

## Step 11. Rollback, per step

| Step that fails | What to do |
|---|---|
| 1 Pre-flight | Nothing has changed. Fix the server (GD, zip, disk) or postpone. |
| 2 Backup | Nothing has changed. Do not continue without a good dump. |
| 3 Maintenance | `php artisan up`. |
| 4 Merge/deploy | Before the deploy: `git push --force-with-lease origin 20e46c0:main` puts `main` back (only if nothing else was pushed). After a failed deploy, Forge keeps the previous release active; a successful deploy of the wrong code is rolled back by redeploying 20e46c0, or by pointing `current` at the previous release in `<site-root>/releases`. The database is untouched until step 5. |
| 5 Migrations/sync | Fix and re-run `migrate --force` (the migrations check before they change). If the data is damaged: restore the dump into an empty database (step 2), redeploy 20e46c0, `php artisan up`. The `down()` methods exist, but `migrate:rollback` of 37 data migrations is not the way back: the dump is. |
| 6 Art | Rsync again; it never touches data. Remove the link (`rm <site-root>/current/resources/images`) to fall back to "no art" (the CMS shows no stamp, nothing breaks). |
| 7 Laps/nicknames | Safe to re-run. If it cannot finish, continue without it: Led Astray and Clean Sweep wait for laps, and the hourly import fills them later. |
| 8 Engine | Re-run the failed season. If the awards look wrong in step 10, restore the dump and redeploy 20e46c0, or keep the site down and investigate with `oof:achievements --dry-run --season=Y`. |
| 9 Stamps | Delete `storage/app/private/stamps/framed` and run `oof:stamps:preview` again. Data is not affected. |
| 10 Smoke test | A wrong known value: keep the site down, compare with the local run (`--dry-run`), and decide between a fix and a restore. |

Full restore in short: `php artisan down`, restore the dump into an empty database (step 2),
redeploy 20e46c0 in Forge, `php artisan up`. The art folder and the framed cache can stay; the
old code ignores them.

---

## Step 12. Afterwards

- Delete `storage/app/private/stamps/preview` (34 MB) if you do not need it.
- Keep `~/backups/oof-before-engine-*.sql.gz` for a week, then delete it.
- Do not close any season until the owner says so (plan deploy step 10).
- After any later catalogue or engine change: `php artisan oof:achievements --stale` (the
  hourly schedule also does this).

---

## Time estimate for the whole deploy

| Step | Estimate | Certainty |
|---|---|---|
| 1 Pre-flight | 15 min | known |
| 2 Backup and copy off the server | 5 to 10 min | depends on database size and connection |
| 3 Maintenance mode | 1 min | known |
| 4 Merge and deploy | 5 to 10 min | depends on the Forge deploy |
| 5 Migrations, practice results, sync | 3 to 8 min | uncertain: the constructor merge and the practice results normalize were never timed on production data |
| 6 Art upload and checks | 5 to 15 min | depends on upload speed (149 MB) |
| 7 Laps, nicknames, dig | 10 to 30 min | uncertain: the dump import on the server, the number of races newer than the dump (75 s each), and `oof:dig` |
| 8 Engine: live seasons, backfill, stale check | 8 to 18 min | measured locally (53 s + about 3.5 min + seconds), times 1.5 to 3 |
| 9 Stamp cache | 4 to 9 min | measured locally (174 s), times 1.5 to 3 |
| 10 Smoke test and `up` | 15 min | known |
| **Total** | **about 1 h 15 min to 2 h 15 min**; plan a 2.5-hour window | |

The uncertain parts:

- The production server's speed (all the 1.5 to 3 times factors).
- What production's data already holds (question 4). If the 1950 to 2022 results are missing,
  add an import step, measured in hours.
- The deploy script's behaviour (question 1).
- How many races are newer than the Jolpica dump.
- Upload speed for the art.
