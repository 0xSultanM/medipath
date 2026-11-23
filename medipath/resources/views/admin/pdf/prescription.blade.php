<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $prescription->prescription_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2a37; font-size: 12px; }
        header { border-bottom: 2px solid #0e7c86; padding-bottom: 10px; margin-bottom: 18px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #0a6169; }
        .muted { color: #556576; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #d8e0e8; padding: 8px 6px; text-align: left; }
        th { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #556576; }
        .right { text-align: right; }
        .box { background: #f4f6f8; border: 1px solid #d8e0e8; border-radius: 8px; padding: 10px; margin-top: 12px; }
        footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #556576; border-top: 1px solid #d8e0e8; padding-top: 6px; }
    </style>
</head>
<body>
<header>
    <h1>{{ $clinic }} · Prescription</h1>
    <div class="muted">{{ $header }}</div>
</header>

<table>
    <tr><th>Prescription</th><th>Patient</th><th>Doctor</th><th class="right">Issued</th></tr>
    <tr>
        <td>{{ $prescription->prescription_code }}</td>
        <td>{{ $prescription->patient?->name }} ({{ $prescription->patient?->patient_code }})</td>
        <td>{{ $prescription->doctor?->user?->name }} · {{ $prescription->doctor?->specialty?->name }}</td>
        <td class="right">{{ $prescription->created_at?->format('d M Y H:i') }}</td>
    </tr>
</table>

<table>
    <thead><tr><th>Medicine</th><th>Dosage</th><th>Schedule</th><th>Duration</th><th class="right">Qty</th></tr></thead>
    <tbody>
    @foreach ($prescription->items as $item)
        <tr>
            <td>{{ $item->medicine?->name ?? 'Medicine' }} {{ $item->medicine?->strength }}</td>
            <td>{{ $item->dosage }}</td>
            <td>{{ $item->schedule }}</td>
            <td>{{ $item->duration }}</td>
            <td class="right">{{ $item->quantity }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="box">
    <strong>Clinical notes</strong>
    <p>{{ $prescription->clinical_notes ?: '—' }}</p>
    @if ($prescription->override_justification)
        <strong>Safety override</strong>
        <p>{{ $prescription->override_justification }}</p>
    @endif
</div>

<footer>{{ $footer }}</footer>
</body>
</html>