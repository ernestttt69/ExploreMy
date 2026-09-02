<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('malaysian_places', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_place_id', 80);
            $table->string('name', 180);
            $table->string('display_name', 255);
            $table->string('admin1', 120)->nullable();
            $table->string('admin2', 120)->nullable();
            $table->string('country_code', 2)->default('MY');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('timezone', 80)->nullable();
            $table->json('provider_payload');
            $table->timestamps();
            $table->unique(['provider', 'provider_place_id']);
            $table->index(['country_code', 'name']);
        });

        Schema::table('itinerary_items', function (Blueprint $table) {
            $table->unsignedBigInteger('place_id')->nullable()->after('trip_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('itinerary_items', function (Blueprint $table) {
            $table->dropColumn('place_id');
        });

        Schema::dropIfExists('malaysian_places');
    }
};
