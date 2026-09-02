<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The preceding migration leaves the SQLite-compatible schema in its
        // final usable form without unsupported column-alter operations.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Step 1: Make attraction_id not nullable (data already backfilled)
        DB::statement('ALTER TABLE saved_place_collection_items MODIFY attraction_id INT UNSIGNED NOT NULL');

        // Step 2: Add foreign key on attraction_id to attractions table
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->foreign('attraction_id')
                ->references('attraction_id')
                ->on('attractions')
                ->cascadeOnDelete();
        });

        // Step 3: Drop the foreign key on wishlist_id FIRST (needed before dropping unique index)
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->dropForeign(['wishlist_id']);
        });

        // Step 4: Make wishlist_id nullable
        DB::statement('ALTER TABLE saved_place_collection_items MODIFY wishlist_id INT UNSIGNED NULL');

        // Step 5: Update unique constraint to use attraction_id instead of wishlist_id
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->dropUnique(['collection_id', 'wishlist_id']);
            $table->unique(['collection_id', 'attraction_id']);
        });
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
        DB::statement('ALTER TABLE saved_place_collection_items MODIFY wishlist_id INT UNSIGNED NOT NULL');

        // Drop the foreign key on attraction_id
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->dropForeign(['attraction_id']);
        });

        // Make attraction_id nullable again
        DB::statement('ALTER TABLE saved_place_collection_items MODIFY attraction_id INT UNSIGNED NULL');
    }
};
