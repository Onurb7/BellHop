<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function download(Booking $booking, Request $request): StreamedResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['staff', 'admin', 'super-admin']);
        $isOwningGuest = $booking->guest_id === $user->guest?->id;

        abort_unless($isStaff || $isOwningGuest, 403);
        abort_unless($booking->hasInvoice(), 404);

        return $booking->getFirstMedia('invoice')->toResponse($request);
    }
}
