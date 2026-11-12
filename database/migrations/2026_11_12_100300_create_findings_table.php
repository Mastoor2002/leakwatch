<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('check_key');
            $table->string('fingerprint', 64);
            $table->string('severity');
            $table->string('title');
            $table->text('summary');
            $table->json('evidence')->nullable();
            $table->text('remediation');
            $table->string('status')->default('open');
            $table->foreignId('first_seen_scan_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->foreignId('last_seen_scan_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('ignored_reason')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'fingerprint']);
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
