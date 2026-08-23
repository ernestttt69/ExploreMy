<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attraction_image', function (Blueprint $table) {
            $table->increments('image_id');

            $table->unsignedInteger('attraction_id');
            $table->text('image_path');

            $table->foreign('attraction_id')
                ->references('attraction_id')
                ->on('attractions')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attraction_image');
    }
};