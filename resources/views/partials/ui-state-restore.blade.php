{{-- After the page content: re-apply remembered collapsed cards before the page paints. Saved by
     ui/ui-state.js. (Sidebar groups are not remembered: only the group of the current page opens.) --}}
<script>
    (() => {
        const read = (key) => {
            try { return JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch { return {}; }
        };

        const cards = read('cards');
        document.querySelectorAll('[data-remember-card]').forEach((card) => {
            const collapsed = cards[card.dataset.rememberCard];
            if (collapsed === undefined) return;
            card.classList.toggle('collapsed-card', collapsed);
            card.querySelectorAll(':scope > .card-header [data-card-widget="collapse"] i').forEach((icon) => {
                icon.classList.toggle('fa-plus', collapsed);
                icon.classList.toggle('fa-minus', !collapsed);
            });
        });
    })();
</script>
