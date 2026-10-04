(() => {
    'use strict';
    document.documentElement.classList.add('js');
    const navigation = document.getElementById('site-navigation');
    const menuButton = document.getElementById('menu-toggle');
    if (navigation && menuButton) {
        navigation.dataset.open = 'false';
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.addEventListener('click', () => {
            const open = navigation.dataset.open !== 'true';
            navigation.dataset.open = String(open);
            menuButton.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && navigation.dataset.open === 'true') {
                navigation.dataset.open = 'false';
                menuButton.setAttribute('aria-expanded', 'false');
                menuButton.focus();
            }
        });
    }
    const themeButton = document.getElementById('theme-toggle');
    const root = document.documentElement;
    let selected;
    try { selected = localStorage.getItem('shop-visual-profile'); } catch (_) { /* Storage is optional. */ }
    if (selected === 'light' || selected === 'dark') { root.dataset.profile = selected; }
    else if (root.dataset.profile === 'auto') { root.dataset.profile = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    if (themeButton) {
        const update = () => {
            const dark = root.dataset.profile === 'dark';
            themeButton.setAttribute('aria-pressed', String(dark));
            themeButton.textContent = dark ? 'Ljust' : 'Mörkt';
            themeButton.setAttribute('aria-label', dark ? 'Byt till ljus profil' : 'Byt till mörk profil');
        };
        themeButton.hidden = false;
        update();
        themeButton.addEventListener('click', () => {
            root.dataset.profile = root.dataset.profile === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('shop-visual-profile', root.dataset.profile); } catch (_) { /* Theme works without storage. */ }
            update();
        });
    }
})();
