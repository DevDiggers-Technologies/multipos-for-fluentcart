"use strict";

import './admin.less';

const ADMIN_OBJECT = window.ddfcposAdminObj || {};
const I18N = ADMIN_OBJECT.i18n || {};
const CONFIG = ADMIN_OBJECT.ddfcpos_configuration || {};
const INVALID_HTML_PATTERN = /<\s?[^>]*\/?\s?>/i;
const FORM_SELECTOR = 'form#ddfcpos-payments-container, form#ddfcpos-tables-container';
const PRODUCT_ACTION_SELECTOR = '.ddfcpos-product-action';
const BARCODE_INPUT_SELECTOR = '.ddfcpos-barcode, .ddfcpos-barcode-quantity';

const onReady = callback => {
    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', callback);
        return;
    }

    callback();
};

const removeElements = elements => {
    [...elements].forEach(element => element.remove());
};

const getTemplate = templateId => (
    window.wp && wp.template && templateId ? wp.template(templateId) : null
);

const showValidationNotice = form => {
    const invalidFormFields = getTemplate('ddfcpos_form_data_error');

    if (invalidFormFields) {
        form.insertAdjacentHTML('beforeBegin', invalidFormFields());
    }
};

const shouldValidateInput = input => (
    input
    && !input.disabled
    && !['hidden', 'submit', 'button'].includes(input.type)
    && !input.classList.contains('ddfcpos-hide')
);

const setInputInvalid = input => {
    input.style.borderColor = 'red';
};

const resetInputState = input => {
    input.style.borderColor = '';
};

const isInvalidInputValue = input => {
    const inputValue = String(input.value || '').trim();

    return !inputValue || '-1' === inputValue || INVALID_HTML_PATTERN.test(inputValue);
};

const validateForm = (form, event) => {
    let hasError = false;

    [...form.elements].forEach(input => {
        resetInputState(input);

        if (!shouldValidateInput(input) || !isInvalidInputValue(input)) {
            return;
        }

        event.preventDefault();
        setInputInvalid(input);
        hasError = true;
    });

    if (hasError) {
        showValidationNotice(form);
    }
};

const bindConfigurationForms = () => {
    const forms = document.querySelectorAll(FORM_SELECTOR);

    if (!forms.length) {
        return;
    }

    forms.forEach(form => {
        form.addEventListener('submit', event => {
            removeElements(document.querySelectorAll('.notice'));
            validateForm(form, event);
        });
    });
};

const getRepeaterTarget = button => button.closest('tr');

const addRepeaterRow = button => {
    const form = button.closest('form');
    const templateId = button.getAttribute('data-template');
    const template = getTemplate(templateId);
    const maxIndexElement = form ? form.querySelector('#ddfcpos-max-index') : null;
    const target = getRepeaterTarget(button);

    if (!template || !maxIndexElement || !target) {
        return;
    }

    const rowIndex = Number.parseInt(maxIndexElement.value, 10) || 0;
    const nextIndex = rowIndex + 1;

    maxIndexElement.value = nextIndex;
    target.insertAdjacentHTML('beforeBegin', template({ key: nextIndex }));
};

const removeRepeaterRow = button => {
    const target = getRepeaterTarget(button);

    if (target) {
        target.remove();
    }
};

const bindRepeaterRows = () => {
    document.addEventListener('click', event => {
        const addRowButton = event.target.closest('.ddfcpos-add-row');
        const removeRowButton = event.target.closest('.ddfcpos-remove-row');

        if (addRowButton) {
            event.preventDefault();
            addRepeaterRow(addRowButton);
            return;
        }

        if (removeRowButton) {
            event.preventDefault();
            removeRepeaterRow(removeRowButton);
        }
    });
};

const getBarcodePrintStyle = () => `<style type="text/css">
    @page {
        size: ${CONFIG.barcode_printer_width} ${CONFIG.barcode_printer_height};
        margin: ${CONFIG.barcode_printer_margin};
        text-align: center;
    }
</style>`;

