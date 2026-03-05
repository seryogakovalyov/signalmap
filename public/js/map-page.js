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

    const map = L.map('report-map', {
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const markersLayer = L.layerGroup().addTo(map);
    const reportMarkers = new Map();
    const reportState = new Map();
    let draftMarker = null;
    let lastRequestedBbox = null;
    let fetchReportsController = null;
    let fetchReportsTimer = null;
    let currentUserLocation = null;
    let suppressViewportPersistence = false;

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

    const createCategoryIcon = (color) => L.divIcon({
        className: '',
        html: `
            <span style="
                display:block;
                width:18px;
                height:18px;
                border-radius:999px;
                background:${color};
                border:3px solid rgba(255,255,255,0.95);
                box-shadow:0 10px 18px rgba(15,23,42,0.18);
            "></span>
        `,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        popupAnchor: [0, -9],
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
                    data-vote-action="confirm"
                    data-report-id="${report.id}"
                    ${hasVotedForAction(report.id, 'confirm') || !report.can_confirm ? 'disabled' : ''}
                    style="border:0;border-radius:999px;padding:0.55rem 0.85rem;background:#0f766e;color:#fff;font:inherit;font-weight:700;cursor:pointer;"
                >
                    Confirm
                </button>
                <button
                    type="button"
                    data-vote-action="clear"
                    data-report-id="${report.id}"
                    ${hasVotedForAction(report.id, 'clear') || report.status === 'resolved' ? 'disabled' : ''}
                    style="border:0;border-radius:999px;padding:0.55rem 0.85rem;background:#92400e;color:#fff;font:inherit;font-weight:700;cursor:pointer;"
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
        const existingMarker = reportMarkers.get(report.id);

        if (existingMarker) {
            existingMarker.setLatLng([report.latitude, report.longitude]);
            existingMarker.setIcon(createCategoryIcon(color));
            existingMarker.setPopupContent(markerPopup(report));
            return;
        }

        const marker = L.marker([report.latitude, report.longitude], {
            icon: createCategoryIcon(color),
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

    const fetchReports = async () => {
        const bounds = map.getBounds();
        const bbox = [
            bounds.getSouthWest().lat,
            bounds.getSouthWest().lng,
            bounds.getNorthEast().lat,
            bounds.getNorthEast().lng,
        ].join(',');

        if (bbox === lastRequestedBbox) {
            return;
        }

        lastRequestedBbox = bbox;

        if (fetchReportsController) {
            fetchReportsController.abort();
        }

        fetchReportsController = new AbortController();

        const response = await fetch(`/api/reports?bbox=${encodeURIComponent(bbox)}`, {
            signal: fetchReportsController.signal,
        });

        if (!response.ok) {
            throw new Error('Unable to load map data.');
        }

        const payload = await response.json();
        const nextIds = new Set();

        payload.data.forEach((report) => {
            if (report.status === 'resolved') {
                removeMarker(report.id);
                return;
            }

            nextIds.add(report.id);
            upsertMarker(report);
        });

        Array.from(reportMarkers.keys()).forEach((reportId) => {
            if (!nextIds.has(reportId)) {
                removeMarker(reportId);
            }
        });

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
            position: 'topright',
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

    map.on('click', (event) => {
        clearMessages();
        setDraftCoordinates(event.latlng);
    });

    map.on('dragend', () => {
        persistCurrentMapView();
    });

    map.on('zoomend', () => {
        persistCurrentMapView();
    });

    map.on('moveend', () => {
        scheduleFetchReports();
    });

    map.on('popupopen', (event) => {
        const popupRoot = event.popup.getElement();

        if (!popupRoot) {
            return;
        }

        popupRoot.querySelectorAll('[data-vote-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                const reportId = Number(button.dataset.reportId);
                const voteAction = button.dataset.voteAction;

                if (!reportId || !voteAction) {
                    return;
                }

                if (hasVotedForAction(reportId, voteAction)) {
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
                        }

                        if (response.status === 403 && voteAction === 'confirm') {
                            const currentReport = reportState.get(reportId);

                            if (currentReport) {
                                currentReport.can_confirm = false;
                                reportState.set(reportId, currentReport);
                                updateMarkerPopup(reportId);
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
                        map.closePopup();
                        return;
                    }

                    reportState.set(reportId, body.data);
                    updateMarkerPopup(reportId);
                } catch (error) {
                    popupRoot.querySelectorAll('[data-vote-action]').forEach((control) => {
                        control.disabled = false;
                    });

                    showMessage(errorBox, error.message || 'Unable to record your vote.');
                }
            });
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
            }

            hint.textContent = 'Report submitted. The community can now verify or clear it.';
            showMessage(successBox, `Report "${createdReport.title}" was submitted.`);
        } catch (error) {
            showMessage(errorBox, error.message || 'Unable to save the report.');
        } finally {
            submitButton.disabled = false;
        }
    });

    initializeMapLocation();
}());
