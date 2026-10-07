<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="PDFolio — every PDF tool you need, running on your own server. Merge, split, compress, convert, protect and more.">
    <title>PDFolio — Your own PDF toolbox</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%232749e8'/%3E%3Cpath d='M9 8h10l4 4v12a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z' fill='white'/%3E%3Cpath d='M19 8v4h4' fill='%23ffb26b'/%3E%3Crect x='10' y='17' width='8' height='2' rx='1' fill='%232749e8'/%3E%3Crect x='10' y='21' width='5' height='2' rx='1' fill='%237cebab'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-slate-800 antialiased">
    <div id="app"></div>
</body>
</html>
