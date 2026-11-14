    (function () {
        var features = JSON.parse(document.getElementById('seismic-data').textContent);
        var i18n = JSON.parse(document.getElementById('seismic-i18n').textContent);

        var mapDefaults = JSON.parse(document.getElementById('seismic-map-defaults').textContent);
        var defaultCenter = mapDefaults.center;
        var defaultZoom = mapDefaults.zoom;

        var map = L.map('seismic-map', {
            center: defaultCenter,
            zoom: defaultZoom,
            scrollWheelZoom: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c];
            });
        }

        var bounds = [];
        features.forEach(function (f) {
            var marker = L.circleMarker([f.lat, f.lng], {
                radius: f.radius,
                weight: 1,
                color: '#ffffff',
                fillColor: f.color,
                fillOpacity: 0.85,
            });
            var magText = (f.magnitude == null)
                ? i18n.no_magnitude
                : ('M ' + f.magnitude.toFixed(1) + (f.mag_type ? ' (' + escapeHtml(f.mag_type) + ')' : ''));
            var levelLabel = (i18n.labels && i18n.labels[f.level_label]) ? i18n.labels[f.level_label] : f.level_label;
            marker.bindPopup(
                '<strong>' + escapeHtml(levelLabel) + '</strong>'
                + '<div>' + magText + '</div>'
                + '<div class="small text-body-secondary">' + escapeHtml(f.region) + '</div>'
                + '<div class="small text-body-secondary">' + escapeHtml(f.time.replace('T', ' ')) + ' UTC · ' + f.depth + ' km</div>'
            );
            marker.addTo(map);
            bounds.push([f.lat, f.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [20, 20], maxZoom: 8 });
        }
    })();
