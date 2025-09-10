<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('general'); // milestone, achievement, tradition, story
            $table->date('memory_date');
            $table->json('participants')->nullable(); // user_ids involved
            $table->json('media')->nullable(); // photos, videos, audio
            $table->json('location')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility')->default('family'); // family, specific_members, private
            $table->json('visible_to')->nullable(); // for specific_members visibility
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->json('ai_generated_tags')->nullable();
            $table->json('ai_detected_emotions')->nullable();
            $table->timestamps();
            
            $table->index('family_id');
            $table->index('created_by');
            $table->index('type');
            $table->index('memory_date');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};