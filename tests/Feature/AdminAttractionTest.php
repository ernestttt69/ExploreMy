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
        config(['services.cloudinary.cloud_name' => null]);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.attractions.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_cloud_photos_are_saved_as_urls_and_failed_edits_are_rolled_back(): void
    {
        config(['services.cloudinary.cloud_name' => 'test-cloud']);
        $cloud = $this->createMock(\App\Services\CloudinaryImageService::class);
        $calls = 0;
        $url = 'https://res.cloudinary.com/test-cloud/image/upload/photo.png';
        $cloud->expects($this->exactly(2))->method('upload')->willReturnCallback(function () use (&$calls, $url) {
            if (++$calls === 2) {
                throw new \RuntimeException('Cloudinary upload failed');
            }
            return $url;
        });
        $this->app->instance(\App\Services\CloudinaryImageService::class, $cloud);
        $state = State::create(['state_name' => 'Johor']);
        $photo = fn () => UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $data = ['place_id' => 'cloud-place', 'state_id' => $state->state_id,
            'attraction_name' => 'Cloud Place', 'location' => 'Johor',
            'entrance_fee' => 'Free', 'budget_level' => 'Free'];
        $this->withSession(['admin_authenticated' => true])->postJson(route('admin.attractions.store'),
            $data + ['images' => [$photo()]])->assertOk();
        $place = Attraction::where('place_id', 'cloud-place')->firstOrFail();
        $this->assertSame($url, $place->image_path);
        $this->assertSame($url, $place->images()->firstOrFail()->image_path);
        $data['attraction_name'] = 'Failed Edit';
        $this->putJson(route('admin.attractions.update', $place), $data + ['images' => [$photo()]])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame('Cloud Place', $place->fresh()->attraction_name);
        $this->assertSame(1, $place->images()->count());
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
