<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('leads', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('custom_fields');
            }
        });

        Schema::table('lead_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_activities', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable()->unique()->after('id');
            }
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            if (!Schema::hasColumn('follow_ups', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('follow_ups', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('reminder_channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('follow_ups', 'client_uuid')) {
                $table->dropColumn(['client_uuid']);
            }
            if (Schema::hasColumn('follow_ups', 'version')) {
                $table->dropColumn(['version']);
            }
        });

        Schema::table('lead_activities', function (Blueprint $table) {
            if (Schema::hasColumn('lead_activities', 'client_uuid')) {
                $table->dropColumn(['client_uuid']);
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'client_uuid')) {
                $table->dropColumn(['client_uuid']);
            }
            if (Schema::hasColumn('leads', 'version')) {
                $table->dropColumn(['version']);
            }
        });
    }
};
