<script>
(function () {
    var html = document.documentElement;

    function stored() {
        try {
            return localStorage.getItem('adm-theme');
        } catch (e) {
            return null;
        }
    }

    function resolve(pref) {
        if (pref === 'system' || pref === null || pref === '') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        return pref === 'dark' ? 'dark' : 'light';
    }

    function apply() {
        var mode = stored() || 'system';
        var res = resolve(mode);
        html.setAttribute('data-adm-theme', res);
        html.setAttribute('data-adm-theme-pref', mode);
        document.querySelectorAll('.adm-theme-btn').forEach(function (btn) {
            var labels = { system: 'Match system', light: 'Light', dark: 'Dark' };
            btn.setAttribute('title', labels[mode] || 'Theme');
            btn.setAttribute('aria-label', 'Color theme: ' + (labels[mode] || mode));
        });
    }

    apply();

    try {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if ((stored() || 'system') === 'system') {
                apply();
            }
        });
    } catch (e) {
        /* ignore */
    }

    document.querySelectorAll('.adm-theme-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cur = stored() || 'system';
            var order = ['system', 'light', 'dark'];
            var i = order.indexOf(cur);
            if (i < 0) {
                i = 0;
            }
            var next = order[(i + 1) % order.length];
            try {
                localStorage.setItem('adm-theme', next);
            } catch (e) {
                /* ignore */
            }
            apply();
        });
    });

    var body = document.body;
    var overlay = document.querySelector('.adm-nav-overlay');
    var toggle = document.querySelector('.adm-nav-toggle');

    function closeNav() {
        body.classList.remove('adm-nav-open');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            body.classList.toggle('adm-nav-open');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', closeNav);
    }
    document.querySelectorAll('.adm-sidebar .adm-nav a').forEach(function (a) {
        a.addEventListener('click', closeNav);
    });
})();
</script>
