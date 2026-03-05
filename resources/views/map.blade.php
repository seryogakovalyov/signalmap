@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin="">
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css"
    crossorigin="">
<link rel="stylesheet" href="{{ asset('css/map-page.css') }}?v={{ filemtime(public_path('css/map-page.css')) }}">
@endpush

@push('scripts')
<script type="application/json" id="map-config">
    @json(['categories' => $categoryOptions])
</script>
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>
<script
    src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"
    crossorigin=""></script>
<script
    src="https://unpkg.com/supercluster@8.0.1/dist/supercluster.min.js"
    crossorigin=""></script>
<script src="{{ asset('js/map-page.js') }}?v={{ filemtime(public_path('js/map-page.js')) }}"></script>
@endpush

@section('content')
<div class="page">
    <aside class="panel sidebar">
        <p class="eyebrow">A community-powered map of real-world events</p>
        <h1>SignalMap</h1>
        <p class="intro">
            Click anywhere on the map to add a report.
            Markers are color-coded by category, and the popup shows the community verification status.
        </p>
        <p class="hint" id="map-hint">Select a location on the map to start a new report.</p>
        <div class="alert alert-success" id="success-message"></div>
        <div class="alert alert-error" id="error-message"></div>

        <form id="report-form">
            <label>
                Title
                <input type="text" name="title" maxlength="255" required>
            </label>

            <label>
                Description
                <textarea name="description" maxlength="5000" required></textarea>
            </label>

            <label>
                Category
                <select name="category_id" {{ $categories->isEmpty() ? 'disabled' : '' }} required>
                    <option value="">Select a category</option>
                    @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>

            <input type="hidden" name="latitude" required>
            <input type="hidden" name="longitude" required>

            <button class="report-submit-button" type="submit" {{ $categories->isEmpty() ? 'disabled' : '' }}>Submit Report</button>
        </form>

        <div class="legend" id="category-legend" aria-label="Category legend"></div>

        <div class="utility-links">
            @auth
            <a href="{{ route('admin.reports.index') }}">Open moderation</a>
            @else
            <a href="{{ route('login') }}">Staff sign in</a>
            @endauth
        </div>
    </aside>

    <section class="panel map-shell">
        <div id="report-map" aria-label="Interactive incident map"></div>
    </section>
</div>
@endsection
