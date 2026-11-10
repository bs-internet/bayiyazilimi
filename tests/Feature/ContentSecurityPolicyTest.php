<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

it('sends a report-only policy whose nonce matches the inline scripts', function () {
    $response = $this->get(route('login'))->assertOk();

    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain("frame-ancestors 'self'")
        ->and($policy)->toContain('report-uri /csp-report')
        ->and(preg_match("/'nonce-([^']+)'/", $policy, $match))->toBe(1)
        ->and($response->getContent())->toContain('<script nonce="'.$match[1].'"');
});

it('enforces the policy or sends none depending on the mode', function () {
    config(['bayiyazilimi.csp' => 'enforce']);

    $enforced = $this->get(route('login'));

    expect($enforced->headers->get('Content-Security-Policy'))->toContain("'strict-dynamic'")
        ->and($enforced->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();

    config(['bayiyazilimi.csp' => 'off']);

    $off = $this->get(route('login'));

    expect($off->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($off->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('lets modules add sources through the security.csp filter', function () {
    hooks()->filter('security.csp', fn (array $value) => array_merge_recursive($value, [
        'frame-src' => ['https://odeme.example.com'],
    ]));

    $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)->toContain("frame-src 'self' https://odeme.example.com");
});

it('logs violation reports without query strings and skips browser extensions', function () {
    Log::spy();

    $this->call('POST', route('csp.report'), content: json_encode(['csp-report' => [
        'document-uri' => 'https://bayi.test/teklif/1?signature=gizli',
        'effective-directive' => 'script-src-elem',
        'blocked-uri' => 'https://kotu.example.com/x.js',
    ]]), server: ['CONTENT_TYPE' => 'application/csp-report'])->assertNoContent();

    $this->call('POST', route('csp.report'), content: json_encode(['csp-report' => [
        'document-uri' => 'https://bayi.test/panel',
        'blocked-uri' => 'chrome-extension://abc/inject.js',
    ]]), server: ['CONTENT_TYPE' => 'application/csp-report'])->assertNoContent();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'CSP ihlali'
        && $context['page'] === 'https://bayi.test/teklif/1'
        && $context['directive'] === 'script-src-elem'
        && $context['blocked'] === 'https://kotu.example.com/x.js');
});
