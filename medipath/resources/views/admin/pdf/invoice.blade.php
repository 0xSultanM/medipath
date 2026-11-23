<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2a37; font-size: 12px; }
        header { border-bottom: 2px solid #0e7c86; padding-bottom: 10px; margin-bottom: 18px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #0a6169; }
        .muted { color: #556576; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #d8e0e8; padding: 8px 6px; text-align: left; }
        th { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #556576; }
        .right { text-align: right; }
        .totals { margin-top: 16px; margin-left: auto; width: 55%; }
        .totals td { border: 0; padding: 4px 6px; }
        footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #556576; border-top: 1px solid #d8e0e8; padding-top: 6px; }
    </style>
</head>
<body>
<header>
    <h1>{{ $clinic }} · Invoice</h1>
    <div class="muted">{{ $header }}</div>
</header>

<table>
    <tr><th>Invoice</th><th>Patient</th><th>Status</th><th class="right">Issued</th></tr>
    <tr>
        <td>{{ $invoice->invoice_code }}</td>
        <td>{{ $invoice->patient?->name }} ({{ $invoice->patient?->patient_code }})</td>
        <td>{{ $invoice->payment_status?->label() }} · {{ $invoice->payment_method ?? '—' }}</td>
        <td class="right">{{ $invoice->created_at?->format('d M Y H:i') }}</td>
    </tr>
</table>

<table>
    <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Line total</th></tr></thead>
    <tbody>
    @foreach ($invoice->items as $item)
        <tr>
            <td>{{ $item->description ?? $item->medicine?->name }}</td>
            <td class="right">{{ $item->quantity }}</td>
            <td class="right">{{ $money->format((float) $item->unit_price, $invoice->currency_code) }}</td>
            <td class="right">{{ $money->format((float) $item->line_total, $invoice->currency_code) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="right">{{ $money->format((float) $invoice->subtotal, $invoice->currency_code) }}</td></tr>
    <tr><td>Discount</td><td class="right">{{ $money->format((float) $invoice->discount, $invoice->currency_code) }}</td></tr>
    <tr><td><strong>Net total</strong></td><td class="right"><strong>{{ $money->format((float) $invoice->net_total, $invoice->currency_code) }}</strong></td></tr>
    <tr><td>Paid</td><td class="right">{{ $money->format((float) $invoice->paid_amount, $invoice->currency_code) }}</td></tr>
</table>

<footer>{{ $footer }}</footer>
</body>
</html>