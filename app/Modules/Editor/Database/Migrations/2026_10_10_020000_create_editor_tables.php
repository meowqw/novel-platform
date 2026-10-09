<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_drafts', function (Blueprint $table): void {
            $table->uuid('chapter_id')->primary();
            $table->jsonb('content_json');
            $table->text('plain_text')->default('');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('chapter_id')
                ->references('id')
                ->on('chapters')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE chapter_drafts ADD CONSTRAINT chapter_drafts_version_positive CHECK (version > 0)');

        Schema::create('chapter_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('chapter_id')->constrained('chapters')->restrictOnDelete();
            $table->jsonb('content_json');
            $table->text('plain_text');
            $table->unsignedBigInteger('source_version');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('label', 120)->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chapter_id', 'created_at', 'id']);
            $table->unique(['chapter_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_revisions');
        Schema::dropIfExists('chapter_drafts');
    }
};
