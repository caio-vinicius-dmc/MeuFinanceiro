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
            const button = event.relatedTarget;
            
            // 1. Pega os dados
            const id = button.getAttribute('data-id');
            const id_empresa = button.getAttribute('data-id_empresa');
            const descricao = button.getAttribute('data-descricao');
            const valor = button.getAttribute('data-valor');
            const tipo = button.getAttribute('data-tipo');
            const vencimento = button.getAttribute('data-vencimento'); 
            
            const modalForm = modalEditarLancamento.querySelector('form');

            // 2. Popula o formulário
            modalForm.querySelector('#edit_id_lancamento').value = id;
            modalForm.querySelector('#edit_id_empresa').value = id_empresa;
            modalForm.querySelector('#edit_descricao').value = descricao;
            modalForm.querySelector('#edit_valor').value = valor;
            modalForm.querySelector('#edit_tipo').value = tipo;
            modalForm.querySelector('#edit_data_vencimento').value = vencimento;
         });
    }

});

/**
 * Mostra/esconde campos de associação na tela de cadastro de usuário
 * baseado no tipo (Cliente ou Contador).
 * @param {string} prefix Prefixo dos IDs dos campos ('', 'edit_', etc.)
 */
function toggleUsuarioCampos(prefix) {
    const tipo = document.getElementById(prefix + 'tipo_usuario').value;
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
async function buscarCnpj() {
    const cnpj = document.getElementById('cnpj').value.replace(/\D/g, ''); 
    if (cnpj.length !== 14) return;
    const razaoSocialInput = document.getElementById('razao_social');
    const nomeFantasiaInput = document.getElementById('nome_fantasia');
    const dataAberturaInput = document.getElementById('data_abertura');
    const loadingSpinner = document.getElementById('cnpj-loading');
    if(loadingSpinner) loadingSpinner.style.display = 'inline-block';
    try {
        const response = await fetch(`process/api_cnpj.php?cnpj=${cnpj}`);
        if (!response.ok) throw new Error('Falha na consulta');
        const data = await response.json();
        if (data.razao_social) {
            razaoSocialInput.value = data.razao_social;
            nomeFantasiaInput.value = data.nome_fantasia || '';
            dataAberturaInput.value = data.data_inicio_atividade || '';
        } else {
            alert('CNPJ não encontrado ou API indisponível.');
            razaoSocialInput.value = '';
            nomeFantasiaInput.value = '';
            dataAberturaInput.value = '';
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
            'rgba(245, 158, 11, 0.9)',  // A Receber (Warning/Pending)
            'rgba(239, 68, 68, 0.9)'    // Contestado (Danger)
        ];
        const borderColors = [
            'rgba(16, 185, 129, 1)',
            'rgba(245, 158, 11, 1)',
            'rgba(239, 68, 68, 1)'
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
}