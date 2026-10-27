(function () {
    'use strict';
    var key = 'g2-dashboard-theme';
    var current = 'light';
    try {
        var saved = window.localStorage.getItem(key);
        if (saved === 'dark' || saved === 'light') current = saved;
    } catch (ignored) { /* Storage may be blocked; switching still works. */ }
    document.documentElement.setAttribute('data-theme', current);
    function ready() {
        var toggle = document.getElementById('theme-toggle');
        if (!toggle) return;
        function reflect() {
            document.documentElement.setAttribute('data-theme', current);
            toggle.setAttribute('aria-pressed', current === 'dark' ? 'true' : 'false');
        }
        reflect();
        toggle.hidden = false;
        toggle.addEventListener('click', function () {
            current = current === 'dark' ? 'light' : 'dark';
            reflect();
            try { window.localStorage.setItem(key, current); } catch (ignored) {}
        });
        window.addEventListener('storage', function (event) {
            if (event.key === key) {
                current = event.newValue === 'dark' ? 'dark' : 'light';
                reflect();
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
    else ready();
}());
