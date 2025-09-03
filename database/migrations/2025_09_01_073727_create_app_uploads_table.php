<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_uploads', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['android', 'ios']);
            $table->string('version', 50);
            $table->string('build_number')->nullable();
            $table->string('file_name');
            $table->string('s3_key');
            $table->string('s3_url');
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['platform', 'is_active']);
            $table->index(['platform', 'version']);
            $table->unique(['platform', 'version', 'build_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_uploads');
    }
};
