<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('green_wallets', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
        });

        Schema::create('green_reward_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('activity', 100);
            $table->integer('amount');
            $table->enum('transaction_type', ['earning', 'spending']);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('green_achievements', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('requirement_type');
            $table->unsignedInteger('requirement_value');
            $table->unsignedInteger('reward_points');
            $table->timestamps();
        });

        Schema::create('user_green_achievements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('achievement_id');
            $table->timestamp('unlocked_at');
            $table->unique(['user_id', 'achievement_id']);
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('achievement_id')->references('id')->on('green_achievements')->cascadeOnDelete();
        });

        Schema::create('green_shop_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->unsignedInteger('price');
            $table->unsignedInteger('exp_value');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('green_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('shop_item_id');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'shop_item_id']);
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('shop_item_id')->references('id')->on('green_shop_items')->cascadeOnDelete();
        });

        Schema::create('green_trees', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('experience')->default(0);
            $table->string('growth_stage')->default('Seedling');
            $table->timestamps();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
        });

        DB::table('green_achievements')->insert([
            ['slug' => 'first-green-step', 'name' => 'First green step', 'description' => 'Earn your first Green Points.', 'requirement_type' => 'points', 'requirement_value' => 1, 'reward_points' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'daily-visitor', 'name' => 'Daily visitor', 'description' => 'Claim the daily login reward.', 'requirement_type' => 'daily_login', 'requirement_value' => 1, 'reward_points' => 25, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'slow-traveller', 'name' => 'Slow traveller', 'description' => 'Complete a public transport journey.', 'requirement_type' => 'transport', 'requirement_value' => 1, 'reward_points' => 75, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'green-gardener', 'name' => 'Green gardener', 'description' => 'Grow your tree to level 3.', 'requirement_type' => 'tree_level', 'requirement_value' => 3, 'reward_points' => 150, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'green-guide', 'name' => 'Green guide', 'description' => 'Earn 2,000 Green Points.', 'requirement_type' => 'points', 'requirement_value' => 2000, 'reward_points' => 300, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'local-lover', 'name' => 'Local lover', 'description' => 'Save five destinations.', 'requirement_type' => 'wishlist_count', 'requirement_value' => 5, 'reward_points' => 100, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('green_shop_items')->insert([
            ['name' => 'Leaf Starter', 'description' => 'A gentle boost for a growing tree.', 'price' => 100, 'exp_value' => 50, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rainforest Mix', 'description' => 'Rich nutrients inspired by Malaysia\'s forests.', 'price' => 250, 'exp_value' => 140, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Canopy Crate', 'description' => 'A powerful blend for ambitious explorers.', 'price' => 500, 'exp_value' => 300, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('green_trees');
        Schema::dropIfExists('green_inventory');
        Schema::dropIfExists('green_shop_items');
        Schema::dropIfExists('user_green_achievements');
        Schema::dropIfExists('green_achievements');
        Schema::dropIfExists('green_reward_transactions');
        Schema::dropIfExists('green_wallets');
    }
};
