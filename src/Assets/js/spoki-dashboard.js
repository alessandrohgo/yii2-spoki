/**
 * Gestisce l'iframe Spoki nella dashboard: autenticazione (via l'endpoint actionAuthToken di
 * DashboardController), cambio sezione senza ricaricare la pagina, deep link tramite l'hash
 * dell'URL. Nessuna dipendenza da jQuery/Bootstrap o altro framework — solo JS puro.
 *
 * Legge i dati dal div#spoki-embedding:
 * - data-auth-token-url: URL dell'endpoint che genera il token di autenticazione
 * - data-language: lingua per l'iframe (es. "it", "en")
 *
 * I link con l'attributo data-spoki-slug (es. dentro il menu delle sezioni) cambiano sezione
 * al click, senza ricaricare la pagina.
 */
(function () {
    'use strict';

    var iframeParent = null;
    var menuLinks = [];
    var authTokenUrl = '';
    var language = 'it';

    /** @type {{token: string, uid: string}|null} */
    var auth = null;
    /** @type {Promise<{token: string, uid: string}>|null} */
    var authPromise = null;

    function buildIframeSrc(slug, token, uid) {
        return 'https://spoki.app/' + encodeURIComponent(slug)
            + '?auth_token=' + encodeURIComponent(token)
            + '&auth_uid=' + encodeURIComponent(uid)
            + '&language=' + encodeURIComponent(language);
    }

    function ensureAuth() {
        if (auth) {
            return Promise.resolve(auth);
        }
        if (authPromise) {
            return authPromise;
        }

        authPromise = fetch(authTokenUrl, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                if (!json || !json.success || !json.token || !json.uid) {
                    throw new Error((json && json.message) || 'Errore di autenticazione Spoki');
                }
                auth = { token: json.token, uid: json.uid };
                return auth;
            })
            .catch(function (error) {
                console.error('Spoki: errore autenticazione', error);
                if (iframeParent) {
                    iframeParent.innerHTML = '<p>Errore durante il caricamento di Spoki. Riprova più tardi.</p>';
                }
                throw error;
            })
            .finally(function () {
                authPromise = null;
            });

        return authPromise;
    }

    function setActiveMenuItem(slug) {
        menuLinks.forEach(function (link) {
            link.classList.toggle('spoki-active', link.getAttribute('data-spoki-slug') === slug);
        });
    }

    function showSection(slug) {
        ensureAuth().then(function (authData) {
            var iframeEl = iframeParent.querySelector('iframe');
            if (!iframeEl) {
                iframeEl = document.createElement('iframe');
                iframeEl.setAttribute('frameborder', '0');
                iframeEl.style.width = '100%';
                iframeEl.style.minHeight = '600px';
                iframeEl.style.border = 'none';
                iframeParent.appendChild(iframeEl);
            }
            iframeEl.setAttribute('src', buildIframeSrc(slug, authData.token, authData.uid));
        });

        location.hash = slug;
        setActiveMenuItem(slug);
    }

    function getInitialSlug() {
        var fromHash = (location.hash || '').replace(/^#/, '');
        if (fromHash) {
            return fromHash;
        }

        return menuLinks.length > 0 ? menuLinks[0].getAttribute('data-spoki-slug') : 'dashboard';
    }

    function init() {
        iframeParent = document.getElementById('spoki-embedding');
        if (!iframeParent) {
            return;
        }

        menuLinks = Array.prototype.slice.call(document.querySelectorAll('[data-spoki-slug]'));
        authTokenUrl = iframeParent.getAttribute('data-auth-token-url') || '';
        language = iframeParent.getAttribute('data-language') || 'it';

        if (!authTokenUrl) {
            console.error('Spoki: data-auth-token-url mancante su #spoki-embedding');
            return;
        }

        menuLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                showSection(link.getAttribute('data-spoki-slug'));
            });
        });

        showSection(getInitialSlug());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
