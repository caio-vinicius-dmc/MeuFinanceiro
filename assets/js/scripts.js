// assets/js/scripts.js

document.addEventListener("DOMContentLoaded", function() {
    
    // --- ATIVAÇÃO DOS TOOLTIPS DO BOOTSTRAP (Dashboard) ---
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    // -------------------------------------------------------------------------

    // --- LÓGICA DE EXPORTAÇÃO (NOVO) ---
    const btnExportar = document.getElementById('btn-exportar');
    const formFiltros = document.getElementById('form-filtros');

    if (btnExportar && formFiltros) {
        btnExportar.addEventListener('click', function(e) {
            e.preventDefault(); // Impede o comportamento padrão
            
            // 1. Obtém todos os parâmetros atuais do formulário de filtros
            const formData = new URLSearchParams(new FormData(formFiltros));

            // 2. Remove o parâmetro "page=lancamentos"
            formData.delete('page');

            // 3. Redireciona para o script de exportação com os parâmetros de filtro
            window.location.href = 'process/export_lancamentos.php?' + formData.toString();
        });
    }
    // -------------------------------------------------------------------------

    // --- Atualiza select de empresas quando o Cliente muda (Admin/Contador) ---
    const clienteSelect = document.getElementById('cliente_id');
    if (clienteSelect) {
        clienteSelect.addEventListener('change', function() {
            const clienteId = this.value;
            // Faz a requisição
            fetch('process/get_empresas_por_cliente.php?cliente_id=' + encodeURIComponent(clienteId), {credentials: 'same-origin'})
                .then(resp => resp.json())
                .then(json => {
                    if (!json.success) {
                        console.warn('Não foi possível buscar empresas:', json.message);
                        return;
                    }
                    // Preenche todos os selects de empresa visíveis na página
                    const selects = document.querySelectorAll('select[name="id_empresa"]');
                    selects.forEach(function(sel) {
                        // Limpa opções
                        while (sel.firstChild) sel.removeChild(sel.firstChild);
                        // Adiciona opção padrão
                        const optAll = document.createElement('option');
                        optAll.value = '';
                        optAll.textContent = (sel.dataset.allowAll === '0') ? 'Selecione...' : 'Todas as Empresas';
                        sel.appendChild(optAll);
                        // Adiciona opções retornadas
                        json.data.forEach(function(emp) {
                            const opt = document.createElement('option');
                            opt.value = emp.id;
                            opt.textContent = emp.razao_social;
                            sel.appendChild(opt);
                        });
                    });
                }).catch(err => console.error('Erro ao buscar empresas:', err));
        });
    }
    
    // --- Lógica do Sidebar Recolhível (Existente) ---
    const toggleButton = document.getElementById('desktop-sidebar-toggle');
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
    }

    // --- Lógica para expandir sidebar ao clicar no dropdown (NOVO) ---
    const userDropdown = document.getElementById('navbarDropdown');
    if (userDropdown) {
        userDropdown.addEventListener('click', function() {
            // Se a sidebar estiver recolhida, expande ela
            if (document.body.classList.contains('sidebar-collapsed')) {
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        });
    }

    // --- Campos Condicionais (Novo Usuário) ---
    const tipoUsuarioSelect = document.getElementById('tipo_usuario');
    if (tipoUsuarioSelect) {
        tipoUsuarioSelect.addEventListener('change', function() {
             toggleUsuarioCampos('');
        });
    }
    
    // --- Campos Condicionais (Editar Usuário) ---
    const editTipoUsuarioSelect = document.getElementById('edit_tipo_usuario');
    if (editTipoUsuarioSelect) {
         editTipoUsuarioSelect.addEventListener('change', function() {
             toggleUsuarioCampos('edit_');
         });
    }

    // --- Inicializador do Gráfico (Dashboard - Fluxo de Caixa) ---
    const chartCanvas = document.getElementById('fluxoCaixaChart');
    if (chartCanvas) {
        initFluxoCaixaChart(chartCanvas);
    }
    
    // --- NOVO Inicializador do Gráfico (Dashboard - Status) ---
    const statusChartCanvas = document.getElementById('statusChart');
    if (statusChartCanvas) {
        initStatusChart(statusChartCanvas);
    }

    // --- Inicializadores dos Gráficos de Cobranças ---
    const cobStatusCanvas = document.getElementById('cobStatusChart');
    if (cobStatusCanvas) initCobStatusChart(cobStatusCanvas);

    const cobTipoCanvas = document.getElementById('cobTipoChart');
    if (cobTipoCanvas) initCobTipoChart(cobTipoCanvas);

    // --- Dashboard section collapse toggles (Lançamentos & Cobranças) ---
    (function() {
        const toggles = Array.from(document.querySelectorAll('.section-toggle'));

        function saveState(sectionId, collapsed) {
            try { localStorage.setItem('dashboard_section_' + sectionId, collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
        }

        function loadState(sectionId) {
            try { return localStorage.getItem('dashboard_section_' + sectionId); } catch (e) { return null; }
        }

        // Map to keep track of sibling nodes hidden when collapsing a section so we can restore them
        const hiddenSiblingsMap = new Map();

        function collectFollowingSiblings(section) {
            const siblings = [];
            let cur = section.nextElementSibling;
            while (cur) {
                if (cur.classList && cur.classList.contains('dashboard-section')) break;
                siblings.push(cur);
                cur = cur.nextElementSibling;
            }
            return siblings;
        }

        function collapseSection(section, btn) {
            const body = section.querySelector('.section-body');
            if (body) {
                // set explicit height then animate to 0
                body.style.height = body.scrollHeight + 'px';
                // force reflow
                body.getBoundingClientRect();
                body.style.transition = 'height 240ms ease';
                body.style.height = '0px';
            }

            // hide any following nodes that are logically part of this topic
            const following = collectFollowingSiblings(section);
            const saved = [];
            following.forEach(function(node) {
                saved.push({ node: node, display: node.style.display || '' });
                node.style.display = 'none';
            });
            if (saved.length) hiddenSiblingsMap.set(section, saved);

            btn.setAttribute('aria-expanded', 'false');
            section.classList.add('collapsed');
            // persist
            if (section.id) saveState(section.id, true);
        }

        function expandSection(section, btn) {
            const body = section.querySelector('.section-body');
            // restore any hidden following siblings
            const saved = hiddenSiblingsMap.get(section);
            if (saved) {
                saved.forEach(function(item) {
                    try { item.node.style.display = item.display || ''; } catch (e) {}
                });
                hiddenSiblingsMap.delete(section);
            }

            if (body) {
                // remove collapsed so natural height is available
                section.classList.remove('collapsed');
                // start from 0 then animate to scrollHeight
                body.style.height = '0px';
                // force reflow
                body.getBoundingClientRect();
                body.style.transition = 'height 240ms ease';
                body.style.height = body.scrollHeight + 'px';
                btn.setAttribute('aria-expanded', 'true');
                // after transition, clear height to allow responsive behavior
                const handler = function() {
                    body.style.height = '';
                    body.removeEventListener('transitionend', handler);
                };
                body.addEventListener('transitionend', handler);
            } else {
                // if no body, just toggle class and aria
                section.classList.remove('collapsed');
                btn.setAttribute('aria-expanded', 'true');
            }

            // persist
            if (section.id) saveState(section.id, false);
        }

        toggles.forEach(function(btn) {
            const sectionId = btn.getAttribute('aria-controls');
            const section = sectionId ? document.getElementById(sectionId) : btn.closest('.dashboard-section');
            if (!section) return;
            // initialize state from storage
            // Default behavior: sections start COLLAPSED unless user previously expanded them
            let state = section.id ? loadState(section.id) : null;
            // Force: o tópico de COBRANÇAS deve vir ocultado por padrão, a menos que o usuário
            // explicitamente tenha salvo o estado 'expanded'. Isso garante que seus subtópicos
            // (nós irmãos que pertencem ao tópico) também sejam escondidos na inicialização.
            if (section.id === 'dashboard-cobrancas-section' && state !== 'expanded') {
                try { localStorage.setItem('dashboard_section_' + section.id, 'collapsed'); } catch (e) {}
                state = 'collapsed';
            }

            if (state === 'expanded') {
                // usuário já tinha expandido antes
                expandSection(section, btn);
            } else {
                // default collapsed (covers state === 'collapsed' or null)
                // utiliza a função collapseSection para aplicar a altura e esconder nós irmãos
                collapseSection(section, btn);
            }

            btn.addEventListener('click', function() {
                const isExpanded = btn.getAttribute('aria-expanded') === 'true';
                if (isExpanded) collapseSection(section, btn);
                else expandSection(section, btn);
            });
        });
    })();

    // --- Lógica do Modal de Edição (Clientes - Existente) ---
    const modalEditarCliente = document.getElementById('modalEditarCliente');
    if (modalEditarCliente) {
        modalEditarCliente.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nome = button.getAttribute('data-nome');
            const email = button.getAttribute('data-email');
            const telefone = button.getAttribute('data-telefone');
            
            const modalForm = modalEditarCliente.querySelector('form');
            modalForm.querySelector('#edit_id_cliente').value = id;
            modalForm.querySelector('#edit_nome_responsavel').value = nome;
            modalForm.querySelector('#edit_email_contato').value = email;
            modalForm.querySelector('#edit_telefone').value = telefone;
        });
    }

    // --- Lógica do Modal de Edição (Empresas - Existente) ---
    const modalEditarEmpresa = document.getElementById('modalEditarEmpresa');
    if (modalEditarEmpresa) {
        modalEditarEmpresa.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const id_cliente = button.getAttribute('data-id_cliente');
            const cnpj = button.getAttribute('data-cnpj');
            const razao_social = button.getAttribute('data-razao_social');
            const nome_fantasia = button.getAttribute('data-nome_fantasia');
            const data_abertura = button.getAttribute('data-data_abertura');
            
            const modalForm = modalEditarEmpresa.querySelector('form');
            modalForm.querySelector('#edit_id_empresa').value = id;
            modalForm.querySelector('#edit_id_cliente').value = id_cliente; 
            modalForm.querySelector('#edit_cnpj').value = cnpj;
            modalForm.querySelector('#edit_razao_social').value = razao_social;
            modalForm.querySelector('#edit_nome_fantasia').value = nome_fantasia;
            modalForm.querySelector('#edit_data_abertura').value = data_abertura;
            // Se o CNPJ já estiver completo, tenta popular automaticamente
            try {
                const digits = (cnpj || '').toString().replace(/\D/g, '');
                if (digits.length === 14) {
                    buscarCnpj('edit_');
                }
            } catch (e) {
                console.error('Erro ao auto-buscar CNPJ no modal de edição:', e);
            }
        });
    }
    
    // --- Lógica do Modal de Edição (Usuários - Existente) ---
    const modalEditarUsuario = document.getElementById('modalEditarUsuario');
    if (modalEditarUsuario) {
         modalEditarUsuario.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nome = button.getAttribute('data-nome');
            const email = button.getAttribute('data-email');
            const telefone = button.getAttribute('data-telefone');
            const tipo = button.getAttribute('data-tipo');
            const id_cliente_associado = button.getAttribute('data-id_cliente_associado');
            const assoc_clientes_json = button.getAttribute('data-assoc_clientes');
            const assoc_clientes_ids = JSON.parse(assoc_clientes_json);
            
            const modalForm = modalEditarUsuario.querySelector('form');
            modalForm.querySelector('#edit_id_usuario').value = id;
            modalForm.querySelector('#edit_nome').value = nome;
            modalForm.querySelector('#edit_email').value = email;
            modalForm.querySelector('#edit_telefone').value = telefone;
            modalForm.querySelector('#edit_tipo_usuario').value = tipo;
            modalForm.querySelector('#nova_senha').value = '';
            toggleUsuarioCampos('edit_');
            modalForm.querySelector('#edit_id_cliente_associado').value = id_cliente_associado;
            
            const multiSelect = modalForm.querySelector('#edit_id_clientes_associados');
            Array.from(multiSelect.options).forEach(option => {
                option.selected = false;
                if (assoc_clientes_ids.map(String).includes(option.value)) {
                    option.selected = true;
                }
            });
         });
    }
    
    // --- Lógica do Modal de Edição (Lançamentos - Existente) ---
    const modalEditarLancamento = document.getElementById('modalEditarLancamento');
    if (modalEditarLancamento) {
         modalEditarLancamento.addEventListener('show.bs.modal', function (event) {
            // Acessa os atributos diretamente de event.relatedTarget
            const id = event.relatedTarget.getAttribute('data-id');
            const id_empresa = event.relatedTarget.getAttribute('data-id_empresa');
            const descricao = event.relatedTarget.getAttribute('data-descricao');
            const valor = event.relatedTarget.getAttribute('data-valor');
            const tipo = event.relatedTarget.getAttribute('data-tipo');
            const vencimento = event.relatedTarget.getAttribute('data-vencimento'); 
            const data_competencia = event.relatedTarget.getAttribute('data-data_competencia');
            const data_pagamento = event.relatedTarget.getAttribute('data-data_pagamento');
            const metodo_pagamento = event.relatedTarget.getAttribute('data-metodo_pagamento');
            const status = event.relatedTarget.getAttribute('data-status');
            const id_categoria = event.relatedTarget.getAttribute('data-id-categoria');
            
            const modalForm = modalEditarLancamento.querySelector('form');

            modalForm.querySelector('#edit_id_lancamento').value = id;
            modalForm.querySelector('#edit_id_empresa').value = id_empresa;
            modalForm.querySelector('#edit_descricao').value = descricao;
            modalForm.querySelector('#edit_valor').value = valor;
            modalForm.querySelector('#edit_tipo').value = tipo;
            modalForm.querySelector('#edit_data_vencimento').value = vencimento;
            modalForm.querySelector('#edit_data_competencia').value = data_competencia;
            modalForm.querySelector('#edit_data_pagamento').value = data_pagamento;
            modalForm.querySelector('#edit_metodo_pagamento').value = metodo_pagamento;
            modalForm.querySelector('#edit_status').value = status;
            // Popula categoria (se o campo existir no modal)
            try {
                const catSel = modalForm.querySelector('#edit_id_categoria');
                if (catSel) catSel.value = id_categoria || '';
            } catch (e) {
                console.warn('Não foi possível popular edit_id_categoria:', e);
            }
            // Popula id_forma_pagamento (se informado no botão trigger)
            try {
                const idForma = event.relatedTarget.getAttribute('data-id-forma-pagamento');
                const hid = modalForm.querySelector('#edit_id_forma_pagamento');
                if (hid) hid.value = idForma || '';
            } catch (e) {
                console.warn('Não foi possível popular edit_id_forma_pagamento:', e);
            }
         });
    }

    // Fallback: também popula o modal de edição quando o botão é clicado diretamente.
    // Isso evita problemas quando `show.bs.modal` não fornece `relatedTarget` (variações de trigger)
    document.querySelectorAll('[data-bs-target="#modalEditarLancamento"]').forEach(function(btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-id');
            const id_empresa = btn.getAttribute('data-id_empresa');
            const descricao = btn.getAttribute('data-descricao');
            const valor = btn.getAttribute('data-valor');
            const tipo = btn.getAttribute('data-tipo');
            const vencimento = btn.getAttribute('data-vencimento'); 
            const data_competencia = btn.getAttribute('data-data_competencia');
            const data_pagamento = btn.getAttribute('data-data_pagamento');
            const metodo_pagamento = btn.getAttribute('data-metodo_pagamento');
            const status = btn.getAttribute('data-status');

            const modalForm = modalEditarLancamento.querySelector('form');
            modalForm.querySelector('#edit_id_lancamento').value = id;
            modalForm.querySelector('#edit_id_empresa').value = id_empresa;
            modalForm.querySelector('#edit_descricao').value = descricao;
            modalForm.querySelector('#edit_valor').value = valor;
            modalForm.querySelector('#edit_tipo').value = tipo;
            modalForm.querySelector('#edit_data_vencimento').value = vencimento;
            modalForm.querySelector('#edit_data_competencia').value = data_competencia;
            modalForm.querySelector('#edit_data_pagamento').value = data_pagamento;
            modalForm.querySelector('#edit_metodo_pagamento').value = metodo_pagamento;
            modalForm.querySelector('#edit_status').value = status;
            try {
                const idForma = btn.getAttribute('data-id-forma-pagamento');
                const hid = modalForm.querySelector('#edit_id_forma_pagamento');
                if (hid) hid.value = idForma || '';
            } catch (e) {
                console.warn('Não foi possível popular edit_id_forma_pagamento (fallback):', e);
            }
            try {
                const idCat = btn.getAttribute('data-id-categoria');
                const catSel = modalForm.querySelector('#edit_id_categoria');
                if (catSel) catSel.value = idCat || '';
            } catch (e) {
                console.warn('Não foi possível popular edit_id_categoria (fallback):', e);
            }
        });
    });

});

    
    
    // --- Auto-busca de CNPJ: adiciona listeners ao campo principal e ao campo de edição ---
    const cnpjField = document.getElementById('cnpj');
    if (cnpjField) {
        // Ao perder foco, tenta buscar
        cnpjField.addEventListener('blur', function() { buscarCnpj(); });
        // Se digitar e completar 14 dígitos, busca automaticamente
        cnpjField.addEventListener('input', function() {
            const digits = this.value.replace(/\D/g, '');
            if (digits.length === 14) buscarCnpj();
        });
    }

    const editCnpjField = document.getElementById('edit_cnpj');
    if (editCnpjField) {
        editCnpjField.addEventListener('blur', function() { buscarCnpj('edit_'); });
        editCnpjField.addEventListener('input', function() {
            const digits = this.value.replace(/\D/g, '');
            if (digits.length === 14) buscarCnpj('edit_');
        });
    }



