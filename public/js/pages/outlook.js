    (function () {
        var features = JSON.parse(document.getElementById('outlook-data').textContent);
        var i18n = JSON.parse(document.getElementById('outlook-i18n').textContent);

        var map = L.map('outlook-map', {
            center: [39.5, -8.0],
            zoom: 6,
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
                radius: 7,
                weight: 1,
                color: '#ffffff',
                fillColor: f.color,
                fillOpacity: 0.9,
            });
            marker.bindPopup(
                '<strong>' + escapeHtml(f.name) + '</strong>'
                + '<div><i class="bi ' + escapeHtml(f.icon) + ' me-1" aria-hidden="true"></i>' + escapeHtml(f.weather) + '</div>'
                + '<div class="small text-body-secondary">'
                +   'Min ' + Math.round(f.t_min) + '° · Max ' + Math.round(f.t_max) + '° · '
                +   Math.round(f.rain_prob) + '% ' + escapeHtml(i18n.rain.toLowerCase())
                + '</div>'
                + '<div class="small text-body-secondary">' + escapeHtml(f.wind) + '</div>'
            );
            marker.addTo(map);
            bounds.push([f.lat, f.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [20, 20], maxZoom: 8 });
        }
    })();
