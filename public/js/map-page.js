(function () {
    const configElement = document.getElementById('map-config');
    const mapElement = document.getElementById('report-map');

    if (!configElement || !mapElement || typeof window.L === 'undefined') {
        return;
    }

    const config = JSON.parse(configElement.textContent || '{}');
    const categories = Array.isArray(config.categories) ? config.categories : [];

    const form = document.getElementById('report-form');
    const hint = document.getElementById('map-hint');
    const successBox = document.getElementById('success-message');
    const errorBox = document.getElementById('error-message');
    const legendElement = document.getElementById('category-legend');
    const submitButton = form.querySelector('button[type="submit"]');
    const latInput = form.querySelector('input[name="latitude"]');
    const lngInput = form.querySelector('input[name="longitude"]');
    const titleInput = form.querySelector('input[name="title"]');
    const descriptionInput = form.querySelector('textarea[name="description"]');
    const categoryInput = form.querySelector('select[name="category_id"]');
    const defaultCenter = [40.7128, -74.0060];
    const defaultZoom = 12;
    const userZoom = 14;
    const geolocationStorageKey = 'civic-reports:last-known-geolocation';
    const viewportStorageKey = 'civic-reports:last-map-view';
    const voteStorageKey = 'civic-reports:session-votes';
    const fetchDebounceMs = 200;
    const maxPointFetchByZoom = (zoom) => (zoom <= 3 ? 2500 : zoom <= 5 ? 4000 : 7000);
    const categoryIcons = {
        community: '👥',
        environment: '🌿',
        infrastructure: '🏢',
        safety: '⚠️',
        traffic: '🚗',
    };

    const map = L.map('report-map', {
        zoomControl: false,
        closePopupOnClick: true,
    });
    const mobileControlsMediaQuery = window.matchMedia('(max-width: 767.98px)');
    let zoomControl = null;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const supportsSupercluster = typeof window.Supercluster === 'function';
    const clusterLayer = L.layerGroup().addTo(map);
    const markersLayer = L.layerGroup().addTo(map);
    const reportMarkers = new Map();
    const reportState = new Map();
    let clusterIndex = null;
    let geocoderResultMarker = null;
    let draftMarker = null;
    let lastRequestedBbox = null;
    let fetchReportsController = null;
    let fetchReportsTimer = null;
    let currentUserLocation = null;
    let suppressViewportPersistence = false;
    let skipNextMoveendFetch = false;

    const mountZoomControl = () => {
        const nextPosition = mobileControlsMediaQuery.matches ? 'bottomleft' : 'bottomright';

        if (zoomControl) {
            map.removeControl(zoomControl);
        }

        zoomControl = L.control.zoom({
            position: nextPosition,
        }).addTo(map);
    };

    const showMessage = (target, text) => {
        target.textContent = text;
        target.classList.add('is-visible');
    };

    const clearMessages = () => {
        successBox.classList.remove('is-visible');
        errorBox.classList.remove('is-visible');
    };

    const formatDate = (value) => {
        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleString();
    };

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const normalizeCategoryKey = (value) => String(value || '')
        .trim()
        .toLowerCase()
        .replaceAll(/[^a-z0-9]+/g, '-');
    const categoryEmoji = (categoryName) => categoryIcons[normalizeCategoryKey(categoryName)] || '📍';

    const renderLegend = () => {
        if (!legendElement) {
            return;
        }

        legendElement.innerHTML = '';

        categories.forEach((category) => {
            const item = document.createElement('div');
            item.className = 'legend-item';

            const swatch = document.createElement('span');
            swatch.className = 'legend-swatch';
            swatch.style.background = category.color;

            const icon = document.createElement('span');
            icon.className = 'legend-icon';
            icon.textContent = categoryEmoji(category.name);
            icon.setAttribute('aria-hidden', 'true');

            const label = document.createElement('span');
            label.textContent = category.name;

            item.appendChild(swatch);
            item.appendChild(icon);
            item.appendChild(label);
            legendElement.appendChild(item);
        });
    };

    const createCategoryIcon = (color, icon) => L.divIcon({
        className: 'category-marker-icon',
        html: `
            <span class="category-marker-dot" style="background:${escapeHtml(color)};">
                <span class="category-marker-emoji" aria-hidden="true">${escapeHtml(icon)}</span>
            </span>
        `,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        popupAnchor: [0, -12],
    });

    const createGeocoderResultIcon = () => L.divIcon({
        className: 'geocoder-result-icon',
        html: '<span aria-hidden="true"></span>',
        iconSize: [20, 20],
        iconAnchor: [10, 10],
    });

    const statusMeta = (status) => {
        switch (status) {
            case 'confirmed':
                return {
                    icon: '🟢',
                    label: 'Confirmed',
                };
            case 'partially_confirmed':
                return {
                    icon: '🟡',
                    label: 'Partially confirmed',
                };
            case 'resolved':
                return {
                    icon: '⚫',
                    label: 'Resolved',
                };
            default:
                return {
                    icon: '⚪',
                    label: 'Unverified',
                };
        }
    };

    const createClusterIcon = (count) => {
        const size = count < 20 ? 34 : count < 100 ? 40 : 48;
        const label = count > 999 ? '999+' : String(count);
        const sizeClass = count < 100 ? 'cluster-small' : count < 500 ? 'cluster-medium' : 'cluster-large';

        return L.divIcon({
            className: `map-cluster ${sizeClass}`,
            html: `<div><span>${escapeHtml(label)}</span></div>`,
            iconSize: [size, size],
        });
    };

    const readVoteStore = () => {
        try {
            const rawValue = window.sessionStorage.getItem(voteStorageKey);

            if (!rawValue) {
                return {};
            }

            const parsedValue = JSON.parse(rawValue);

            return parsedValue && typeof parsedValue === 'object' ? parsedValue : {};
        } catch (error) {
            return {};
        }
    };

    const hasVotedForAction = (reportId, voteType) => Boolean(readVoteStore()[String(reportId)]?.[voteType]);

    const rememberVote = (reportId, voteType) => {
        try {
            const votes = readVoteStore();
            const currentVotes = votes[String(reportId)];
            const normalizedVotes =
                currentVotes && typeof currentVotes === 'object' && !Array.isArray(currentVotes)
                    ? currentVotes
                    : {};

            votes[String(reportId)] = {
                ...normalizedVotes,
                [voteType]: true,
            };
            window.sessionStorage.setItem(voteStorageKey, JSON.stringify(votes));
        } catch (error) {
            // Ignore storage failures; the backend vote will still be recorded.
        }
    };

    const markerPopup = (report) => `
        <div class="report-popup-content" style="min-width:240px;max-width:100%;">
            <strong class="report-popup-title" style="display:block;margin-bottom:0.4rem;">${escapeHtml(report.title)}</strong>
            <div style="display:flex;align-items:center;gap:0.35rem;margin-bottom:0.45rem;font-size:0.9rem;color:#475569;">
                <span aria-hidden="true">${escapeHtml(categoryEmoji(report.category?.name))}</span>
                <strong>${escapeHtml(report.category?.name || 'Uncategorized')}</strong>
            </div>
            <div class="report-popup-description" style="margin-bottom:0.6rem;line-height:1.45;">${escapeHtml(report.description)}</div>
            <div style="margin-bottom:0.35rem;font-size:0.9rem;">
                <strong>Status:</strong>
                <span>${statusMeta(report.status).icon} ${escapeHtml(statusMeta(report.status).label)}</span>
            </div>
            <div style="margin-bottom:0.55rem;font-size:0.9rem;">
                <strong>Confirmations:</strong> ${escapeHtml(report.confirmations_count)}
            </div>
            <div style="margin-bottom:0.6rem;font-size:0.82rem;color:#6b7280">${escapeHtml(formatDate(report.created_at))}</div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <button
                    type="button"
                    class="report-popup-action report-popup-action-confirm"
                    data-vote-action="confirm"
                    data-report-id="${report.id}"
                    ${hasVotedForAction(report.id, 'confirm') || !report.can_confirm ? 'disabled' : ''}
                    style="border:0;border-radius:999px;padding:0.55rem 0.85rem;color:#fff;font:inherit;font-weight:700;"
                >
                    Confirm
                </button>
                <button
                    type="button"
                    class="report-popup-action report-popup-action-clear"
                    data-vote-action="clear"
                    data-report-id="${report.id}"
                    ${hasVotedForAction(report.id, 'clear') || report.status === 'resolved' ? 'disabled' : ''}
                    style="border:0;border-radius:999px;padding:0.55rem 0.85rem;color:#fff;font:inherit;font-weight:700;"
                >
                    Gone
                </button>
            </div>
            ${
                !report.can_confirm
                    ? '<div style="margin-top:0.5rem;font-size:0.78rem;color:#6b7280;">You cannot confirm your own report.</div>'
                    : hasVotedForAction(report.id, 'confirm') || hasVotedForAction(report.id, 'clear')
                      ? '<div style="margin-top:0.5rem;font-size:0.78rem;color:#6b7280;">Repeated votes of the same type are blocked for this browser session.</div>'
                      : ''
            }
        </div>
    `;

    const updateMarkerPopup = (reportId) => {
        const report = reportState.get(reportId);
        const marker = reportMarkers.get(reportId);

        if (!report || !marker) {
            return;
        }

        marker.setPopupContent(markerPopup(report));

        if (marker.isPopupOpen()) {
            marker.openPopup();
        }
    };

    const upsertMarker = (report) => {
        reportState.set(report.id, report);

        const color = report.category?.color || '#2563eb';
        const icon = categoryEmoji(report.category?.name);
        const existingMarker = reportMarkers.get(report.id);

        if (existingMarker) {
            existingMarker.setLatLng([report.latitude, report.longitude]);
            existingMarker.setIcon(createCategoryIcon(color, icon));
            existingMarker.setPopupContent(markerPopup(report));
            return;
        }

        const marker = L.marker([report.latitude, report.longitude], {
            icon: createCategoryIcon(color, icon),
        })
            .bindPopup(markerPopup(report))
            .addTo(markersLayer);

        reportMarkers.set(report.id, marker);
    };

    const removeMarker = (reportId) => {
        const marker = reportMarkers.get(reportId);

        if (marker) {
            markersLayer.removeLayer(marker);
            reportMarkers.delete(reportId);
        }

        reportState.delete(reportId);
    };

    const clearRenderedMarkers = () => {
        reportMarkers.forEach((marker) => {
            markersLayer.removeLayer(marker);
        });
        reportMarkers.clear();
        clusterLayer.clearLayers();
    };

    const getOpenedPopupReportId = () => {
        const popupElement = map._popup?.getElement?.();

        if (!popupElement) {
            return null;
        }

        const voteButton = popupElement.querySelector('[data-report-id]');

        if (!voteButton) {
            return null;
        }

        const reportId = Number(voteButton.getAttribute('data-report-id'));

        return Number.isFinite(reportId) && reportId > 0 ? reportId : null;
    };

    const buildClusterIndex = () => {
        if (!supportsSupercluster) {
            return;
        }

        const points = Array.from(reportState.values())
            .filter((report) => report.status !== 'resolved')
            .map((report) => ({
                type: 'Feature',
                geometry: {
                    type: 'Point',
                    coordinates: [report.longitude, report.latitude],
                },
                properties: {
                    reportId: report.id,
                },
            }));

        clusterIndex = new window.Supercluster({
            radius: 50,
            maxZoom: 18,
            minPoints: 2,
        });
        clusterIndex.load(points);
    };

    const renderViewportFromClusterIndex = () => {
        if (!supportsSupercluster || !clusterIndex) {
            return;
        }

        clearRenderedMarkers();

        const bounds = map.getBounds();
        const zoom = Math.round(map.getZoom());
        const features = clusterIndex.getClusters(
            [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()],
            zoom
        );

        features.forEach((feature) => {
            const [lng, lat] = feature.geometry.coordinates;
            const properties = feature.properties || {};

            if (properties.cluster) {
                const marker = L.marker([lat, lng], {
                    icon: createClusterIcon(properties.point_count || 0),
                    keyboard: false,
                });

                marker.on('click', () => {
                    const expansionZoom = clusterIndex.getClusterExpansionZoom(properties.cluster_id);
                    map.setView([lat, lng], Math.min(expansionZoom, 19));
                });
                marker.addTo(clusterLayer);
                return;
            }

            const reportId = Number(properties.reportId);
            const report = reportState.get(reportId);

            if (!report) {
                return;
            }

            upsertMarker(report);
        });
    };

    const fetchReports = async () => {
        const openedPopupReportId = getOpenedPopupReportId();
        const bounds = map.getBounds();
        const zoom = Math.round(map.getZoom());
        const bbox = [
            bounds.getSouthWest().lat,
            bounds.getSouthWest().lng,
            bounds.getNorthEast().lat,
            bounds.getNorthEast().lng,
        ].join(',');
        const limit = maxPointFetchByZoom(zoom);
        const requestKey = `${bbox}|z:${zoom}|l:${limit}`;

        if (requestKey === lastRequestedBbox) {
            return;
        }

        lastRequestedBbox = requestKey;

        if (fetchReportsController) {
            fetchReportsController.abort();
        }

        fetchReportsController = new AbortController();

        const params = new URLSearchParams({
            bbox,
            limit: String(limit),
        });
        const response = await fetch(`/api/reports?${params.toString()}`, {
            signal: fetchReportsController.signal,
        });

        if (!response.ok) {
            throw new Error('Unable to load map data.');
        }

        const payload = await response.json();
        reportState.clear();
        (Array.isArray(payload.data) ? payload.data : []).forEach((report) => {
            if (report.status !== 'resolved') {
                reportState.set(report.id, report);
            }
        });

        if (supportsSupercluster) {
            buildClusterIndex();
            renderViewportFromClusterIndex();
        } else {
            clearRenderedMarkers();
            reportState.forEach((report) => {
                upsertMarker(report);
            });
        }

        if (openedPopupReportId && reportMarkers.has(openedPopupReportId)) {
            reportMarkers.get(openedPopupReportId)?.openPopup();
        }

        fetchReportsController = null;
    };

    const scheduleFetchReports = () => {
        if (fetchReportsTimer) {
            window.clearTimeout(fetchReportsTimer);
        }

        fetchReportsTimer = window.setTimeout(() => {
            fetchReports()
                .catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    lastRequestedBbox = null;
                    showMessage(errorBox, error.message);
                })
                .finally(() => {
                    fetchReportsTimer = null;
                });
        }, fetchDebounceMs);
    };

    const setDraftCoordinates = (latlng) => {
        const latitude = Number(latlng.lat.toFixed(7));
        const longitude = Number(latlng.lng.toFixed(7));

        latInput.value = latitude;
        lngInput.value = longitude;
        hint.textContent = `Draft location selected at ${latitude}, ${longitude}.`;

        if (draftMarker) {
            draftMarker.setLatLng(latlng);
            return;
        }

        draftMarker = L.marker(latlng, {
            draggable: true,
        }).addTo(map);

        draftMarker.on('dragend', (event) => {
            setDraftCoordinates(event.target.getLatLng());
        });
    };

    const readStoredGeolocation = () => {
        try {
            const rawValue = window.localStorage.getItem(geolocationStorageKey);

            if (!rawValue) {
                return null;
            }

            const parsedValue = JSON.parse(rawValue);

            if (
                !Array.isArray(parsedValue) ||
                parsedValue.length !== 2 ||
                !parsedValue.every((value) => Number.isFinite(value))
            ) {
                return null;
            }

            return parsedValue;
        } catch (error) {
            return null;
        }
    };

    const storeGeolocation = (coordinates) => {
        try {
            window.localStorage.setItem(geolocationStorageKey, JSON.stringify(coordinates));
        } catch (error) {
            // Ignore storage failures; geolocation should still work without persistence.
        }
    };

    const readStoredMapView = () => {
        try {
            const rawValue = window.localStorage.getItem(viewportStorageKey);

            if (!rawValue) {
                return null;
            }

            const parsedValue = JSON.parse(rawValue);

            if (
                !parsedValue ||
                typeof parsedValue !== 'object' ||
                !Array.isArray(parsedValue.center) ||
                parsedValue.center.length !== 2 ||
                !parsedValue.center.every((value) => Number.isFinite(value)) ||
                !Number.isFinite(parsedValue.zoom)
            ) {
                return null;
            }

            return parsedValue;
        } catch (error) {
            return null;
        }
    };

    const storeMapView = (coordinates, zoom) => {
        try {
            window.localStorage.setItem(
                viewportStorageKey,
                JSON.stringify({
                    center: coordinates.map((value) => Number(Number(value).toFixed(6))),
                    zoom: Number(zoom),
                })
            );
        } catch (error) {
            // Ignore storage failures; the map can still function without persistence.
        }
    };

    const persistCurrentMapView = () => {
        if (suppressViewportPersistence) {
            return;
        }

        const center = map.getCenter();

        storeMapView([center.lat, center.lng], map.getZoom());
    };

    const applyMapView = (coordinates, zoom, message, persistViewport = false) => {
        suppressViewportPersistence = true;
        map.once('moveend', () => {
            suppressViewportPersistence = false;

            if (persistViewport) {
                storeMapView(coordinates, zoom);
            }
        });

        map.setView(coordinates, zoom);

        if (message) {
            hint.textContent = message;
        }
    };

    const centerOnCurrentLocation = () => {
        const applyResolvedLocation = (coordinates, message) => {
            currentUserLocation = coordinates;
            storeGeolocation(coordinates);
            applyMapView(coordinates, userZoom, message, true);
        };

        if (currentUserLocation) {
            applyResolvedLocation(currentUserLocation, 'Map centered on your current location.');
            return;
        }

        if (!navigator.geolocation) {
            showMessage(errorBox, 'Geolocation is unavailable in this browser.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                applyResolvedLocation(
                    [
                        position.coords.latitude,
                        position.coords.longitude,
                    ],
                    'Map centered on your current location.'
                );
            },
            () => {
                showMessage(errorBox, 'Unable to determine your current location.');
            },
            {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 300000,
            }
        );
    };

    const getGeolocationPermissionState = async () => {
        if (!navigator.permissions || !navigator.permissions.query) {
            return null;
        }

        try {
            const permission = await navigator.permissions.query({
                name: 'geolocation',
            });

            return permission.state;
        } catch (error) {
            return null;
        }
    };

    const requestCurrentPosition = (options) => new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });

    const CurrentLocationControl = L.Control.extend({
        options: {
            position: 'bottomright',
        },
        onAdd() {
            const button = L.DomUtil.create('button', 'map-location-button');
            button.type = 'button';
            button.title = 'Return to my location';
            button.setAttribute('aria-label', 'Return to my location');
            button.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="1.8"></circle>
                    <circle cx="12" cy="12" r="2.6" fill="currentColor"></circle>
                </svg>
            `;

            L.DomEvent.disableClickPropagation(button);
            L.DomEvent.on(button, 'click', () => {
                clearMessages();
                centerOnCurrentLocation();
            });

            return button;
        },
    });

    map.addControl(new CurrentLocationControl());

    if (mobileControlsMediaQuery.addEventListener) {
        mobileControlsMediaQuery.addEventListener('change', mountZoomControl);
    } else if (mobileControlsMediaQuery.addListener) {
        mobileControlsMediaQuery.addListener(mountZoomControl);
    }

    if (window.L.Control.Geocoder) {
        // Keep plugin UI control, but route all network requests through our own autosuggest logic
        // to avoid duplicate requests and Nominatim rate-limit spikes.
        const silentGeocoder = {
            geocode(_query, callback, context) {
                if (typeof callback === 'function') {
                    callback.call(context, []);
                }
            },
            suggest(_query, callback, context) {
                if (typeof callback === 'function') {
                    callback.call(context, []);
                }
            },
            reverse(_location, _scale, callback, context) {
                if (typeof callback === 'function') {
                    callback.call(context, []);
                }
            },
        };

        const applyGeocodeSelection = (center, bbox = null) => {
            if (bbox && bbox.isValid && bbox.isValid()) {
                map.fitBounds(bbox, {
                    padding: [24, 24],
                    maxZoom: 16,
                });
            } else {
                map.setView(center, 16);
            }

            if (geocoderResultMarker) {
                map.removeLayer(geocoderResultMarker);
            }

            geocoderResultMarker = L.marker(center, {
                icon: createGeocoderResultIcon(),
                keyboard: false,
            }).addTo(map);
        };

        const geocoder = L.Control.geocoder({
            geocoder: silentGeocoder,
            defaultMarkGeocode: false,
            collapsed: false,
            position: 'topleft',
            placeholder: 'Search address or place...',
            errorMessage: 'Address not found.',
            queryMinLength: 3,
            suggestMinLength: 999,
            suggestTimeout: 200,
        })
            .on('markgeocode', (event) => {
                applyGeocodeSelection(event.geocode.center, event.geocode.bbox);
            })
            .addTo(map);

        const geocoderContainer = geocoder.getContainer();
        const geocoderInput = geocoderContainer?.querySelector('.leaflet-control-geocoder-form input');
        if (geocoderInput) {
            const nativeAlternatives = geocoderContainer?.querySelector('.leaflet-control-geocoder-alternatives');
            if (nativeAlternatives) {
                nativeAlternatives.remove();
            }

            geocoderInput.setAttribute('placeholder', 'Search address or place...');
            geocoderInput.setAttribute('aria-label', 'Search address or place');
            geocoderInput.setAttribute('autocomplete', 'off');
            geocoderInput.setAttribute('role', 'combobox');
            geocoderInput.setAttribute('aria-expanded', 'false');
            geocoderInput.setAttribute('aria-autocomplete', 'list');

            const suggestionList = document.createElement('ul');
            suggestionList.className = 'geocoder-suggestions';
            suggestionList.hidden = true;
            suggestionList.id = 'map-geocoder-suggestions';
            suggestionList.setAttribute('role', 'listbox');
            geocoderInput.setAttribute('aria-controls', suggestionList.id);
            geocoder.getContainer()?.appendChild(suggestionList);

            let suggestionsAbortController = null;
            let suggestionsTimer = null;
            let highlightedSuggestionIndex = -1;
            let currentSuggestions = [];
            let suggestionsBlockedUntil = 0;

            const applySuggestionSelection = (item) => {
                geocoderInput.value = item.display_name;
                hideSuggestions();

                const lat = Number(item.lat);
                const lon = Number(item.lon);
                const center = L.latLng(lat, lon);

                let bbox = null;
                if (Array.isArray(item.boundingbox) && item.boundingbox.length === 4) {
                    const south = Number(item.boundingbox[0]);
                    const north = Number(item.boundingbox[1]);
                    const west = Number(item.boundingbox[2]);
                    const east = Number(item.boundingbox[3]);

                    if ([south, north, west, east].every(Number.isFinite)) {
                        bbox = L.latLngBounds(
                            [south, west],
                            [north, east],
                        );
                    }
                }

                applyGeocodeSelection(center, bbox);
            };

            const syncHighlightedSuggestion = () => {
                const buttons = suggestionList.querySelectorAll('.geocoder-suggestion-item');

                buttons.forEach((button, index) => {
                    const isActive = index === highlightedSuggestionIndex;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');

                    if (isActive) {
                        geocoderInput.setAttribute('aria-activedescendant', button.id);
                        button.scrollIntoView({
                            block: 'nearest',
                        });
                    }
                });

                if (highlightedSuggestionIndex < 0) {
                    geocoderInput.removeAttribute('aria-activedescendant');
                }
            };

            const hideSuggestions = () => {
                suggestionList.hidden = true;
                suggestionList.innerHTML = '';
                currentSuggestions = [];
                highlightedSuggestionIndex = -1;
                geocoderInput.setAttribute('aria-expanded', 'false');
                geocoderInput.removeAttribute('aria-activedescendant');
            };

            const renderSuggestions = (items) => {
                suggestionList.innerHTML = '';
                currentSuggestions = items;
                highlightedSuggestionIndex = -1;

                if (!items.length) {
                    hideSuggestions();
                    return;
                }

                items.forEach((item, index) => {
                    const li = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'geocoder-suggestion-item';
                    button.id = `map-geocoder-option-${index}`;
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', 'false');
                    button.textContent = item.display_name;

                    button.addEventListener('click', () => {
                        applySuggestionSelection(item);
                    });

                    button.addEventListener('mouseenter', () => {
                        highlightedSuggestionIndex = index;
                        syncHighlightedSuggestion();
                    });

                    li.appendChild(button);
                    suggestionList.appendChild(li);
                });

                suggestionList.hidden = false;
                geocoderInput.setAttribute('aria-expanded', 'true');
            };

            const fetchSuggestions = async (query) => {
                if (Date.now() < suggestionsBlockedUntil) {
                    hideSuggestions();
                    return;
                }

                if (suggestionsAbortController) {
                    suggestionsAbortController.abort();
                }

                suggestionsAbortController = new AbortController();

                const bounds = map.getBounds();
                const viewbox = [
                    bounds.getWest(),
                    bounds.getNorth(),
                    bounds.getEast(),
                    bounds.getSouth(),
                ].join(',');
                const params = new URLSearchParams({
                    q: query,
                    format: 'jsonv2',
                    addressdetails: '1',
                    limit: '8',
                    'accept-language': 'uk,ru,en',
                    viewbox,
                    bounded: '0',
                });

                const response = await fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                    signal: suggestionsAbortController.signal,
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    if (response.status === 429) {
                        suggestionsBlockedUntil = Date.now() + 60000;
                    }

                    hideSuggestions();
                    return;
                }

                const payload = await response.json();
                renderSuggestions(Array.isArray(payload) ? payload : []);
            };

            const scheduleSuggestions = (query) => {
                if (suggestionsTimer) {
                    window.clearTimeout(suggestionsTimer);
                }

                if (query.trim().length < 3) {
                    hideSuggestions();
                    return;
                }

                suggestionsTimer = window.setTimeout(() => {
                    fetchSuggestions(query.trim()).catch((error) => {
                        if (error.name !== 'AbortError') {
                            hideSuggestions();
                        }
                    });
                }, 380);
            };

            geocoderInput.addEventListener('input', (event) => {
                scheduleSuggestions(event.target.value || '');
            });

            geocoderInput.addEventListener('focus', (event) => {
                scheduleSuggestions(event.target.value || '');
            });

            geocoderInput.addEventListener('keydown', (event) => {
                const hasSuggestions = !suggestionList.hidden && currentSuggestions.length > 0;

                if (event.key === 'Escape' && hasSuggestions) {
                    event.preventDefault();
                    hideSuggestions();
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();

                    if (!hasSuggestions) {
                        scheduleSuggestions(geocoderInput.value || '');
                        return;
                    }

                    highlightedSuggestionIndex =
                        highlightedSuggestionIndex < currentSuggestions.length - 1
                            ? highlightedSuggestionIndex + 1
                            : 0;
                    syncHighlightedSuggestion();
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();

                    if (!hasSuggestions) {
                        scheduleSuggestions(geocoderInput.value || '');
                        return;
                    }

                    highlightedSuggestionIndex =
                        highlightedSuggestionIndex > 0
                            ? highlightedSuggestionIndex - 1
                            : currentSuggestions.length - 1;
                    syncHighlightedSuggestion();
                    return;
                }

                if (event.key === 'Enter' && hasSuggestions && highlightedSuggestionIndex >= 0) {
                    event.preventDefault();
                    applySuggestionSelection(currentSuggestions[highlightedSuggestionIndex]);
                }
            });

            // Prevent the plugin's internal Enter handler from triggering its own geocode request.
            geocoderInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
            }, true);

            geocoderInput.addEventListener('blur', () => {
                window.setTimeout(hideSuggestions, 140);
            });

            L.DomEvent.disableScrollPropagation(suggestionList);
            L.DomEvent.disableClickPropagation(suggestionList);
            L.DomEvent.on(suggestionList, 'wheel', L.DomEvent.stopPropagation);
            L.DomEvent.on(suggestionList, 'touchstart', L.DomEvent.stopPropagation);
            L.DomEvent.on(suggestionList, 'touchmove', L.DomEvent.stopPropagation);
        }
    }

    mountZoomControl();

    map.on('click', (event) => {
        clearMessages();
        setDraftCoordinates(event.latlng);
    });

    map.on('dragend', () => {
        persistCurrentMapView();
    });

    map.on('zoomend', () => {
        persistCurrentMapView();

        if (supportsSupercluster && clusterIndex) {
            renderViewportFromClusterIndex();
        }
    });

    map.on('moveend', () => {
        if (skipNextMoveendFetch) {
            skipNextMoveendFetch = false;
            return;
        }

        scheduleFetchReports();
    });

    map.on('popupopen', (event) => {
        // Leaflet autopan fires moveend when popup opens near map edge.
        // Skip one fetch cycle to avoid immediate layer re-render that closes the popup.
        skipNextMoveendFetch = true;

        const popupRoot = event.popup.getElement();

        if (!popupRoot) {
            return;
        }

        const syncPopupVoteControls = () => {
            popupRoot.querySelectorAll('[data-vote-action]').forEach((control) => {
                const reportId = Number(control.dataset.reportId);
                const voteAction = control.dataset.voteAction;
                const report = reportState.get(reportId);

                if (!report || !voteAction) {
                    return;
                }

                if (voteAction === 'confirm') {
                    control.disabled = hasVotedForAction(reportId, 'confirm') || !report.can_confirm;
                    return;
                }

                if (voteAction === 'clear') {
                    control.disabled = hasVotedForAction(reportId, 'clear') || report.status === 'resolved';
                }
            });
        };

        syncPopupVoteControls();

        if (popupRoot.dataset.voteHandlerBound === '1') {
            return;
        }

        popupRoot.dataset.voteHandlerBound = '1';
        popupRoot.addEventListener('click', async (clickEvent) => {
            const button = clickEvent.target.closest('[data-vote-action]');

            if (!button || !popupRoot.contains(button)) {
                return;
            }

            const reportId = Number(button.dataset.reportId);
            const voteAction = button.dataset.voteAction;

            if (!reportId || !voteAction) {
                return;
            }

            if (hasVotedForAction(reportId, voteAction)) {
                showMessage(errorBox, `You have already submitted a ${voteAction} vote for this report.`);
                return;
            }

            popupRoot.querySelectorAll('[data-vote-action]').forEach((control) => {
                control.disabled = true;
            });

            try {
                const response = await fetch(`/api/reports/${reportId}/${voteAction}`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const body = await response.json();

                if (response.status === 409 || response.status === 403) {
                    if (response.status === 409) {
                        rememberVote(reportId, voteAction);
                        updateMarkerPopup(reportId);
                        syncPopupVoteControls();
                    }

                    if (response.status === 403 && voteAction === 'confirm') {
                        const currentReport = reportState.get(reportId);

                        if (currentReport) {
                            currentReport.can_confirm = false;
                            reportState.set(reportId, currentReport);
                            updateMarkerPopup(reportId);
                            syncPopupVoteControls();
                        }
                    }

                    showMessage(errorBox, body.message || 'This action is not available for this report.');
                    return;
                }

                if (!response.ok) {
                    throw new Error(body.message || 'Unable to record your vote.');
                }

                rememberVote(reportId, voteAction);

                if (body.data.status === 'resolved') {
                    removeMarker(reportId);
                    if (supportsSupercluster) {
                        buildClusterIndex();
                        renderViewportFromClusterIndex();
                    }
                    map.closePopup();
                    return;
                }

                reportState.set(reportId, body.data);
                updateMarkerPopup(reportId);
                syncPopupVoteControls();
            } catch (error) {
                popupRoot.querySelectorAll('[data-vote-action]').forEach((control) => {
                    control.disabled = false;
                });

                showMessage(errorBox, error.message || 'Unable to record your vote.');
            }
        });
    });

    const initializeMapLocation = () => {
        const storedMapView = readStoredMapView();
        const storedGeolocation = readStoredGeolocation();

        if (storedMapView) {
            applyMapView(
                storedMapView.center,
                storedMapView.zoom,
                'Map restored to your last viewed area. Use the location button to return to your position.'
            );
        } else if (storedGeolocation) {
            applyMapView(
                storedGeolocation,
                userZoom,
                'Map centered on your last known location. Updating with your current position...'
            );
        } else {
            hint.textContent = 'Determining your location...';
        }

        currentUserLocation = storedGeolocation;

        if (!navigator.geolocation) {
            if (!storedMapView && !storedGeolocation) {
                applyMapView(
                    defaultCenter,
                    defaultZoom,
                    'Geolocation is unavailable. Showing the default map area.'
                );
            }

            return;
        }

        (async () => {
            const permissionState = await getGeolocationPermissionState();
            const primaryTimeout = permissionState === 'prompt' ? 30000 : 8000;

            const resolveLocation = async () => {
                try {
                    return await requestCurrentPosition({
                        enableHighAccuracy: false,
                        timeout: primaryTimeout,
                        maximumAge: 300000,
                    });
                } catch (error) {
                    // First prompt can exceed timeout while user interacts with the browser permission dialog.
                    // Retry once with a relaxed timeout before falling back to the default map area.
                    if (error?.code === 3) {
                        return requestCurrentPosition({
                            enableHighAccuracy: false,
                            timeout: 60000,
                            maximumAge: 300000,
                        });
                    }

                    throw error;
                }
            };

            try {
                const position = await resolveLocation();
                const userLocation = [
                    position.coords.latitude,
                    position.coords.longitude,
                ];

                currentUserLocation = userLocation;
                storeGeolocation(userLocation);

                if (!storedMapView) {
                    applyMapView(
                        userLocation,
                        userZoom,
                        'Map centered on your current location. Click anywhere to create a report.'
                    );
                }
            } catch (error) {
                if (!storedMapView && !storedGeolocation) {
                    applyMapView(
                        defaultCenter,
                        defaultZoom,
                        'Location access was unavailable. Showing the default map area.'
                    );
                }
            }
        })();
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearMessages();

        if (!latInput.value || !lngInput.value) {
            showMessage(errorBox, 'Pick a location on the map before submitting.');
            return;
        }

        submitButton.disabled = true;

        try {
            const payload = {
                title: titleInput.value.trim(),
                description: descriptionInput.value.trim(),
                category_id: Number(categoryInput.value),
                latitude: Number(latInput.value),
                longitude: Number(lngInput.value),
            };

            const response = await fetch('/api/reports', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const body = await response.json();

            if (!response.ok) {
                const firstError = body.errors
                    ? Object.values(body.errors).flat()[0]
                    : body.message;

                throw new Error(firstError || 'Unable to save the report.');
            }

            form.reset();
            latInput.value = '';
            lngInput.value = '';

            if (draftMarker) {
                map.removeLayer(draftMarker);
                draftMarker = null;
            }

            const createdReport = body.data;

            if (createdReport.status !== 'resolved') {
                upsertMarker(createdReport);
                if (supportsSupercluster) {
                    buildClusterIndex();
                    renderViewportFromClusterIndex();
                }
            }

            hint.textContent = 'Report submitted. The community can now verify or clear it.';
            showMessage(successBox, `Report "${createdReport.title}" was submitted.`);
        } catch (error) {
            showMessage(errorBox, error.message || 'Unable to save the report.');
        } finally {
            submitButton.disabled = false;
        }
    });

    renderLegend();
    initializeMapLocation();
}());
