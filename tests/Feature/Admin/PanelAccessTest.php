<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);
    }

    public function test_an_allow_listed_user_can_open_the_panel(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($user)->get('/_nimda')->assertOk();
    }

    public function test_the_allow_list_ignores_case(): void
    {
        $user = User::factory()->create(['email' => 'Admin@Example.com']);

        $this->actingAs($user)->get('/_nimda')->assertOk();
    }

    public function test_another_user_is_forbidden(): void
    {
        $user = User::factory()->create(['email' => 'someone@example.com']);

        $this->actingAs($user)->get('/_nimda')->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/_nimda')->assertRedirect('/_nimda/login');
    }

    public function test_an_empty_allow_list_lets_nobody_in(): void
    {
        config(['admin.emails' => []]);

        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($user)->get('/_nimda')->assertForbidden();
    }
}
