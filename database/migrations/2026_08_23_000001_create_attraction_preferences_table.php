<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('attraction_preferences')) {
            return;
        }

        Schema::create('attraction_preferences', function (Blueprint $table) {
            $table->increments('attraction_preference_id');
            $table->unsignedInteger('attraction_id');
            $table->unsignedInteger('preference_id');
            $table->unique(['attraction_id', 'preference_id']);
            $table->foreign('attraction_id')->references('attraction_id')->on('attractions')->cascadeOnDelete();
            $table->foreign('preference_id')->references('preference_id')->on('preference_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attraction_preferences');
    }
};
