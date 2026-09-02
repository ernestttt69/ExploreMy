<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_guests_can_access_the_explore_homepage()
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_guests_are_redirected_to_login_when_saving_a_place()
    {
        $response = $this->post('/attractions/1/wishlist');

        $response->assertRedirect('/login');
    }
}
