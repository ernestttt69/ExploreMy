<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attraction_preferences', function (Blueprint $table) {
            $table->increments('attraction_preference_id');

            $table->unsignedInteger('attraction_id');
            $table->unsignedInteger('preference_id');

            $table->foreign('attraction_id')
                ->references('attraction_id')
                ->on('attractions')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('preference_id')
                ->references('preference_id')
                ->on('preference_categories')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attraction_preferences');
    }
};