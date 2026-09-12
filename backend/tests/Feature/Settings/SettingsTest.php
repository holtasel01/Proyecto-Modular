<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_admin_can_view_settings(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::set('min_confidence_reject', '0.50');

        $response = $this->actingAs($admin)->getJson('/api/settings');

        $response->assertOk();
        $this->assertContains('min_confidence_reject', array_column($response->json('data'), 'key'));
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patchJson('/api/settings', [
            'min_confidence_reject' => 0.4,
            'duplicate_window_seconds' => 45,
        ]);

        $response->assertOk();
        $this->assertSame('45', Setting::get('duplicate_window_seconds'));
    }

    public function test_min_confidence_trust_must_be_at_least_the_reject_threshold(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patchJson('/api/settings', [
            'min_confidence_reject' => 0.8,
            'min_confidence_trust' => 0.5, // menor que el rechazo, inválido
        ])->assertStatus(422);
    }

    public function test_settings_reject_out_of_range_values(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patchJson('/api/settings', [
            'min_confidence_reject' => 1.5,
        ])->assertStatus(422);
    }

    public function test_student_cannot_view_or_change_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/settings')->assertStatus(403);
        $this->actingAs($user)->patchJson('/api/settings', ['duplicate_window_seconds' => 10])->assertStatus(403);
    }
}
