<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('saved_attractions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('attraction_id');
            $table->timestamps();
            $table->unique(['user_id', 'attraction_id']);
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('attraction_id')->references('attraction_id')->on('attractions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_attractions');
    }
};
