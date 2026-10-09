<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('slug', 220);
            $table->string('status', 32)->default('ongoing');
            $table->timestamps();

            $table->unique(['author_id', 'slug']);
            $table->index(['author_id', 'created_at']);
        });

        Schema::create('volumes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_id')->constrained('works')->cascadeOnDelete();
            $table->string('title', 200);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['work_id', 'position']);
            $table->unique(['id', 'work_id']);
            $table->index(['work_id', 'position']);
        });

        Schema::create('chapters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_id')->constrained('works')->cascadeOnDelete();
            $table->uuid('volume_id')->nullable();
            $table->string('title', 200);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['work_id', 'volume_id', 'position']);

            $table->foreign(['volume_id', 'work_id'])
                ->references(['id', 'work_id'])
                ->on('volumes')
                ->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX chapters_work_volume_position_unique ON chapters (work_id, volume_id, position) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('volumes');
        Schema::dropIfExists('works');
    }
};
