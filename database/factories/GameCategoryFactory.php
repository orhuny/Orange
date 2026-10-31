<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Models\GameCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameCategory>
 */
class GameCategoryFactory extends Factory
{
    protected $model = GameCategory::class;

    public function definition(): array
    {
        $slug = 'category-'.$this->faker->unique()->numberBetween(1000, 9999);

        return [
            'slug' => $slug,
            'name' => ucfirst($this->faker->word()),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
