<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover_photo')->nullable();
            $table->string('privacy')->default('family'); // family, specific_members, public
            $table->json('allowed_members')->nullable(); // for specific_members privacy
            $table->boolean('allow_download')->default(true);
            $table->boolean('allow_comments')->default(true);
            $table->integer('photo_count')->default(0);
            $table->integer('video_count')->default(0);
            $table->bigInteger('total_size')->default(0); // in bytes
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            
            $table->index('family_id');
            $table->index('created_by');
            $table->index('privacy');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_albums');
    }
};