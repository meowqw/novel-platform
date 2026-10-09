<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('checksum_sha256', 64);
            $table->string('thumb_path', 512)->nullable();
            $table->unsignedBigInteger('thumb_byte_size')->nullable();
            $table->unsignedInteger('thumb_width')->nullable();
            $table->unsignedInteger('thumb_height')->nullable();
            $table->timestamps();

            $table->unique(['disk', 'path']);
            $table->index(['owner_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });

        // Chapter snapshot refs only. Cover visibility is live via works.cover_media_id.
        Schema::create('published_media_references', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_id')->constrained('media_assets')->restrictOnDelete();
            $table->foreignUuid('work_id')->constrained('works')->cascadeOnDelete();
            $table->foreignUuid('chapter_id')->constrained('chapters')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chapter_id', 'media_id']);
            $table->index('media_id');
            $table->index('work_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_media_references');
        Schema::dropIfExists('media_assets');
    }
};
