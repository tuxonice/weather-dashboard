            var seriesName = JSON.parse(document.getElementById('tide-series-name').textContent);
            am5.ready(function () {
                var dataEl = document.getElementById('tide-chart-data');
                if (!dataEl) {
                    return;
                }
                var data = JSON.parse(dataEl.textContent);

                var root = am5.Root.new('tide-chart');
                root.setThemes([am5themes_Animated.new(root)]);

                var chart = root.container.children.push(am5xy.XYChart.new(root, {
                    panX: false,
                    panY: false,
                    wheelX: 'none',
                    wheelY: 'none',
                    layout: root.verticalLayout,
                }));

                var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
                    baseInterval: { timeUnit: 'minute', count: 10 },
                    renderer: am5xy.AxisRendererX.new(root, { minGridDistance: 60 }),
                    tooltipDateFormat: 'dd MMM HH:mm',
                }));
                xAxis.get('dateFormats')['hour'] = 'HH:mm';
                xAxis.get('dateFormats')['day'] = 'dd MMM';
                xAxis.get('periodChangeDateFormats')['hour'] = 'HH:mm';
                xAxis.get('periodChangeDateFormats')['day'] = 'dd MMM';

                var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                    renderer: am5xy.AxisRendererY.new(root, {}),
                    numberFormat: "#.0'm'",
                    extraTooltipPrecision: 2,
                }));

                var series = chart.series.push(am5xy.SmoothedXLineSeries.new(root, {
                    name: seriesName,
                    xAxis: xAxis,
                    yAxis: yAxis,
                    valueXField: 't',
                    valueYField: 'h',
                    stroke: am5.color(0x0d6efd),
                    fill: am5.color(0x0d6efd),
                    tooltip: am5.Tooltip.new(root, {
                        labelText: '{valueY.formatNumber("#.00")} m',
                    }),
                }));
                series.strokes.template.setAll({ strokeWidth: 2 });
                series.fills.template.setAll({ fillOpacity: 0.15, visible: true });

                series.data.setAll(data);

                chart.set('cursor', am5xy.XYCursor.new(root, {
                    behavior: 'none',
                    xAxis: xAxis,
                }));
            });
        
