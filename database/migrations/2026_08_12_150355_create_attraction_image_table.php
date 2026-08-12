<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attraction_image', function (Blueprint $table) {
            $table->id('image_id');
            $table->unsignedBigInteger('attraction_id');
            $table->string('image_path', 255);

            $table->foreign('attraction_id')
                  ->references('attraction_id')
                  ->on('attractions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attraction_image');
    }
};