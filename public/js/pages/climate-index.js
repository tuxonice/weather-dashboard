    (function () {
        var input = document.getElementById('climate-filter');
        if (!input) { return; }

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            document.querySelectorAll('#climate-districts > .col').forEach(function (col) {
                var items = col.querySelectorAll('[data-municipality]');
                var anyVisible = false;
                items.forEach(function (item) {
                    var match = q === '' || item.getAttribute('data-municipality').indexOf(q) !== -1;
                    item.style.display = match ? '' : 'none';
                    if (match) { anyVisible = true; }
                });
                col.style.display = anyVisible ? '' : 'none';
            });
        });
    })();
