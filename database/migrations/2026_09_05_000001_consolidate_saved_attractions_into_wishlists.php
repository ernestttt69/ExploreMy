<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saved_attractions')) {
            return;
        }

        // Preserve legacy saves without duplicating existing wishlist entries.
        DB::table('saved_attractions')->orderBy('id')->chunkById(500, function ($saves): void {
            foreach ($saves as $save) {
                $key = ['user_id' => $save->user_id, 'attraction_id' => $save->attraction_id];
                if (! DB::table('wishlists')->where($key)->exists()) {
                    DB::table('wishlists')->insert($key);
                }
            }
        });

        Schema::drop('saved_attractions');
    }

    public function down(): void
    {
        if (! Schema::hasTable('saved_attractions')) {
            (require __DIR__.'/2026_08_19_000005_create_saved_attractions_table.php')->up();
        }

        DB::table('wishlists')->orderBy('wishlist_id')->chunkById(500, function ($saves): void {
            foreach ($saves as $save) {
                $key = ['user_id' => $save->user_id, 'attraction_id' => $save->attraction_id];
                if (! DB::table('saved_attractions')->where($key)->exists()) {
                    DB::table('saved_attractions')->insert($key + ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        }, 'wishlist_id');
    }
};
