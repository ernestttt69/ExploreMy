<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\SavedPlaceCollection;
use App\Services\SystemGuideContext;
use Tests\TestCase;

class SystemGuideContextTest extends TestCase
{
    public function test_snapshot_uses_only_authenticated_user_and_omits_identity(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$owner, $other, $other] as $index => $user) {
            SavedPlaceCollection::create(['user_id' => $user->getKey(), 'name' => 'Private collection '.$index,
                'start_date' => today(), 'end_date' => today()]);
        }
        $this->actingAs($owner);
        $context = app(SystemGuideContext::class)->build();
        $this->assertStringContainsString('"collection_count":1', $context);
        $this->assertStringNotContainsString($owner->email, $context);
        $this->assertStringNotContainsString($other->email, $context);
        $this->assertStringNotContainsString('Private collection', $context);
        $this->assertStringContainsString('"latest_collection_place_count":0', $context);
    }
}
