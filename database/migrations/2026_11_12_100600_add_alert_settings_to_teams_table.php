<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('alerts_enabled')->default(true)->after('is_personal');
            $table->text('slack_webhook_url')->nullable()->after('alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['alerts_enabled', 'slack_webhook_url']);
        });
    }
};
