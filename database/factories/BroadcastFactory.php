<?php

namespace Database\Factories;

use App\Models\Broadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message' => fake()->sentence(),
            'status' => Broadcast::STATUS_SCHEDULED,
            'scheduled_at' => now(),
            'batch_size' => 1000,
            'batch_delay_minutes' => 0,
        ];
    }
}
