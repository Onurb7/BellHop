<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class GenerateBookingInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public Booking $booking)
    {
    }

    /**
     * Also re-dispatched when a refund lands on an already-invoiced
     * booking (see StripeWebhookController), so the stored PDF never
     * goes stale — it always reflects the current charge/payment ledger,
     * refund lines included. Reuses the existing invoice number on a
     * regeneration and only emails once, on first generation.
     */
    public function handle(): void
    {
        $this->booking->refresh();

        $isFirstGeneration = ! $this->booking->hasInvoice();

        $this->booking->load(['guest', 'room.roomType', 'charges', 'payments']);

        $invoiceNumber = $this->booking->invoice_number
            ?? 'INV-'.str_pad((string) $this->booking->id, 6, '0', STR_PAD_LEFT);

        $this->booking->update([
            'invoice_number' => $invoiceNumber,
            'invoice_generated_at' => now(),
        ]);

        $pdf = Pdf::loadView('pdfs.invoice', ['booking' => $this->booking]);

        $this->booking
            ->addMediaFromString($pdf->output())
            ->usingFileName("{$invoiceNumber}.pdf")
            ->toMediaCollection('invoice');

        if ($isFirstGeneration) {
            Mail::to($this->booking->guest->email)->send(new InvoiceMail($this->booking));
        }
    }
}
