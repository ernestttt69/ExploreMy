<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_green_achievements', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('unlocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_green_achievements', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
        });
    }
};
