<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => OperatorStatus::Active,
            'callback_base_url' => 'https://'.$this->faker->domainName().'/orange',
            'secret_key' => Str::random(64),
            'ip_whitelist' => [],
            'config' => [],
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => OperatorStatus::Suspended]);
    }
}
