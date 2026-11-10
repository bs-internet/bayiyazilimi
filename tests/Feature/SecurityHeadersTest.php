<?php

declare(strict_types=1);

test('security middleware applies browser safety headers', function () {
    $response = $this->get('/');

    $response
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');

    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeTrue();
});
