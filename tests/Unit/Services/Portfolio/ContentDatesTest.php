<?php

use App\Services\Portfolio\ContentDates;

test('displays only the precision recorded in content', function (?string $input, string $output) {
    expect((new ContentDates)->format($input))->toBe($output);
})->with([
    [null, ''], ['2023', '2023'], ['2025-09', 'Sep 2025'], ['2025-09-27', '27 Sep 2025'],
]);

test('labels ongoing and expected date ranges honestly', function () {
    $dates = new ContentDates;

    expect($dates->range('2025-09-27', '2029-10', expected: true))->toBe('27 Sep 2025 — Expected Oct 2029');
    expect($dates->range('2025', null, ongoing: true))->toBe('2025 — Present');
    expect($dates->range(null, '2023'))->toBe('2023');
    expect($dates->range('2023', null))->toBe('2023');
});

test('refuses invalid dates rather than silently changing them', function () {
    expect(fn () => (new ContentDates)->format('2025-02-30'))->toThrow(RuntimeException::class);
});
