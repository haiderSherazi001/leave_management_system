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

    const initial = window.officeLocationInitial ?? { latitude: 0, longitude: 0, radiusMeters: 100 };
    const startPosition = [initial.latitude, initial.longitude];

    const map = L.map(el).setView(startPosition, 17);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const marker = L.marker(startPosition, { draggable: true }).addTo(map);
    const circle = L.circle(startPosition, {
        radius: initial.radiusMeters,
        color: '#0d9488',
        fillColor: '#0d9488',
        fillOpacity: 0.15,
    }).addTo(map);

    function reportPosition(lat, lng) {
        if (!wireId) {
            return;
        }

        Livewire.find(wireId).call('setCoordinates', lat, lng);
    }

    marker.on('dragend', () => {
        const position = marker.getLatLng();
        circle.setLatLng(position);
        reportPosition(position.lat, position.lng);
    });

    // Clicking anywhere on the map also moves the pin, not just dragging the
    // existing marker - matches how most map location-pickers behave.
    map.on('click', (event) => {
        marker.setLatLng(event.latlng);
        circle.setLatLng(event.latlng);
        reportPosition(event.latlng.lat, event.latlng.lng);
    });

    // Keeps the map in sync when the coordinates change some other way -
    // typed directly into the latitude/longitude fields, or the "Use my
    // current location" button - rather than by dragging the pin itself.
    Livewire.on('office-location-changed', (payload) => {
        const position = [payload.latitude, payload.longitude];
        marker.setLatLng(position);
        circle.setLatLng(position);
        circle.setRadius(payload.radiusMeters);
        map.panTo(position);
    });
});
