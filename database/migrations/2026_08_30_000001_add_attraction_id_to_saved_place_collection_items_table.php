<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Add attraction_id column (nullable initially for backfill)
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->unsignedInteger('attraction_id')->nullable()->after('collection_id');
        });

        // Step 2: Backfill attraction_id from existing wishlist records
        // Update each record individually so this also works with SQLite. SQLite
        // does not support MySQL's joined UPDATE syntax used by the query builder.
        DB::table('saved_place_collection_items as ci')
            ->join('wishlists as w', 'ci.wishlist_id', '=', 'w.wishlist_id')
            ->select('ci.collection_item_id', 'w.attraction_id')
            ->orderBy('ci.collection_item_id')
            ->each(function ($item) {
                DB::table('saved_place_collection_items')
                    ->where('collection_item_id', $item->collection_item_id)
                    ->update(['attraction_id' => $item->attraction_id]);
            });

        // Laravel 9 requires the optional Doctrine DBAL package to alter SQLite
        // columns. The column is nullable only for the legacy backfill; new
        // collection items are validated by the application before insertion.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // Step 3: Make attraction_id not nullable after backfill
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->unsignedInteger('attraction_id')->nullable(false)->change();
        });

        // Step 4: Add foreign key on attraction_id to attractions table
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->foreign('attraction_id')
                ->references('attraction_id')
                ->on('attractions')
                ->cascadeOnDelete();
        });

        // Step 5: Make wishlist_id nullable and remove cascade delete
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->unsignedInteger('wishlist_id')->nullable()->change();
        });

        // Step 6: Drop the foreign key constraint on wishlist_id (to remove cascade)
        Schema::table('saved_place_collection_items', function (Blueprint $table) {
            $table->dropForeign(['wishlist_id']);
        });

        // Step 7: Update unique constraint to use attraction_id instead of wishlist_id
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
