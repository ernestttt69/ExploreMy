<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_caches', function (Blueprint $table) {
            $table->id();
            $table->string('location_key', 80);
            $table->date('forecast_date');
            $table->json('payload');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['location_key', 'forecast_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_caches');
    }
};
