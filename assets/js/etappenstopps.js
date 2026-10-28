(function () {
    'use strict';

    const toggleSelector = '.wg-etappenstopp-entry-toggle';

    function openSpickzettelWindow(url) {
        const width = Math.round(window.screen.width * 0.8);
        const height = Math.round(window.screen.height * 0.8);
        const left = Math.round((window.screen.width - width) / 2);
        const top = Math.round((window.screen.height - height) / 2);

        return window.open(
            url,
            'spickzettelWindow',
            'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
        );
    }

    function openSpickzettel(trigger) {
        const config = window.wgMembershipEtappenstopps || {};
        if (!config.spickzettelUrl || !config.restNonce || trigger.dataset.wgSpickzettelLoading === '1') {
            return;
        }

        trigger.dataset.wgSpickzettelLoading = '1';
        fetch(config.spickzettelUrl, {
            credentials: 'same-origin',
            headers: {'X-WP-Nonce': config.restNonce}
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Nicht autorisiert');
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.url) {
                    throw new Error('Kein Spickzettel-Link gefunden.');
                }

                document.dispatchEvent(new CustomEvent('open-spickzettel-overlay'));
                const popup = openSpickzettelWindow(data.url.replace(/\/preview.*$/, '/edit'));
                if (!popup) {
                    throw new Error('Bitte Pop-ups für diese Seite erlauben.');
                }

                window.setTimeout(function () {
                    document.dispatchEvent(new CustomEvent('generateblocks/closeOverlay'));
                }, 300);
            })
            .catch(function (error) {
                window.alert(error.message || String(error));
            })
            .finally(function () {
                delete trigger.dataset.wgSpickzettelLoading;
            });
    }

    function targetFor(toggle) {
        const id = toggle.dataset.target;
        return id ? document.getElementById(id) : null;
    }

    function targetIsOpen(toggle) {
        if (toggle.classList.contains('open-spickzettel')) {
            return toggle.classList.contains('is-open');
        }
        const target = targetFor(toggle);
        return !!target && (target.classList.contains('visible') || target.style.display === 'block');
    }

    function setToggleState(toggle, isOpen) {
        toggle.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function prepareToggle(toggle) {
        const row = toggle.parentElement;
        if (row) {
            row.classList.add('wg-etappenstopp-entry-row');
            if (row.lastElementChild && row.lastElementChild !== toggle) {
                row.lastElementChild.classList.add('wg-etappenstopp-entry-time');
            }
        }
        const targetId = toggle.dataset.target;
        if (targetId) {
            toggle.setAttribute('aria-controls', targetId);
        }
        setToggleState(toggle, targetIsOpen(toggle));
    }

    function syncToggleStates() {
        document.querySelectorAll(toggleSelector).forEach(function (toggle) {
            if (!toggle.classList.contains('open-spickzettel')) {
                setToggleState(toggle, targetIsOpen(toggle));
            }
        });
    }

    function initialize() {
        document.querySelectorAll(toggleSelector).forEach(prepareToggle);

        document.addEventListener('click', function (event) {
            const spickzettel = event.target.closest(toggleSelector + '.open-spickzettel');
            if (spickzettel && window.wgMembershipEtappenstopps) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openSpickzettel(spickzettel);
                return;
            }

            if (!event.target.closest(toggleSelector)) {
                return;
            }
            window.requestAnimationFrame(syncToggleStates);
            window.setTimeout(syncToggleStates, 100);
            window.setTimeout(syncToggleStates, 500);
        }, true);

        document.addEventListener('open-spickzettel-overlay', function () {
            document.querySelectorAll(toggleSelector + '.open-spickzettel').forEach(function (toggle) {
                setToggleState(toggle, true);
            });
        });

        document.addEventListener('generateblocks/closeOverlay', function () {
            document.querySelectorAll(toggleSelector + '.open-spickzettel').forEach(function (toggle) {
                setToggleState(toggle, false);
            });
        });

        const observer = new MutationObserver(syncToggleStates);
        document.querySelectorAll('.dynamic-box').forEach(function (target) {
            observer.observe(target, {attributes: true, attributeFilter: ['class', 'style', 'hidden']});
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
