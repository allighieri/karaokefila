<!-- Modal Gerenciar Eventos -->
<div class="modal fade" id="modalEventos" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event"></i> Gerenciar Eventos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Formulário para criar novo evento -->
                <div class="mb-4">
                    <h6 class="mb-3"><i class="bi bi-plus-circle"></i> Criar Novo Evento</h6>
                    <form id="formCriarEvento">
                        <div class="mb-3 row">
                            <label for="nome-evento" class="col-sm-3 col-form-label">Evento</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="nome-evento" name="nome" required placeholder="Ex: Karaokê da Sexta">
                            </div>
                        </div>
                        <div class="mb-3 row" id="campo-mc" style="display: none;">
                            <label for="select-mc" class="col-sm-3 col-form-label">MC</label>
                            <div class="col-sm-9">
                                <select class="form-select" id="select-mc" name="id_usuario_mc">
                                    <option value="">Selecione um MC</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-success" id="btn-criar-evento">
                                <i class="bi bi-plus-lg"></i> Criar Evento
                            </button>
                            <button type="button" class="btn btn-secondary" id="btn-cancelar-evento" onclick="cancelarEdicao()" style="display: none;">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                <hr>

                <!-- Lista de eventos -->
                <div>
                    <h6 class="mb-3"><i class="bi bi-list-ul"></i> Meus Eventos</h6>
                    <div id="loading-eventos" class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                    <div id="lista-eventos"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Aguardar o carregamento completo do DOM e jQuery
if (typeof jQuery === 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        // Aguardar jQuery ser carregado
        var checkJQuery = setInterval(function() {
            if (typeof jQuery !== 'undefined') {
                clearInterval(checkJQuery);
                initEventosModal();
            }
        }, 100);
    });
} else {
    $(document).ready(function() {
        initEventosModal();
    });
}

