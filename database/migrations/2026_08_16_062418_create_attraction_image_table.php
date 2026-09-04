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

            // The attractions table is created by a later legacy migration.
            // Its foreign key is added after both tables exist.
        });
    }

    public function down()
    {
        Schema::dropIfExists('attraction_image');
    }
};
