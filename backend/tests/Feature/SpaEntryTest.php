<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.frontend_url', 'https://portal.test');
    }

    public function test_the_vue_login_route_redirects_to_the_frontend_application(): void
    {
        $this->get('/login')->assertRedirect('https://portal.test/login');
    }

    public function test_deep_links_and_query_strings_are_forwarded_to_the_frontend_application(): void
    {
        $this->get('/app/grantees?lang=tl')->assertRedirect('https://portal.test/app/grantees?lang=tl');
        $this->get('/student/documents')->assertRedirect('https://portal.test/student/documents');
    }
}
