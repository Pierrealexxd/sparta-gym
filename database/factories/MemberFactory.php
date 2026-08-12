<?php

namespace Database\Factories;

use App\Models\Member;
use App\Support\GymContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        $genero = fake()->randomElement(['M', 'F']);
        $ingreso = fake()->dateTimeBetween('-3 years', 'now');

        return [
            'gym_id'     => GymContext::id(),
            'code'       => 'SP-' . fake()->unique()->numberBetween(1000, 9999),
            'qr_token'   => (string) Str::uuid(),
            'first_name' => fake()->firstName($genero === 'M' ? 'male' : 'female'),
            'last_name'  => fake()->lastName() . ' ' . fake()->lastName(),
            'document'   => (string) fake()->unique()->numberBetween(10000000, 79999999),
            'email'      => fake()->unique()->safeEmail(),
            'phone'      => '9' . fake()->numerify('########'),
            'birth_date' => fake()->dateTimeBetween('-55 years', '-16 years'),
            'gender'     => $genero,
            'emergency_contact' => fake()->name(),
            'emergency_phone'   => '9' . fake()->numerify('########'),
            // Rango realista para población adulta peruana.
            'height_cm'  => $genero === 'M'
                ? fake()->numberBetween(162, 186)
                : fake()->numberBetween(150, 172),
            'joined_at'  => $ingreso,
            'status'     => fake()->randomElement(['activo', 'activo', 'activo', 'activo', 'inactivo']),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => 'inactivo']);
    }
}