// =========================
// Mobile filters modal logic (DOMContentLoaded)
// Move filtros para dentro do modal quando a largura for <= BREAKPOINT
// =========================
document.addEventListener('DOMContentLoaded', function() {
    const BREAKPOINT = 1367;
    const FORM_SELECTORS = ['#form-filtros', '#form-filtros-dashboard', '#form-filtros-cobrancas'];
    const modalEl = document.getElementById('mobileFiltersModal');
    const modalBody = document.getElementById('mobile-filters-modal-body');
    if (!modalEl || !modalBody) return;
    const bsModal = new bootstrap.Modal(modalEl, {backdrop: 'static'});

    const movedForms = new Map();

    function createToggleButton() {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-primary btn-sm mobile-filters-toggle ms-auto';
        // ms-auto empurra o botão para a direita dentro de um container flex
        btn.innerHTML = '<i class="bi bi-funnel-fill"></i> Filtros';
        return btn;
    }

    function moveFormToModal(formEl) {
        if (!formEl) return;
        if (movedForms.has(formEl)) { try { bsModal.show(); } catch (e) {} ; return; }
        const originalParent = formEl.parentNode;
        const originalNext = formEl.nextSibling;
        // capture section collapse state so we can restore it after closing modal
        let sectionState = null;
        try {
            const section = formEl.closest('.dashboard-section');
            if (section) {
                const secId = section.id || null;
                const expanded = !section.classList.contains('collapsed');
                sectionState = { id: secId, expanded: expanded };
            }
        } catch (e) { sectionState = null; }

        movedForms.set(formEl, { parent: originalParent, nextSibling: originalNext, sectionState: sectionState });
        modalBody.innerHTML = '';
        modalBody.appendChild(formEl);
        formEl.style.display = '';
        try { bsModal.show(); } catch (e) { console.warn('Erro ao abrir modal de filtros (move):', e); }
    }

    function restoreMovedForms() {
        movedForms.forEach(function(state, form) {
            try {
                if (state.nextSibling) state.parent.insertBefore(form, state.nextSibling);
                else state.parent.appendChild(form);
                // Em telas pequenas, o formulário deve permanecer oculto na página (pois está acessível via modal)
                form.style.display = (window.innerWidth <= BREAKPOINT) ? 'none' : '';

                // restore the collapse state of the related section (if we captured it)
                try {
                    const s = state.sectionState;
                    let section = null;
                    if (s && s.id) section = document.getElementById(s.id);
                    // if id not available or element not found, try to find closest section again
                    if (!section) section = form.closest('.dashboard-section');
                    if (section && s) {
                        const btn = section.querySelector('.section-toggle');
                        // Ajusta visual e aria do botão
                        if (s.expanded) {
                            section.classList.remove('collapsed');
                            if (btn) btn.setAttribute('aria-expanded', 'true');
                            const body = section.querySelector('.section-body');
                            if (body) body.style.height = '';
                            // mostra os nós irmãos que pertencem a este tópico
                            let curShow = section.nextElementSibling;
                            while (curShow) {
                                if (curShow.classList && curShow.classList.contains('dashboard-section')) break;
                                curShow.style.display = '';
                                curShow = curShow.nextElementSibling;
                            }
                        } else {
                            section.classList.add('collapsed');
                            if (btn) btn.setAttribute('aria-expanded', 'false');
                            const body = section.querySelector('.section-body');
                            if (body) body.style.height = '0px';
                            // esconde os nós irmãos que pertencem a este tópico
                            let curHide = section.nextElementSibling;
                            while (curHide) {
                                if (curHide.classList && curHide.classList.contains('dashboard-section')) break;
                                curHide.style.display = 'none';
                                curHide = curHide.nextElementSibling;
                            }
                        }
                    }
                } catch (e) {
                    console.warn('Erro ao restaurar estado da seção após mover o formulário:', e);
                }

            } catch (e) {
                console.warn('Erro ao restaurar form depois do modal:', e);
            }
        });
        movedForms.clear();
    }

    modalEl.addEventListener('hidden.bs.modal', restoreMovedForms);

    function ensureButtonsAndVisibility() {
        const width = window.innerWidth;
        FORM_SELECTORS.forEach(function(sel) {
            const formEl = document.querySelector(sel);
            if (!formEl) return;
            const card = formEl.closest('.card');
            const header = card ? card.querySelector('.card-header') : null;

            if (width <= BREAKPOINT) {
                formEl.style.display = 'none';
                // Se já existir um botão estático, garante que esteja visível e que tenha handler
                let existingBtn = header ? header.querySelector('.mobile-filters-toggle') : null;
                if (existingBtn) {
                    existingBtn.style.display = '';
                    // evita rebinds
                    if (!existingBtn.dataset.mobileBound) {
                        existingBtn.addEventListener('click', function(evt) {
                            // tenta mover o form para o modal antes do Bootstrap abrir
                            let targetForm = null;
                            try { if (card) targetForm = card.querySelector('form'); } catch (e) { targetForm = null; }
                            if (!targetForm) targetForm = formEl;
                            if (!targetForm) {
                                for (const s of FORM_SELECTORS) {
                                    const f = document.querySelector(s);
                                    if (f) { targetForm = f; break; }
                                }
                            }
                            if (targetForm) moveFormToModal(targetForm);
                            // allow default (bootstrap) to continue
                        });
                        existingBtn.dataset.mobileBound = '1';
                    }
                } else if (header && !header.querySelector('.mobile-filters-toggle')) {
                    const btn = createToggleButton();
                    btn.addEventListener('click', function() {
                        // Em telas pequenas abrimos o modal e movemos o form
                        const widthNow = window.innerWidth;
                        let targetForm = null;
                        try { if (card) targetForm = card.querySelector('form'); } catch (e) { targetForm = null; }
                        if (!targetForm) targetForm = formEl;
                        if (!targetForm) {
                            for (const s of FORM_SELECTORS) {
                                const f = document.querySelector(s);
                                if (f) { targetForm = f; break; }
                            }
                        }
                        if (!targetForm) return;
                        if (widthNow <= BREAKPOINT) {
                            moveFormToModal(targetForm);
                        } else {
                            // Desktop: apenas foca/rola até o formulário em vez de abrir modal
                            try {
                                const firstInput = targetForm.querySelector('input, select, textarea');
                                if (firstInput) {
                                    firstInput.focus();
                                    firstInput.scrollIntoView({behavior: 'smooth', block: 'center'});
                                } else {
                                    targetForm.scrollIntoView({behavior: 'smooth', block: 'center'});
                                }
                            } catch (e) { console.warn('Erro ao focar form:', e); }
                        }
                    });
                    // garante header como flex para que ms-auto funcione e empurre o botão ao fim
                    header.style.display = header.style.display || 'flex';
                    header.style.alignItems = header.style.alignItems || 'center';
                    header.style.gap = header.style.gap || '0.5rem';
                    header.appendChild(btn);
                }
            } else {
                formEl.style.display = '';
                if (header) {
                    const btn = header.querySelector('.mobile-filters-toggle');
                    if (btn) btn.remove();
                }
                if (modalBody) modalBody.innerHTML = '';
            }
        });
    }

    ensureButtonsAndVisibility();

    let resizeTimer = null;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() { ensureButtonsAndVisibility(); }, 120);
    });

    // Se houver botões que abrem o modal genérico (por exemplo um botão global de Filtros),
    // garante que quando clicados movam o form apropriado para dentro do modal.
    document.querySelectorAll('[data-bs-target="#mobileFiltersModal"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            // tenta localizar um formulário dos seletores conhecidos (prioridade definida pela lista)
            let targetForm = null;
            for (const s of FORM_SELECTORS) {
                const f = document.querySelector(s);
                if (f) { targetForm = f; break; }
            }
            if (targetForm) moveFormToModal(targetForm);
        });
    });
});


