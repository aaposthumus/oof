<?php

namespace Database\Factories;

use App\Enums\NicknameStatus;
use App\Models\Driver;
use App\Models\DriverNickname;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverNickname>
 */
class DriverNicknameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'nickname' => ucfirst(fake()->unique()->word()).ucfirst(fake()->word()),
            'motto' => fake()->sentence(3),
            'stage' => 1,
            'status' => NicknameStatus::Active,
            'is_draft' => true,
        ];
    }

    /**
     * The planned next stage of a nickname.
     */
    public function evolvingFrom(DriverNickname $nickname): static
    {
        return $this->state(fn () => [
            'driver_id' => $nickname->driver_id,
            'stage' => $nickname->stage + 1,
            'evolves_from_id' => $nickname->id,
            'status' => NicknameStatus::Planned,
        ]);
    }
}
