<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->foreignUuid('cover_media_id')
                ->nullable()
                ->after('status')
                ->constrained('media_assets')
                ->restrictOnDelete();

            $table->index('cover_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cover_media_id');
        });
    }
};
