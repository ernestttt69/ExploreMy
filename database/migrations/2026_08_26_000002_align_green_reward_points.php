<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('green_achievements')->where('slug', 'first-green-step')->update(['reward_points' => 50]);
        DB::table('green_achievements')->where('slug', 'daily-visitor')->update(['reward_points' => 50]);
        DB::table('green_achievements')->where('slug', 'slow-traveller')->update(['requirement_type' => 'itinerary', 'reward_points' => 50]);
        DB::table('green_achievements')->where('slug', 'green-gardener')->update(['reward_points' => 150]);
        DB::table('green_achievements')->where('slug', 'green-guide')->update(['reward_points' => 150]);
        DB::table('green_achievements')->where('slug', 'local-lover')->update(['reward_points' => 100]);
    }

    public function down(): void
    {
        DB::table('green_achievements')->where('slug', 'first-green-step')->update(['reward_points' => 50]);
        DB::table('green_achievements')->where('slug', 'daily-visitor')->update(['reward_points' => 25]);
        DB::table('green_achievements')->where('slug', 'slow-traveller')->update(['requirement_type' => 'transport', 'reward_points' => 75]);
    }
};