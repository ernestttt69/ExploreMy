<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE trips MODIFY user_id INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE itinerary_shares MODIFY created_by INT UNSIGNED NOT NULL');

        Schema::table('trips', function (Blueprint $table): void {
            $table->foreign('user_id', 'trips_user_id_foreign')
                ->references('user_id')->on('user')->cascadeOnDelete()->cascadeOnUpdate();
        });
        Schema::table('itinerary_items', function (Blueprint $table): void {
            $table->foreign('trip_id', 'itinerary_items_trip_id_foreign')
                ->references('id')->on('trips')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('place_id', 'itinerary_items_place_id_foreign')
                ->references('id')->on('malaysian_places')->nullOnDelete()->cascadeOnUpdate();
        });
        Schema::table('itinerary_shares', function (Blueprint $table): void {
            $table->foreign('trip_id', 'itinerary_shares_trip_id_foreign')
                ->references('id')->on('trips')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by', 'itinerary_shares_created_by_foreign')
                ->references('user_id')->on('user')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('itinerary_shares', function (Blueprint $table): void {
            $table->dropForeign('itinerary_shares_trip_id_foreign');
            $table->dropForeign('itinerary_shares_created_by_foreign');
        });
        Schema::table('itinerary_items', function (Blueprint $table): void {
            $table->dropForeign('itinerary_items_trip_id_foreign');
            $table->dropForeign('itinerary_items_place_id_foreign');
        });
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropForeign('trips_user_id_foreign');
        });

        DB::statement('ALTER TABLE trips MODIFY user_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE itinerary_shares MODIFY created_by BIGINT UNSIGNED NOT NULL');
    }
};
