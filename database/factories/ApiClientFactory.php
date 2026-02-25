<?php

namespace Database\Factories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'token_hash' => hash('sha256', Str::random(64)),
            'scopes' => ['events:read'],
            'allowed_origins' => null,
            'allowed_network_ids' => null,
            'rate_limit_per_minute' => 120,
            'active' => true,
            'expires_at' => null,
            'last_used_at' => null,
        ];
    }
}
