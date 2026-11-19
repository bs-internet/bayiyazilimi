<?php

declare(strict_types=1);

use App\Enums\Shop\PaymentStatus as S;

it('allows exactly the documented payment status transitions', function () {
    $allowed = [
        'pending' => ['partial', 'paid', 'failed'],
        'partial' => ['paid', 'pending', 'failed', 'refunded'],
        'paid' => ['pending', 'partial', 'refunded'],
        'failed' => ['paid', 'pending'],
        'refunded' => ['paid'],
        'cancelled' => [],
    ];

    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            if ($from === $to) {
                continue;
            }

            expect($from->canTransitionTo($to))->toBe(in_array($to->value, $allowed[$from->value], true), "{$from->value} → {$to->value}");
        }
    }
});

it('throws on a transition outside the map and accepts staying in place', function () {
    S::Refunded->assertTransition(S::Refunded);
    S::Pending->assertTransition(S::Paid);

    expect(fn () => S::Refunded->assertTransition(S::Failed))->toThrow(LogicException::class)
        ->and(fn () => S::Failed->assertTransition(S::Refunded))->toThrow(LogicException::class);
});
