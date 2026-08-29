<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->increments('wishlist_id');

            $table->unsignedInteger('user_id');
            $table->unsignedInteger('attraction_id');

            $table->foreign('user_id')
                ->references('user_id')
                ->on('user')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('attraction_id')
                ->references('attraction_id')
                ->on('attractions')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique(['user_id', 'attraction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
