<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dateTime('reminder_at')->nullable()->after('scheduled_at')->index();
            $table->string('reminder_key', 100)->nullable()->after('reminder_at')->index();
            $table->string('reminder_status', 30)->default('pending')->after('reminder_key')->index();
            $table->dateTime('reminder_sent_at')->nullable()->after('reminder_status');
            $table->string('reminder_channel', 50)->nullable()->after('reminder_sent_at');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignId('follow_up_id')->nullable()->after('lead_id')->constrained('follow_ups')->nullOnDelete();
            $table->string('reminder_key', 100)->nullable()->after('metadata')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropForeign(['follow_up_id']);
            $table->dropColumn(['follow_up_id', 'reminder_key']);
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_at',
                'reminder_key',
                'reminder_status',
                'reminder_sent_at',
                'reminder_channel',
            ]);
        });
    }
};
