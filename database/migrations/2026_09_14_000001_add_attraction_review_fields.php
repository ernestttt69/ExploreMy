<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attractions', function (Blueprint $table) {
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('verified_at')->nullable()->index();
            $table->boolean('is_visible')->default(true)->index();
        });
    }
    public function down(): void {
        Schema::table('attractions', fn (Blueprint $table) => $table->dropColumn(['source_url', 'verified_at', 'is_visible']));
    }
};
