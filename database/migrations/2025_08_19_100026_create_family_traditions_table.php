<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_traditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->text('description');
            $table->string('frequency')->default('yearly'); // daily, weekly, monthly, yearly, special
            $table->json('schedule_details')->nullable();
            $table->date('started_date')->nullable();
            $table->json('participants')->nullable();
            $table->json('activities')->nullable();
            $table->json('recipes')->nullable(); // for food traditions
            $table->json('songs_games')->nullable(); // for activity traditions
            $table->json('media')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('times_celebrated')->default(0);
            $table->timestamp('last_celebrated_at')->nullable();
            $table->timestamps();
            
            $table->index('family_id');
            $table->index('frequency');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_traditions');
    }
};