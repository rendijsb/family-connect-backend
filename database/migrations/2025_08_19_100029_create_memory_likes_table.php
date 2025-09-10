<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained('memories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            
            $table->index('memory_id');
            $table->index('user_id');
            $table->unique(['memory_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_likes');
    }
};