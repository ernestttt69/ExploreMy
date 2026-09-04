<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add attraction_id column (nullable initially for backfill)
        if (! Schema::hasColumn('saved_place_collection_items', 'attraction_id')) {
            Schema::table('saved_place_collection_items', function (Blueprint $table) {
                $table->unsignedInteger('attraction_id')->nullable()->after('collection_id');
            });
        }

        // Step 2: Backfill attraction_id from existing wishlist records
        if (DB::getDriverName() !== 'sqlite') {
            DB::table('saved_place_collection_items as ci')
                ->join('wishlists as w', 'ci.wishlist_id', '=', 'w.wishlist_id')
                ->update(['ci.attraction_id' => DB::raw('w.attraction_id')]);
        }

        // The remaining MySQL-compatible changes are performed by the next
        // migration, without requiring the optional Doctrine DBAL package.
    }

    public function down(): void
    {
        // Restore the original unique constraint
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->dropUnique(['collection_id', 'attraction_id']);
            $table->unique(['collection_id', 'wishlist_id']);
        });

        // Restore the foreign key on wishlist_id with cascade
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->foreign('wishlist_id')
                ->references('wishlist_id')
                ->on('wishlists')
                ->cascadeOnDelete();
        });

        // Make wishlist_id not nullable again
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->unsignedInteger('wishlist_id')->nullable(false)->change();
        });

        // Drop the foreign key on attraction_id
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->dropForeign(['attraction_id']);
        });

        // Drop the attraction_id column
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->dropColumn('attraction_id');
        });
    }
};
