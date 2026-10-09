<?php

it('returns a healthy json payload', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertExactJson([
            'status' => 'ok',
        ]);
});
