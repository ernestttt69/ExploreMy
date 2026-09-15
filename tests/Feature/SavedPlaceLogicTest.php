<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SavedPlaceLogicTest extends TestCase
{
    public function test_collection_creation_returns_a_card_without_redirecting(): void
    {
        // The production independence migration skips its table rebuild on SQLite.
        // Match its final schema so this test exercises the production insert path.
        Schema::drop('saved_place_collection_items');
        Schema::create('saved_place_collection_items', function ($table) {
            $table->increments('collection_item_id');
            $table->unsignedInteger('collection_id');
            $table->unsignedInteger('attraction_id');
            $table->unsignedInteger('wishlist_id')->nullable();
            $table->unique(['collection_id', 'attraction_id']);
            $table->timestamps();
        });
        $user = User::factory()->create();
        $state = State::create(['state_name' => 'Johor']);
        $attraction = Attraction::create([
            'place_id' => 'ajax-collection-place', 'state_id' => $state->state_id,
            'attraction_name' => 'Collection Place', 'location' => 'Johor',
        ]);
        $this->actingAs($user)->postJson(route('attractions.wishlist.add', $attraction->getKey()))->assertOk();
        $payload = [
            'name' => '<Trip>', 'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(), 'attraction_ids' => [$attraction->getKey()],
        ];
        $response = $this->postJson(route('saved-places.collections.store'), $payload)
            ->assertCreated()->assertJsonStructure(['message', 'html']);
        $this->assertNull($response->json('redirect'));
        $this->assertStringContainsString('&lt;Trip&gt;', $response->json('html'));
        $this->assertStringContainsString('Collection Place', $response->json('html'));
        $this->assertStringContainsString(__('saved_extra.add_remaining', ['count' => 1]), $response->json('html'));
        $this->get(route('saved-places.index'))->assertOk()->assertSee('id="collection-list"', false);
        $this->postJson(route('saved-places.collections.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $payload['name'] = 'Invalid place';
        $payload['attraction_ids'] = [99999999];
        $this->postJson(route('saved-places.collections.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('attraction_ids');

        $payload['name'] = 'Empty trip';
        unset($payload['attraction_ids']);
        $emptyResponse = $this->postJson(route('saved-places.collections.store'), $payload)
            ->assertCreated()->assertJsonStructure(['html']);
        $this->assertStringContainsString(__('saved_extra.add_remaining', ['count' => 2]), $emptyResponse->json('html'));
        $collection = \App\Models\SavedPlaceCollection::where('name', 'Empty trip')->firstOrFail();
        $this->assertSame(0, $collection->items()->count());
        $add = $this->postJson(route('saved-places.collections.places.store', $collection->getKey()), [
            'attraction_ids' => [$attraction->getKey()],
        ])->assertOk()->assertJsonStructure(['html']);
        $this->assertNull($add->json('redirect'));
        $this->assertSame(1, $collection->items()->count());
        $item = $collection->items()->first();
        $remove = $this->deleteJson(route('saved-places.collections.places.destroy', [$collection->getKey(), $item->getKey()]))
            ->assertOk()->assertJsonStructure(['html']);
        $this->assertNull($remove->json('redirect'));
        $this->assertSame(0, $collection->items()->count());
        $this->assertStringContainsString('data-update-collection', $remove->json('html'));
    }

    public function test_saving_and_removing_a_place_updates_admin_counts_without_legacy_table(): void
    {
        $this->assertFalse(Schema::hasTable('saved_attractions'));
        $user = User::factory()->create();
        $state = State::create(['state_name' => 'Johor']);
        $attraction = Attraction::create([
            'place_id' => 'saved-place-test',
            'state_id' => $state->state_id,
            'attraction_name' => 'Saved Place Test',
            'location' => 'Johor',
        ]);

        $this->actingAs($user)->postJson(route('attractions.wishlist.add', $attraction->getKey()))->assertOk();
        $this->postJson(route('attractions.wishlist.add', $attraction->getKey()))->assertOk();
        $this->assertSame(1, $user->savedAttractions()->count());
        $this->assertSame(1, $attraction->savedByUsers()->count());
        $this->withSession(['admin_authenticated' => true])->get(route('admin.attractions.index'))
            ->assertOk()->assertViewHas('attractions', fn ($rows) => $rows->first()->saved_by_users_count === 1);

        $this->deleteJson(route('attractions.wishlist.remove', $attraction->getKey()))->assertOk();
        $this->assertSame(0, $attraction->savedByUsers()->count());
        $this->assertSame(0, $user->savedAttractions()->count());
    }

    public function test_migration_preserves_legacy_saves_and_deduplicates_existing_saves(): void
    {
        $user = User::factory()->create();
        $state = State::create(['state_name' => 'Johor']);
        $keys = [];
        foreach (['first', 'second'] as $name) {
            $attraction = Attraction::create([
                'place_id' => $name, 'state_id' => $state->state_id,
                'attraction_name' => $name, 'location' => 'Johor',
            ]);
            $keys[] = ['user_id' => $user->getKey(), 'attraction_id' => $attraction->getKey()];
        }
        (require database_path('migrations/2026_08_19_000005_create_saved_attractions_table.php'))->up();
        DB::table('saved_attractions')->insert($keys);
        DB::table('wishlists')->insert($keys[0]);
        $migration = require database_path('migrations/2026_09_05_000001_consolidate_saved_attractions_into_wishlists.php');
        $migration->up();
        $this->assertFalse(Schema::hasTable('saved_attractions'));
        $this->assertDatabaseCount('wishlists', 2);
        foreach ($keys as $key) {
            $this->assertDatabaseHas('wishlists', $key);
        }
        $migration->down();
        $this->assertDatabaseCount('saved_attractions', 2);
        $this->assertDatabaseCount('wishlists', 2);
        $migration->up();
    }
}
