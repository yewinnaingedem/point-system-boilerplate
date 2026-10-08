{{-- After the sidebar and page content: re-open/close remembered sidebar groups and cards before
     the page paints. The group holding the current page always stays open. Saved by ui/ui-state.js. --}}
<script>
    (() => {
        const read = (key) => {
            try { return JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch { return {}; }
        };

        const groups = read('sidebar.groups');
        document.querySelectorAll('[data-menu-group]').forEach((item) => {
            const open = groups[item.dataset.menuGroup];
            if (open === undefined || item.querySelector(':scope > .nav-link.active')) return;
            item.classList.toggle('menu-open', open);
            item.querySelector(':scope > .nav-treeview').style.display = open ? 'block' : 'none';
        });

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
