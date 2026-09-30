@if($map['enabled'])
    @push('styles')
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.10.0/mapbox-gl.css">
    @endpush
@endif

@push('scripts')
    @if($map['enabled'])
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.10.0/mapbox-gl.js"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const element = document.querySelector('#portal-map');
            @if($map['enabled'])
            if (element && window.mapboxgl) {
                const config = JSON.parse(element.dataset.map);
                mapboxgl.accessToken = config.token;
                const map = new mapboxgl.Map({ container: element, style: config.style, center: config.center, zoom: 15, attributionControl: false });
                map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
                map.on('load', () => {
                    new mapboxgl.Marker({ color: '#006266' }).setLngLat(config.center).addTo(map);
                    map.addSource('attendance-area', { type: 'geojson', data: { type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: config.center } } });
                    map.addLayer({ id: 'attendance-area', type: 'circle', source: 'attendance-area', paint: { 'circle-radius': { stops: [[0, 0], [20, config.radiusMeters / 0.3]] }, 'circle-color': '#006266', 'circle-opacity': 0.12, 'circle-stroke-color': '#006266', 'circle-stroke-width': 2 } });
                });
            }
            @endif
            document.querySelectorAll('[data-portal-attendance]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const feedback = form.querySelector('[data-location-feedback]');
                    if (!navigator.geolocation) { feedback.textContent = 'Browser ini tidak mendukung lokasi perangkat.'; feedback.classList.add('is-visible'); return; }
                    form.querySelector('button[type="submit"]').disabled = true;
                    navigator.geolocation.getCurrentPosition((position) => {
                        const data = new FormData(form);
                        data.append('location[latitude]', position.coords.latitude);
                        data.append('location[longitude]', position.coords.longitude);
                        fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then((response) => { window.location.assign(response.url); }).catch(() => { feedback.textContent = 'Presensi belum terkirim. Periksa koneksi lalu coba lagi.'; feedback.classList.add('is-visible'); form.querySelector('button[type="submit"]').disabled = false; });
                    }, () => { feedback.textContent = 'Izinkan akses lokasi perangkat untuk melanjutkan presensi.'; feedback.classList.add('is-visible'); form.querySelector('button[type="submit"]').disabled = false; }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
                });
            });
        });
    </script>
@endpush
