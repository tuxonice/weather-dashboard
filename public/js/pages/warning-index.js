(function () {
    var LEVEL_COLORS = {
        green:  '#adb5bd',
        yellow: '#ffc107',
        orange: '#fd7e14',
        red:    '#dc3545'
    };

    var levels = JSON.parse(document.getElementById('warning-levels').textContent);

    var SVG_FILES = {
        mainland: 'warning-map-mainland',
        madeira:  'warning-map-madeira',
        azores:   'warning-map-azores',
    };

    Object.keys(SVG_FILES).forEach(function (region) {
        var containerId = SVG_FILES[region];
        fetch('/geo/warning-areas-' + region + '.svg')
            .then(function (r) { return r.text(); })
            .then(function (svgText) {
                var container = document.getElementById(containerId);
                container.innerHTML = svgText;
                applyInlineStyles(container);
                var svg = container.querySelector('svg');
                if (svg) {
                    svg.style.width = '100%';
                    svg.style.height = '100%';
                    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
                }
                container.querySelectorAll('path[data-area]').forEach(function (path) {
                    var area  = path.getAttribute('data-area');
                    var name  = path.getAttribute('data-name');
                    var level = levels[area] || 'green';
                    var color = LEVEL_COLORS[level] || LEVEL_COLORS.green;
                    path.setAttribute('fill', color);
                    path.style.cursor = 'pointer';
                    path.setAttribute('title', name + ' (' + area + ')');
                    path.addEventListener('mouseenter', function () { path.setAttribute('fill', '#0d6efd'); });
                    path.addEventListener('mouseleave', function () { path.setAttribute('fill', color); });
                    path.addEventListener('click', function () {
                        var card = document.getElementById('area-card-' + area);
                        if (card) {
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            card.classList.add('ring-highlight');
                            setTimeout(function () { card.classList.remove('ring-highlight'); }, 2000);
                        }
                    });
                });
            });
    });
})();
