import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Leaflet's default marker icon references its image files by relative URL,
// which breaks once Vite bundles/hashes assets - point it at the same
// images through Vite's own asset pipeline instead of leaving Leaflet's
// broken-by-default relative paths in place.
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

document.addEventListener('livewire:init', () => {
    const el = document.getElementById('office-location-map');

    if (el === null || el.dataset.mapInitialized) {
        return;
    }

    el.dataset.mapInitialized = '1';

    const wireEl = el.closest('[wire\\:id]');
    const wireId = wireEl?.getAttribute('wire:id');

    const initial = window.officeLocationInitial ?? { latitude: null, longitude: null, radiusMeters: 100 };
    const hasInitialPosition = initial.latitude !== null && initial.longitude !== null;

    // No pin placed yet defaults to a wide, neutral world view rather than
    // guessing a location - there's no reasonable default office anywhere
    // on Earth, unlike the radius, which has a harmless generic default.
    const map = L.map(el).setView(
        hasInitialPosition ? [initial.latitude, initial.longitude] : [20, 0],
        hasInitialPosition ? 17 : 2,
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    let marker = null;
    let circle = null;

    function reportPosition(lat, lng) {
        if (!wireId) {
            return;
        }

        Livewire.find(wireId).call('setCoordinates', lat, lng);
    }

    // Creates the marker/circle on first placement (nothing to drag until a
    // position exists), or just repositions them on every call after that.
    function placePin(lat, lng, radiusMeters) {
        const position = [lat, lng];

        if (marker === null) {
            marker = L.marker(position, { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const draggedTo = marker.getLatLng();
                circle?.setLatLng(draggedTo);
                reportPosition(draggedTo.lat, draggedTo.lng);
            });
        } else {
            marker.setLatLng(position);
        }

        if (circle === null) {
            circle = L.circle(position, {
                radius: radiusMeters,
                color: '#0d9488',
                fillColor: '#0d9488',
                fillOpacity: 0.15,
            }).addTo(map);
        } else {
            circle.setLatLng(position);
            circle.setRadius(radiusMeters);
        }
    }

    if (hasInitialPosition) {
        placePin(initial.latitude, initial.longitude, initial.radiusMeters);
    }

    // Clicking anywhere on the map places (or moves) the pin - matches how
    // most map location-pickers behave, and is the only way to place a pin
    // that doesn't exist yet.
    map.on('click', (event) => {
        placePin(event.latlng.lat, event.latlng.lng, circle?.getRadius() ?? initial.radiusMeters);
        reportPosition(event.latlng.lat, event.latlng.lng);
    });

    // Keeps the map in sync when the coordinates change some other way -
    // typed directly into the latitude/longitude fields, or the "Use my
    // current location" button - rather than by dragging the pin itself.
    Livewire.on('office-location-changed', (payload) => {
        if (payload.latitude === null || payload.longitude === null) {
            return;
        }

        placePin(payload.latitude, payload.longitude, payload.radiusMeters);
        map.panTo([payload.latitude, payload.longitude]);
    });
});
