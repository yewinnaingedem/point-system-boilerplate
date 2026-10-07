{{-- First thing in <body>: apply the saved light/dark choice before the page paints. --}}
<script>
    (() => {
        let theme = null;
        try { theme = localStorage.getItem('theme'); } catch {}
        const dark = theme ? theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (dark) document.body.classList.add('dark-mode');
    })();
</script>
