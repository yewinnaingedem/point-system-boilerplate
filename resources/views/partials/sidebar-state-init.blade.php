{{-- Right after dark-mode-init: re-apply a collapsed sidebar before the page paints (desktop only;
     below 992 px AdminLTE shows the sidebar as an overlay and manages it itself). Saved by ui/ui-state.js. --}}
<script>
    (() => {
        let state = null;
        try { state = localStorage.getItem('sidebar'); } catch {}
        if (state === 'collapsed' && window.innerWidth >= 992) document.body.classList.add('sidebar-collapse');
    })();
</script>
