<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_publications', function (Blueprint $table): void {
            $table->uuid('work_id')->primary();
            $table->boolean('is_published')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('unpublished_at')->nullable();
            $table->timestamps();

            $table->foreign('work_id')
                ->references('id')
                ->on('works')
                ->restrictOnDelete();
        });

        Schema::create('chapter_publications', function (Blueprint $table): void {
            $table->uuid('chapter_id')->primary();
            $table->boolean('is_published')->default(false);
            $table->jsonb('content_json')->nullable();
            $table->text('plain_text')->nullable();
            $table->unsignedBigInteger('source_draft_version')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('unpublished_at')->nullable();
            $table->timestamps();

            $table->foreign('chapter_id')
                ->references('id')
                ->on('chapters')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE chapter_publications
            ADD CONSTRAINT chapter_publications_published_payload_check
            CHECK (
                is_published = false
                OR (
                    content_json IS NOT NULL
                    AND plain_text IS NOT NULL
                    AND source_draft_version IS NOT NULL
                    AND published_at IS NOT NULL
                )
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_publications');
        Schema::dropIfExists('work_publications');
    }
};