const getBarcodePrintContent = (row, quantity) => {
    const printContentElement = row.querySelector('.ddfcpos-barcode-print-content');
    const printContent = printContentElement ? printContentElement.innerHTML : '';

    return Array.from({ length: quantity }, () => printContent).join('');
};

const isMobileDevice = () => /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

const PRINT_IMAGE_TIMEOUT = 5000;

// The barcode is fetched from the REST route, so on a slow host it is still in
// flight when a fixed timer fires and the label prints blank. Wait for the images
// themselves, with a ceiling so a failed request never blocks the dialog.
const whenPrintImagesReady = printDocument => {
    const pending = Array.from(printDocument.images || [])
        .filter(image => !image.complete)
        .map(image => new Promise(resolve => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        }));

    if (!pending.length) {
        return Promise.resolve();
    }

    return Promise.race([
        Promise.all(pending),
        new Promise(resolve => setTimeout(resolve, PRINT_IMAGE_TIMEOUT)),
    ]);
};

const printInPopup = html => {
    const printWindow = window.open('', 'PRINT', 'height=400,width=600');

    if (!printWindow) {
        return;
    }

    printWindow.document.open();
    printWindow.document.clear();
    printWindow.document.writeln(html);
    printWindow.document.close();

    whenPrintImagesReady(printWindow.document).then(() => {
        printWindow.focus();
        printWindow.print();
    });
};

const printInFrame = html => {
    const frame = document.createElement('iframe');
    frame.name = 'ddfcpos-barcode-print-frame';
    document.body.appendChild(frame);

    const frameDocument = frame.contentWindow ? frame.contentWindow.document : frame.contentDocument;

    frameDocument.open();
    frameDocument.write(html);
    frameDocument.close();

    whenPrintImagesReady(frameDocument).then(() => {
        frame.contentWindow.focus();
        frame.contentWindow.print();

        // Removing the frame in the same tick cancels the dialog in some browsers.
        setTimeout(() => document.body.removeChild(frame), 1000);
    });
};

const handleBarcodePrint = row => {
    const quantityElement = row.querySelector('.ddfcpos-barcode-quantity');
    const quantity = quantityElement ? Number.parseInt(quantityElement.value, 10) : 0;

    if (!quantity) {
        alert(I18N.barcodeQuantityError);
        return;
    }

    quantityElement.value = '';

    const html = `<html><head><title>Barcode</title>${getBarcodePrintStyle()}</head><body>${getBarcodePrintContent(row, quantity)}</body></html>`;

    if (isMobileDevice()) {
        printInPopup(html);
        return;
    }

    printInFrame(html);
};

// Bulk "Print labels": every ticked row, each as many times as its quantity box says (one when empty).
const bindBulkLabelPrint = () => {
    document.addEventListener('submit', event => {
        const form = event.target;

        if (!form.matches || !form.matches('.ddfcpos-barcodes-form')) {
            return;
        }

        // Only the bulk Apply buttons: the search box and the page number box submit the same
        // form. WordPress routes the bottom Apply through #doaction, so either select may carry it.
        const submitter = event.submitter || document.activeElement;
        const actions = Array.from(form.querySelectorAll('select[name="action"], select[name="action2"]')).map(select => select.value);

        if (!submitter || !['doaction', 'doaction2'].includes(submitter.id) || !actions.includes('ddfcpos_print_labels')) {
            return;
        }

        event.preventDefault();

        const labels = Array.from(form.querySelectorAll('input[name="ddfcpos-id[]"]:checked')).map(checkbox => {
            const row = checkbox.closest('tr');
            const quantityElement = row.querySelector('.ddfcpos-barcode-quantity');
            const quantity = Math.max(1, Number.parseInt(quantityElement && quantityElement.value, 10) || 1);

            return getBarcodePrintContent(row, quantity);
        }).join('');

        if (!labels) {
            alert(I18N.barcodeSelectError);
            return;
        }

        const html = `<html><head><title>Barcode</title>${getBarcodePrintStyle()}</head><body>${labels}</body></html>`;

        if (isMobileDevice()) {
            printInPopup(html);
            return;
        }

        printInFrame(html);
    });
};

