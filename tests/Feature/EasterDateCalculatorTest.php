<?php

use App\Support\EasterDateCalculator;

it('resolves the correct real-world Easter Sunday for a range of known years', function () {
    expect(EasterDateCalculator::forYear(2023)->toDateString())->toBe('2023-04-09')
        ->and(EasterDateCalculator::forYear(2024)->toDateString())->toBe('2024-03-31')
        ->and(EasterDateCalculator::forYear(2025)->toDateString())->toBe('2025-04-20')
        ->and(EasterDateCalculator::forYear(2026)->toDateString())->toBe('2026-04-05')
        ->and(EasterDateCalculator::forYear(2027)->toDateString())->toBe('2027-03-28')
        ->and(EasterDateCalculator::forYear(2000)->toDateString())->toBe('2000-04-23')
        ->and(EasterDateCalculator::forYear(2016)->toDateString())->toBe('2016-03-27');
});
