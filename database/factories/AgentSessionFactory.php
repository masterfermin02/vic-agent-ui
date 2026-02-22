<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgentSession>
 */
class AgentSessionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->vicidialCredentials(),
            'campaign_id' => strtoupper(fake()->lexify('????')),
            'campaign_name' => fake()->words(3, true),
            'status' => 'ready',
            'asterisk_channel' => null,
            'current_lead_id' => null,
            'current_phone' => null,
            'current_lead_name' => null,
            'call_started_at' => null,
        ];
    }

    public function incall(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'incall',
            'asterisk_channel' => 'SIP/agent-'.fake()->numerify('####'),
            'current_lead_id' => (string) fake()->numberBetween(1000, 9999),
            'current_phone' => fake()->phoneNumber(),
            'current_lead_name' => fake()->name(),
            'call_started_at' => now(),
        ]);
    }

    public function wrapup(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'wrapup',
        ]);
    }
}
