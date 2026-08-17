<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Support\GymContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prueba de cordura: la landing responde. Necesita el gimnasio con el
     * slug de config('sparta.gym_slug'), igual que en producción (sin él,
     * LandingController responde 503 a propósito).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $gym = Gym::create([
            'name'      => 'Sparta Gym',
            'slug'      => config('sparta.gym_slug', 'sparta-gym'),
            'is_active' => true,
        ]);
        GymContext::set($gym);

        $this->get('/')->assertStatus(200);
    }
}
