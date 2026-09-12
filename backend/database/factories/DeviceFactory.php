<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'nombre' => 'PC Laboratorio '.fake()->unique()->randomLetter(),
            'ubicacion' => 'Laboratorio principal',
        ];
    }
}
