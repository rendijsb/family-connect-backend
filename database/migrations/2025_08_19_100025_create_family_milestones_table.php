<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('type'); // first_steps, graduation, wedding, birth, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('milestone_date');
            $table->json('media')->nullable();
            $table->json('metadata')->nullable(); // type-specific data
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern')->nullable();
            $table->boolean('notify_family')->default(true);
            $table->timestamps();
            
            $table->index('family_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('milestone_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_milestones');
    }
};