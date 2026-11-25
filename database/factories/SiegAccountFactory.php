<?php

namespace Database\Factories;

use App\Models\SiegAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiegAccount>
 */
class SiegAccountFactory extends Factory
{
    protected $model = SiegAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'name' => 'Conta '.Str::random(6),
            'api_key' => 'test-key-'.Str::random(24),
            'mode' => 'nsu',
            'enabled' => true,
            'consecutive_failures' => 0,
        ];
    }
}