const bindProductActions = () => {
    document.addEventListener('click', event => {
        const button = event.target.closest(PRODUCT_ACTION_SELECTOR);

        if (!button) {
            return;
        }

        event.preventDefault();

        const row = button.closest('tr');
        const productAction = button.getAttribute('data-action');

        if (!row) {
            return;
        }

        if ('print_barcode' === productAction) {
            handleBarcodePrint(row);
            return;
        }
    });
};

const bindBarcodeEnterKey = () => {
    document.addEventListener('keydown', event => {
        if (!event.target.matches(BARCODE_INPUT_SELECTOR) || ('Enter' !== event.key && 13 !== event.keyCode)) {
            return;
        }

        const nextElement = event.target.nextElementSibling;

        event.preventDefault();

        if (nextElement) {
            nextElement.click();
        }
    });
};

// Receipt previews: fill a template with the sample order and draw it in an isolated frame.
const INVOICE_PREVIEW = ADMIN_OBJECT.invoicePreview || {};
const RECEIPT_THUMB_WIDTH = 420;

const fillInvoiceTemplate = html => {
    const tokens = INVOICE_PREVIEW.tokens || {};

    return String(html || '')
        .replace(/\$\{\s*applyFilters\([^}]*\)\s*\}/g, '')
        .replace(/\$\{\s*(\w+)\s*\}/g, (match, name) => (Object.prototype.hasOwnProperty.call(tokens, name) ? tokens[name] : ''));
};

const getReceiptDocument = (html, css) => `<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;background:#fff;}body{padding:10px;}</style><style>${css || ''}</style></head><body>${fillInvoiceTemplate(html)}</body></html>`;

// Printer widths are saved as text such as "80mm"; the preview needs pixels.
const getReceiptWidth = () => {
    const match = String(INVOICE_PREVIEW.width || '').trim().match(/^([\d.]+)\s*(mm|cm|in|px)?$/i);
    const factor = { mm: 3.7795, cm: 37.795, in: 96, px: 1 };
    const width = match ? parseFloat(match[1]) * factor[(match[2] || 'mm').toLowerCase()] : 302;

    return Math.round(Math.min(Math.max(width, 200), 640));
};

const fitFrameHeight = frame => {
    const doc = frame.contentDocument;

    if (doc && doc.documentElement) {
        frame.style.height = `${doc.documentElement.scrollHeight}px`;
    }
};

const renderInvoiceThumbs = () => {
    document.querySelectorAll('[data-ddfcpos-invoice-thumb]:not(.is-ready)').forEach(thumb => {
        const frame = document.createElement('iframe');

        thumb.classList.add('is-ready');
        frame.setAttribute('sandbox', 'allow-same-origin');
        frame.setAttribute('tabindex', '-1');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.width = `${RECEIPT_THUMB_WIDTH}px`;
        frame.style.transform = `scale(${thumb.clientWidth / RECEIPT_THUMB_WIDTH})`;
        frame.addEventListener('load', () => fitFrameHeight(frame));
        frame.srcdoc = getReceiptDocument(INVOICE_PREVIEW.html, INVOICE_PREVIEW.css);
        thumb.appendChild(frame);
    });
};

// Full size receipt preview in a dialog: real width, other paper sizes, zoom and a test print.
const PREVIEW_PAPERS = [ [ '', '' ], [ '58mm', '58 mm' ], [ '80mm', '80 mm' ], [ '210mm', 'A4' ] ];
const PREVIEW_ZOOMS = [ 0.75, 1, 1.25, 1.5 ];

const toPixels = width => {
    const match = String(width || '').trim().match(/^([\d.]+)\s*(mm|cm|in|px)?$/i);
    const factor = { mm: 3.7795, cm: 37.795, in: 96, px: 1 };

    return match ? Math.round(parseFloat(match[1]) * factor[(match[2] || 'mm').toLowerCase()]) : 302;
};

