<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->unsignedInteger('confirmations_count')->default(0)->after('category_id');
            $table->unsignedInteger('clear_votes_count')->default(0)->after('confirmations_count');
        });

        DB::table('reports')
            ->where('status', 'pending')
            ->update(['status' => 'unverified']);

        DB::table('reports')
            ->where('status', 'published')
            ->update([
                'status' => 'confirmed',
                'confirmations_count' => 3,
            ]);

        DB::table('reports')
            ->where('status', 'rejected')
            ->update([
                'status' => 'resolved',
                'clear_votes_count' => 3,
            ]);

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('status')->default('unverified')->change();
        });
    }

    public function down(): void
    {
        DB::table('reports')
            ->where('status', 'unverified')
            ->update(['status' => 'pending']);

        DB::table('reports')
            ->whereIn('status', ['partially_confirmed', 'confirmed'])
            ->update([
                'status' => 'published',
                'confirmations_count' => 0,
            ]);

        DB::table('reports')
            ->where('status', 'resolved')
            ->update([
                'status' => 'rejected',
                'clear_votes_count' => 0,
            ]);

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('status')->default('pending')->change();
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn(['confirmations_count', 'clear_votes_count']);
        });
    }
};
