    (function () {
        const input = document.getElementById('station-filter');
        if (!input) return;
        const rows = document.querySelectorAll('[data-station-name]');
        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            rows.forEach(row => {
                const match = !q || row.dataset.stationName.includes(q);
                row.classList.toggle('d-none', !match);
            });
        });
    })();
