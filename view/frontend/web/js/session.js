/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * The visitor's session, fragments and form_key in one request
 * (Controller\Session\Bootstrap):
 * 1. ask for every [data-zero-fragment] and the form_key (unless cached below);
 * 2. fire zero:session-ready, before the swaps so swapped ones don't answer;
 * 3. swap each fragment in (outerHTML);
 * 4. write the form_key into every form, now and after HTMX swaps, and
 *    release forms submitted before it arrived.
 */
(function () {
    'use strict';

    var primer = document.querySelector('[data-zero-session]');
    // A page cached before the one-request change (its primer loads the
    // session itself and fires zero:session-ready; the form_key has its own
    // URL): keep doing what that page expects until the cache turns over.
    var legacy = primer ? null : document.querySelector('[data-formkey-url]');
    if (!primer && !legacy) {
        return;
    }
    var key = null;
    var ready = false;
    var held = [];

    function apply(root) {
        if (key === null || !root || !root.querySelectorAll) {
            return;
        }
        root.querySelectorAll('input[name="form_key"]').forEach(function (input) {
            input.value = key;
        });
    }

    function release() {
        if (ready) {
            return;
        }
        ready = true;
        held.splice(0).forEach(function (entry) {
            if (entry.form.isConnected) {
                entry.form.requestSubmit(entry.submitter && entry.submitter.form === entry.form ? entry.submitter : undefined);
            }
        });
    }

    // Capture phase: runs before HTMX's own submit handlers on the form.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (ready || !form.querySelector || !form.querySelector('input[name="form_key"]')) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        held.push({ form: form, submitter: event.submitter });
    }, true);

    document.body.addEventListener('htmx:afterSettle', function (event) {
        apply(event.detail && event.detail.elt ? event.detail.elt.parentNode || document : document);
    });

    function swapIn(fragments) {
        Object.keys(fragments || {}).forEach(function (name) {
            document.querySelectorAll('[data-zero-fragment="' + name + '"]').forEach(function (target) {
                if (window.htmx && typeof window.htmx.swap === 'function') {
                    window.htmx.swap(target, fragments[name], { swapStyle: 'outerHTML' });
                } else {
                    target.outerHTML = fragments[name];
                }
            });
        });
    }

    /*
     * Browser cache of the fragments and form_key, as Luma's customer-data
     * does: most page views need no request. Dropped when
     * private_content_version (renewed on every POST) or mage-cache-sessid
     * (sign in/out, new session) changes, on another language or store view,
     * after 20 minutes, or when a fragment is missing. Flash messages are
     * never cached.
     */
    var CACHE_KEY = 'zero-private-v1';
    var CACHE_TTL = 20 * 60 * 1000;

    function cookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[-.]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value) {
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }

    function version() {
        return [cookie('private_content_version') || '', document.documentElement.lang || '', cookie('store') || ''].join('|');
    }

    function readCache(names) {
        try {
            if (!cookie('mage-cache-sessid')) {
                localStorage.removeItem(CACHE_KEY);
                return null;
            }
            var cached = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
            var complete = cached && names.every(function (name) {
                return name === 'messages' || Object.prototype.hasOwnProperty.call(cached.fragments, name);
            });
            return complete && cached.formKey && cached.version === version() && Date.now() - cached.at < CACHE_TTL ? cached : null;
        } catch (e) {
            return null;
        }
    }

    function writeCache(data, referer) {
        try {
            var fragments = {};
            Object.keys(data.fragments || {}).forEach(function (name) {
                if (name !== 'messages') {
                    fragments[name] = data.fragments[name];
                }
            });
            localStorage.setItem(CACHE_KEY, JSON.stringify({
                version: version(), at: Date.now(), formKey: data.formKey, referer: referer, fragments: fragments
            }));
            setCookie('mage-cache-sessid', 'true');
        } catch (e) {
            // No storage (private mode, quota): every page asks, as before.
        }
    }

    function hasMessages() {
        var value = cookie('mage-messages');
        return !!value && value !== '[]';
    }

    function show(data) {
        if (data.formKey) {
            key = data.formKey;
            // Magento makes a form_key cookie the session's key (RegisterFormKeyFromCookie),
            // so the cached key stays valid even if the session is renewed.
            setCookie('form_key', key);
        }
        document.body.dispatchEvent(new CustomEvent('zero:session-ready', { bubbles: true }));
        swapIn(data.fragments);
        apply(document);
    }

    function ask(names, referer) {
        var params = new URLSearchParams({ fragments: names.join(',') });
        if (referer) {
            params.set('referer', referer);
        }
        var url = primer.getAttribute('data-zero-session');

        return fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + params.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'HX-Request': 'true', 'Accept': 'application/json' }
        }).then(function (response) { return response.ok ? response.json() : {}; });
    }

    function start() {
        var names = [];
        document.querySelectorAll('[data-zero-fragment]').forEach(function (element) {
            var name = element.getAttribute('data-zero-fragment');
            if (names.indexOf(name) === -1) {
                names.push(name);
            }
        });
        var links = document.querySelector('[data-zero-fragment="accountlinks"][data-referer]');
        var referer = links ? links.getAttribute('data-referer') : '';
        var cached = readCache(names);

        if (cached) {
            var fragments = {};
            names.forEach(function (name) {
                if (name !== 'messages') {
                    // "Sign In" returns to this page, not to the one the links were fetched on.
                    fragments[name] = cached.referer && referer
                        ? cached.fragments[name].split(cached.referer).join(referer)
                        : cached.fragments[name];
                }
            });
            show({ formKey: cached.formKey, fragments: fragments });
            release();
            if (names.indexOf('messages') !== -1 && hasMessages()) {
                ask(['messages']).then(function (data) { swapIn(data.fragments); }).catch(function () {});
            }
            return;
        }

        ask(names, referer)
            .then(function (data) {
                show(data);
                if (data.formKey) {
                    writeCache(data, referer);
                }
            })
            .catch(function (error) {
                if (window.console) {
                    window.console.error('Zero session:', error);
                }
                document.body.dispatchEvent(new CustomEvent('zero:session-ready', { bubbles: true }));
            })
            .finally(release);
    }

    function startLegacy() {
        document.body.addEventListener('zero:session-ready', function () {
            fetch(legacy.getAttribute('data-formkey-url'), { credentials: 'same-origin', cache: 'no-store', headers: { 'HX-Request': 'true' } })
                .then(function (response) { return response.ok ? response.text() : ''; })
                .then(function (html) {
                    var input = new DOMParser().parseFromString(html, 'text/html').querySelector('input[name="form_key"]');
                    if (input && input.value) {
                        key = input.value;
                        apply(document);
                    }
                })
                .catch(function () {})
                .finally(release);
        });
    }

    // Start once HTMX has processed the page: it signals that with htmx:load
    // on <body>, right after its own DOMContentLoaded work. A cached answer is
    // ready at once, and zero:session-ready must not fire before HTMX listens
    // for it (the sidebar and recently viewed widgets load on it). This file
    // runs before that event (deferred script), so the listener can't miss it.
    function whenHtmxReady(callback) {
        if (!window.htmx) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback);
            } else {
                callback();
            }
            return;
        }
        document.addEventListener('htmx:load', function onLoad(event) {
            if (event.target === document.body) {
                document.removeEventListener('htmx:load', onLoad);
                callback();
            }
        });
    }

    if (legacy) {
        startLegacy();
    } else {
        whenHtmxReady(start);
    }
    setTimeout(release, 8000);
})();
