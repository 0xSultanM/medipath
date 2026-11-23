<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediPath responsive harness · {{ $width }}px</title>
    <style>
        html, body { margin: 0; padding: 0; background: #1f2937; }
        .stage {
            width: {{ (int) round($width * $scale) }}px;
            height: {{ (int) round(1200 * $scale) }}px;
            overflow: hidden;
            margin: 0 auto;
        }
        iframe {
            width: {{ $width }}px;
            height: 1200px;
            border: 0;
            transform: scale({{ $scale }});
            transform-origin: top left;
            background: #fff;
        }
        .meta {
            color: #9ca3af;
            font: 12px/1.4 ui-monospace, monospace;
            padding: 6px 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="meta">MediPath · {{ $width }}px viewport · {{ $path }}</div>
    <div class="stage"><iframe src="{{ $path }}" title="MediPath at {{ $width }} pixels"></iframe></div>
</body>
</html>