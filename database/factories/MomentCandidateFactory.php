<?php

namespace Database\Factories;

use App\Enums\MomentCandidateStatus;
use App\Models\ExcavationRun;
use App\Models\MomentCandidate;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MomentCandidate>
 */
class MomentCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'excavation_run_id' => ExcavationRun::factory(),
            'type' => 'fewest_starters',
            'round_id' => Round::factory(),
            'driver_id' => null,
            'season_team_id' => null,
            'score' => fake()->randomFloat(2, 0, 100),
            'evidence' => ['starters' => 10],
            'status' => MomentCandidateStatus::New,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => MomentCandidateStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => MomentCandidateStatus::Rejected]);
    }
}
