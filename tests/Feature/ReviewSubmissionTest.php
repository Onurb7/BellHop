<?php

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Scrapes the embedded Inertia `data-page` JSON blob, same helper
 * established in BookingDepositPlanTest.php.
 */
function reviewInertiaProps(TestResponse $response): array
{
    preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $matches);

    return json_decode($matches[1], true)['props'];
}

function makeReview(array $overrides = []): Review
{
    return Review::create(array_merge([
        'booking_id' => Booking::factory()->create()->id,
        'uuid' => Str::uuid(),
        'send_at' => now()->subDays(3),
        'sent_at' => now()->subDays(3),
    ], $overrides));
}

it('shows the review form for an unsubmitted review', function () {
    $review = makeReview();

    $props = reviewInertiaProps($this->get("/review/{$review->uuid}"));

    expect($props['review']['already_submitted'])->toBeFalse();
});

it('shows the already-submitted state for a review that was already submitted', function () {
    $review = makeReview(['rating' => 5, 'submitted_at' => now()]);

    $props = reviewInertiaProps($this->get("/review/{$review->uuid}"));

    expect($props['review']['already_submitted'])->toBeTrue();
});

it('lets a guest submit a rating and an optional written review', function () {
    $review = makeReview();

    $this->post("/review/{$review->uuid}", [
        'rating' => 4,
        'body' => 'Lovely stay, would come back.',
    ])->assertSessionHasNoErrors();

    $fresh = $review->fresh();
    expect($fresh->rating)->toBe(4)
        ->and($fresh->body)->toBe('Lovely stay, would come back.')
        ->and($fresh->submitted_at)->not->toBeNull();
});

it('lets a guest submit just a rating with no written review', function () {
    $review = makeReview();

    $this->post("/review/{$review->uuid}", ['rating' => 3])->assertSessionHasNoErrors();

    expect($review->fresh()->rating)->toBe(3)
        ->and($review->fresh()->body)->toBeNull();
});

it('rejects a rating outside 1-5', function () {
    $review = makeReview();

    $this->post("/review/{$review->uuid}", ['rating' => 6])->assertSessionHasErrors('rating');
    $this->post("/review/{$review->uuid}", [])->assertSessionHasErrors('rating');
});

it('refuses a second submission and leaves the original values intact', function () {
    $review = makeReview(['rating' => 5, 'body' => 'Original review.', 'submitted_at' => now()]);

    $this->post("/review/{$review->uuid}", ['rating' => 1, 'body' => 'Overwritten?'])
        ->assertStatus(422);

    expect($review->fresh()->rating)->toBe(5)
        ->and($review->fresh()->body)->toBe('Original review.');
});
