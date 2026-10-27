<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminDecoyTest extends TestCase
{
    public function test_admin_returns_a_plain_404(): void
    {
        $this->get('/admin')->assertNotFound();
        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin/anything/else')->assertNotFound();
    }

    public function test_the_decoy_does_not_reveal_the_real_panel(): void
    {
        $this->get('/admin')->assertDontSee('_nimda');
    }
}
