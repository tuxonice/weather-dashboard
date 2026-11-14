    (function () {
        var data = JSON.parse(document.getElementById('trend-data').textContent);

        // Draws an upward-pointing arrow (north / 0°) onto a small canvas, used
        // as a Chart.js point style. pointRotation then turns it to the wind
        // direction. Returns a canvas so it renders synchronously (no onload).
        function makeArrow(color) {
            var size = 20, cx = size / 2;
            var canvas = document.createElement('canvas');
            canvas.width = canvas.height = size;
            var ctx = canvas.getContext('2d');
            ctx.strokeStyle = color;
            ctx.fillStyle = color;
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.beginPath();          // shaft
            ctx.moveTo(cx, size - 4);
            ctx.lineTo(cx, 7);
            ctx.stroke();
            ctx.beginPath();          // head
            ctx.moveTo(cx, 2);
            ctx.lineTo(cx - 4, 9);
            ctx.lineTo(cx + 4, 9);
            ctx.closePath();
            ctx.fill();
            return canvas;
        }

        data.charts.forEach(function (c) {
            var el = document.getElementById('trend-' + c.id);
            if (!el) {
                return;
            }

            var isBar = c.chartType === 'bar';

            var dataset = isBar ? {
                data: c.values,
                backgroundColor: 'rgba(' + c.color + ', 0.6)',
                borderColor: 'rgba(' + c.color + ', 0.9)',
                borderWidth: 1,
                borderRadius: 3,
            } : {
                data: c.values,
                borderColor: 'rgba(' + c.color + ', 0.9)',
                backgroundColor: 'rgba(' + c.color + ', 0.12)',
                borderWidth: 2,
                fill: true,
                // Spline smoothing.
                tension: 0.4,
                pointRadius: 2,
                pointHoverRadius: 5,
            };

            var datasets = [dataset];

            // Second line on the same axis. The measured line drops its own
            // fill so the only shaded region is the gap between the two,
            // tinted by which line sits on top.
            if (c.overlay) {
                dataset.fill = false;
                dataset.label = c.label;

                var overlay = {
                    data: c.overlay.values,
                    label: c.overlay.label,
                    borderColor: 'rgba(' + c.overlay.color + ', 0.95)',
                    borderWidth: 2,
                    tension: 0.4,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    fill: {
                        target: '-1',
                        above: 'rgba(220, 53, 69, 0.18)',
                        below: 'rgba(13, 110, 253, 0.18)',
                    },
                };

                // Hours plotted from an assumed calm are drawn hollow so they
                // read as estimates rather than measurements.
                if (c.overlay.assumed) {
                    overlay.pointBackgroundColor = c.overlay.assumed.map(function (a) {
                        return a ? '#fff' : 'rgba(' + c.overlay.color + ', 0.95)';
                    });
                    overlay.pointBorderColor = 'rgba(' + c.overlay.color + ', 0.95)';
                    overlay.pointBorderWidth = c.overlay.assumed.map(function (a) { return a ? 2 : 1; });
                    overlay.pointRadius = c.overlay.assumed.map(function (a) { return a ? 4 : 2; });
                    overlay.pointHoverRadius = c.overlay.assumed.map(function (a) { return a ? 6 : 5; });
                }

                datasets.push(overlay);
            }

            // Wind speed: render each point as a direction arrow. Calm/unknown
            // hours (null bearing) keep a plain dot.
            if (c.bearings) {
                var arrow = makeArrow('rgba(' + c.color + ', 0.95)');
                dataset.pointStyle = c.bearings.map(function (b) {
                    return b === null ? 'circle' : arrow;
                });
                // Arrow points north at 0°; rotate to the downwind bearing
                // (from-bearing + 180) so it shows where the wind is heading.
                dataset.pointRotation = c.bearings.map(function (b) {
                    return b === null ? 0 : (b + 180) % 360;
                });
                dataset.pointRadius = c.bearings.map(function (b) {
                    return b === null ? 2 : 9;
                });
                dataset.pointHoverRadius = c.bearings.map(function (b) {
                    return b === null ? 4 : 11;
                });
            }

            new Chart(el, {
                type: isBar ? 'bar' : 'line',
                data: {
                    labels: data.labels,
                    datasets: datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            beginAtZero: c.zero,
                            title: { display: true, text: c.unit },
                        },
                        x: {
                            ticks: { maxRotation: 0, autoSkipPadding: 12 },
                        },
                    },
                    plugins: {
                        legend: { display: !!c.overlay, position: 'bottom', labels: { usePointStyle: true, boxHeight: 8 } },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    if (ctx.parsed.y === null || ctx.parsed.y === undefined) {
                                        return undefined;
                                    }
                                    var value = ctx.parsed.y.toFixed(c.decimals) + ' ' + c.unit;
                                    return c.overlay ? ctx.dataset.label + ': ' + value : value;
                                },
                                afterLabel: function (ctx) {
                                    var notes = [];
                                    if (c.dirCodes) {
                                        var code = c.dirCodes[ctx.dataIndex];
                                        if (code) {
                                            notes.push(data.dirLabel + ': ' + code);
                                        }
                                    }
                                    if (c.overlay && ctx.datasetIndex === 1
                                        && c.overlay.assumed && c.overlay.assumed[ctx.dataIndex]) {
                                        notes.push(data.assumedNote);
                                    }
                                    return notes.length ? notes : undefined;
                                },
                                // With two temperature lines the gap between
                                // them is the point, so state it outright.
                                footer: function (items) {
                                    if (!c.overlay || items.length < 2) {
                                        return undefined;
                                    }
                                    var measured = null, apparent = null;
                                    items.forEach(function (i) {
                                        if (i.datasetIndex === 0) { measured = i.parsed.y; }
                                        if (i.datasetIndex === 1) { apparent = i.parsed.y; }
                                    });
                                    if (measured === null || apparent === null) {
                                        return undefined;
                                    }
                                    var d = apparent - measured;
                                    var sign = d > 0 ? '+' : (d < 0 ? '\u2212' : '');
                                    return data.deltaLabel + ': ' + sign + Math.abs(d).toFixed(1) + ' ' + c.unit;
                                },
                            },
                        },
                    },
                },
            });
        });
    })();
