import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const adminFormDialog = document.querySelector('#admin-form-dialog');
const adminFormContent = document.querySelector('#admin-form-dialog-content');
const adminFormTitle = document.querySelector('#admin-form-dialog-title');

if (adminFormDialog && adminFormContent && adminFormTitle) {
    const formHtmlCache = new Map();
    const formHtmlCacheTtl = 30_000;
    let activeFormRequest = 0;

    const isAdminCreateOrEditPath = (pathname) => /^\/admin\/.+\/(?:create|[^/]+\/edit)$/.test(pathname);

    const fetchAdminFormHtml = (url) => {
        const cached = formHtmlCache.get(url);
        if (cached && cached.expiresAt > Date.now()) {
            return cached.promise;
        }

        const promise = fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        }).then(async (response) => ({
            response,
            html: await response.text(),
        })).catch((error) => {
            formHtmlCache.delete(url);
            throw error;
        });

        formHtmlCache.set(url, {
            expiresAt: Date.now() + formHtmlCacheTtl,
            promise,
        });
        return promise;
    };

    const prefetchAdminForm = (link) => {
        if (!link || link.target || link.hasAttribute('download')) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || !isAdminCreateOrEditPath(url.pathname)) {
            return;
        }

        fetchAdminFormHtml(url.href).catch(() => formHtmlCache.delete(url.href));
    };

    const showAdminFormError = (message) => {
        const error = document.createElement('p');
        error.className = 'admin-form-load-error';
        error.setAttribute('role', 'alert');
        error.textContent = message;
        adminFormContent.replaceChildren(error);
    };

    const loadAdminForm = async (url, requestId) => {
        adminFormContent.textContent = 'Cargando formulario…';

        const { response, html } = await fetchAdminFormHtml(url);
        if (!adminFormDialog.open || requestId !== activeFormRequest) {
            return;
        }

        const responseDocument = new DOMParser().parseFromString(html, 'text/html');
        const form = responseDocument.querySelector('main form');

        if (!response.ok || !form) {
            formHtmlCache.delete(url);
            if (response.redirected && !isAdminCreateOrEditPath(new URL(response.url).pathname)) {
                window.location.assign(response.url);
                return;
            }

            throw new Error('No se pudo cargar el formulario. Intenta nuevamente.');
        }

        adminFormTitle.textContent = responseDocument.querySelector('.page-heading-slot h1, main h1')?.textContent.trim()
            || 'Formulario';
        form.classList.add('admin-modal-form');
        adminFormContent.replaceChildren(form);
        form.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus();
    };

    document.addEventListener('pointerover', (event) => {
        const link = event.target.closest('a[data-admin-form-modal][href]');
        if (link && !link.contains(event.relatedTarget)) {
            prefetchAdminForm(link);
        }
    });

    document.addEventListener('focusin', (event) => {
        prefetchAdminForm(event.target.closest('a[data-admin-form-modal][href]'));
    });

    const prefetchCreateForm = () => {
        prefetchAdminForm(document.querySelector('a[data-admin-form-modal][href*="/create"]'));
    };
    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(prefetchCreateForm, { timeout: 1500 });
    } else {
        window.setTimeout(prefetchCreateForm, 250);
    }

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[data-admin-form-modal][href]');
        if (!link || link.target || link.hasAttribute('download')) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || !isAdminCreateOrEditPath(url.pathname)) {
            return;
        }

        event.preventDefault();
        if (adminFormDialog.open) {
            return;
        }

        adminFormDialog.showModal();
        document.body.classList.add('admin-form-open');
        const requestId = ++activeFormRequest;

        try {
            await loadAdminForm(url.href, requestId);
        } catch (error) {
            if (adminFormDialog.open && requestId === activeFormRequest) {
                showAdminFormError(error.message);
            }
        }
    });

    adminFormDialog.addEventListener('click', (event) => {
        if (event.target.closest('[data-admin-form-close]')) {
            adminFormDialog.close();
            return;
        }

        const cancelLink = event.target.closest('.admin-modal-form a[href]');
        if (cancelLink && new URL(cancelLink.href, window.location.href).pathname === window.location.pathname) {
            event.preventDefault();
            adminFormDialog.close();
        }
    });

    adminFormDialog.addEventListener('submit', async (event) => {
        const form = event.target.closest('form.admin-modal-form');
        if (!form) {
            return;
        }

        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"], button:not([type])');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando…';
        }

        try {
            const response = await fetch(form.action, {
                method: form.method.toUpperCase(),
                body: new FormData(form),
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.redirected && !isAdminCreateOrEditPath(new URL(response.url).pathname)) {
                formHtmlCache.clear();
                adminFormDialog.close();
                window.location.reload();
                return;
            }

            const html = await response.text();
            const responseDocument = new DOMParser().parseFromString(html, 'text/html');
            if (response.status === 401 || response.status === 403) {
                throw new Error(responseDocument.querySelector('main')?.textContent.trim()
                    || 'Tu sesión no tiene permiso para completar esta acción.');
            }

            const updatedForm = responseDocument.querySelector('main form');
            if (!updatedForm || !response.ok) {
                throw new Error(responseDocument.querySelector('main')?.textContent.trim()
                    || 'No se pudo guardar el formulario. Revisa los datos e intenta nuevamente.');
            }

            adminFormTitle.textContent = responseDocument.querySelector('.page-heading-slot h1, main h1')?.textContent.trim()
                || adminFormTitle.textContent;
            updatedForm.classList.add('admin-modal-form');
            adminFormContent.replaceChildren(updatedForm);
            formHtmlCache.clear();
        } catch (error) {
            showAdminFormError(error.message);
        }
    });

    adminFormDialog.addEventListener('close', () => {
        activeFormRequest += 1;
        document.body.classList.remove('admin-form-open');
        adminFormContent.replaceChildren();
    });

    let pointerStartedOnBackdrop = false;
    adminFormDialog.addEventListener('pointerdown', (event) => {
        pointerStartedOnBackdrop = event.target === adminFormDialog;
    });
    adminFormDialog.addEventListener('pointerup', (event) => {
        if (pointerStartedOnBackdrop && event.target === adminFormDialog) {
            adminFormDialog.close();
        }
        pointerStartedOnBackdrop = false;
    });
    adminFormDialog.addEventListener('pointercancel', () => {
        pointerStartedOnBackdrop = false;
    });
    adminFormDialog.addEventListener('cancel', () => {
        document.body.classList.remove('admin-form-open');
    });

}
