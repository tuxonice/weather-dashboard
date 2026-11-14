    (function () {
        var data = JSON.parse(document.getElementById('wind-rose-data').textContent);

        // Top of the colour scale, in km/h. Average hourly speeds rarely
        // exceed this, and a fixed domain keeps colours comparable between
        // stations. Speed maps to a hue ramp: blue (calm) → red (strong).
        var SPEED_MAX = 40;
        function speedColor(speed, alpha) {
            if (speed === null || speed === undefined) {
                return 'rgba(173, 181, 189, ' + alpha + ')'; // grey: no speed data
            }
            var t = Math.max(0, Math.min(1, speed / SPEED_MAX));
            var hue = 240 - 240 * t; // 240° blue → 0° red
            return 'hsla(' + hue + ', 70%, 50%, ' + alpha + ')';
        }

        // Paints the compass codes (N, NE, …) around the rim at their true
        // bearings. Labels are ordered N→NW, i.e. 0°, 45°, … 315° clockwise
        // from the top — matching the chart's -22.5° start angle.
        var compassLabels = {
            id: 'compassLabels',
            afterDraw: function (chart) {
                var scale = chart.scales.r;
                if (!scale) {
                    return;
                }
                var ctx = chart.ctx;
                var radius = scale.drawingArea + 16;
                ctx.save();
                ctx.font = '600 13px system-ui, sans-serif';
                ctx.fillStyle = '#6c757d';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                chart.data.labels.forEach(function (label, i) {
                    var bearing = (Math.PI / 180) * (i * 45);
                    var x = scale.xCenter + radius * Math.sin(bearing);
                    var y = scale.yCenter - radius * Math.cos(bearing);
                    ctx.fillText(label, x, y);
                });
                ctx.restore();
            },
        };

        new Chart(document.getElementById('wind-rose'), {
            type: 'polarArea',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.counts,
                    // Radius = hours from each direction; fill colour = the
                    // average wind speed recorded from that direction.
                    backgroundColor: data.avgSpeeds.map(function (s) { return speedColor(s, 0.6); }),
                    borderColor: data.avgSpeeds.map(function (s) { return speedColor(s, 0.95); }),
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                // Leave room around the rim for the compass labels.
                layout: { padding: 24 },
                scales: {
                    r: {
                        beginAtZero: true,
                        // Chart.js v4 polar-area rotation lives on the radial
                        // scale (the chart-level `startAngle` option is ignored
                        // for polar charts). The default of 0 already centres
                        // the first sector (N) at the top, with each 45° sector
                        // centred on its compass point — exactly what we want.
                        startAngle: -22.5,
                        ticks: { precision: 0, backdropColor: 'transparent' },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.label + ': ' + ctx.raw + ' ' + data.i18n.hours;
                            },
                            afterLabel: function (ctx) {
                                var speed = data.avgSpeeds[ctx.dataIndex];
                                if (speed === null || speed === undefined) {
                                    return data.i18n.avgSpeed + ': ' + data.i18n.noSpeed;
                                }
                                return data.i18n.avgSpeed + ': ' + speed.toFixed(1) + ' km/h';
                            },
                        },
                    },
                },
            },
            plugins: [compassLabels],
        });
    })();