/**
 * Inicializa o gráfico de Fluxo de Caixa (Linhas)
 */
function initFluxoCaixaChart(canvasElement) {
     try {
         const chartDataScript = document.getElementById('chartData');
         if (!chartDataScript) {
             console.error("Elemento #chartData não encontrado. O gráfico não pode ser renderizado.");
             return;
         }
         const chartData = JSON.parse(chartDataScript.textContent);
         const ctx = canvasElement.getContext('2d');
         const corReceita = 'rgba(16, 185, 129, 1)'; 
         const corDespesa = 'rgba(239, 68, 68, 1)'; 
         const corGrid = 'rgba(200, 200, 200, 0.1)';
         new Chart(ctx, {
             type: 'line',
             data: {
                 labels: chartData.labels,
                 datasets: [
                     {
                         label: 'Receitas',
                         data: chartData.receitas,
                         borderColor: corReceita,
                         backgroundColor: corReceita,
                         tension: 0.3, 
                         fill: false,
                         pointBackgroundColor: corReceita,
                         pointBorderWidth: 2
                     },
                     {
                         label: 'Despesas',
                         data: chartData.despesas,
                         borderColor: corDespesa,
                         backgroundColor: corDespesa,
                         tension: 0.3,
                         fill: false,
                         pointBackgroundColor: corDespesa,
                         pointBorderWidth: 2
                     }
                 ]
             },
             options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 scales: {
                     y: {
                         beginAtZero: true,
                         ticks: {
                             callback: function(value, index, values) {
                                 return 'R$ ' + value.toLocaleString('pt-BR');
                             }
                         },
                         grid: {
                             color: corGrid
                         }
                     },
                     x: {
                          grid: {
                             display: false
                         }
                     }
                 },
                 plugins: {
                     tooltip: {
                         mode: 'index',
                         intersect: false,
                         callbacks: {
                             label: function(context) {
                                 let label = context.dataset.label || '';
                                 if (label) {
                                     label += ': ';
                                 }
                                 if (context.parsed.y !== null) {
                                     label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y);
                                 }
                                 return label;
                             }
                         }
                     },
                     legend: {
                         position: 'top',
                         align: 'end',
                         labels: {
                             usePointStyle: true,
                             boxWidth: 8
                         }
                     }
                 }
             }
         });
     } catch (e) {
         console.error("Erro ao carregar dados do gráfico de fluxo:", e);
         // Exibe mensagem de erro na tela do canvas
         if (canvasElement && canvasElement.getContext) {
            canvasElement.getContext('2d').fillText("Erro ao carregar dados do gráfico.", 10, 50);
         }
     }
}

