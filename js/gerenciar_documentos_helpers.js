// Helpers for gerenciar_documentos page (extracted from inline script)
(function () {
    // debounce helper
    function debounce(fn, wait) {
        var t;
        return function () { var ctx = this, args = arguments; clearTimeout(t); t = setTimeout(function(){ fn.apply(ctx, args); }, wait); };
    }

    // show toast using bootstrap Toast
    function showToast(message, type) {
        type = type || 'primary';
        var container = document.getElementById('docToastContainer');
        if (!container) return;
        var toastId = 'doc-toast-' + Date.now();
        var toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center text-bg-' + type + ' border-0';
        toastEl.role = 'alert';
        toastEl.ariaLive = 'assertive';
        toastEl.ariaAtomic = 'true';
        toastEl.id = toastId;
        toastEl.innerHTML = '<div class="d-flex"><div class="toast-body">' + (message || '') + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        container.appendChild(toastEl);
        try {
            var bt = new bootstrap.Toast(toastEl, { delay: 5000 });
            bt.show();
            toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove(); });
        } catch (e) {
            setTimeout(function () { toastEl.remove(); }, 5000);
        }
    }

    // Attach AJAX autocomplete to a search input (adds items to nearest .pasta-user-list or #user_checklist)
    function attachAjaxUserAutocomplete(inputEl) {
        if (!inputEl) return;
        var dropdown = null;
        inputEl.addEventListener('input', debounce(function () {
            var q = this.value.trim();
            if (q.length < 2) { if (dropdown) dropdown.remove(); return; }
            var base = (window.DOC_SEARCH_USERS_URL || '');
            var url = base + '?q=' + encodeURIComponent(q) + '&limit=10';
            fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (json) {
                if (!json.ok) return;
                if (dropdown) dropdown.remove();
                dropdown = document.createElement('div');
                dropdown.className = 'border bg-white position-absolute p-1';
                dropdown.style.zIndex = 9999;
                dropdown.style.maxHeight = '220px';
                dropdown.style.overflow = 'auto';
                json.results.forEach(function (u) {
                    var item = document.createElement('div');
                    item.className = 'px-2 py-1 ajax-user-item';
                    item.style.cursor = 'pointer';
                    item.textContent = u.nome + (u.cliente_nome ? ' — ' + u.cliente_nome : '');
                    item.setAttribute('data-id', u.id);
                    item.addEventListener('click', function () {
                        var container = inputEl.parentElement.querySelector('.pasta-user-list') || document.getElementById('user_checklist');
                        if (!container) return;
                        var existing = container.querySelector('input[value="' + u.id + '"]');
                        if (existing) {
                            existing.checked = true;
                        } else {
                            var div = document.createElement('div');
                            div.className = 'form-check pasta-user-item';
                            div.setAttribute('data-name', (u.nome + ' ' + (u.cliente_nome || '')).toLowerCase());
                            var input = document.createElement('input');
                            input.className = 'form-check-input';
                            input.type = 'checkbox';
                            input.name = 'user_ids[]';
                            var containerId = container.id || '';
                            var chkId = (containerId === 'user_checklist' ? 'user_check_' + u.id : 'pasta_user_' + u.id + '_' + Math.floor(Math.random()*100000));
                            input.id = chkId;
                            input.value = u.id;
                            input.checked = true;
                            var label = document.createElement('label');
                            label.className = 'form-check-label';
                            label.htmlFor = chkId;
                            label.innerHTML = u.nome + (u.cliente_nome ? ' <small class="text-muted">— ' + u.cliente_nome + '</small>' : '');
                            div.appendChild(input);
                            div.appendChild(label);
                            container.appendChild(div);
                        }
                        if (dropdown) dropdown.remove();
                        inputEl.value = '';
                    });
                    dropdown.appendChild(item);
                });
                inputEl.parentElement.appendChild(dropdown);
                dropdown.style.left = inputEl.offsetLeft + 'px';
                dropdown.style.top = (inputEl.offsetTop + inputEl.offsetHeight) + 'px';
                dropdown.style.minWidth = inputEl.offsetWidth + 'px';
            }).catch(function(e){ console.error('autocomplete err', e); });
        }, 250));
        document.addEventListener('click', function (ev) { if (dropdown && !inputEl.contains(ev.target)) { if (dropdown.parentElement) dropdown.parentElement.removeChild(dropdown); dropdown = null; } });
    }

    // Insert pasta row helper
    function insertPastaRow(pasta, isRoot) {
        try {
            var tbody = document.getElementById(isRoot ? 'pastas_table_body' : 'subpastas_table_body');
            if (!tbody) return;
            var tr = document.createElement('tr');
            var tdNome = document.createElement('td'); tdNome.textContent = pasta.nome || '';
            var tdPai = document.createElement('td'); tdPai.textContent = pasta.parent_nome || '-';
            var tdAcoes = document.createElement('td');
            var assocCountDiv = document.createElement('div'); assocCountDiv.style.display = 'inline-block'; assocCountDiv.style.verticalAlign = 'middle'; assocCountDiv.style.width = '220px';
            var small = document.createElement('div'); small.className = 'small text-muted'; small.textContent = 'Associados: ' + (pasta.ass_count || 0); assocCountDiv.appendChild(small);
            tdAcoes.appendChild(assocCountDiv);
            var btn = document.createElement('button'); btn.className = 'btn btn-sm btn-primary ms-1 pasta-actions-btn'; btn.setAttribute('type','button');
            btn.setAttribute('data-pasta-id', pasta.id); btn.setAttribute('data-pasta-nome', pasta.nome); btn.setAttribute('data-pasta-owner', pasta.owner_user_id || 0); btn.setAttribute('data-pasta-ass', (pasta.ass_ids || []).join(',')); btn.textContent = 'Ações';
            tdAcoes.appendChild(btn);

            if (isRoot) {
                tr.appendChild(tdNome);
                var tdAssoc = document.createElement('td'); tdAssoc.appendChild(assocCountDiv.cloneNode(true)); tr.appendChild(tdAssoc);
                tr.appendChild(tdAcoes);
                tbody.insertBefore(tr, tbody.firstChild);
            } else {
                tr.appendChild(tdNome); tr.appendChild(tdPai); tr.appendChild(tdAcoes);
                tbody.insertBefore(tr, tbody.firstChild);
            }

            var selects = document.querySelectorAll('select[id^="parent_id"], select#edit_pasta_parent');
            selects.forEach(function(s){ var opt = document.createElement('option'); opt.value = pasta.id; opt.textContent = pasta.nome; s.appendChild(opt); });
            window.DOC_PASTAS = window.DOC_PASTAS || [];
            window.DOC_PASTAS.push({ id: parseInt(pasta.id), nome: pasta.nome, parent_id: pasta.parent_id });
        } catch (e) { console.error('insertPastaRow error', e); }
    }

    function updatePastaCounts() {
        var roots = document.querySelectorAll('#pastas_table_body button[data-pasta-id]').length;
        var subs = document.querySelectorAll('#subpastas_table_body button[data-pasta-id]').length;
        var rootBadge = document.querySelector('#pastas_table').closest('.card').querySelector('.card-header .badge');
        var subBadge = document.querySelector('#subpastas_table').closest('.card').querySelector('.card-header .badge');
        if (rootBadge) rootBadge.textContent = roots;
        if (subBadge) subBadge.textContent = subs;
    }

    // expose helpers to global scope used by inline code
    window.gd = window.gd || {};
    window.gd.attachAjaxUserAutocomplete = attachAjaxUserAutocomplete;
    window.gd.showToast = showToast;
    window.gd.insertPastaRow = insertPastaRow;
    window.gd.updatePastaCounts = updatePastaCounts;
})();
