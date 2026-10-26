<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title');
            $table->string('url', 1024);
            $table->json('data')->nullable();
            $table->string('event_key');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'user_id', 'event_key'], 'school_notification_recipient_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_notifications');
    }
};
