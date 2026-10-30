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

const normalizeProductSearch = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase();

const productSearchInputSelector = '[data-product-search-input]';

const closeProductSearchResults = (widget) => {
    const input = widget.querySelector(productSearchInputSelector);
    const results = widget.querySelector('[role="listbox"]');
    if (!input || !results) {
        return;
    }

    results.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    results.querySelectorAll('[data-product-search-option]').forEach((option) => {
        option.setAttribute('aria-selected', 'false');
        option.classList.remove('bg-green-50', 'text-green-900');
    });
    widget.dataset.activeOptionIndex = '-1';
};

const showProductSearchResults = (widget) => {
    const input = widget.querySelector(productSearchInputSelector);
    const results = widget.querySelector('[role="listbox"]');
    if (!input || !results) {
        return;
    }

    const query = normalizeProductSearch(input.value.trim());
    const options = Array.from(results.querySelectorAll('[data-product-search-option]'));
    let visibleCount = 0;

    options.forEach((option) => {
        const matches = normalizeProductSearch(option.dataset.searchText ?? option.textContent).includes(query);
        option.hidden = !matches;
        option.setAttribute('aria-selected', 'false');
        option.classList.remove('bg-green-50', 'text-green-900');
        if (matches) {
            visibleCount += 1;
        }
    });

    const noResults = results.querySelector('[data-product-search-no-results]');
    if (noResults) {
        noResults.hidden = visibleCount > 0;
    }
    results.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    input.removeAttribute('aria-activedescendant');
    widget.dataset.activeOptionIndex = '-1';
};

const selectProductSearchOption = (widget, option) => {
    const input = widget.querySelector(productSearchInputSelector);
    const valueInput = widget.querySelector('[data-product-search-value]');
    if (!input || !valueInput) {
        return;
    }

    input.value = option.textContent.trim();
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.setCustomValidity('');
    valueInput.value = option.dataset.productId;
    valueInput.dispatchEvent(new Event('input', { bubbles: true }));
    valueInput.dispatchEvent(new Event('change', { bubbles: true }));
    closeProductSearchResults(widget);
};

