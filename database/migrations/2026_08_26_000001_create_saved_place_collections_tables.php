<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('saved_place_collections', function (Blueprint $table) {
            $table->increments('collection_id');
            $table->unsignedInteger('user_id');
            $table->string('name', 80);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
        });

        Schema::create('saved_place_collection_items', function (Blueprint $table) {
            $table->increments('collection_item_id');
            $table->unsignedInteger('collection_id');
            $table->unsignedInteger('wishlist_id');
            $table->timestamps();

            $table->unique(['collection_id', 'wishlist_id']);
            $table->foreign('collection_id')->references('collection_id')->on('saved_place_collections')->cascadeOnDelete();
            $table->foreign('wishlist_id')->references('wishlist_id')->on('wishlists')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_place_collection_items');
        Schema::dropIfExists('saved_place_collections');
    }
};
