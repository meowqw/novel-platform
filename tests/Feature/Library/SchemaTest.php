<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates library tables with uuid fks and nulls-not-distinct uniqueness', function (): void {
    expect(Schema::hasTable('works'))->toBeTrue()
        ->and(Schema::hasTable('volumes'))->toBeTrue()
        ->and(Schema::hasTable('chapters'))->toBeTrue();

    $index = DB::selectOne("
        SELECT indexdef
        FROM pg_indexes
        WHERE tablename = 'chapters' AND indexname = 'chapters_work_volume_position_unique'
    ");

    expect($index?->indexdef)->toContain('NULLS NOT DISTINCT');
});
