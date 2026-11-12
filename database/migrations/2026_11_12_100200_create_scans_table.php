<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('trigger')->default('manual');
            $table->json('context')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('grade', 1)->nullable();
            $table->unsignedSmallInteger('new_findings_count')->default(0);
            $table->unsignedSmallInteger('resolved_findings_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('scan_check_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('check_key');
            $table->string('check_class');
            $table->unsignedSmallInteger('position');
            $table->string('status')->nullable();
            $table->json('result')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['scan_id', 'check_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_check_runs');
        Schema::dropIfExists('scans');
    }
};