function initEventosModal() {
    let nivelUsuario = '<?php echo NIVEL_ACESSO; ?>';
    
    // Configurar campos baseado no nível do usuário
    if (nivelUsuario === 'admin' || nivelUsuario === 'super_admin') {
        $('#campo-mc').show();
        carregarMCs();
    }
    
    // Carregar eventos quando o modal é aberto
    $('#modalEventos').on('shown.bs.modal', function() {
        carregarEventos();
    });
    
    // Submissão do formulário de criar evento
    $('#formCriarEvento').on('submit', function(e) {
        e.preventDefault();
        criarEvento();
    });
    
    function carregarMCs() {
        $.ajax({
            url: 'api_eventos.php',
            method: 'POST',
            data: { action: 'obter_mcs' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let select = $('#select-mc');
                    select.empty().append('<option value="">Selecione um MC</option>');
                    
                    response.mcs.forEach(function(mc) {
                        let optionText = mc.nome;
                        if (nivelUsuario === 'super_admin' && mc.nome_tenant) {
                            optionText += ' (' + mc.nome_tenant + ')';
                        }
                        select.append('<option value="' + mc.id + '">' + optionText + '</option>');
                    });
                }
            },
            error: function() {
                mostrarAlerta('Erro ao carregar MCs', 'danger');
            }
        });
    }
    
    function criarEvento() {
        let formData = $('#formCriarEvento').serialize() + '&action=criar_evento';
        
        $.ajax({
            url: 'api_eventos.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    mostrarAlerta(response.message, 'success');
                    $('#formCriarEvento')[0].reset();
                    carregarEventos();
                } else {
                    mostrarAlerta(response.message, 'danger');
                }
            },
            error: function() {
                mostrarAlerta('Erro ao criar evento', 'danger');
            }
        });
    }
    
    function carregarEventos() {
        $('#loading-eventos').show();
        $('#lista-eventos').empty();
        
        $.ajax({
            url: 'api_eventos.php',
            method: 'POST',
            data: { action: 'listar_eventos' },
            dataType: 'json',
            success: function(response) {
                $('#loading-eventos').hide();
                
                if (response.success) {
                    if (response.eventos.length === 0) {
                        $('#lista-eventos').html('<p class="text-muted">Nenhum evento encontrado.</p>');
                    } else {
                        let html = `
                            <div class="table-responsive-sm">
                                <table class="table table-striped table-hover table-sm table-responsive-sm">
                                    <thead>
                                        <tr>
                                            <th scope="col">Nome do Evento</th>
                                            <th scope="col">MC</th>
                                            <th scope="col">Status</th>
                                            <th scope="col" style="width: 2%;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        response.eventos.forEach(function(evento) {
                            let statusClass = evento.status === 'ativo' ? 'success' : 'secondary';
                            let statusText = evento.status === 'ativo' ? 'Ativo' : 'Inativo';
                            let dataFormatada = new Date(evento.created_at).toLocaleDateString('pt-BR');
                            
                            html += `
                                <tr>
                                    <td>
                                        <div>
                                            <strong>${evento.nome}</strong>
                                            <br><small class="text-muted">Criado em: ${dataFormatada}</small>
                                        </div>
                                    </td>
                                    <td>${evento.nome_mc}</td>
                                    <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                                    <td>
                                        <div class="d-flex flex-nowrap gap-1">
                                            <button class="btn btn-outline-primary btn-sm" onclick="editarEvento(${evento.id})" title="Editar evento">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deletarEvento(${evento.id})" title="Deletar evento">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <button class="btn btn-outline-${evento.status === 'ativo' ? 'warning' : 'success'} btn-sm" onclick="alterarStatusEvento(${evento.id}, '${evento.status === 'ativo' ? 'inativo' : 'ativo'}')" title="${evento.status === 'ativo' ? 'Desativar' : 'Ativar'} evento">
                                                <i class="bi bi-${evento.status === 'ativo' ? 'pause' : 'play'}"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                        
                        $('#lista-eventos').html(html);
                    }
                } else {
                    mostrarAlerta(response.message, 'danger');
                }
            },
            error: function() {
                $('#loading-eventos').hide();
                mostrarAlerta('Erro ao carregar eventos', 'danger');
            }
        });
    }
    
    window.alterarStatusEvento = function(idEvento, novoStatus) {
        $.ajax({
            url: 'api_eventos.php',
            method: 'POST',
            data: {
                action: 'alterar_status',
                id_evento: idEvento,
                novo_status: novoStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    mostrarAlerta(response.message, 'success');
                    carregarEventos();
                } else {
                    mostrarAlerta(response.message, 'danger');
                }
            },
            error: function() {
                mostrarAlerta('Erro ao alterar status do evento', 'danger');
            }
        });
    };
    
    window.editarEvento = function(idEvento) {
        // Buscar dados do evento
        $.ajax({
            url: 'api_eventos.php',
            method: 'POST',
            data: {
                action: 'buscar_evento',
                id_evento: idEvento
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                     // Preencher o formulário com os dados do evento
                     $('#nome-evento').val(response.evento.nome);
                     $('#select-mc').val(response.evento.id_usuario_mc);
                     $('#btn-criar-evento').html('<i class="bi bi-pencil"></i> Atualizar Evento').attr('onclick', 'atualizarEvento(' + idEvento + ')').attr('type', 'button');
                     $('#btn-cancelar-evento').show();
                 } else {
                    mostrarAlerta(response.message, 'danger');
                }
            },
            error: function() {
                mostrarAlerta('Erro ao buscar dados do evento', 'danger');
            }
        });
    };
    
    window.atualizarEvento = function(idEvento) {
         let nomeEvento = $('#nome-evento').val().trim();
         let mcResponsavel = $('#select-mc').val();
         
         if (!nomeEvento) {
             mostrarAlerta('Por favor, preencha o nome do evento', 'warning');
             return;
         }
         
         // Só validar MC se o campo estiver visível (admin/super_admin)
         if ($('#campo-mc').is(':visible') && !mcResponsavel) {
             mostrarAlerta('Por favor, selecione um MC responsável', 'warning');
             return;
         }
         
         let data = {
             action: 'atualizar_evento',
             id_evento: idEvento,
             nome: nomeEvento
         };
         
         // Só incluir id_usuario_mc se o campo estiver visível
         if ($('#campo-mc').is(':visible')) {
             data.id_usuario_mc = mcResponsavel;
         }
         
         $.ajax({
             url: 'api_eventos.php',
             method: 'POST',
             data: data,
             dataType: 'json',
             success: function(response) {
                 if (response.success) {
                     mostrarAlerta(response.message, 'success');
                     cancelarEdicao();
                     carregarEventos();
                 } else {
                     mostrarAlerta(response.message, 'danger');
                 }
             },
             error: function() {
                 mostrarAlerta('Erro ao atualizar evento', 'danger');
             }
         });
     };
    
    window.deletarEvento = function(idEvento) {
        //if (confirm('Tem certeza que deseja deletar este evento? Esta ação não pode ser desfeita.')) {
            $.ajax({
                url: 'api_eventos.php',
                method: 'POST',
                data: {
                    action: 'deletar_evento',
                    id_evento: idEvento
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        mostrarAlerta(response.message, 'success');
                        carregarEventos();
                    } else {
                        mostrarAlerta(response.message, 'danger');
                    }
                },
                error: function() {
                    mostrarAlerta('Erro ao deletar evento', 'danger');
                }
            });
        //}
    };
    
    window.cancelarEdicao = function() {
         $('#nome-evento').val('');
         $('#select-mc').val('');
         $('#btn-criar-evento').html('<i class="bi bi-plus-lg"></i> Criar Evento').attr('onclick', null).attr('type', 'submit');
         $('#btn-cancelar-evento').hide();
     };
    
    function mostrarAlerta(mensagem, tipo) {
        // Usar o sistema de alertas existente ou criar um simples
        let alertClass = tipo === 'success' ? 'alert-success' : 'alert-danger';
        let alerta = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${mensagem}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        
        // Adicionar o alerta no topo do modal
        $('.modal-body').prepend(alerta);
        
        // Remover automaticamente após 5 segundos
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }
}
</script>