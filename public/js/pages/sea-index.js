    (function () {
        const input = document.getElementById('sea-filter');
        if (!input) return;
        const regions = document.querySelectorAll('[data-region]');
        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            regions.forEach(region => {
                let visible = 0;
                region.querySelectorAll('[data-location-name]').forEach(card => {
                    const match = !q || card.dataset.locationName.includes(q);
                    card.classList.toggle('d-none', !match);
                    if (match) visible++;
                });
                region.classList.toggle('d-none', visible === 0);
            });
        });
    })();
