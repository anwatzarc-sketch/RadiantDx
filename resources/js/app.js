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
 * Shared controlled-vocabulary selector.
 *
 * Backs <x-form.shared-enum-select>. Options are fetched from the enum
 * catalogue endpoint rather than rendered into the page, so a vocabulary has
 * one definition and no view can drift from it.
 *
 * Searching matches the display text and the vocabulary's own synonyms, so
 * "card" finds Cardiology and Cardiothoracic Surgery, and "heart" finds
 * Cardiology even though the word does not appear in its label.
 *
 * A dependent selector follows another field: when the parent changes, the list
 * reloads and a selection that no longer belongs to the new parent is cleared,
 * so an invalid pairing cannot survive in the form. The server enforces the
 * same rule independently — see App\Rules\ValidSubSpeciality.
 */
Alpine.data('sharedEnumSelect', (config = {}) => ({
    enumName: config.enumName,
    endpoint: config.endpoint,
    parent: config.parent ?? null,
    parentField: config.parentField ?? null,
    nullable: config.nullable !== false,

    open: false,
    loading: false,
    failed: false,
    loaded: false,
    query: '',
    highlighted: 0,
    options: [],
    selected: config.initial ?? '',
    selectedLabel: '',

    init() {
        // A dependent selector with no parent yet has nothing to offer, so it
        // stays disabled rather than presenting an empty list.
        if (this.selected) {
            this.load();
        }
    },

    get waitingForParent() {
        return this.parentField !== null && !this.parent;
    },

    get isDisabled() {
        return this.$el.querySelector('button[disabled]') !== null || this.waitingForParent;
    },

    get results() {
        const query = this.query.trim().toLowerCase();

        if (!query) {
            return this.options;
        }

        return this.options.filter((option) => {
            if (option.text.toLowerCase().includes(query)) {
                return true;
            }

            return (option.searchKeywords ?? []).some((keyword) => keyword.toLowerCase().includes(query));
        });
    },

    async load() {
        if (this.waitingForParent) {
            this.options = [];
            return;
        }

        this.loading = true;
        this.failed = false;

        const url = new URL(`${this.endpoint}/${this.enumName}`, window.location.origin);
        if (this.parent) {
            url.searchParams.set('parent', this.parent);
        }

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));

            const payload = await response.json();
            this.options = payload.values ?? [];
            this.loaded = true;
            this.syncSelectedLabel();
        } catch {
            this.failed = true;
            this.options = [];
        } finally {
            this.loading = false;
        }
    },

    syncSelectedLabel() {
        const match = this.options.find((option) => option.value === this.selected);

        if (match) {
            this.selectedLabel = match.text;
            return;
        }

        // The stored value is not in the current list — either it is retired, or
        // the parent changed underneath it. Clearing is the safe reading: a
        // value that is not offered must not be silently resubmitted.
        if (this.loaded && this.selected) {
            this.clear();
        }
    },

    async toggle() {
        if (this.isDisabled) return;

        this.open = !this.open;

        if (this.open) {
            if (!this.loaded) await this.load();
            this.highlighted = Math.max(0, this.results.findIndex((o) => o.value === this.selected));
            this.$nextTick(() => this.$refs.search?.focus());
        }
    },

    close() {
        this.open = false;
        this.query = '';
        this.$refs.trigger?.focus();
    },

    move(offset) {
        const count = this.results.length;
        if (count === 0) return;

        this.highlighted = (this.highlighted + offset + count) % count;
    },

    choose(option) {
        if (!option) return;

        this.selected = option.value;
        this.selectedLabel = option.text;
        this.announce();
        this.close();
    },

    clear() {
        this.selected = '';
        this.selectedLabel = '';
        this.announce();
    },

    /** Lets a dependent selector downstream of this one react. */
    announce() {
        window.dispatchEvent(
            new CustomEvent('shared-enum-changed', {
                detail: { enumName: this.enumName, value: this.selected },
            }),
        );
    },

    onSiblingChanged(event) {
        if (this.parentField === null || event.detail.enumName !== this.parentField) {
            return;
        }

        this.parent = event.detail.value || null;
        this.loaded = false;
        this.options = [];
        this.load();
    },
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