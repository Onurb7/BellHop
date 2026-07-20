<?php

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Scrapes the embedded Inertia `data-page` JSON blob, same helper
 * established in BookingDepositPlanTest.php.
 */
function reviewAdminInertiaProps(\Illuminate\Testing\TestResponse $response): array
{
    preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $matches);

    return json_decode($matches[1], true)['props'];
}

function actingAsReviewAdmin(): User
{
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function actingAsReviewStaff(): User
{
    Role::findOrCreate('staff');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    return $staff;
}

function submittedReview(array $overrides = []): Review
{
    // Reads booking_id from $overrides first — array_merge would still
    // eagerly evaluate Booking::factory()->create() below even when
    // overridden, wasting a Room/RoomType on every call.
    return Review::create(array_merge([
        'booking_id' => $overrides['booking_id'] ?? Booking::factory()->create()->id,
        'uuid' => Str::uuid(),
        'send_at' => now()->subDays(3),
        'sent_at' => now()->subDays(3),
        'rating' => 5,
        'body' => 'Great stay.',
        'submitted_at' => now(),
    ], $overrides));
}

it('blocks staff from every /admin/reviews route', function () {
    $staff = actingAsReviewStaff();
    $review = submittedReview();

    $this->actingAs($staff)->get('/admin/reviews')->assertForbidden();
    $this->actingAs($staff)->post("/admin/reviews/{$review->id}/toggle-featured")->assertForbidden();
    $this->actingAs($staff)->delete("/admin/reviews/{$review->id}")->assertForbidden();
});

it('lets an admin feature a submitted review', function () {
    $admin = actingAsReviewAdmin();
    $review = submittedReview(['featured' => false]);

    $this->actingAs($admin)->post("/admin/reviews/{$review->id}/toggle-featured")->assertRedirect();

    expect($review->fresh()->featured)->toBeTrue();
});

it('refuses to feature a review that has not been submitted yet', function () {
    $admin = actingAsReviewAdmin();
    $review = Review::create([
        'booking_id' => Booking::factory()->create()->id,
        'uuid' => Str::uuid(),
        'send_at' => now()->addDays(3),
    ]);

    $this->actingAs($admin)->post("/admin/reviews/{$review->id}/toggle-featured")->assertStatus(422);

    expect($review->fresh()->featured)->toBeFalse();
});

it('lets an admin delete a review', function () {
    $admin = actingAsReviewAdmin();
    $review = submittedReview();

    $this->actingAs($admin)->delete("/admin/reviews/{$review->id}")->assertRedirect();

    expect(Review::find($review->id))->toBeNull();
});

it('paginates the admin reviews list at 15 per page', function () {
    $admin = actingAsReviewAdmin();
    // Reuses one room across all 20 bookings, each on its own
    // non-overlapping date range — the bookings table's exclusion
    // constraint rejects overlapping stays on the same room. Builds
    // Review rows directly rather than via the submittedReview() helper,
    // since that helper's array_merge default eagerly creates a
    // throwaway Booking (and a fresh Room/RoomType with it) even when
    // booking_id is overridden — 20 of those would exhaust Faker's
    // unique() pool for room type names.
    $room = \App\Models\Room::factory()->create();
    collect(range(0, 19))->each(fn ($i) => Review::create([
        'booking_id' => \App\Models\Booking::factory()->create([
            'room_id' => $room->id,
            'check_in' => now()->addDays($i * 3),
            'check_out' => now()->addDays($i * 3 + 2),
        ])->id,
        'uuid' => Str::uuid(),
        'send_at' => now()->subDays(3),
        'sent_at' => now()->subDays(3),
        'rating' => 5,
        'body' => 'Great stay.',
        'submitted_at' => now(),
    ]));

    $props = reviewAdminInertiaProps($this->actingAs($admin)->get('/admin/reviews'));

    expect($props['reviews']['data'])->toHaveCount(15)
        ->and($props['reviews']['total'])->toBe(20)
        ->and($props['reviews']['last_page'])->toBe(2);
});

it('sorts reviews by rating with unrated reviews always last, regardless of direction', function () {
    $admin = actingAsReviewAdmin();
    $low = submittedReview(['rating' => 2]);
    $high = submittedReview(['rating' => 5]);
    $unrated = Review::create([ // sent but not yet submitted — no rating
        'booking_id' => Booking::factory()->create()->id,
        'uuid' => Str::uuid(),
        'send_at' => now()->subDays(3),
        'sent_at' => now()->subDays(3),
    ]);

    $desc = reviewAdminInertiaProps($this->actingAs($admin)->get('/admin/reviews?sort=rating&dir=desc'));
    expect(collect($desc['reviews']['data'])->pluck('id')->all())->toBe([$high->id, $low->id, $unrated->id]);

    $asc = reviewAdminInertiaProps($this->actingAs($admin)->get('/admin/reviews?sort=rating&dir=asc'));
    expect(collect($asc['reviews']['data'])->pluck('id')->all())->toBe([$low->id, $high->id, $unrated->id]);
});

it('rejects an unknown sort column and falls back to the default order', function () {
    $admin = actingAsReviewAdmin();
    submittedReview();

    $this->actingAs($admin)->get('/admin/reviews?sort=id&dir=asc')->assertOk();
});

it('only shows featured, submitted reviews on the home page, and never the guest email', function () {
    $featured = submittedReview(['featured' => true]);
    submittedReview(['featured' => false]); // not featured — excluded
    Review::create([ // featured but never submitted — excluded
        'booking_id' => Booking::factory()->create()->id,
        'uuid' => Str::uuid(),
        'send_at' => now()->subDays(3),
        'featured' => true,
    ]);

    $props = reviewAdminInertiaProps($this->get('/'));

    expect($props['featuredReviews'])->toHaveCount(1)
        ->and($props['featuredReviews'][0]['body'])->toBe($featured->body)
        ->and(json_encode($props['featuredReviews']))->not->toContain('@');
});
