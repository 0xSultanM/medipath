<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MediPath report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2a37; font-size: 10px; }
        h1 { font-size: 18px; margin: 0 0 2px; color: #0a6169; }
        h2 { font-size: 12px; margin: 16px 0 4px; color: #0a6169; }
        .muted { color: #556576; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border-bottom: 1px solid #d8e0e8; padding: 5px; text-align: left; }
        th { font-size: 8px; text-transform: uppercase; color: #556576; }
        .right { text-align: right; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; width: 50%; border: 0; }
        .box { border: 1px solid #d8e0e8; border-radius: 6px; padding: 8px; }
        footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8px; color: #556576; border-top: 1px solid #d8e0e8; padding-top: 4px; }
    </style>
</head>
<body>
<h1>MediPath reports &amp; analytics</h1>
<div class="muted">Range {{ $report['from'] }} → {{ $report['to'] }} · generated {{ now()->format('d M Y H:i') }}</div>

<table class="grid"><tr><td>
    <h2>1 · Monthly revenue</h2>
    <table>
        <tr><th>Month</th><th class="right">Collected</th></tr>
        @foreach ($report['monthlyRevenue']['labels'] as $i => $label)
            <tr><td>{{ $label }}</td><td class="right">{{ $money->format($report['monthlyRevenue']['values'][$i]) }}</td></tr>
        @endforeach
    </table>
    <p><strong>Total: {{ $report['monthlyRevenue']['totalFormatted'] }}</strong></p>
</td><td>
    <h2>2 · Patient growth</h2>
    <table>
        <tr><th>Month</th><th class="right">New patients</th></tr>
        @foreach ($report['patientGrowth']['labels'] as $i => $label)
            <tr><td>{{ $label }}</td><td class="right">{{ $report['patientGrowth']['values'][$i] }}</td></tr>
        @endforeach
    </table>
    <p><strong>Total: {{ $report['patientGrowth']['total'] }}</strong> · walk-ins {{ $report['patientGrowth']['walkIns'] }}</p>
</td></tr></table>

<table class="grid"><tr><td class="box">
    <h2>3 · Prescription completion</h2>
    <p>Total {{ $report['prescriptions']['total'] }} · dispensed {{ $report['prescriptions']['dispensed'] }} ·
        completion {{ $report['prescriptions']['completionRate'] }}% · refused {{ $report['prescriptions']['refusedRate'] }}%</p>
    <p>Median time to dispense: {{ $report['prescriptions']['medianDispenseLabel'] }}</p>
</td><td class="box">
    <h2>4 · Invoice collection</h2>
    <p>Paid {{ $report['collection']['paidFormatted'] }} ({{ $report['collection']['paidCount'] }})</p>
    <p>Pending {{ $report['collection']['pendingFormatted'] }} ({{ $report['collection']['pendingCount'] }})</p>
    <p>Refunded {{ $report['collection']['refundedFormatted'] }} ({{ $report['collection']['refundedCount'] }})</p>
</td></tr></table>

<table class="grid"><tr><td class="box">
    <h2>5 · Stock health &amp; expiry risk</h2>
    <p>Out {{ $report['stock']['out'] }} · critical {{ $report['stock']['critical'] }} · low {{ $report['stock']['low'] }}
        · expiring ≤30d {{ $report['stock']['expiring30'] }} · ≤90d {{ $report['stock']['expiring90'] }}</p>
    @foreach ($report['stock']['items'] as $item)
        <div>{{ $item['name'] }} {{ $item['strength'] }} — {{ $item['stock'] }} ({{ $item['status'] }})</div>
    @endforeach
</td><td class="box">
    <h2>6 · Appointment conversion &amp; cancellation</h2>
    <p>Total {{ $report['appointments']['total'] }} · pending {{ $report['appointments']['pending'] }} ·
        accepted {{ $report['appointments']['accepted'] }} · completed {{ $report['appointments']['completed'] }}</p>
    <p>Acceptance {{ $report['appointments']['acceptanceRate'] }}% · completion {{ $report['appointments']['completionRate'] }}% ·
        cancellation {{ $report['appointments']['cancellationRate'] }}%</p>
</td></tr></table>

<h2>7 · Top-doctor leaderboard</h2>
<table>
    <thead><tr><th>#</th><th>Doctor</th><th>Specialty</th><th class="right">Visits</th><th class="right">Completion</th><th class="right">Rating</th><th class="right">Revenue</th></tr></thead>
    <tbody>
    @foreach ($report['leaderboard'] as $index => $doctor)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $doctor['name'] }}</td>
            <td>{{ $doctor['specialty'] }}</td>
            <td class="right">{{ $doctor['visits'] }}</td>
            <td class="right">{{ $doctor['completion'] }}%</td>
            <td class="right">{{ $doctor['rating'] ?? '—' }}</td>
            <td class="right">{{ $doctor['revenueFormatted'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<footer>Generated electronically by MediPath · admin report · money values are currency snapshots.</footer>
</body>
</html>