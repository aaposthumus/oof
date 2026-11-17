<?php

use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every award gets its context (scope and key, season, round, session), its
 * recipient (type, season team, team), a snapshot of tier, shelf and points,
 * its variant, its calendar date, its source and the moment it first became
 * final. Existing awards are backfilled; the unique index moves from the
 * session to the context key, so weekend and season awards can follow.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->index('race_session_id');
            $table->foreignId('season_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->nullable()->after('season_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16)->nullable()->after('race_session_id');
            $table->string('context_key', 32)->nullable()->after('scope');
            $table->string('recipient_type', 16)->nullable()->after('context_key');
            $table->foreignId('season_team_id')->nullable()->after('driver_id')->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('season_team_id')->constrained()->nullOnDelete();
            $table->string('tier', 16)->nullable()->after('achievement_id');
            $table->string('shelf', 16)->nullable()->after('tier');
            $table->unsignedSmallInteger('points')->nullable()->after('shelf');
            $table->foreignId('achievement_variant_id')->nullable()->after('variant_name')->constrained()->nullOnDelete();
            $table->date('occurred_on')->nullable()->after('achievement_variant_id');
            $table->unsignedSmallInteger('occurred_md')->nullable()->after('occurred_on');
            $table->string('source', 16)->nullable()->after('occurred_md');
            $table->timestamp('finalized_at')->nullable()->after('decision_note');
        });

        DB::statement(<<<'SQL'
            UPDATE achievement_awards
            JOIN race_sessions ON race_sessions.id = achievement_awards.race_session_id
            JOIN rounds ON rounds.id = race_sessions.round_id
            SET achievement_awards.season_id = rounds.season_id,
                achievement_awards.round_id = rounds.id,
                achievement_awards.scope = 'session',
                achievement_awards.context_key = CONCAT('session:', race_sessions.id),
                achievement_awards.occurred_on = COALESCE(race_sessions.scheduled_date, rounds.date),
                achievement_awards.occurred_md = MONTH(COALESCE(race_sessions.scheduled_date, rounds.date)) * 100 + DAY(COALESCE(race_sessions.scheduled_date, rounds.date)),
                achievement_awards.source = 'engine',
                achievement_awards.finalized_at = IF(achievement_awards.status = 'final', COALESCE(achievement_awards.decided_at, achievement_awards.updated_at), NULL)
            SQL);

        DB::statement(<<<'SQL'
            UPDATE achievement_awards
            JOIN driver_entries ON driver_entries.id = achievement_awards.driver_entry_id
            JOIN season_teams ON season_teams.id = driver_entries.season_team_id
            SET achievement_awards.season_team_id = season_teams.id,
                achievement_awards.team_id = season_teams.team_id
            SQL);

        DB::statement(<<<'SQL'
            UPDATE achievement_awards
            JOIN achievement_variants ON achievement_variants.achievement_id = achievement_awards.achievement_id
                AND achievement_variants.driver_id = achievement_awards.driver_id
                AND achievement_variants.name = achievement_awards.variant_name
            SET achievement_awards.achievement_variant_id = achievement_variants.id
            SQL);

        foreach (Achievement::query()->get() as $achievement) {
            DB::table('achievement_awards')->where('achievement_id', $achievement->id)->update([
                'tier' => $achievement->tier?->value,
                'shelf' => $achievement->shelf->value,
                'points' => $achievement->points(),
                'recipient_type' => $achievement->recipientType()->value,
            ]);
        }

        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->unsignedBigInteger('season_id')->nullable(false)->change();
            $table->unsignedBigInteger('race_session_id')->nullable()->change();
            $table->string('scope', 16)->nullable(false)->change();
            $table->string('context_key', 32)->nullable(false)->change();
            $table->string('recipient_type', 16)->nullable(false)->change();
            $table->string('shelf', 16)->nullable(false)->change();
            $table->unsignedSmallInteger('points')->nullable(false)->change();
            $table->date('occurred_on')->nullable(false)->change();
            $table->unsignedSmallInteger('occurred_md')->nullable(false)->change();
            $table->string('source', 16)->nullable(false)->default('engine')->change();

            $table->dropUnique('achievement_awards_instance_unique');
            $table->unique(['context_key', 'achievement_id', 'instance_key'], 'achievement_awards_context_unique');
            $table->index('occurred_md');
            $table->index(['season_id', 'status']);
        });
    }

    /**
     * Reverse the migrations. Awards without a session (none exist yet) cannot go back.
     */
    public function down(): void
    {
        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->dropUnique('achievement_awards_context_unique');
            $table->dropIndex(['occurred_md']);
            $table->dropIndex(['season_id', 'status']);
            $table->unique(['race_session_id', 'achievement_id', 'instance_key'], 'achievement_awards_instance_unique');
            $table->unsignedBigInteger('race_session_id')->nullable(false)->change();
        });

        Schema::table('achievement_awards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('season_id');
            $table->dropConstrainedForeignId('round_id');
            $table->dropConstrainedForeignId('season_team_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('achievement_variant_id');
            $table->dropColumn(['scope', 'context_key', 'recipient_type', 'tier', 'shelf', 'points', 'occurred_on', 'occurred_md', 'source', 'finalized_at']);
            $table->dropIndex(['race_session_id']);
        });
    }
};
