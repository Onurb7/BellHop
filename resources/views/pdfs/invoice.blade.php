<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; color: #1b1b18; font-size: 12px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .brand { font-size: 20px; font-weight: bold; }
        .invoice-meta { text-align: right; font-size: 12px; color: #6b5330; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b5330; margin-bottom: 4px; }
        .details { width: 100%; margin-bottom: 24px; }
        .details td { vertical-align: top; padding-right: 24px; }
        table.ledger { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.ledger th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b5330; border-bottom: 1px solid #e9d4a0; padding: 6px 4px; }
        table.ledger td { padding: 6px 4px; border-bottom: 1px solid #f0e6cc; }
        table.ledger td.amount { text-align: right; }
        table.ledger tfoot td { border-top: 1px solid #1b1b18; border-bottom: none; font-weight: bold; }
        .credit { color: #b91c1c; }
        .footer { margin-top: 32px; font-size: 10px; color: #6b5330; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">🛎️ Bellhop</div>
        <div class="invoice-meta">
            <div><strong>Invoice {{ $booking->invoice_number }}</strong></div>
            <div>Issued {{ now()->format('F j, Y') }}</div>
        </div>
    </div>

    <table class="details">
        <tr>
            <td>
                <div class="section-title">Billed to</div>
                {{ $booking->guest->name }}<br>
                {{ $booking->guest->email }}
                @if ($booking->guest->phone)
                    <br>{{ $booking->guest->phone }}
                @endif
            </td>
            <td>
                <div class="section-title">Stay details</div>
                {{ $booking->room->roomType->name }} — Room {{ $booking->room->number }}<br>
                {{ $booking->check_in->format('l, F j, Y') }} → {{ $booking->check_out->format('l, F j, Y') }}
            </td>
        </tr>
    </table>

    <div class="section-title">Charges</div>
    <table class="ledger">
        <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($booking->charges as $charge)
                <tr>
                    <td>{{ $charge->description }}</td>
                    <td class="amount {{ $charge->amount_cents < 0 ? 'credit' : '' }}">
                        {{ $charge->amount_cents < 0 ? '-' : '' }}${{ number_format(abs($charge->amount_cents) / 100, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="amount">${{ number_format($booking->totalCents() / 100, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">Payments</div>
    <table class="ledger">
        <thead>
            <tr>
                <th>Kind</th>
                <th>Date</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($booking->payments as $payment)
                <tr>
                    <td>{{ ucfirst($payment->kind->value) }}</td>
                    <td>{{ $payment->verified_at->format('M j, Y') }}</td>
                    <td class="amount {{ $payment->amount_cents < 0 ? 'credit' : '' }}">
                        {{ $payment->amount_cents < 0 ? '-' : '' }}${{ number_format(abs($payment->amount_cents) / 100, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Balance due</td>
                <td class="amount">${{ number_format($booking->balanceDueCents() / 100, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">Thank you for staying with Bellhop.</div>
</body>
</html>
