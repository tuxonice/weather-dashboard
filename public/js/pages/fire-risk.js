    (function () {
        var features = JSON.parse(document.getElementById('fire-data').textContent);

        var map = L.map('fire-map', {
            center: [39.5, -8.0],
            zoom: 7,
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
                radius: 6,
                weight: 1,
                color: '#ffffff',
                fillColor: f.color,
                fillOpacity: 0.85,
            });
            marker.bindPopup(
                '<strong>' + escapeHtml(f.level_label) + '</strong>'
                + '<div class="small text-body-secondary">DICO ' + escapeHtml(f.dico) + '</div>'
            );
            marker.addTo(map);
            bounds.push([f.lat, f.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [20, 20] });
        }
    })();
