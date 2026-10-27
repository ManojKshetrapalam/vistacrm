<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id', 32)->unique()->index();
            $table->string('name');
            $table->string('phone');
            $table->string('phone_normalized', 20)->index();
            $table->string('email')->nullable()->index();
            $table->string('company_name')->nullable();
            $table->string('source')->default('Website')->index();
            $table->string('service_interest')->nullable()->index();
            $table->text('additional_details')->nullable();
            $table->enum('status', [
                'new',
                'assigned',
                'contacted',
                'follow_up',
                'interested',
                'converted',
                'lost',
                'not_reachable'
            ])->default('new')->index();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('last_contact_date')->nullable();
            $table->dateTime('next_follow_up_date')->nullable()->index();
            $table->json('custom_fields')->nullable();
            $table->timestamps();

            $table->index(['status', 'assigned_to']);
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
