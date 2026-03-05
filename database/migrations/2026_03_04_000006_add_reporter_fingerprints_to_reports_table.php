<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('reporter_ip_hash', 64)->nullable()->after('category_id');
            $table->string('reporter_browser_id', 64)->nullable()->after('reporter_ip_hash');

            $table->index('reporter_ip_hash');
            $table->index('reporter_browser_id');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex(['reporter_ip_hash']);
            $table->dropIndex(['reporter_browser_id']);
            $table->dropColumn(['reporter_ip_hash', 'reporter_browser_id']);
        });
    }
};
