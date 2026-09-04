<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\State;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminAttractionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.access_code' => 'test-admin-code']);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.attractions.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_login_with_access_code(): void
    {
        $this->post(route('admin.authenticate'), ['access_code' => 'test-admin-code'])
            ->assertSessionHas('admin_authenticated', true)
            ->assertRedirect(route('admin.attractions.index'));
    }

    public function test_admin_can_create_update_and_delete_an_attraction(): void
    {
        $state = State::create(['state_name' => 'Johor']);
        $session = ['admin_authenticated' => true];

        $this->withSession($session)->post(route('admin.attractions.store'), [
            'place_id' => 'admin-test-place',
            'state_id' => $state->state_id,
            'attraction_name' => 'Admin Test Place',
            'category' => 'Family',
            'location' => 'Johor Bahru, Johor',
            'entrance_fee' => 'Free',
            'budget_level' => 'Free',
            'rating' => 4.2,
        ])->assertRedirect(route('admin.attractions.index'));

        $attraction = Attraction::where('place_id', 'admin-test-place')->firstOrFail();

        $this->withSession($session)->put(route('admin.attractions.update', $attraction), [
            'place_id' => 'admin-test-place',
            'state_id' => $state->state_id,
            'attraction_name' => 'Updated Admin Test Place',
            'location' => 'Iskandar Puteri, Johor',
            'entrance_fee' => 'RM 10',
            'budget_level' => 'Low',
            'rating' => 4.5,
        ])->assertRedirect(route('admin.attractions.index'));

        $this->assertDatabaseHas('attractions', [
            'attraction_id' => $attraction->attraction_id,
            'attraction_name' => 'Updated Admin Test Place',
        ]);

        $this->withSession($session)
            ->delete(route('admin.attractions.destroy', $attraction))
            ->assertRedirect();

        $this->assertDatabaseMissing('attractions', ['attraction_id' => $attraction->attraction_id]);
    }

    public function test_admin_can_add_view_and_delete_attraction_images(): void
    {
        $state = State::create(['state_name' => 'Selangor']);
        $session = ['admin_authenticated' => true];

        $this->withSession($session)->post(route('admin.attractions.store'), [
            'place_id' => 'admin-image-place',
            'state_id' => $state->state_id,
            'attraction_name' => 'Admin Image Place',
            'location' => 'Selangor',
            'entrance_fee' => 'Free',
            'budget_level' => 'Free',
            'images' => [UploadedFile::fake()->createWithContent(
                'first.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            )],
        ])->assertRedirect(route('admin.attractions.index'));

        $attraction = Attraction::where('place_id', 'admin-image-place')->firstOrFail();
        $image = $attraction->images()->firstOrFail();

        $this->withSession($session)->get(route('admin.attractions.edit', $attraction))
            ->assertOk()
            ->assertSee($image->image_path);

        $this->withSession($session)
            ->delete(route('admin.attractions.images.destroy', [$attraction, $image]))
            ->assertRedirect();

        $this->assertDatabaseMissing('attraction_image', ['image_id' => $image->image_id]);
        $this->assertNull($attraction->fresh()->image_path);
    }
}
