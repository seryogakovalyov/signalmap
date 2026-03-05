<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('vote_type');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'vote_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_votes');
    }
};
