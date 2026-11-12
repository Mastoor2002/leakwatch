<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('host');
            $table->string('verification_token', 64);
            $table->string('verification_method')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('verification_checked_at')->nullable();
            $table->boolean('is_laravel')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('grade', 1)->nullable();
            $table->unsignedBigInteger('last_scan_id')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('scan_frequency')->default('weekly');
            $table->string('agent_token_hash', 64)->nullable()->unique();
            $table->string('agent_token_last_four', 4)->nullable();
            $table->timestamp('agent_last_seen_at')->nullable();
            $table->date('certificate_alerted_for')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
