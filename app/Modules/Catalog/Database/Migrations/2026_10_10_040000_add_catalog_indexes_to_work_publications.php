<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_publications', function (Blueprint $table): void {
            $table->index(['is_published', 'published_at'], 'work_publications_is_published_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('work_publications', function (Blueprint $table): void {
            $table->dropIndex('work_publications_is_published_published_at_index');
        });
    }
};