document.querySelectorAll('[data-product-search-widget]').forEach((widget) => {
    const input = widget.querySelector(productSearchInputSelector);
    const valueInput = widget.querySelector('[data-product-search-value]');
    if (input && valueInput) {
        input.setCustomValidity(input.value && valueInput.value ? '' : input.dataset.selectionError ?? '');
        input.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('focusin', (event) => {
    if (event.target.matches(productSearchInputSelector)) {
        const widget = event.target.closest('[data-product-search-widget]');
        const valueInput = widget.querySelector('[data-product-search-value]');
        event.target.setCustomValidity(event.target.value && valueInput.value ? '' : event.target.dataset.selectionError ?? '');
        showProductSearchResults(widget);
    }
});

document.addEventListener('input', (event) => {
    if (!event.target.matches(productSearchInputSelector)) {
        return;
    }

    const widget = event.target.closest('[data-product-search-widget]');
    const valueInput = widget.querySelector('[data-product-search-value]');
    valueInput.value = '';
    valueInput.dispatchEvent(new Event('input', { bubbles: true }));
    valueInput.dispatchEvent(new Event('change', { bubbles: true }));
    event.target.setCustomValidity(event.target.dataset.selectionError ?? '');
    showProductSearchResults(widget);
});

document.addEventListener('keydown', (event) => {
    if (!event.target.matches(productSearchInputSelector)) {
        return;
    }

    const widget = event.target.closest('[data-product-search-widget]');
    const results = widget.querySelector('[role="listbox"]');
    const options = Array.from(results.querySelectorAll('[data-product-search-option]')).filter((option) => !option.hidden);
    let activeOptionIndex = Number(widget.dataset.activeOptionIndex ?? -1);

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (results.hidden) {
            showProductSearchResults(widget);
        }
        if (options.length === 0) {
            return;
        }

        const direction = event.key === 'ArrowDown' ? 1 : -1;
        const currentIndex = activeOptionIndex < 0 && direction < 0 ? 0 : activeOptionIndex;
        activeOptionIndex = ((currentIndex + direction) % options.length + options.length) % options.length;
        options.forEach((option, index) => {
            const isActive = index === activeOptionIndex;
            option.setAttribute('aria-selected', String(isActive));
            option.classList.toggle('bg-green-50', isActive);
            option.classList.toggle('text-green-900', isActive);
        });
        widget.dataset.activeOptionIndex = String(activeOptionIndex);
        event.target.setAttribute('aria-activedescendant', options[activeOptionIndex].id);
        options[activeOptionIndex].scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'Enter' && !results.hidden && options.length > 0) {
        event.preventDefault();
        selectProductSearchOption(widget, options[activeOptionIndex < 0 ? 0 : activeOptionIndex]);
    } else if (event.key === 'Escape') {
        closeProductSearchResults(widget);
    }
});

document.addEventListener('focusout', (event) => {
    if (event.target.matches(productSearchInputSelector)) {
        const widget = event.target.closest('[data-product-search-widget]');
        window.setTimeout(() => {
            if (!widget.contains(document.activeElement)) {
                closeProductSearchResults(widget);
            }
        }, 0);
    }
});

document.addEventListener('pointerdown', (event) => {
    const option = event.target.closest('[data-product-search-option]');
    if (option) {
        event.preventDefault();
        return;
    }

    if (!event.target.closest('[data-product-search-widget]')) {
        document.querySelectorAll('[data-product-search-widget]').forEach(closeProductSearchResults);
    }
});

document.addEventListener('click', (event) => {
    const option = event.target.closest('[data-product-search-option]');
    if (option) {
        selectProductSearchOption(option.closest('[data-product-search-widget]'), option);
    }
});

const directReceiptItems = document.querySelector('#direct-receipt-items');
const directReceiptItemTemplate = document.querySelector('#direct-receipt-item-template');

if (directReceiptItems && directReceiptItemTemplate) {
    let nextDirectReceiptItemIndex = Math.max(0, ...Array.from(
        directReceiptItems.querySelectorAll('[data-direct-receipt-item]'),
        (row) => Number(row.querySelector('[data-direct-receipt-product]')?.name.match(/\d+/)?.[0] ?? -1) + 1,
    ));

    const updateExpirationField = (row) => {
        const productSelect = row.querySelector('[data-direct-receipt-product]');
        const expirationField = row.querySelector('[data-direct-receipt-expiration]');
        const expirationInput = expirationField?.querySelector('input[type="date"]');
        const selectedProduct = productSelect?.value
            ? row.querySelector(`[data-product-id="${CSS.escape(productSelect.value)}"]`)
            : null;
        const requiresExpiration = selectedProduct?.dataset.tracksExpiration === 'true';

        if (expirationField && expirationInput) {
            expirationField.hidden = !requiresExpiration;
            expirationInput.required = requiresExpiration;
            if (!requiresExpiration) {
                expirationInput.value = '';
            }
        }
    };

    directReceiptItems.querySelectorAll('[data-direct-receipt-item]').forEach(updateExpirationField);

    directReceiptItems.addEventListener('change', (event) => {
        if (event.target.matches('[data-direct-receipt-product]')) {
            updateExpirationField(event.target.closest('[data-direct-receipt-item]'));
        }
    });

    directReceiptItems.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-direct-receipt-item]');
        if (removeButton) {
            removeButton.closest('[data-direct-receipt-item]').remove();
        }
    });

    document.querySelector('[data-add-direct-receipt-item]')?.addEventListener('click', () => {
        const row = directReceiptItemTemplate.content.firstElementChild.cloneNode(true);
        row.innerHTML = row.innerHTML.replaceAll('__INDEX__', String(nextDirectReceiptItemIndex));
        nextDirectReceiptItemIndex += 1;
        directReceiptItems.append(row);
        row.querySelector('[data-product-search-input]')?.focus();
    });
}

const directIssueItems = document.querySelector('#direct-issue-items');
const directIssueItemTemplate = document.querySelector('#direct-issue-item-template');

