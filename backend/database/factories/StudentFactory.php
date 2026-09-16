<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            // Solo números, como las matrículas reales de la UDG (9 dígitos).
            'matricula' => fake()->unique()->numerify('#########'),
            'nombre' => fake()->name(),
            'carrera' => fake()->randomElement(['ISC', 'IIS', 'LCC', null]),
            'horas_meta' => 480,
            'estado' => 'activo',
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'inactivo']);
    }
}
