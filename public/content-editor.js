(() => {
    'use strict';

    const boot = JSON.parse(document.getElementById('ed-boot').textContent);
    const schema = boot.schema;
    const collections = Object.fromEntries(schema.collections.map((c) => [c.name, c]));
    const textGroups = Object.fromEntries(schema.textGroups.map((g) => [g.id, g]));
    const textDefaults = boot.textDefaults;
    const PAGE_GROUPS = ['Home', 'Résumé', 'Services', 'Everywhere'];
    const LANGS = [['en', 'EN', 'English'], ['pl', 'PL', 'Polski']];

    const $ = (id) => document.getElementById(id);
    const nav = $('ed-nav');
    const main = $('ed-main');
    const statusEl = $('ed-status');

    let doc;
    let published;
    let revision;
    let savedSnapshot;
    let conflict = false;
    let busy = false;
    const openCards = new Set();
    let view = store('ed-view') || 'both';
    let current = store('ed-section') || 'list:experience';

    // ---------- helpers ----------

    function store(key, value) {
        try {
            if (value === undefined) return localStorage.getItem(key);
            localStorage.setItem(key, value);
        } catch (e) { /* storage can be unavailable */ }
        return null;
    }

    function canon(value) {
        if (Array.isArray(value)) return '[' + value.map(canon).join(',') + ']';
        if (value && typeof value === 'object') {
            return '{' + Object.keys(value).sort().map((k) => JSON.stringify(k) + ':' + canon(value[k])).join(',') + '}';
        }
        return JSON.stringify(value);
    }

    function prepare(source) {
        const d = JSON.parse(JSON.stringify(source || {}));
        if (!d.collections || Array.isArray(d.collections)) d.collections = {};
        schema.collections.forEach((c) => { if (!Array.isArray(d.collections[c.name])) d.collections[c.name] = []; });
        if (!d.texts || Array.isArray(d.texts)) d.texts = {};
        return d;
    }

    function load(state) {
        doc = prepare(state.draft);
        published = prepare(state.published);
        revision = state.revision;
        savedSnapshot = canon(doc);
    }

    function el(tag, attrs, ...children) {
        const node = document.createElement(tag);
        Object.entries(attrs || {}).forEach(([key, value]) => {
            if (value === null || value === undefined || value === false) return;
            if (key === 'class') node.className = value;
            else if (key === 'text') node.textContent = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else if (value === true) node.setAttribute(key, '');
            else node.setAttribute(key, value);
        });
        children.flat().forEach((child) => {
            if (child === null || child === undefined || child === false) return;
            node.append(child instanceof Node ? child : document.createTextNode(String(child)));
        });
        return node;
    }

    const put = (parent, ...nodes) => parent.append(...nodes.filter((node) => node !== null && node !== undefined && node !== false));

    const displayLang = () => (view === 'pl' ? 'pl' : 'en');
    const pick = (value) => (typeof value === 'string' ? value : (value?.[displayLang()] || value?.en || ''));
    const isDirty = () => canon(doc) !== savedSnapshot;
    const isUnpublished = () => canon(doc) !== canon(published);
    const publishedItem = (name, id) => published.collections[name].find((item) => item.id === id);

    function itemState(name, item) {
        const before = publishedItem(name, item.id);
        if (!before) return 'new';
        return canon(before) === canon(item) ? '' : 'changed';
    }

    function collectionChanged(name) {
        return canon(doc.collections[name]) !== canon(published.collections[name]);
    }

    function groupChanged(group) {
        return group.fields.some((f) => canon(doc.texts[f.key] || null) !== canon(published.texts[f.key] || null));
    }

    function newId(def) {
        const base = def.item.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 28) || 'item';
        const taken = new Set(doc.collections[def.name].map((i) => i.id));
        let id;
        do { id = base + '-' + Math.random().toString(36).slice(2, 7); } while (taken.has(id));
        return id;
    }

    function blankItem(def) {
        const item = { id: newId(def) };
        def.fields.forEach((f) => {
            if (f.type === 'list') item[f.name] = [];
            else if (f.type === 'bool') item[f.name] = false;
            else if (f.type === 'image') item[f.name] = schema.images[0];
            else if (f.type === 'url' || f.shared) item[f.name] = '';
            else item[f.name] = { en: '', pl: '' };
        });
        return item;
    }

    // Grow textareas with their text; the English and Polish boxes of one pair keep the same height.
    function autosize(area) {
        const pair = area.closest('.ed-pair');
        let areas = pair ? [...pair.querySelectorAll('textarea')].filter((a) => a.offsetParent !== null) : [area];
        if (areas.length > 1 && areas[0].getBoundingClientRect().left === areas[1].getBoundingClientRect().left) {
            areas = [area];
        }
        areas.forEach((a) => { a.style.height = 'auto'; });
        const height = Math.max(44, ...areas.map((a) => a.scrollHeight + 2));
        areas.forEach((a) => { a.style.height = height + 'px'; });
    }

    function toast(message, options = {}) {
        const box = $('ed-toasts');
        const node = el('div', { class: 'ed-toast' + (options.kind ? ' is-' + options.kind : ''), role: options.kind === 'error' ? 'alert' : 'status' },
            el('span', { text: message }));
        let timer;
        const close = () => { clearTimeout(timer); node.remove(); };
        if (options.action) {
            node.append(el('button', { type: 'button', text: options.action, onclick: () => { close(); options.onAction(); } }));
        }
        node.append(el('button', { type: 'button', class: 'ed-toast-close', 'aria-label': 'Close', text: '×', onclick: close }));
        box.append(node);
        timer = setTimeout(close, options.timeout || (options.kind === 'error' ? 12000 : 5000));
    }

    // ---------- status, buttons, navigation ----------

    function refreshStatus() {
        const dirty = isDirty();
        const unpublished = isUnpublished();
        let text = 'Everything is published';
        let kind = 'ok';
        if (conflict) { text = 'Changed in another tab — reload'; kind = 'error'; }
        else if (busy) { text = 'Saving…'; kind = 'busy'; }
        else if (dirty) { text = 'Unsaved changes'; kind = 'dirty'; }
        else if (unpublished) { text = 'Draft saved, not published'; kind = 'draft'; }
        statusEl.textContent = text;
        statusEl.dataset.kind = kind;
        $('ed-save').disabled = busy || conflict || !dirty;
        $('ed-publish').disabled = busy || conflict || !unpublished;
        $('ed-discard').disabled = busy || (!dirty && !unpublished);
        nav.querySelectorAll('[data-section]').forEach((link) => {
            const [kindName, id] = link.dataset.section.split(':');
            const changed = kindName === 'list' ? collectionChanged(id) : groupChanged(textGroups[id]);
            link.classList.toggle('is-changed', changed);
            const count = link.querySelector('.ed-count');
            if (count && kindName === 'list') count.textContent = doc.collections[id].length;
        });
    }

    function changed() {
        refreshStatus();
    }

    function renderNav() {
        nav.replaceChildren();
        PAGE_GROUPS.forEach((page) => {
            const lists = schema.collections.filter((c) => c.group === page);
            const texts = schema.textGroups.filter((g) => g.group === page);
            if (!lists.length && !texts.length) return;
            const section = el('div', { class: 'ed-nav-group' }, el('h2', { text: page }));
            lists.forEach((c) => section.append(navLink('list:' + c.name, c.label, doc.collections[c.name].length)));
            texts.forEach((g) => {
                const label = g.label.replace(page + ' · ', '');
                section.append(navLink('text:' + g.id, label.charAt(0).toUpperCase() + label.slice(1), null));
            });
            nav.append(section);
        });
    }

    // Keep the active section visible in the menu without scrolling the page.
    function revealCurrentLink() {
        const link = nav.querySelector('[aria-current="page"]');
        if (!link) return;
        if (nav.scrollWidth > nav.clientWidth) nav.scrollLeft = link.offsetLeft - (nav.clientWidth - link.offsetWidth) / 2;
        else if (link.offsetTop < nav.scrollTop || link.offsetTop > nav.scrollTop + nav.clientHeight - link.offsetHeight) nav.scrollTop = link.offsetTop - 40;
    }

    function navLink(id, label, count) {
        return el('a', {
            href: '#' + id, class: 'ed-nav-link', 'data-section': id, 'aria-current': id === current ? 'page' : null,
            onclick: (event) => { event.preventDefault(); select(id); },
        }, el('span', { class: 'ed-nav-icon', 'aria-hidden': 'true', text: id.startsWith('list:') ? '≡' : 'T' }),
        el('span', { class: 'ed-nav-label', text: label }),
        count === null ? null : el('span', { class: 'ed-count', text: count }),
        el('span', { class: 'ed-dot', title: 'Unpublished changes' }));
    }

    function select(id) {
        current = id;
        store('ed-section', id);
        nav.querySelectorAll('[data-section]').forEach((link) => {
            if (link.dataset.section === id) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
        });
        revealCurrentLink();
        renderMain();
        main.focus({ preventScroll: true });
        window.scrollTo({ top: 0 });
    }

    // ---------- main area ----------

    function viewSwitch() {
        const box = el('div', { class: 'ed-segmented', role: 'group', 'aria-label': 'Languages shown' });
        [['both', 'EN + PL'], ['en', 'EN'], ['pl', 'PL']].forEach(([value, label]) => {
            box.append(el('button', {
                type: 'button', text: label, 'aria-pressed': String(view === value),
                onclick: () => { view = value; store('ed-view', value); renderMain(); },
            }));
        });
        return box;
    }

    function renderMain() {
        main.className = 'ed-main ed-view-' + view;
        main.replaceChildren();
        const [kind, id] = current.split(':');
        if (kind === 'list' && collections[id]) renderCollection(collections[id]);
        else if (kind === 'text' && textGroups[id]) renderTextGroup(textGroups[id]);
        else { current = 'list:experience'; renderCollection(collections.experience); }
        main.querySelectorAll('textarea').forEach(autosize);
    }

    function header(title, description) {
        return el('div', { class: 'ed-head' },
            el('div', {}, el('h1', { text: title }), description ? el('p', { text: description }) : null),
            viewSwitch());
    }

    function renderCollection(def) {
        const items = doc.collections[def.name];
        main.append(header(def.label, def.description));
        const list = el('div', { class: 'ed-cards' });
        items.forEach((item, index) => list.append(card(def, item, index)));
        if (!items.length) list.append(el('p', { class: 'ed-empty', text: 'Nothing here yet.' }));
        main.append(list);
        sortable(list, '.ed-card', 'card', (order) => {
            doc.collections[def.name] = order.map((i) => items[i]);
            renderMain();
            changed();
        });
        main.append(el('button', {
            type: 'button', class: 'ed-add', disabled: items.length >= schema.limits.items,
            onclick: () => {
                const item = blankItem(def);
                items.push(item);
                openCards.add(item.id);
                renderMain();
                changed();
                const node = main.querySelector(`[data-id="${item.id}"]`);
                node?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                node?.querySelector('input, textarea')?.focus({ preventScroll: true });
            },
        }, el('span', { 'aria-hidden': 'true', text: '+' }), ' Add ' + def.item));
    }

    function cardTitle(def, item) {
        const parts = def.title.map((field) => pick(item[field]).replace(/\s+/g, ' ').trim()).filter(Boolean);
        return { title: parts[0] || 'New ' + def.item, subtitle: parts.slice(1).join(' · ') };
    }

    function card(def, item, index) {
        const items = doc.collections[def.name];
        const isOpen = openCards.has(item.id);
        const titleEl = el('strong', { class: 'ed-card-title' });
        const subtitleEl = el('span', { class: 'ed-card-subtitle' });
        const badges = el('span', { class: 'ed-badges' });
        const body = el('div', { class: 'ed-card-body', id: 'body-' + item.id, hidden: !isOpen });

        const refreshHead = () => {
            const { title, subtitle } = cardTitle(def, item);
            titleEl.textContent = title;
            subtitleEl.textContent = subtitle;
            badges.replaceChildren();
            def.fields.filter((f) => f.type === 'bool' && f.badge && item[f.name]).forEach((f) => badges.append(el('span', { class: 'ed-badge', text: f.badge })));
            const state = itemState(def.name, item);
            if (state) badges.append(el('span', { class: 'ed-badge is-' + state, text: state === 'new' ? 'New' : 'Unpublished' }));
        };

        const move = (delta) => {
            const target = index + delta;
            if (target < 0 || target >= items.length) return;
            items.splice(target, 0, items.splice(index, 1)[0]);
            renderMain();
            changed();
            main.querySelector(`[data-id="${item.id}"] .ed-handle`)?.focus();
        };

        const toggle = () => {
            if (openCards.has(item.id)) openCards.delete(item.id); else openCards.add(item.id);
            body.hidden = !openCards.has(item.id);
            node.classList.toggle('is-open', !body.hidden);
            expand.setAttribute('aria-expanded', String(!body.hidden));
            body.querySelectorAll('textarea').forEach(autosize);
        };

        const expand = el('button', {
            type: 'button', class: 'ed-card-toggle', 'aria-expanded': String(isOpen), 'aria-controls': 'body-' + item.id,
            onclick: toggle,
        }, el('span', { class: 'ed-card-text' }, titleEl, subtitleEl), badges, el('span', { class: 'ed-chevron', 'aria-hidden': 'true' }));

        const menu = actionsMenu([
            ['Move up', () => move(-1), index === 0],
            ['Move down', () => move(1), index === items.length - 1],
            ['Duplicate', () => {
                const copy = JSON.parse(JSON.stringify(item));
                copy.id = newId(def);
                items.splice(index + 1, 0, copy);
                openCards.add(copy.id);
                renderMain();
                changed();
            }, items.length >= schema.limits.items],
            ['Delete', () => {
                const removed = items.splice(index, 1)[0];
                renderMain();
                changed();
                toast(`${cardTitle(def, removed).title} deleted.`, {
                    action: 'Undo', timeout: 8000,
                    onAction: () => { doc.collections[def.name].splice(index, 0, removed); renderMain(); changed(); },
                });
            }, false, true],
        ]);

        const handle = el('button', {
            type: 'button', class: 'ed-handle', 'data-drag': 'card', 'aria-label': `Move ${def.item}. Drag, or use the arrow keys`,
            onkeydown: (event) => {
                if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
                if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
            },
        }, gripIcon());

        const node = el('article', { class: 'ed-card' + (isOpen ? ' is-open' : ''), 'data-id': item.id, 'data-index': index },
            el('div', { class: 'ed-card-head' }, handle, expand, menu), body);

        def.fields.forEach((field) => body.append(fieldEditor(def, item, field, () => { refreshHead(); changed(); })));
        refreshHead();
        return node;
    }

    function gripIcon() {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 12 18');
        svg.setAttribute('aria-hidden', 'true');
        [[3, 3], [9, 3], [3, 9], [9, 9], [3, 15], [9, 15]].forEach(([cx, cy]) => {
            const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            dot.setAttribute('cx', cx);
            dot.setAttribute('cy', cy);
            dot.setAttribute('r', 1.6);
            svg.append(dot);
        });
        return svg;
    }

    function actionsMenu(actions) {
        const wrap = el('div', { class: 'ed-more' });
        const list = el('div', { class: 'ed-menu', role: 'menu', hidden: true });
        const button = el('button', {
            type: 'button', class: 'ed-more-btn', 'aria-label': 'More actions', 'aria-haspopup': 'true', 'aria-expanded': 'false',
            onclick: (event) => {
                event.stopPropagation();
                const willOpen = list.hidden;
                closeMenus();
                list.hidden = !willOpen;
                button.setAttribute('aria-expanded', String(willOpen));
                if (willOpen) list.querySelector('button:not([disabled])')?.focus();
            },
        }, '⋯');
        actions.forEach(([label, run, disabled, danger]) => {
            list.append(el('button', {
                type: 'button', role: 'menuitem', class: danger ? 'is-danger' : null, disabled: disabled,
                onclick: () => { closeMenus(); run(); },
            }, label));
        });
        put(wrap, button, list);
        return wrap;
    }

    function closeMenus() {
        document.querySelectorAll('.ed-menu').forEach((menu) => { menu.hidden = true; });
        document.querySelectorAll('[aria-haspopup][aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    }

    // ---------- fields ----------

    function fieldEditor(def, item, field, onChange) {
        const id = `f-${item.id}-${field.name}`;
        const wrap = el('div', { class: 'ed-field ed-field-' + field.type });
        const label = el('label', { class: 'ed-label', for: field.type === 'list' || field.type === 'image' ? null : id }, field.label);
        const hint = field.hint ? el('span', { class: 'ed-hint', text: field.hint }) : null;

        if (field.type === 'bool') {
            const input = el('input', {
                type: 'checkbox', id, role: 'switch', checked: item[field.name] === true,
                onchange: () => { item[field.name] = input.checked; onChange(); },
            });
            put(wrap, el('label', { class: 'ed-switch', for: id }, input, el('span', { class: 'ed-switch-track', 'aria-hidden': 'true' }), field.label));
            return wrap;
        }

        if (field.type === 'image') {
            const grid = el('div', { class: 'ed-images', role: 'radiogroup', 'aria-label': field.label });
            schema.images.forEach((src, i) => {
                const radio = el('input', {
                    type: 'radio', name: id, value: src, id: id + '-' + i, checked: item[field.name] === src,
                    onchange: () => { item[field.name] = src; onChange(); },
                });
                grid.append(el('label', { class: 'ed-image', for: id + '-' + i }, radio, el('img', { src, alt: '', loading: 'lazy' })));
            });
            put(wrap, el('span', { class: 'ed-label', text: field.label }), grid);
            return wrap;
        }

        if (field.type === 'url' || (field.shared && field.type !== 'list')) {
            const input = el('input', {
                id, type: field.type === 'url' ? 'url' : 'text', value: item[field.name] || '', placeholder: field.type === 'url' ? 'https://' : null,
                maxlength: schema.limits.text, spellcheck: field.type === 'url' ? 'false' : null,
                oninput: () => { item[field.name] = input.value; onChange(); },
            });
            put(wrap, label, hint, el('div', { class: 'ed-single' }, input));
            return wrap;
        }

        if (field.type === 'list') {
            put(wrap, el('div', { class: 'ed-label', text: field.label }), hint, listEditor(item, field, onChange));
            return wrap;
        }

        put(wrap, label, hint, pairEditor(id, item[field.name], field.type === 'textarea', (lang, value) => {
            item[field.name] = { ...item[field.name], [lang]: value };
            onChange();
        }));
        return wrap;
    }

    function pairEditor(id, value, multiline, onInput, placeholders) {
        const pair = el('div', { class: 'ed-pair' });
        LANGS.forEach(([lang, short, long]) => {
            const attrs = {
                id: lang === 'en' ? id : id + '-pl', lang, maxlength: schema.limits.text, 'aria-label': long,
                placeholder: placeholders?.[lang] || null,
                oninput: (event) => { if (event.target.tagName === 'TEXTAREA') autosize(event.target); onInput(lang, event.target.value); },
            };
            const input = multiline ? el('textarea', { ...attrs, rows: 2 }) : el('input', { ...attrs, type: 'text' });
            input.value = value?.[lang] ?? '';
            pair.append(el('div', { class: 'ed-lang', 'data-lang': lang }, input, el('span', { class: 'ed-lang-tag', 'aria-hidden': 'true', text: short })));
        });
        return pair;
    }

    function listEditor(item, field, onChange) {
        const entries = item[field.name];
        const box = el('div', { class: 'ed-list' });
        const rows = el('div', { class: 'ed-entries' });
        const rebuild = (focusIndex) => {
            rows.replaceChildren();
            entries.forEach((entry, index) => {
                const move = (delta) => {
                    const target = index + delta;
                    if (target < 0 || target >= entries.length) return;
                    entries.splice(target, 0, entries.splice(index, 1)[0]);
                    rebuild();
                    onChange();
                    rows.children[target]?.querySelector('.ed-handle')?.focus();
                };
                const row = el('div', { class: 'ed-entry', 'data-index': index },
                    el('button', {
                        type: 'button', class: 'ed-handle', 'data-drag': 'entry', 'aria-label': 'Move. Drag, or use the arrow keys',
                        onkeydown: (event) => {
                            if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
                            if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
                        },
                    }, gripIcon()),
                    pairEditor(`e-${item.id}-${field.name}-${index}`, entry, true, (lang, value) => {
                        entries[index] = { ...entries[index], [lang]: value };
                        onChange();
                    }),
                    el('button', {
                        type: 'button', class: 'ed-remove', 'aria-label': 'Remove', title: 'Remove',
                        onclick: () => {
                            const removed = entries.splice(index, 1)[0];
                            rebuild();
                            onChange();
                            if (removed.en || removed.pl) {
                                toast('Removed.', { action: 'Undo', onAction: () => { entries.splice(index, 0, removed); rebuild(); onChange(); } });
                            }
                        },
                    }, '×'));
                rows.append(row);
            });
            rows.querySelectorAll('textarea').forEach(autosize);
            add.disabled = entries.length >= schema.limits.entries;
            if (focusIndex !== undefined) rows.children[focusIndex]?.querySelector('textarea')?.focus();
        };
        const add = el('button', {
            type: 'button', class: 'ed-add-entry',
            onclick: () => { entries.push({ en: '', pl: '' }); rebuild(entries.length - 1); onChange(); },
        }, '+ ' + (field.add || 'Add'));
        sortable(rows, '.ed-entry', 'entry', (order) => {
            const reordered = order.map((i) => entries[i]);
            entries.splice(0, entries.length, ...reordered);
            rebuild();
            onChange();
        });
        box.append(rows, add);
        rebuild();
        return box;
    }

    // ---------- page texts ----------

    function renderTextGroup(group) {
        main.append(header(group.label, 'Single texts on the page. Edited texts are marked; “Use original” brings back the text from the code.'));
        const panel = el('div', { class: 'ed-texts' });
        group.fields.forEach((field) => {
            const defaults = textDefaults[field.key];
            const value = () => doc.texts[field.key] || defaults;
            const badge = el('span', { class: 'ed-badge is-changed', text: 'Edited' });
            const reset = el('button', { type: 'button', class: 'ed-link', text: 'Use original' });
            const row = el('div', { class: 'ed-text-row' });
            const refresh = () => {
                const edited = Boolean(doc.texts[field.key]);
                badge.hidden = !edited;
                reset.hidden = !edited;
            };
            const update = (lang, text) => {
                const next = field.shared ? { en: text, pl: text } : { ...value(), [lang]: text };
                if (canon(next) === canon(defaults)) delete doc.texts[field.key]; else doc.texts[field.key] = next;
                refresh();
                changed();
            };
            const id = 't-' + field.key.replace(/[^a-z0-9]/gi, '-');
            let editor;
            if (field.shared) {
                const input = el('input', { id, type: 'url', value: value().en, spellcheck: 'false', oninput: () => update('en', input.value) });
                editor = el('div', { class: 'ed-single' }, input);
            } else {
                editor = pairEditor(id, value(), field.multiline, update, defaults);
            }
            reset.addEventListener('click', () => {
                delete doc.texts[field.key];
                renderMain();
                changed();
            });
            row.append(el('div', { class: 'ed-text-meta' }, el('label', { class: 'ed-label', for: id, text: field.label }), badge, reset), editor);
            panel.append(row);
            refresh();
        });
        main.append(panel);
    }

    // ---------- drag and drop ----------

    function sortable(container, selector, kind, onDrop) {
        container.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest(`[data-drag="${kind}"]`);
            if (!handle || event.button !== 0) return;
            const item = handle.closest(selector);
            if (!item || item.parentElement !== container) return;
            event.preventDefault();
            closeMenus();
            const startY = event.clientY;
            const grab = startY - item.getBoundingClientRect().top;
            // Bodies of other cards fold while sorting; keep the grabbed item under the pointer.
            container.classList.add('is-sorting');
            window.scrollBy(0, item.getBoundingClientRect().top - (startY - grab));
            item.classList.add('is-dragging');
            const pointerId = event.pointerId;
            let pointerY = startY;
            let moved = false;
            let frame = 0;

            const items = () => [...container.querySelectorAll(':scope > ' + selector)];
            const reorder = () => {
                const others = items().filter((s) => s !== item);
                const before = others.find((s) => { const r = s.getBoundingClientRect(); return pointerY < r.top + r.height / 2; }) || null;
                const target = before ? before : null;
                if ((target && item.nextElementSibling === target) || (!target && others.length && others[others.length - 1].nextElementSibling === item)) return;
                const tops = new Map(others.map((s) => [s, s.getBoundingClientRect().top]));
                if (target) container.insertBefore(item, target); else others[others.length - 1].after(item);
                others.forEach((s) => {
                    const delta = tops.get(s) - s.getBoundingClientRect().top;
                    if (delta) s.animate([{ transform: `translateY(${delta}px)` }, { transform: 'none' }], { duration: 160, easing: 'ease-out' });
                });
            };
            const position = () => {
                item.style.transform = '';
                const natural = item.getBoundingClientRect().top;
                item.style.transform = `translateY(${pointerY - grab - natural}px)`;
            };
            const tick = () => {
                const edge = 90;
                let speed = 0;
                if (pointerY < edge) speed = -Math.ceil((edge - pointerY) / 5);
                else if (pointerY > window.innerHeight - edge) speed = Math.ceil((pointerY - (window.innerHeight - edge)) / 5);
                if (speed) { window.scrollBy(0, speed); reorder(); position(); }
                frame = requestAnimationFrame(tick);
            };
            frame = requestAnimationFrame(tick);
            const onMove = (e) => {
                if (e.pointerId !== pointerId) return;
                pointerY = e.clientY;
                if (Math.abs(pointerY - startY) > 3) moved = true;
                reorder();
                position();
            };
            // Listen on the window: moving the item in the page would drop pointer capture on the handle.
            const finish = (e) => {
                if (e.pointerId !== pointerId) return;
                cancelAnimationFrame(frame);
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', finish);
                window.removeEventListener('pointercancel', finish);
                item.classList.remove('is-dragging');
                item.style.transform = '';
                container.classList.remove('is-sorting');
                const order = items().map((s) => Number(s.dataset.index));
                if (moved && order.some((value, index) => value !== index)) onDrop(order);
            };
            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', finish);
            window.addEventListener('pointercancel', finish);
        });
    }

    // ---------- saving ----------

    async function send(action) {
        if (busy) return false;
        busy = true;
        refreshStatus();
        try {
            const response = await fetch(boot.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Csrf-Token': boot.csrf },
                body: JSON.stringify({ action, revision, document: doc }),
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                if (data.conflict) conflict = true;
                toast(data.error || 'Saving failed. Your changes are still here; try again.', { kind: 'error' });
                return false;
            }
            const keepView = current;
            load(data.state);
            current = keepView;
            renderNav();
            renderMain();
            return true;
        } catch (error) {
            toast('No connection. Your changes are still here; try again.', { kind: 'error' });
            return false;
        } finally {
            busy = false;
            refreshStatus();
        }
    }

    $('ed-save').addEventListener('click', async () => {
        if (await send('draft')) toast('Draft saved. Visitors still see the published version.');
    });
    $('ed-publish').addEventListener('click', async () => {
        if (await send('publish')) toast('Published. Visitors now see your changes.', { kind: 'ok' });
    });
    $('ed-discard').addEventListener('click', async () => {
        if (!window.confirm('Discard all draft changes and go back to the published version?')) return;
        if (await send('discard')) toast('Draft discarded.');
    });

    const previewMenu = $('ed-preview-menu');
    const previewToggle = $('ed-preview-toggle');
    boot.preview.forEach((target) => {
        previewMenu.append(el('button', {
            type: 'button', role: 'menuitem', text: target.label,
            onclick: async () => {
                closeMenus();
                const tab = window.open('about:blank', '_blank');
                if (isDirty() && !(await send('draft'))) { tab?.close(); return; }
                if (tab) tab.location.href = target.url; else window.location.href = target.url;
            },
        }));
    });
    previewToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = previewMenu.hidden;
        closeMenus();
        previewMenu.hidden = !willOpen;
        previewToggle.setAttribute('aria-expanded', String(willOpen));
    });

    document.addEventListener('click', (event) => { if (!event.target.closest('.ed-menu')) closeMenus(); });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenus();
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (!$('ed-save').disabled) $('ed-save').click();
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (isDirty()) { event.preventDefault(); event.returnValue = ''; }
    });
    const measureTop = () => document.documentElement.style.setProperty('--top-height', document.querySelector('.ed-top').offsetHeight + 'px');
    window.addEventListener('resize', () => main.querySelectorAll('textarea').forEach(autosize));
    if ('ResizeObserver' in window) new ResizeObserver(measureTop).observe(document.querySelector('.ed-top'));
    measureTop();

    load(boot.state);
    renderNav();
    renderMain();
    refreshStatus();
    revealCurrentLink();
})();
