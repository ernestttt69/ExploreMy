<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('preference_id');

            $table->unique(['user_id', 'preference_id']);
            $table->foreign('user_id')
                ->references('user_id')
                ->on('user')
                ->cascadeOnDelete();
            $table->foreign('preference_id')
                ->references('preference_id')
                ->on('preference_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
