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
