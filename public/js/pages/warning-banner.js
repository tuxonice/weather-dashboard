    (function () {
        var timeline = document.querySelector('.wx-timeline'), clear = document.getElementById('wx-clear'), head = document.getElementById('wx-details-head'), hint = document.getElementById('wx-hint');
        function reset() {
            document.querySelectorAll('.wx-card.selected,.wx-seg.active').forEach(function (e) { e.classList.remove('selected', 'active'); });
            timeline.classList.remove('has-selection');
            head.hidden = true; hint.hidden = false;
        }
        document.querySelectorAll('.wx-seg').forEach(function (b) {
            b.addEventListener('click', function () {
                var wasActive = b.classList.contains('active');
                reset();
                if (wasActive) return;
                var card = document.getElementById(b.dataset.wxTarget);
                if (!card) return;
                card.classList.add('selected'); b.classList.add('active'); timeline.classList.add('has-selection'); head.hidden = false; hint.hidden = true;
                card.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
            });
        });
        clear.addEventListener('click', reset);
    })();
    
