<?php

use App\Enums\BookingStatus;
use App\Mail\ReviewFollowupMail;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function staffChecksOut(): array
{
    Role::findOrCreate('staff');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $booking = Booking::factory()->create(['status' => BookingStatus::CheckedIn]);

    return [$staff, $booking];
}

it('creates a review row with a 3-day send_at when a booking is checked out', function () {
    [$staff, $booking] = staffChecksOut();

    $this->actingAs($staff)->post("/reservations/{$booking->id}/check-out")->assertSessionHas('success');

    $review = Review::where('booking_id', $booking->id)->first();
    expect($review)->not->toBeNull()
        ->and($review->uuid)->not->toBeNull()
        ->and($review->sent_at)->toBeNull()
        ->and($review->send_at->toDateString())->toBe(now()->addDays(3)->toDateString());
});

it('sends the follow-up and stamps sent_at only for reviews whose send_at has passed', function () {
    Mail::fake();

    $due = Review::create([
        'booking_id' => Booking::factory()->create()->id,
        'uuid' => \Illuminate\Support\Str::uuid(),
        'send_at' => now()->subDay(),
    ]);
    $notYetDue = Review::create([
        'booking_id' => Booking::factory()->create()->id,
        'uuid' => \Illuminate\Support\Str::uuid(),
        'send_at' => now()->addDays(2),
    ]);
    $alreadySent = Review::create([
        'booking_id' => Booking::factory()->create()->id,
        'uuid' => \Illuminate\Support\Str::uuid(),
        'send_at' => now()->subDays(2),
        'sent_at' => now()->subDay(),
    ]);

    $this->artisan('reviews:send-followups')->assertSuccessful();

    Mail::assertSent(ReviewFollowupMail::class, 1);
    expect($due->fresh()->sent_at)->not->toBeNull()
        ->and($notYetDue->fresh()->sent_at)->toBeNull()
        ->and($alreadySent->fresh()->sent_at->eq($alreadySent->sent_at))->toBeTrue();
});
