<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Bellhop') }}</title>

        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><foreignObject width=%22100%22 height=%22100%22><div xmlns=%22http://www.w3.org/1999/xhtml%22 style=%22font-size:80px;display:flex;align-items:center;justify-content:center;height:100%25%22>%F0%9F%9B%8E%EF%B8%8F</div></foreignObject></svg>">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|playfair-display:500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
