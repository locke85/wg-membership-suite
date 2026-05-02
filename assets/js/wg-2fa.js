(function () {
    'use strict';

    const openModal = (modalId) => {
        if (!modalId) {
            return;
        }

        const tryGenerateBlocksApi = () => {
            if (window.GenerateBlocksPro && window.GenerateBlocksPro.modal && typeof window.GenerateBlocksPro.modal.open === 'function') {
                window.GenerateBlocksPro.modal.open(modalId);
                return true;
            }

            if (window.GenerateBlocks && window.GenerateBlocks.modal && typeof window.GenerateBlocks.modal.open === 'function') {
                window.GenerateBlocks.modal.open(modalId);
                return true;
            }

            return false;
        };

        if (tryGenerateBlocksApi()) {
            return;
        }

        const triggerSelector = `[data-gb-modal-trigger="${modalId}"]`;
        const trigger = document.querySelector(triggerSelector);
        if (trigger) {
            trigger.click();
            return;
        }

        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('gb-modal--hidden');
            modal.setAttribute('aria-hidden', 'false');
        }
    };

    const closeModal = (modalId) => {
        if (!modalId) {
            return;
        }

        if (window.GenerateBlocksPro && window.GenerateBlocksPro.modal && typeof window.GenerateBlocksPro.modal.close === 'function') {
            window.GenerateBlocksPro.modal.close(modalId);
            return;
        }

        if (window.GenerateBlocks && window.GenerateBlocks.modal && typeof window.GenerateBlocks.modal.close === 'function') {
            window.GenerateBlocks.modal.close(modalId);
            return;
        }

        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('gb-modal--hidden');
            modal.setAttribute('aria-hidden', 'true');
        }
    };

    const handleResponse = (response) => {
        if (!response) {
            throw new Error('Unbekannte Serverantwort.');
        }

        if (typeof response.json === 'function') {
            return response.json();
        }

        return response;
    };

    document.addEventListener('DOMContentLoaded', () => {
        const config = window.wgTwoFactor || null;
        if (!config) {
            return;
        }

        const modalId = config.modalId || 'wg-2fa-modal';
        const form = document.querySelector('[data-wg-2fa-form]');
        if (!form) {
            return;
        }

        const codeInput = form.querySelector('input[name="wg_2fa_code"]');
        const messageBox = form.querySelector('[data-wg-2fa-message]');
        const submitButton = form.querySelector('[data-wg-2fa-submit]');
        const resendButton = form.querySelector('[data-wg-2fa-resend]');

        const setMessage = (text, type) => {
            if (!messageBox) {
                return;
            }

            messageBox.textContent = text || '';
            messageBox.setAttribute('data-state', type || '');
            messageBox.hidden = !text;
        };

        const lockForm = (locked) => {
            const isLocked = Boolean(locked);
            if (submitButton) {
                submitButton.disabled = isLocked;
            }
            if (resendButton) {
                resendButton.disabled = isLocked;
            }
            form.classList.toggle('is-loading', isLocked);
        };

        const focusField = () => {
            if (codeInput) {
                codeInput.focus({ preventScroll: true });
                codeInput.select();
            }
        };

        const sendRequest = (url, payload) => (
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce,
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload || {}),
            })
        );

        form.addEventListener('submit', (event) => {
            event.preventDefault();

            if (!config.restUrl) {
                console.error('2FA REST-Endpunkt nicht gesetzt.');
                return;
            }

            const rawValue = codeInput ? codeInput.value : '';
            const sanitizedValue = (rawValue || '').replace(/[^0-9]/g, '').slice(0, 6);

            if (!sanitizedValue || sanitizedValue.length !== 6) {
                setMessage(config.messages.invalid || 'Bitte geben Sie den vollständigen Code ein.', 'error');
                focusField();
                return;
            }

            lockForm(true);
            setMessage('', '');

            sendRequest(config.restUrl, { code: sanitizedValue })
                .then(handleResponse)
                .then((result) => {
                    lockForm(false);

                    if (!result) {
                        setMessage(config.messages.genericError || 'Es ist ein Fehler aufgetreten.', 'error');
                        return;
                    }

                    if (result.success && result.data) {
                        setMessage(result.data.message || config.messages.success || 'Erfolgreich verifiziert.', 'success');
                        setTimeout(() => {
                            closeModal(modalId);
                            if (result.data.redirect) {
                                window.location.href = result.data.redirect;
                            } else if (config.reloadOnSuccess) {
                                window.location.reload();
                            }
                        }, 500);
                        return;
                    }

                    const errorMsg = (result.data && result.data.message) || config.messages.genericError || 'Verifizierung fehlgeschlagen.';
                    setMessage(errorMsg, 'error');
                    focusField();
                })
                .catch((error) => {
                    console.error(error);
                    lockForm(false);
                    setMessage(config.messages.networkError || 'Server nicht erreichbar. Bitte erneut versuchen.', 'error');
                    focusField();
                });
        });

        if (resendButton && config.resendUrl) {
            resendButton.addEventListener('click', (event) => {
                event.preventDefault();

                lockForm(true);
                setMessage('', '');

                sendRequest(config.resendUrl, {})
                    .then(handleResponse)
                    .then((result) => {
                        lockForm(false);

                        if (result && result.success && result.data) {
                            setMessage(result.data.message || config.messages.resendSuccess || 'Neuer Code versendet.', 'success');
                            return;
                        }

                        const errorMsg = (result && result.data && result.data.message) || config.messages.resendError || 'Code konnte nicht erneut gesendet werden.';
                        setMessage(errorMsg, 'error');
                    })
                    .catch((error) => {
                        console.error(error);
                        lockForm(false);
                        setMessage(config.messages.networkError || 'Server nicht erreichbar. Bitte erneut versuchen.', 'error');
                    })
                    .finally(() => {
                        focusField();
                    });
            });
        }

        if (config.shouldOpen) {
            setTimeout(() => {
                openModal(modalId);
                focusField();
            }, Math.max(Number(config.openDelay) || 0, 10));
        }
    });
})();
