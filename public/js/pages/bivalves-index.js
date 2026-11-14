    (function () {
        var features = JSON.parse(document.getElementById('bivalve-data').textContent);
        var i18n = JSON.parse(document.getElementById('bivalve-i18n').textContent);

        var map = L.map('bivalve-map', {
            center: [39.5, -8.5],
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

        function specieList(arr) {
            if (!arr || arr.length === 0) { return '<span class="text-body-secondary small">—</span>'; }
            return arr.map(function (s) {
                var cls = (s.classification && s.classification !== 'NA')
                    ? ' <span class="text-body-secondary small">(' + escapeHtml(s.classification) + ')</span>'
                    : '';
                var label = s.specie_c || s.specie_s || '—';
                return escapeHtml(label) + cls;
            }).join(', ');
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
                + '<div class="small text-body-secondary">'
                +   escapeHtml(f.code) + ' · ' + escapeHtml(f.zone_type) + ' · ' + escapeHtml(f.region_name)
                + '</div>'
                + '<div class="mt-1"><strong>' + escapeHtml(f.status_label) + '</strong></div>'
                + '<div class="small mt-1"><span class="text-success">' + escapeHtml(i18n.open) + '</span> ' + specieList(f.open) + '</div>'
                + '<div class="small"><span class="text-danger">' + escapeHtml(i18n.closed) + '</span> ' + specieList(f.close) + '</div>'
            );
            marker.addTo(map);
            bounds.push([f.lat, f.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [20, 20], maxZoom: 9 });
        }
    })();
