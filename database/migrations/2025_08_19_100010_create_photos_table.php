<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('photo_albums')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->bigInteger('size'); // in bytes
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->json('metadata')->nullable(); // EXIF data, location, etc.
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->json('people_tagged')->nullable(); // user_ids
            $table->string('location')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            
            $table->index('album_id');
            $table->index('uploaded_by');
            $table->index('taken_at');
            $table->index('is_favorite');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};