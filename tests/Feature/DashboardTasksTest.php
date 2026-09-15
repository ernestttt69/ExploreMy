<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Trip;
use Tests\TestCase;

class DashboardTasksTest extends TestCase
{
    public function test_dashboard_excludes_finished_and_other_users_trips(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewHas('upcomingTrip', null);
        foreach ([[-5, -2, $user->getKey()], [1, 2, User::factory()->create()->getKey()], [3, 4, $user->getKey()]] as [$start, $end, $owner]) {
            $trip = Trip::create(['user_id' => $owner, 'title' => 'Test trip', 'destination' => 'Malaysia',
                'start_date' => today()->addDays($start), 'end_date' => today()->addDays($end)]);
        }
        $this->get(route('dashboard'))->assertOk()->assertViewHas('upcomingTrip', fn ($result) => $result->id === $trip->id);
    }
}