if (directIssueItems && directIssueItemTemplate) {
    const issueForm = directIssueItems.closest('form');
    const availabilityElement = document.getElementById(directIssueItems.dataset.availabilitySource);
    const availableQuantities = availabilityElement ? JSON.parse(availabilityElement.textContent) : {};
    const warehouseSelect = issueForm?.querySelector('#warehouse_id');

    const formatIssueQuantity = (quantityMilli) => {
        const quantity = Math.max(0, Number(quantityMilli));
        const integer = Math.floor(quantity / 1000).toString();
        const groupedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, issueForm.dataset.thousandsSeparator);
        const fraction = String(quantity % 1000).padStart(3, '0');

        return `${groupedInteger}${issueForm.dataset.decimalSeparator}${fraction}`;
    };

    const updateIssueAvailability = (row) => {
        const productInput = row.querySelector('[data-direct-issue-product]');
        const selectedProduct = row.querySelector(`[data-product-id="${CSS.escape(productInput.value)}"]`);
        const availableLabel = row.querySelector('[data-direct-issue-available]');
        const quantityInput = row.querySelector('[data-direct-issue-quantity]');
        const allocationNote = row.querySelector('[data-direct-issue-allocation-note]');
        const warehouseId = warehouseSelect?.value;
        const availableMilli = selectedProduct && warehouseId
            ? Number(availableQuantities[warehouseId]?.[productInput.value] ?? 0)
            : 0;

        availableLabel.textContent = `${formatIssueQuantity(availableMilli)}${selectedProduct ? ` ${selectedProduct.dataset.unit}` : ''}`;
        quantityInput.max = (availableMilli / 1000).toFixed(3);
        quantityInput.disabled = availableMilli <= 0;
        quantityInput.required = availableMilli > 0;
        if (quantityInput.value && Number(quantityInput.value) * 1000 > availableMilli) {
            quantityInput.value = '';
        }
        allocationNote.textContent = selectedProduct?.dataset.allocationNote ?? allocationNote.dataset.emptyNote;
    };

    directIssueItems.querySelectorAll('[data-direct-issue-item]').forEach(updateIssueAvailability);
    directIssueItems.addEventListener('change', (event) => {
        if (event.target.matches('[data-direct-issue-product]')) {
            updateIssueAvailability(event.target.closest('[data-direct-issue-item]'));
        }
    });
    warehouseSelect?.addEventListener('change', () => {
        directIssueItems.querySelectorAll('[data-direct-issue-item]').forEach(updateIssueAvailability);
    });

    let nextDirectIssueItemIndex = Math.max(0, ...Array.from(
        directIssueItems.querySelectorAll('[data-direct-issue-item]'),
        (row) => Number(row.querySelector('[data-direct-issue-product-search]')?.id.match(/\d+/)?.[0] ?? -1) + 1,
    ));

    directIssueItems.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-direct-issue-item]');
        if (removeButton) {
            removeButton.closest('[data-direct-issue-item]').remove();
        }
    });

    document.querySelector('[data-add-direct-issue-item]')?.addEventListener('click', () => {
        const row = directIssueItemTemplate.content.firstElementChild.cloneNode(true);
        row.innerHTML = row.innerHTML.replaceAll('__INDEX__', String(nextDirectIssueItemIndex));
        nextDirectIssueItemIndex += 1;
        directIssueItems.append(row);
        initializeProductSearch(row);
        row.querySelector('[data-product-search-input]')?.focus();
    });
}

const directOrderItems = document.querySelector('[data-direct-order-items]');
const directOrderItemTemplate = document.querySelector('#direct-order-item-template');

if (directOrderItems && directOrderItemTemplate) {
    const updateOrderItemLimit = (row) => {
        const selection = row.querySelector('[data-order-item-select]');
        const quantity = row.querySelector('[data-order-item-quantity]');
        const selectedOption = selection?.tagName === 'SELECT'
            ? selection.selectedOptions[0]
            : selection?.value
                ? row.querySelector(`[data-product-id="${CSS.escape(selection.value)}"]`)
                : null;
        const maximum = selectedOption?.dataset.maxQuantity;
        if (quantity && maximum) {
            quantity.max = maximum;
            quantity.min = maximum;
            quantity.value = maximum;
        } else if (quantity && selection) {
            quantity.min = '0.001';
            quantity.max = '999999999.999';
        }
    };

    directOrderItems.querySelectorAll('[data-direct-order-item]').forEach((row) => {
        updateOrderItemLimit(row);
    });
    directOrderItems.addEventListener('change', (event) => {
        if (event.target.matches('[data-order-item-select]')) {
            updateOrderItemLimit(event.target.closest('[data-direct-order-item]'));
        }
    });

    let nextDirectOrderItemIndex = Math.max(0, ...Array.from(
        directOrderItems.querySelectorAll('[data-direct-order-item]'),
        (row) => Number(row.querySelector('[data-order-item-select]')?.id.match(/\d+/)?.[0] ?? -1) + 1,
    ));

    directOrderItems.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-direct-order-item]');
        if (removeButton) {
            const rows = directOrderItems.querySelectorAll('[data-direct-order-item]');
            if (rows.length > 1) {
                removeButton.closest('[data-direct-order-item]').remove();
            }
        }
    });

    document.querySelector('[data-add-direct-order-item]')?.addEventListener('click', () => {
        const row = directOrderItemTemplate.content.firstElementChild.cloneNode(true);
        row.innerHTML = row.innerHTML.replaceAll('__INDEX__', String(nextDirectOrderItemIndex));
        nextDirectOrderItemIndex += 1;
        directOrderItems.append(row);
        row.querySelector('[data-product-search-input], [data-order-item-select]')?.focus();
    });
}
