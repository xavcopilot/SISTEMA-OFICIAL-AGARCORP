<?php

namespace App\Support;

use Illuminate\Http\Response;

class ExportNoticeResponse
{
    public static function emptyData(string $title, string $message, int $status = 200): Response
    {
        $safeTitle = e($title);
        $safeMessage = e($message);

        $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle}</title>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(135deg, #f8fafc, #eef2ff);
            color: #0f172a;
        }
        .card {
            width: min(92vw, 560px);
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
            padding: 22px 22px 20px;
        }
        .badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 999px;
            padding: 5px 10px;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 8px;
            line-height: 1.3;
        }
        p {
            margin: 0;
            color: #334155;
            line-height: 1.55;
        }
    </style>
</head>
<body>
    <article class="card" role="status" aria-live="polite">
        <span class="badge">Sin datos</span>
        <h1>{$safeTitle}</h1>
        <p>{$safeMessage}</p>
    </article>
</body>
</html>
HTML;

        return response($html, $status)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
