<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates identity schema with uuid users and case-insensitive uniques', function (): void {
    expect(Schema::hasColumns('users', [
        'id', 'username', 'name', 'email', 'email_verified_at', 'password', 'bio', 'avatar_path',
    ]))->toBeTrue();

    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue();

    $tokenable = DB::selectOne("
        SELECT data_type
        FROM information_schema.columns
        WHERE table_name = 'personal_access_tokens'
          AND column_name = 'tokenable_id'
    ");

    expect($tokenable?->data_type)->toBe('uuid');

    $indexes = collect(DB::select("
        SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'users'
    "))->pluck('indexname');

    expect($indexes)->toContain('users_username_lower_unique')
        ->and($indexes)->toContain('users_email_lower_unique');
});
