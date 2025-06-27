<?php

namespace Database\Factories;

use App\Models\MercadoLibreCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

class MercadoLibreCredentialFactory extends Factory
{
    protected $model = MercadoLibreCredential::class;

    public function definition()
    {
        return [
            'client_id' => $this->faker->unique()->numerify('########'),
            'client_secret' => $this->faker->sha256,
            'access_token' => $this->faker->sha256,
            'refresh_token' => $this->faker->sha256,
            'expires_at' => now()->addHours(6),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
} 