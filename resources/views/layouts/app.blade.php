<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $metaUrl = url('/map');
        $metaImage = url('/og/signalmap-og.jpg');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>SignalMap — Community Reporting Map</title>
    <meta
        name="description"
        content="SignalMap is a community-powered civic map where people can report real-world events, view local incidents, and help verify or resolve reports together in real time.">

    <meta property="og:title" content="SignalMap — Community Reporting Map">
    <meta
        property="og:description"
        content="SignalMap is a community-powered civic map where people can report real-world events, view local incidents, and help verify or resolve reports together in real time.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:image" content="{{ $metaImage }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="SignalMap — Community Reporting Map">
    <meta name="twitter:description" content="Community-powered reporting map">
    <meta name="twitter:image" content="{{ $metaImage }}">
    @stack('styles')
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>