/**
 * NOVO: Inicializa o gráfico de Status (Pizza/Doughnut)
 */
function initStatusChart(canvasElement) {
     try {
        const statusChartDataScript = document.getElementById('statusChartData');
        if (!statusChartDataScript) {
             return;
        }
        const chartData = JSON.parse(statusChartDataScript.textContent);
        const ctx = canvasElement.getContext('2d');
        
        // Cores personalizadas (Baseado nas classes do Dashboard)
        const backgroundColors = [
            'rgba(16, 185, 129, 0.9)',  // Pago (Success)
            'rgba(245, 158, 11, 0.9)'   // A Receber (Warning/Pending)
        ];
        const borderColors = [
            'rgba(16, 185, 129, 1)',
            'rgba(245, 158, 11, 1)'
        ];

        // Se todos os valores forem zero, exibe uma mensagem no console e não tenta renderizar.
        const totalSum = chartData.values.reduce((sum, value) => sum + value, 0);
        if (totalSum === 0) {
            console.warn("Gráfico de Status: Todos os valores são zero. Nenhum dado para renderizar.");
            // Opcional: Adicionar texto no canvas informando a falta de dados
             ctx.font = '14px Arial';
             ctx.fillStyle = '#6c757d';
             ctx.textAlign = 'center';
             ctx.fillText('Sem receitas no período para comparação.', canvasElement.width / 2, canvasElement.height / 2);
            return;
        }

        new Chart(ctx, {
            type: 'doughnut', // Tipo de gráfico de rosca
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.values,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right', // Coloca a legenda à direita
                        labels: {
                            boxWidth: 15 
                        }
                    },
                    tooltip: {
                        callbacks: {
                            // Exibe o valor total e o percentual no tooltip
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const value = context.parsed;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    
                                    label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
                                    label += ` (${percentage}%)`;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

    } catch (e) {
        console.error("Erro ao carregar dados do gráfico de status:", e);
    }
};


/**
 * Mostra/esconde campos de associação na tela de cadastro de usuário
 * baseado no tipo (Cliente ou Contador).
 * @param {string} prefix Prefixo dos IDs dos campos ('', 'edit_', etc.)
 */
function toggleUsuarioCampos(prefix) {
    const tipoEl = document.getElementById(prefix + 'tipo_usuario');
    if (!tipoEl) return; // Se o select não existir, sai cedo
    const tipo = tipoEl.value;
    const divCliente = document.getElementById(prefix + 'assoc_cliente_div');
    const divContador = document.getElementById(prefix + 'assoc_contador_div');

    if (!divCliente || !divContador) return; 

    if (tipo === 'cliente') {
        divCliente.style.display = 'block';
        divContador.style.display = 'none';
    } else if (tipo === 'contador') {
        divCliente.style.display = 'none';
        divContador.style.display = 'block';
    } else {
        divCliente.style.display = 'none';
        divContador.style.display = 'none';
    }
}


/**
 * Busca dados de um CNPJ na BrasilAPI (Existente)
 */
async function buscarCnpj(prefix = '') {
    // prefix: '' para form principal, 'edit_' para modal de edição
    const el = document.getElementById(prefix + 'cnpj');
    if (!el) return;
    const cnpj = el.value.replace(/\D/g, ''); 
    if (cnpj.length !== 14) return;
    const razaoSocialInput = document.getElementById(prefix + 'razao_social');
    const nomeFantasiaInput = document.getElementById(prefix + 'nome_fantasia');
    const dataAberturaInput = document.getElementById(prefix + 'data_abertura');
    const loadingSpinner = document.getElementById(prefix + 'cnpj-loading') || document.getElementById('cnpj-loading');
    if(loadingSpinner) loadingSpinner.style.display = 'inline-block';
    try {
        const response = await fetch(`process/api_cnpj.php?cnpj=${cnpj}`);
        if (!response.ok) throw new Error('Falha na consulta');
        const data = await response.json();
        if (data.razao_social) {
            if (razaoSocialInput) razaoSocialInput.value = data.razao_social;
            if (nomeFantasiaInput) nomeFantasiaInput.value = data.nome_fantasia || '';
            // BrasilAPI retorna data_inicio_atividade no formato YYYY-MM-DD possivelmente com hora
            if (dataAberturaInput) {
                // Tenta normalizar para YYYY-MM-DD
                const dt = data.data_inicio_atividade || data.opening_date || '';
                if (dt) {
                    const d = new Date(dt);
                    if (!isNaN(d.getTime())) {
                        const yyyy = d.getFullYear();
                        const mm = String(d.getMonth() + 1).padStart(2, '0');
                        const dd = String(d.getDate()).padStart(2, '0');
                        dataAberturaInput.value = `${yyyy}-${mm}-${dd}`;
                    } else {
                        dataAberturaInput.value = dt;
                    }
                } else {
                    dataAberturaInput.value = '';
                }
            }
        } else {
            alert('CNPJ não encontrado ou API indisponível.');
            if (razaoSocialInput) razaoSocialInput.value = '';
            if (nomeFantasiaInput) nomeFantasiaInput.value = '';
            if (dataAberturaInput) dataAberturaInput.value = '';
        }
    } catch (error) {
        console.error('Erro ao buscar CNPJ:', error);
        alert('Erro ao processar a solicitação de CNPJ.');
    } finally {
        if(loadingSpinner) loadingSpinner.style.display = 'none';
    }
}


/**
 * Inicializa o gráfico de Fluxo de Caixa (Linhas)
 */
function initFluxoCaixaChart(canvasElement) {
     try {
         const chartDataScript = document.getElementById('chartData');
         if (!chartDataScript) {
             console.error("Elemento #chartData não encontrado. O gráfico não pode ser renderizado.");
             return;
         }
         const chartData = JSON.parse(chartDataScript.textContent);
         const ctx = canvasElement.getContext('2d');
         const corReceita = 'rgba(16, 185, 129, 1)'; 
         const corDespesa = 'rgba(239, 68, 68, 1)'; 
         const corGrid = 'rgba(200, 200, 200, 0.1)';
         new Chart(ctx, {
             type: 'line',
             data: {
                 labels: chartData.labels,
                 datasets: [
                     {
                         label: 'Receitas',
                         data: chartData.receitas,
                         borderColor: corReceita,
                         backgroundColor: corReceita,
                         tension: 0.3, 
                         fill: false,
                         pointBackgroundColor: corReceita,
                         pointBorderWidth: 2
                     },
                     {
                         label: 'Despesas',
                         data: chartData.despesas,
                         borderColor: corDespesa,
                         backgroundColor: corDespesa,
                         tension: 0.3,
                         fill: false,
                         pointBackgroundColor: corDespesa,
                         pointBorderWidth: 2
                     }
                 ]
             },
             options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 scales: {
                     y: {
                         beginAtZero: true,
                         ticks: {
                             callback: function(value, index, values) {
                                 return 'R$ ' + value.toLocaleString('pt-BR');
                             }
                         },
                         grid: {
                             color: corGrid
                         }
                     },
                     x: {
                          grid: {
                             display: false
                         }
                     }
                 },
                 plugins: {
                     tooltip: {
                         mode: 'index',
                         intersect: false,
                         callbacks: {
                             label: function(context) {
                                 let label = context.dataset.label || '';
                                 if (label) {
                                     label += ': ';
                                 }
                                 if (context.parsed.y !== null) {
                                     label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y);
                                 }
                                 return label;
                             }
                         }
                     },
                     legend: {
                         position: 'top',
                         align: 'end',
                         labels: {
                             usePointStyle: true,
                             boxWidth: 8
                         }
                     }
                 }
             }
         });
     } catch (e) {
         console.error("Erro ao carregar dados do gráfico de fluxo:", e);
         // Exibe mensagem de erro na tela do canvas
         if (canvasElement && canvasElement.getContext) {
            canvasElement.getContext('2d').fillText("Erro ao carregar dados do gráfico.", 10, 50);
         }
     }
}

/**
 * Inicializa gráfico de status de cobranças (doughnut)
 */
function initCobStatusChart(canvasElement) {
    try {
        const script = document.getElementById('cobStatusChartData');
        if (!script) return;
        const data = JSON.parse(script.textContent);
        const total = data.values.reduce((s, v) => s + v, 0);
        const ctx = canvasElement.getContext('2d');
        if (total === 0) {
            ctx.font = '14px Arial';
            ctx.fillStyle = '#6c757d';
            ctx.textAlign = 'center';
            ctx.fillText('Sem dados de cobranças no período.', canvasElement.width/2, canvasElement.height/2);
            return;
        }
        const backgroundColors = ['rgba(239,68,68,0.9)', 'rgba(245,158,11,0.9)', 'rgba(16,185,129,0.9)', 'rgba(99,102,241,0.9)'];
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{ data: data.values, backgroundColor: backgroundColors.slice(0, data.labels.length) }]
            },
            options: { maintainAspectRatio: false }
        });
    } catch (e) {
        console.error('Erro initCobStatusChart', e);
    }
}

/**
 * Inicializa gráfico de total por tipo de cobranças (bar)
 */
function initCobTipoChart(canvasElement) {
    try {
        const script = document.getElementById('cobTipoChartData');
        if (!script) return;
        const data = JSON.parse(script.textContent);
        const ctx = canvasElement.getContext('2d');
        const total = data.values.reduce((s, v) => s + v, 0);
        if (total === 0) {
            ctx.font = '14px Arial';
            ctx.fillStyle = '#6c757d';
            ctx.textAlign = 'center';
            ctx.fillText('Sem valores por tipo no período.', canvasElement.width/2, canvasElement.height/2);
            return;
        }
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{ label: 'Total (R$)', data: data.values, backgroundColor: 'rgba(16,185,129,0.8)' }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: { ticks: { callback: (v)=> 'R$ ' + v.toLocaleString('pt-BR') } }
                },
                plugins: { tooltip: { callbacks: { label: function(ctx){ return new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(ctx.parsed.y); } } } }
            }
        });
    } catch (e) {
        console.error('Erro initCobTipoChart', e);
    }
}