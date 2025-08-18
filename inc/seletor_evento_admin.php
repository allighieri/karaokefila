<?php
// Componente para seleção de evento por administradores
if (NIVEL_ACESSO === 'admin' || NIVEL_ACESSO === 'super_admin') {
?>
<div class="card mb-3 border-info">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="bi bi-calendar-event"></i> Seleção de Evento para Gerenciamento</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <label for="seletor-evento-admin" class="form-label">Selecione o evento para gerenciar:</label>
                <select class="form-select" id="seletor-evento-admin">
                    <option value="">Carregando eventos...</option>
                </select>
            </div>
            <div class="col-md-6">
                <div id="info-evento-selecionado" class="mt-2">
                    <small class="text-muted">Nenhum evento selecionado</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Aguardar jQuery estar disponível
function inicializarSeletorEventos() {
    if (typeof jQuery === 'undefined') {
        setTimeout(inicializarSeletorEventos, 100);
        return;
    }
    
    $(document).ready(function() {
        // Aguardar um pouco para garantir que a página carregou completamente
        setTimeout(function() {
            carregarEventosParaSelecao();
        }, 500);
        
        $('#seletor-evento-admin').on('change', function() {
            var eventoId = $(this).val();
            if (eventoId) {
                selecionarEventoAdmin(eventoId);
            }
        });
    });
}

// Inicializar quando o script for carregado
inicializarSeletorEventos();

function carregarEventosParaSelecao() {
    $.ajax({
        url: 'api_admin_eventos.php',
        method: 'POST',
        data: { action: 'listar_eventos_para_admin' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var select = $('#seletor-evento-admin');
                select.empty();
                select.append('<option value="">Selecione um evento</option>');
                
                response.eventos.forEach(function(evento) {
                    var optionText = evento.nome + ' (' + evento.nome_mc + ')';
                    if (evento.status === 'ativo') {
                        optionText += ' - ATIVO';
                    }
                    var option = $('<option></option>')
                        .attr('value', evento.id)
                        .text(optionText)
                        .data('evento', evento);
                    
                    if (evento.status === 'ativo') {
                        option.addClass('fw-bold');
                    }
                    
                    select.append(option);
                });
                
                // Verificar se há um evento já selecionado na sessão
                verificarEventoSelecionado();
            } else {
                $('#seletor-evento-admin').html('<option value="">Erro ao carregar eventos</option>');
            }
        },
        error: function(xhr, status, error) {
            if (xhr.status === 401 || xhr.status === 403) {
                $('#seletor-evento-admin').html('<option value="">Acesso negado - Faça login novamente</option>');
            } else {
                $('#seletor-evento-admin').html('<option value="">Erro ao carregar eventos</option>');
            }
        }
    });
}

function selecionarEventoAdmin(eventoId) {
    $.ajax({
        url: 'api_admin_eventos.php',
        method: 'POST',
        data: { 
            action: 'selecionar_evento_admin',
            evento_id: eventoId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var eventoSelecionado = $('#seletor-evento-admin option:selected').data('evento');
                atualizarInfoEventoSelecionado(eventoSelecionado);
                
                // Recarregar a página para aplicar o novo evento
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                alert('Erro ao selecionar evento: ' + response.message);
            }
        },
        error: function() {
            alert('Erro ao selecionar evento');
        }
    });
}

function verificarEventoSelecionado() {
    $.ajax({
        url: 'api_admin_eventos.php',
        method: 'POST',
        data: { action: 'obter_evento_selecionado' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.evento_selecionado) {
                $('#seletor-evento-admin').val(response.evento_selecionado.id);
                atualizarInfoEventoSelecionado(response.evento_selecionado);
            }
        }
    });
}

function atualizarInfoEventoSelecionado(evento) {
    if (evento) {
        var info = '<strong>Evento Selecionado:</strong> ' + evento.nome + '<br>';
        info += '<strong>MC:</strong> ' + evento.nome_mc + '<br>';
        info += '<strong>Status:</strong> <span class="badge ' + 
                (evento.status === 'ativo' ? 'bg-success' : 'bg-secondary') + '">' + 
                evento.status.toUpperCase() + '</span>';
        
        $('#info-evento-selecionado').html(info);
    } else {
        $('#info-evento-selecionado').html('<small class="text-muted">Nenhum evento selecionado</small>');
    }
}
</script>

<?php
}
?>