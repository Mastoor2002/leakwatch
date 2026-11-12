<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependency_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('lock_hash', 64)->nullable();
            $table->string('php_version')->nullable();
            $table->string('laravel_version')->nullable();
            $table->unsignedInteger('package_count')->default(0);
            $table->timestamp('reported_at');
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('installed_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependency_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('version');
            $table->boolean('is_dev')->default(false);

            $table->index('name');
        });

        Schema::create('advisories', function (Blueprint $table) {
            $table->id();
            $table->string('advisory_id')->unique();
            $table->string('package_name')->index();
            $table->string('title');
            $table->string('cve')->nullable();
            $table->string('link')->nullable();
            $table->text('affected_versions');
            $table->string('severity')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('package_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advisory_id')->constrained()->cascadeOnDelete();
            $table->string('package_name');
            $table->string('installed_version');
            $table->boolean('is_dev')->default(false);
            $table->string('status')->default('open');
            $table->timestamp('first_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'advisory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_findings');
        Schema::dropIfExists('advisories');
        Schema::dropIfExists('installed_packages');
        Schema::dropIfExists('dependency_snapshots');
    }
};
