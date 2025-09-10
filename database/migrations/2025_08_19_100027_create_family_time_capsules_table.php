<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_time_capsules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('contents')->nullable(); // messages, photos, videos, predictions
            $table->json('contributors')->nullable();
            $table->timestamp('sealed_at');
            $table->timestamp('opens_at');
            $table->boolean('is_opened')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->json('opening_conditions')->nullable(); // special conditions to open
            $table->timestamps();
            
            $table->index('family_id');
            $table->index('opens_at');
            $table->index('is_opened');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_time_capsules');
    }
};