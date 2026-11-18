<?php

namespace Database\Factories;

use App\Enums\Breakthrough;
use App\Enums\EvolutionStatus;
use App\Models\DriverNickname;
use App\Models\NicknameEvolution;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NicknameEvolution>
 */
class NicknameEvolutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'to_nickname_id' => fn () => DriverNickname::factory()->evolvingFrom(DriverNickname::factory()->create())->create()->id,
            'driver_id' => fn (array $attributes) => DriverNickname::find($attributes['to_nickname_id'])->driver_id,
            'from_nickname_id' => fn (array $attributes) => DriverNickname::find($attributes['to_nickname_id'])->evolves_from_id,
            'season_id' => fn () => Season::firstOrCreate(['year' => 2027])->id,
            'reason' => Breakthrough::FirstPodium,
            'evidence' => ['rows' => []],
            'status' => EvolutionStatus::Proposed,
        ];
    }
}
