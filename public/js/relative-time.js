(function () {
    function updateRelativeTimes() {
        var elements = document.querySelectorAll('[data-relative-time]');
        if (!elements.length || typeof Intl === 'undefined' || !Intl.RelativeTimeFormat) {
            return;
        }

        var now = new Date();
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            var timestamp = new Date(el.getAttribute('data-timestamp'));
            var seconds = Math.floor((now - timestamp) / 1000);
            var locale = el.getAttribute('data-locale');
            var prefix = el.getAttribute('data-prefix');
            var formatter = new Intl.RelativeTimeFormat(locale, { style: 'short', numeric: 'auto' });
            var value, unit;

            if (seconds < 60) {
                value = 0;
                unit = 'second';
            } else if (seconds < 3600) {
                value = -Math.floor(seconds / 60);
                unit = 'minute';
            } else if (seconds < 86400) {
                value = -Math.floor(seconds / 3600);
                unit = 'hour';
            } else {
                value = -Math.floor(seconds / 86400);
                unit = 'day';
            }

            el.textContent = prefix + ' ' + formatter.format(value, unit);
        }
    }

    updateRelativeTimes();
    setInterval(updateRelativeTimes, 60000);
})();
