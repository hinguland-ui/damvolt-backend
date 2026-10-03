// Damvolt Admin — shared behaviour
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    // ---------- Mobile sidebar ----------
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');
    document.querySelectorAll('[data-toggle-sidebar]').forEach((btn) =>
        btn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        })
    );
    overlay && overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });

    // ---------- Toast ----------
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
    window.toast = (type, title) => Toast.fire({ icon: type, title });
    const flash = document.getElementById('flash');
    if (flash) window.toast(flash.dataset.type, flash.dataset.message);

    // Forms with <input name="_return"> come back to the exact page + tab they were sent from.
    const stampReturn = (form) => {
        const r = form.querySelector('input[name="_return"]');
        if (r) r.value = location.origin + location.pathname + location.search + location.hash;
    };
    document.addEventListener('submit', (e) => e.target.querySelector && stampReturn(e.target), true);

    // ---------- Delete confirmation: <form class="js-delete"> (works for dynamically added forms too) ----------
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form.js-delete');
        if (!form) return;
        e.preventDefault();
        Swal.fire({
            title: form.dataset.title || 'Are you sure?', text: 'This action cannot be undone.', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Yes, delete',
        }).then((r) => {
            if (!r.isConfirmed) return;
            stampReturn(form);
            form.submit();
        });
    });

    // ---------- Widgets ----------
    function initWidgets(root) {
        root = root || document;

        root.querySelectorAll('select.js-select:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            new TomSelect(el, { allowEmptyOption: true, create: false, dropdownParent: 'body' });
        });

        root.querySelectorAll('select.js-tags:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            new TomSelect(el, { create: true, persist: false, createOnBlur: true, dropdownParent: 'body', plugins: ['remove_button'], placeholder: el.dataset.placeholder || 'Type and press Enter' });
        });

        root.querySelectorAll('.js-date:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            flatpickr(el, { dateFormat: 'Y-m-d' });
        });

        // Drag & drop re-ordering of rows inside a repeater
        root.querySelectorAll('.rep-rows:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            Sortable.create(el, { handle: '.drag-handle', animation: 180, ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen', forceFallback: false });
        });

        // Drag & drop re-ordering of saved records (saved instantly via AJAX)
        root.querySelectorAll('.js-sort-list:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            Sortable.create(el, {
                handle: '.drag-handle', animation: 180, ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen',
                onEnd: () => {
                    const ids = [...el.querySelectorAll(':scope > [data-id]')].map((n) => n.dataset.id);
                    el.querySelectorAll(':scope > [data-id] .pos').forEach((p, i) => (p.textContent = i + 1));
                    fetch(el.dataset.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body: JSON.stringify({ ids }),
                    }).then((r) => (r.ok ? window.toast('success', 'Order saved') : window.toast('error', 'Could not save order')));
                },
            });
        });

        // Character counters (SEO fields)
        root.querySelectorAll('[data-counter]:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            const out = el.closest('.field')?.querySelector('.char-count');
            const max = +el.dataset.counter;
            const upd = () => {
                if (!out) return;
                out.textContent = `${el.value.length} / ${max}`;
                out.classList.toggle('over', el.value.length > max);
            };
            el.addEventListener('input', upd);
            upd();
        });

        // Slug generator (slugify): <input data-slug-source="#title">
        root.querySelectorAll('[data-slug-source]:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            const src = document.querySelector(el.dataset.slugSource);
            if (!src) return;
            let manual = !!el.value;
            el.addEventListener('input', () => (manual = !!el.value));
            src.addEventListener('input', () => {
                if (!manual) el.value = slugify(src.value, { lower: true, strict: true });
            });
        });

        // Rich text (Quill): <div class="js-quill" data-input="#content">
        root.querySelectorAll('.js-quill:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            const input = document.querySelector(el.dataset.input);
            const q = new Quill(el, {
                theme: 'snow',
                modules: { toolbar: [[{ header: [2, 3, 4, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']] },
            });
            if (input.value) q.clipboard.dangerouslyPasteHTML(input.value);
            input.form.addEventListener('submit', () => (input.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML));
        });

        // Plain Quill demo editor
        root.querySelectorAll('.js-editor:not([data-init])').forEach((el) => {
            el.dataset.init = 1;
            new Quill(el, { theme: 'snow' });
        });
    }
    // Google search-result preview (.serp): follows the meta title / description / slug inputs in the same form
    function initSerp(root) {
        (root || document).querySelectorAll('.serp:not([data-init])').forEach((box) => {
            box.dataset.init = 1;
            const form = box.closest('form');
            const pick = (sel) => (sel ? form.querySelector(sel) : null);
            const d = box.dataset;
            const titleIn = pick(d.titleInput), descIn = pick(d.descInput);
            const fTitleIn = pick(d.fallbackTitleInput), fDescIn = pick(d.fallbackDescInput), slugIn = pick(d.slugInput);
            const cut = (text, n) => (text.length > n ? text.slice(0, n - 1).trimEnd() + '…' : text);
            const $ = (c) => box.querySelector(c);

            const render = () => {
                const own = titleIn ? titleIn.value.trim() : '';
                const fallbackTitle = fTitleIn && fTitleIn.value.trim() ? fTitleIn.value.trim() + ' | ' + d.siteName : d.fallbackTitle ? (d.path && d.path !== '/' ? d.fallbackTitle + ' | ' + d.siteName : d.fallbackTitle) : d.siteName;
                $('.serp-title').textContent = cut(own || fallbackTitle, 60);

                const ownDesc = descIn ? descIn.value.trim() : '';
                const desc = ownDesc || (fDescIn ? fDescIn.value.trim() : '') || d.fallbackDesc;
                const descEl = $('.serp-desc');
                descEl.textContent = desc ? cut(desc, 160) : 'No description yet — Google will pick text from the page. Add a meta description (120–160 characters) to control this.';
                descEl.classList.toggle('empty', !desc);

                let path = d.path || '';
                if (slugIn) {
                    const slug = slugIn.value.trim();
                    path = d.prefix + (slug || '…');
                }
                $('.serp-name').textContent = d.siteName;
                $('.serp-url').textContent = d.siteHost + (path && path !== '/' ? ' › ' + path.replace(/^\//, '').split('/').join(' › ') : '');
            };

            [titleIn, descIn, fTitleIn, fDescIn, slugIn].filter(Boolean).forEach((el) => el.addEventListener('input', render));
            render();
        });
    }

    window.initWidgets = initWidgets;
    initWidgets();
    initSerp();

    // DataTables: <table class="js-datatable">
    if (window.jQuery && $.fn.DataTable) {
        $('.js-datatable').DataTable({ pageLength: 10, lengthChange: false, language: { search: '', searchPlaceholder: 'Search...' } });
    }

    // ---------- Image preview ----------
    document.addEventListener('change', (e) => {
        const input = e.target.closest('input.js-img-input');
        if (!input) return;
        const nameEl = input.closest('.img-field').querySelector('.file-name');
        if (nameEl) nameEl.textContent = input.files[0] ? input.files[0].name : 'No file chosen';
        const box = input.closest('.img-field').querySelector('.preview');
        const file = input.files[0];
        if (!file) return;
        box.innerHTML = '';
        const img = new Image();
        img.src = URL.createObjectURL(file);
        box.appendChild(img);
        const remove = input.closest('.img-field').querySelector('input[name^="remove_"]');
        if (remove) remove.checked = false;
    });

    // ---------- Repeaters ----------
    document.addEventListener('click', (e) => {
        const add = e.target.closest('.js-rep-add');
        if (add) {
            const rep = add.closest('.js-repeater');
            const rows = rep.querySelector('.rep-rows');
            const max = +rep.dataset.max || 999;
            if (rows.children.length >= max) return window.toast('info', `Maximum ${max} items`);
            const idx = +rep.dataset.next++;
            const holder = document.createElement('div');
            holder.innerHTML = rep.querySelector('template').innerHTML.replaceAll('__IDX__', idx).trim();
            const row = holder.firstElementChild;
            rows.appendChild(row);
            initWidgets(row);
            row.querySelector('input,textarea')?.focus();
            return;
        }
        const rm = e.target.closest('.js-rep-remove');
        if (rm) rm.closest('.rep-row').remove();
    });

    // ---------- Tabs remember their place in the URL hash (#tab-slides) ----------
    const tabButtons = document.querySelectorAll('[data-tab-key]');
    if (tabButtons.length) {
        const show = () => {
            const key = location.hash.replace('#tab-', '');
            const btn = [...tabButtons].find((b) => b.dataset.tabKey === key);
            if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
        };
        tabButtons.forEach((b) => b.addEventListener('shown.bs.tab', () => history.replaceState(null, '', '#tab-' + b.dataset.tabKey)));
        window.addEventListener('hashchange', show);
        show();
    }

    // ---------- Clear cache (profile dropdown) ----------
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-clear-cache');
        if (!btn) return;
        const icon = btn.querySelector('i');
        btn.disabled = true;
        icon.classList.add('spin');
        try {
            const res = await fetch(btn.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            const json = await res.json().catch(() => ({}));
            window.toast(res.ok && json.ok ? 'success' : 'error', json.message || 'Could not clear the cache.');
        } catch (err) {
            window.toast('error', 'Could not clear the cache.');
        } finally {
            btn.disabled = false;
            icon.classList.remove('spin');
        }
    });

    // Sidebar: highlight the sub-menu link that matches the current tab (#tab-slides)
    const hashLinks = document.querySelectorAll('.sidebar [data-hash]');
    const markHash = () => hashLinks.forEach((a) => a.classList.toggle('active', a.getAttribute('data-hash') === location.hash && a.pathname === location.pathname));
    window.addEventListener('hashchange', markHash);
    markHash();

    // ---------- Modal managers (slides, industries, FAQs …) ----------
    document.querySelectorAll('.js-crud').forEach((wrap) => {
        const modalEl = wrap.querySelector('.modal');
        document.body.appendChild(modalEl); // out of the tab pane, so it always sits above the backdrop
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const form = modalEl.querySelector('form');
        const method = form.querySelector('input[name="_method"]');
        const title = modalEl.querySelector('.modal-title');
        const noun = wrap.dataset.noun;

        const setReturn = () => (form.querySelector('input[name="_return"]').value = location.origin + location.pathname + location.search + location.hash);

        const reset = () => {
            form.reset();
            form.querySelectorAll('select').forEach((s) => s.tomselect && s.tomselect.setValue(s.options[0]?.value ?? '', true));
            form.querySelectorAll('.img-field .preview').forEach((p) => (p.innerHTML = '<i class="bi bi-image"></i>'));
            form.querySelectorAll('.is-invalid').forEach((i) => i.classList.remove('is-invalid'));
        };

        wrap.addEventListener('click', (e) => {
            const addBtn = e.target.closest('.js-crud-add');
            const editBtn = e.target.closest('.js-crud-edit');
            if (!addBtn && !editBtn) return;

            reset();
            setReturn();

            if (addBtn) {
                form.action = wrap.dataset.store;
                method.disabled = true;
                title.textContent = `Add ${noun}`;
                const sw = form.querySelector('input[type=checkbox][name=is_active]');
                if (sw) sw.checked = true;
                const firstSel = form.querySelector('select[name=rating]');
                if (firstSel && firstSel.tomselect) firstSel.tomselect.setValue('5', true);
            } else {
                const item = JSON.parse(editBtn.dataset.item);
                form.action = editBtn.dataset.url;
                method.disabled = false;
                title.textContent = `Edit ${noun}`;
                Object.entries(item).forEach(([k, v]) => {
                    const el = form.elements[k];
                    if (!el || el.type === 'file') return;
                    if (el.type === 'checkbox') el.checked = !!v;
                    else if (el.tomselect) el.tomselect.setValue(String(v ?? ''), true);
                    else el.value = v ?? '';
                });
                form.querySelectorAll('.img-field').forEach((f) => {
                    const input = f.querySelector('input[type=file]');
                    const url = item[input.name + '_url'];
                    const box = f.querySelector('.preview');
                    box.innerHTML = '<i class="bi bi-image"></i>';
                    if (url) {
                        const img = new Image();
                        img.alt = '';
                        img.src = url;
                        box.replaceChildren(img);
                    }
                });
            }
            modal.show();
        });
    });

    // ---------- Shared chart defaults (brand blue palette) ----------
    window.chartBase = {
        chart: { fontFamily: 'inherit', toolbar: { show: false } },
        grid: { borderColor: '#e5e5ea', strokeDashArray: 3 },
        dataLabels: { enabled: false },
    };
    window.chartColors = ['#2078fe', '#4dc9ff', '#121014', '#7c8aa5', '#c5d6f5'];
})();
