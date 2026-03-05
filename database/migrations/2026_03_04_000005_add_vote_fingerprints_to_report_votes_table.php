<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_votes', function (Blueprint $table): void {
            $table->string('ip_hash', 64)->nullable()->after('vote_type');
            $table->string('browser_id', 64)->nullable()->after('ip_hash');

            $table->index(['report_id', 'vote_type', 'ip_hash']);
            $table->index(['report_id', 'vote_type', 'browser_id']);
        });
    }

    public function down(): void
    {
        Schema::table('report_votes', function (Blueprint $table): void {
            $table->dropIndex(['report_id', 'vote_type', 'ip_hash']);
            $table->dropIndex(['report_id', 'vote_type', 'browser_id']);
            $table->dropColumn(['ip_hash', 'browser_id']);
        });
    }
};