const createInvoicePreviewDialog = getDocument => {
    const dialog = document.createElement('dialog');
    let paper = '';
    let zoom = 1;

    dialog.className = 'ddfcpos-invoice-dialog';
    dialog.setAttribute('aria-label', I18N.previewTitle || '');

    const segment = (label, items, isActive, onPick) => {
        const group = document.createElement('div');
        group.className = 'ddfcpos-invoice-dialog-segment';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', label);

        items.forEach(([ value, text ]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = text;
            button.className = isActive(value) ? 'is-active' : '';
            button.addEventListener('click', () => {
                onPick(value);
                group.querySelectorAll('button').forEach(other => other.classList.toggle('is-active', other === button));
            });
            group.appendChild(button);
        });

        return group;
    };

    const header = document.createElement('div');
    header.className = 'ddfcpos-invoice-dialog-bar';
    header.innerHTML = '<div><strong></strong><span></span></div>';
    header.querySelector('strong').textContent = I18N.previewTitle || '';
    header.querySelector('span').textContent = I18N.previewHint || '';

    const stage = document.createElement('div');
    stage.className = 'ddfcpos-invoice-dialog-stage';

    // The sizer takes the scaled size, so zooming in scrolls the stage instead of overflowing it.
    const sizer = document.createElement('div');
    sizer.className = 'ddfcpos-invoice-dialog-sizer';

    const frame = document.createElement('iframe');
    // No scripts in a template, but a test print needs the frame's own print dialog.
    frame.setAttribute('sandbox', 'allow-same-origin allow-modals');
    // The frame is as tall as the receipt; the stage around it does the scrolling.
    frame.setAttribute('scrolling', 'no');
    frame.title = I18N.previewTitle || '';
    sizer.appendChild(frame);
    stage.appendChild(sizer);

    const layout = () => {
        const width = paper ? toPixels(paper) : getReceiptWidth();

        frame.style.width = `${width}px`;
        frame.style.transform = `scale(${zoom})`;
        // Measure again at the new width: a narrower paper makes a longer receipt.
        frame.style.height = '0px';
        fitFrameHeight(frame);
        sizer.style.width = `${width * zoom}px`;
        sizer.style.height = `${frame.offsetHeight * zoom}px`;
    };

    frame.addEventListener('load', layout);

    const actions = document.createElement('div');
    actions.className = 'ddfcpos-invoice-dialog-actions';
    actions.appendChild(segment(I18N.previewPaper || '', PREVIEW_PAPERS.map(([ value, text ]) => [ value, value ? text : (I18N.previewPrinterWidth || '%s').replace('%s', INVOICE_PREVIEW.width || '') ]), value => value === paper, value => {
        paper = value;
        layout();
    }));
    actions.appendChild(segment(I18N.previewZoom || '', PREVIEW_ZOOMS.map(value => [ value, `${Math.round(value * 100)}%` ]), value => value === zoom, value => {
        zoom = value;
        layout();
    }));

    const printButton = document.createElement('button');
    printButton.type = 'button';
    printButton.className = 'button';
    printButton.textContent = I18N.previewPrint || '';
    printButton.addEventListener('click', () => frame.contentWindow && frame.contentWindow.print());
    actions.appendChild(printButton);

    // Icon only, the same round close as the framework's mobile settings drawer.
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'ddfcpos-invoice-dialog-close';
    closeButton.setAttribute('aria-label', I18N.previewClose || '');
    closeButton.title = I18N.previewClose || '';
    closeButton.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    closeButton.addEventListener('click', () => dialog.close());
    actions.appendChild(closeButton);

    header.appendChild(actions);
    dialog.appendChild(header);
    dialog.appendChild(stage);
    // Inside the framework wrapper so buttons and fields pick up the dashboard styles.
    (document.querySelector('.devdiggers-wrap') || document.body).appendChild(dialog);

    // A click on the dimmed backdrop (the dialog element itself) closes it, like Esc does.
    dialog.addEventListener('click', event => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    return {
        open: () => {
            frame.srcdoc = getDocument();
            dialog.showModal();
        },
        refresh: () => {
            if (dialog.open) {
                frame.srcdoc = getDocument();
            }
        },
    };
};

// Layout palettes: swatches fill the colour pickers, and a mini POS preview follows every change.
const PALETTE_KEYS = ['primary-color', 'secondary-color', 'font-color', 'surface-color', 'muted-background-color-1', 'muted-background-color-2', 'button-font-color', 'border-color', 'success-color'];

// "#555" and "#555555" are the same colour; compare the long lowercase form.
const normalizeHex = value => {
    const hex = String(value || '').trim().toLowerCase();
    return /^#[0-9a-f]{3}$/.test(hex) ? '#' + [...hex.slice(1)].map(c => c + c).join('') : hex;
};

const initPalettePicker = () => {
    const picker = document.querySelector('.ddfcpos-palette-picker');
    if (!picker || picker.dataset.ready) {
        return;
    }
    picker.dataset.ready = '1';

    const $ = window.jQuery;
    const preview = picker.querySelector('.ddfcpos-palette-preview');
    const options = [...picker.querySelectorAll('.ddfcpos-palette-option')];
    const field = key => document.getElementById(`ddfcpos-layout-${key}`);
    const current = () => PALETTE_KEYS.reduce((colors, key) => {
        const input = field(key);
        colors[key] = input ? normalizeHex(input.value) : '';
        return colors;
    }, {});
    let applying = false;

    const sync = () => {
        const colors = current();
        PALETTE_KEYS.forEach(key => colors[key] && preview.style.setProperty(`--pp-${key}`, colors[key]));

        const match = options.find(option => {
            if (!option.dataset.colors) {
                return false;
            }
            const palette = JSON.parse(option.dataset.colors);
            return PALETTE_KEYS.every(key => !palette[key] || normalizeHex(palette[key]) === colors[key]);
        });

        options.forEach(option => {
            const active = match ? option === match : option.dataset.palette === 'custom';
            option.classList.toggle('ddfcpos-active', active);
            option.setAttribute('aria-checked', active ? 'true' : 'false');
        });

        const custom = picker.querySelector('.ddfcpos-palette-custom');
        custom.querySelectorAll('i').forEach((swatch, index) => {
            swatch.style.background = colors[['primary-color', 'muted-background-color-1', 'font-color'][index]];
        });
    };

    options.forEach(option => option.dataset.colors && option.addEventListener('click', () => {
        const palette = JSON.parse(option.dataset.colors);
        applying = true;
        Object.keys(palette).forEach(key => {
            const input = field(key);
            if (!input) {
                return;
            }
            if ($ && $(input).closest('.wp-picker-container').length) {
                $(input).wpColorPicker('color', palette[key]);
            } else {
                input.value = palette[key];
            }
        });
        applying = false;
        sync();
    }));

    if ($) {
        $(document).on('ddfw_color_picker_change', () => !applying && setTimeout(sync));
    }
    PALETTE_KEYS.forEach(key => field(key) && field(key).addEventListener('input', sync));
    sync();
};

// Receipt templates screen: the template card opens the full size preview.
const bindInvoicePreview = () => {
    const button = document.querySelector('.ddfcpos-invoice-fullscreen:not(.is-ready)');

    if (!button) {
        return;
    }

    button.classList.add('is-ready');

    if (!window.HTMLDialogElement) {
        button.hidden = true;
        return;
    }

    const dialog = createInvoicePreviewDialog(() => getReceiptDocument(INVOICE_PREVIEW.html, INVOICE_PREVIEW.css));

    button.addEventListener('click', dialog.open);
    document.querySelectorAll('[data-ddfcpos-invoice-thumb]').forEach(thumb => thumb.addEventListener('click', dialog.open));
};

onReady(() => {
    bindConfigurationForms();
    bindRepeaterRows();
    bindProductActions();
    bindBulkLabelPrint();
    bindBarcodeEnterKey();
    renderInvoiceThumbs();
    bindInvoicePreview();
    document.addEventListener('ddfw:view:load', renderInvoiceThumbs);
    document.addEventListener('ddfw:view:load', bindInvoicePreview);
    initPalettePicker();
    document.addEventListener('ddfw:view:load', initPalettePicker);
});
