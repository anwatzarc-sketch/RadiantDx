import Alpine from 'alpinejs';

/**
 * Alpine.js component registration for Laboratory Management UI.
 * Handles dynamic pickers, option list builders, notification toasts,
 * and confirmation modals.
 */

/**
 * Requisition Form Investigation Picker.
 * Maintains an ordered list of selected test and panel identifiers ("type:id").
 */
Alpine.data('investigationPicker', (catalogue = [], initial = []) => ({
    catalogue,
    query: '',
    selected: Array.isArray(initial) ? initial.slice() : [],

    get results() {
        const query = this.query.trim().toLowerCase();

        return this.catalogue
            .filter((entry) => !this.isSelected(entry.key))
            .filter((entry) => {
                if (!query) return true;
                return (
                    (entry.name ?? '').toLowerCase().includes(query) ||
                    (entry.code ?? '').toLowerCase().includes(query) ||
                    (entry.category ?? '').toLowerCase().includes(query)
                );
            })
            .slice(0, 40);
    },

    get selectedEntries() {
        const catalogueMap = new Map(this.catalogue.map((entry) => [entry.key, entry]));
        return this.selected.map((key) => catalogueMap.get(key)).filter(Boolean);
    },

    isSelected(key) {
        return this.selected.includes(key);
    },

    add(key) {
        if (key && !this.isSelected(key)) {
            this.selected.push(key);
        }
        this.query = '';
    },

    remove(key) {
        this.selected = this.selected.filter((entryKey) => entryKey !== key);
    },

    move(key, offset) {
        const index = this.selected.indexOf(key);
        const target = index + offset;

        if (index === -1 || target < 0 || target >= this.selected.length) {
            return;
        }

        const next = [...this.selected];
        [next[index], next[target]] = [next[target], next[index]];
        this.selected = next;
    },

    clear() {
        this.selected = [];
    }
}));

/**
 * Panel Editor Test Picker.
 * Selects and orders member laboratory tests.
 */
Alpine.data('testPicker', (catalogue = [], initial = []) => ({
    catalogue,
    query: '',
    selected: Array.isArray(initial) ? initial.slice() : [],

    get results() {
        const query = this.query.trim().toLowerCase();

        return this.catalogue
            .filter((entry) => !this.selected.includes(entry.id))
            .filter((entry) => {
                if (!query) return true;
                return (
                    (entry.name ?? '').toLowerCase().includes(query) ||
                    (entry.code ?? '').toLowerCase().includes(query)
                );
            })
            .slice(0, 40);
    },

    get selectedEntries() {
        const catalogueMap = new Map(this.catalogue.map((entry) => [entry.id, entry]));
        return this.selected.map((id) => catalogueMap.get(id)).filter(Boolean);
    },

    add(id) {
        if (id !== undefined && !this.selected.includes(id)) {
            this.selected.push(id);
        }
        this.query = '';
    },

    remove(id) {
        this.selected = this.selected.filter((entryId) => entryId !== id);
    },

    move(id, offset) {
        const index = this.selected.indexOf(id);
        const target = index + offset;

        if (index === -1 || target < 0 || target >= this.selected.length) {
            return;
        }

        const next = [...this.selected];
        [next[index], next[target]] = [next[target], next[index]];
        this.selected = next;
    }
}));

/**
 * Parameter Dropdown Option Rows Manager.
 * Handles dynamic addition, deletion, and reordering of option items.
 */
Alpine.data('optionRows', (initial = []) => ({
    rows: Array.isArray(initial) && initial.length > 0
        ? initial.map((row) => ({ ...row }))
        : [{ value: '', label: '', is_abnormal: false }],

    add() {
        this.rows.push({ value: '', label: '', is_abnormal: false });
    },

    remove(index) {
        this.rows.splice(index, 1);

        if (this.rows.length === 0) {
            this.add();
        }
    },

    move(index, offset) {
        const target = index + offset;
        if (target < 0 || target >= this.rows.length) return;

        const next = [...this.rows];
        [next[index], next[target]] = [next[target], next[index]];
        this.rows = next;
    }
}));

/**
 * Toast Notification Auto-Dismiss Component.
 * Binds to `<x-flash />` elements for transient alert messages.
 */
Alpine.data('toast', (options = {}) => ({
    show: true,
    timeout: null,
    delay: options.delay ?? 5000,

    init() {
        if (this.delay > 0) {
            this.timeout = setTimeout(() => {
                this.dismiss();
            }, this.delay);
        }
    },

    dismiss() {
        this.show = false;
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
    }
}));

/**
 * Confirmation Modal Handler.
 * Intercepts destructive actions before form submission or API dispatch.
 */
Alpine.data('confirmDialog', () => ({
    open: false,
    title: '',
    message: '',
    onConfirm: null,

    ask({ title = 'Confirm Action', message = 'Are you sure you want to proceed?', onConfirm }) {
        this.title = title;
        this.message = message;
        this.onConfirm = onConfirm;
        this.open = true;
    },

    confirm() {
        if (typeof this.onConfirm === 'function') {
            this.onConfirm();
        }
        this.open = false;
    },

    cancel() {
        this.open = false;
    }
}));

/**
 * Labels data-table cells for the stacked, small-screen presentation.
 *
 * Below the sm breakpoint app.css turns each table row into a card and prints
 * each cell's column heading beside its value, taken from a data-label
 * attribute. Rather than requiring every <td> in the application to carry that
 * attribute by hand — which a new module would inevitably forget — the labels
 * are copied from the table's own <thead> at runtime.
 *
 * Any table that uses .data-table is handled, including ones that do not exist
 * yet. Without JavaScript the tables keep their horizontal scroll, so this is
 * an enhancement rather than a dependency.
 */
function labelDataTables(root = document) {
    root.querySelectorAll('table.data-table').forEach((table) => {
        const headings = Array.from(table.querySelectorAll('thead > tr > th')).map((th) => {
            // An action column's heading is screen-reader-only text; showing it
            // against the buttons would just read "Actions Actions".
            const srOnly = th.querySelector('.sr-only');
            if (srOnly && srOnly.textContent.trim() === th.textContent.trim()) {
                return '';
            }

            return th.textContent.replace(/\s+/g, ' ').trim();
        });

        if (headings.length === 0) {
            return;
        }

        table.querySelectorAll('tbody > tr').forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                // A spanning cell (an "empty" row, say) has no single heading.
                if (cell.hasAttribute('data-label') || cell.colSpan > 1) {
                    return;
                }

                cell.setAttribute('data-label', headings[index] ?? '');
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => labelDataTables());
document.addEventListener('alpine:initialized', () => labelDataTables());

/**
 * Service worker registration.
 *
 * The worker caches build output and icons only — never an authenticated page.
 * See public/sw.js for why that matters here.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // An unregistered worker costs nothing but the offline page, so a
            // failure here should never surface to the user.
        });
    });

    // Signing out drops everything the worker holds, so nothing outlives the
    // session on a shared laboratory machine.
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (new URL(form.action, window.location.origin).pathname.replace(/\/$/, '').endsWith('/logout')) {
            navigator.serviceWorker.controller?.postMessage('clear-caches');
        }
    });
}

window.Alpine = Alpine;
Alpine.start();