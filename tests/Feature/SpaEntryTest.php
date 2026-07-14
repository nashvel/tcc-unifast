<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaEntryTest extends TestCase
{
    public function test_the_vue_application_shell_is_served(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('UniFAST TES');
    }

    public function test_deep_links_are_handled_by_the_spa_fallback(): void
    {
        $this->get('/app/grantees')->assertOk();
        $this->get('/student/documents')->assertOk();
    }
}
