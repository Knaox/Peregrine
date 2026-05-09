<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ResourceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceTemplate>
 */
class ResourceTemplateFactory extends Factory
{
    protected $model = ResourceTemplate::class;

    public function definition(): array
    {
        // The `name` is unique at the DB level. Use the faker sequence to
        // avoid collisions across many factory invocations in a test run.
        return [
            'name' => 'tpl-'.$this->faker->unique()->slug(2),
            'ram' => $this->faker->randomElement([1024, 2048, 4096, 8192]),
            'cpu' => $this->faker->randomElement([100, 200, 300]),
            'disk' => $this->faker->randomElement([10240, 20480, 40960]),
            'swap_mb' => 0,
            'io_weight' => 500,
            'cpu_pinning' => null,
        ];
    }

    public function medium(): self
    {
        return $this->state([
            'name' => 'Medium-Medium',
            'ram' => 4096,
            'cpu' => 200,
            'disk' => 20480,
        ]);
    }
}
