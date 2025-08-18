<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'init.php';
require_once 'funcoes_fila.php'; // Inclui as funções e a conexão PDO
require_once 'funcoes_lista_cantor.php';

if (!check_access(NIVEL_ACESSO, ['admin', 'mc'])) {
    header("Location: " . $rootPath . "login");
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Músicas do Cantor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="css/style_gerenciar_musicas.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Otimizações para melhor performance do sortable */
        .ui-sortable-helper {
            opacity: 0.8;
            transform: rotate(2deg);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 1000;
        }
        
        .ui-sortable-placeholder {
            border: 2px dashed #007bff;
            background-color: rgba(0,123,255,0.1);
            visibility: visible !important;
            height: 72px !important;
            margin: 5px 0;
            border-radius: 5px;
        }
        
        .ui-sortable-placeholder * {
            visibility: hidden;
        }
        
        /* Melhora a performance durante o drag */
        .ui-sortable-helper * {
            pointer-events: none;
        }
        
        /* Transições suaves */
        .queue-item {
            transition: transform 0.2s ease;
        }
        
        .queue-item:hover {
            transform: translateX(2px);
        }
    </style>

</head>
<body>
<?php include_once 'inc/nav.php'; ?>
<div class="container">

    <div id="alertContainer" class="mt-3"></div>


    <h3>Gerenciar Músicas por Cantor</h3>


    <?php if ($mensagem_sucesso != ''): ?>
        <div class="alert success"><?php echo htmlspecialchars($mensagem_sucesso); ?></div>
    <?php endif; ?>
    <?php if ($mensagem_erro != ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($mensagem_erro); ?></div>
    <?php endif; ?>


    <form method="GET" action="musicas_cantores.php" id="form-selecao">
        <input type="hidden" name="action" value="add_cantor">
        <?php 
        // Como cada MC só pode ter um evento ativo, usamos o primeiro da lista
        $evento_ativo_mc = !empty($eventos_ativos) ? $eventos_ativos[0] : null;
        $evento_selecionado_id = $evento_ativo_mc ? $evento_ativo_mc['id'] : null;
        ?>
        <input type="hidden" name="evento_id" value="<?php echo $evento_selecionado_id ? htmlspecialchars($evento_selecionado_id) : ''; ?>">
        
        <?php if ($evento_ativo_mc): ?>
            <div class="alert alert-info mb-3">
                <strong>Evento Ativo:</strong> <?php echo htmlspecialchars($evento_ativo_mc['nome'] . ' - MC: ' . $evento_ativo_mc['nome_mc']); ?>
            </div>
        <?php endif; ?>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="cantor_id" class="form-label">Cantor(a):</label>
                <select id="cantor_id" name="cantor_id" class="form-select" onchange="this.form.submit()" <?php echo !$evento_selecionado_id ? 'disabled' : ''; ?>>
                    <option value="">Selecione um(a) cantor(a)</option>
                    <?php if ($evento_selecionado_id): ?>
                        <?php foreach ($cantores_disponiveis as $cantor): ?>
                            <option value="<?php echo htmlspecialchars($cantor['id']); ?>"
                                <?php echo ($cantor_selecionado_id == $cantor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cantor['nome_cantor']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
         </div>
    </form>
    <p>Selecione um(a) cantor(a) para exibir ou adicionar músicas para ele(a)</p>

    <?php if ($cantor_selecionado_id && !empty($cantores_disponiveis)): ?>
        <?php
        $nome_cantor_selecionado = 'Cantor Desconhecido';
        foreach ($cantores_disponiveis as $c) {
            if ($c['id'] == $cantor_selecionado_id) {
                $nome_cantor_selecionado = $c['nome_cantor'];
                break;
            }
        }
        ?>



        <h5>Adicionar Nova Música para <?php echo htmlspecialchars($nome_cantor_selecionado); ?></h5>

        <form method="POST" id="form-add-musica" style="display: none;">
            <input type="hidden" name="action" value="add_musica_cantor">
            <input type="hidden" name="id_cantor" value="<?php echo htmlspecialchars($cantor_selecionado_id); ?>">
            <input type="hidden" name="id_evento" value="<?php echo htmlspecialchars($evento_selecionado_id); ?>">
            <input type="hidden" id="id_musica" name="id_musica">
        </form>

        <label for="search_musica"><small>Pesquisar Música por título, artista, código ou trecho... (Clique na música para adicionar)</small></label>
        <div class="row">
            <div class="col-12 col-lg-12">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="search_musica" placeholder="Digite para buscar músicas..." autocomplete="off">
                </div>
            </div>
        </div>
        <hr class="my-5" />

        <h3>Músicas de <?php echo htmlspecialchars($nome_cantor_selecionado); ?></h3>

        <div id="musicas-cantor-section">
            <?php if (empty($musicas_do_cantor)): ?>
                <p id="no-musicas-message" style="display: block;">Nenhuma música adicionada para este cantor ainda.</p>
                <ul class="sortable-list-musicas" id="sortable-musicas-cantor" style="display: none;"></ul>
            <?php else: ?>

                <p id="no-musicas-message" style="display: none;">Nenhuma música adicionada para este cantor ainda.</p>
                <ul class="sortable-list-musicas" id="sortable-musicas-cantor">
                    <?php foreach ($musicas_do_cantor as $musica): ?>
                        <?php
                        $statusClass = '';
                        $statusText = '';
                        $statusSortable = '';
                        switch($musica['status']) {
                            case 'aguardando': $statusClass = 'badge-info'; $statusText = '⏳ Aguardando'; $statusSortable = 'aguardando'; break;
                            case 'cantou': $statusClass = 'badge-success'; $statusText = '✅ Cantou'; $statusSortable = 'cantou'; break;
                            case 'pulou': $statusClass = 'badge-warning'; $statusText = '⏭️Pulou'; $statusSortable = 'pulou'; break;
                            case 'selecionada_para_rodada': $statusClass = 'badge-primary'; $statusText = '⏳ Na fila da rodada'; $statusSortable = 'selecionada_para_rodada'; break;
                            case 'em_execucao': $statusClass = 'badge-danger'; $statusText = '🎤 Sua vez!'; $statusSortable = 'em_execucao'; break;
                            default: $statusClass = 'badge-muted'; $statusText = 'Desconhecido';
                        }
                        $isCurrentlyPlaying = ($musica['status'] == 'em_execucao');
                        ?>
                        <li class="queue-item <?php echo $isCurrentlyPlaying ? 'list-group-item-danger' : ''; ?>"
                            data-musica-cantor-id="<?php echo htmlspecialchars($musica['musica_cantor_id']); ?>"
                            data-id-musica="<?php echo htmlspecialchars($musica['id_musica']); ?>"
                            data-id-cantor="<?php echo htmlspecialchars($cantor_selecionado_id); ?>"
                            data-status="<?php echo $statusSortable; ?>">
                            <div>
                                <span class="ordem-numero"><?php echo htmlspecialchars($musica['ordem_na_lista']); ?>.</span>
                                <span><?php echo htmlspecialchars($musica['titulo']); ?> (<?php echo htmlspecialchars($musica['artista']); ?>)</span>
                                <br>
                                <small>
                                    <span class="badge bg-secondary me-1" style="width: 47px; text-align: right;"><strong><?php echo htmlspecialchars($musica['codigo']); ?></strong></span>
                                    <span class="status-text badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </small>
                            </div>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-info" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#trocarMusicaModal" 
                                        data-musica-cantor-id="<?php echo htmlspecialchars($musica['musica_cantor_id']); ?>"
                                        data-current-music-title="<?php echo htmlspecialchars($musica['titulo'] . ' (' . $musica['artista'] . ')'); ?>"
                                        title="Trocar Música"
                                        <?php if ($musica['status'] === 'em_execucao' || $musica['status'] === 'cantou'): ?>disabled<?php endif; ?>>
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="remove_musica_cantor">
                                    <input type="hidden" name="musica_cantor_id" value="<?php echo htmlspecialchars($musica['musica_cantor_id']); ?>">
                                    <input type="hidden" name="cantor_id" value="<?php echo htmlspecialchars($cantor_selecionado_id); ?>">
                                    <input type="hidden" name="evento_id" value="<?php echo htmlspecialchars($evento_selecionado_id); ?>">
                                    <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash-fill"></i></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    <?php elseif (empty($cantores_disponiveis)): ?>
        <p>Para escolher a música para cantar, primeiro cadastre um cantor no menu <a href="cantores.php">Cantores</a>.</p>
    <?php endif; ?>

    <?php include_once 'modal_resetar_sistema.php'?>
<?php include_once 'modal_editar_codigo.php'?>
<?php include_once 'modal_add_repertorio.php'?>
<?php include_once 'modal_eventos.php'?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="/fila/js/resetar_sistema.js"></script>
    <script src="/fila/js/gerenciar_codigo.js"></script>
    <script src="/fila/js/add_repertorio.js"></script>


    <style>
        /* Garantir que o autocomplete apareça acima da modal */
        .ui-autocomplete {
            z-index: 9999 !important;
            max-height: 200px;
            overflow-y: auto;
        }
        
        /* Estilo para indicador de carregamento */
        .loading {
            background-image: url('data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wjRLEuQRNnGt7QpVdNhHJBkaK0VGJQCdHjyOHcn9TItNT6cnKBBIAIfkECQoAAAAsAAAAABAAEAAAAzQIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wjRLEuQRNnGt7QpVdNhHJBkaK0VGJQCdHjyOHcn9TItNT6cnKBBIAOw==') !important;
            background-repeat: no-repeat !important;
            background-position: right 10px center !important;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Definir variáveis globais JavaScript
            var idCantorAtual = <?php echo json_encode($cantor_selecionado_id); ?>;
            var idEventoAtual = <?php echo json_encode($evento_selecionado_id); ?>;

            window.showAlert = function(message, type) {
                var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                    '<span>' + message + '</span>' +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div>';
                $('#alertContainer').html(alertHtml);
                setTimeout(function() {
                    $('#alertContainer .alert').alert('close');
                }, 5000); // Alerta desaparece após 5 segundos
            }

            let isDragging = false; // Flag para indicar se o sortable está sendo arrastado

            // Funções auxiliares (manter como estão)
            function escapeRegex(value) {
                return value.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, "\\$&");
            }

            function highlightMatch(text, term) {
                if (!term) {
                    return text;
                }
                var matcher = new RegExp("(" + escapeRegex(term) + ")", "ig");
                return text.replace(matcher, "<strong>$1</strong>");
            }

            // Configuração do autocomplete para busca de músicas com melhorias baseadas no musicasbusca.php
            var searchTimer;
            var searchDelay = 300; // Delay para evitar muitas requisições
            var isSearching = false;
            
            $("#search_musica").autocomplete({
                source: function(request, response) {
                    // Limpa o timer anterior se existir
                    clearTimeout(searchTimer);
                    
                    // Define um novo timer para a busca
                    searchTimer = setTimeout(function() {
                        // Verifica se já está fazendo uma busca
                        if (isSearching) {
                            return;
                        }
                        
                        isSearching = true;
                        
                        $.ajax({
                            url: 'api.php',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'search_musicas',
                                term: request.term
                            },
                            beforeSend: function() {
                                // Adiciona indicador visual de carregamento
                                $('#search_musica').addClass('loading');
                            },
                            success: function(data) {
                                if (data.length === 0) {
                                    response([{ label: "Nenhum resultado encontrado para '" + request.term + "'", value: "" }]);
                                } else {
                                    response($.map(data, function(item) {
                                        return {
                                            label: '<strong style="display: inline-block; width: 47px; text-align: right; padding-right:4px;">' + item.codigo + '</strong> ' + item.titulo + ' - ' + item.artista,
                                            value: item.id_musica,
                                            titulo: item.titulo,
                                            artista: item.artista,
                                            codigo: item.codigo
                                        };
                                    }));
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error("Erro na busca de músicas:", {
                                    status: status,
                                    error: error,
                                    responseText: xhr.responseText
                                });
                                
                                
                                
                                response([{ label: "Erro ao buscar resultados. Tente novamente.", value: "" }]);
                            },
                            complete: function() {
                                $('#search_musica').removeClass('loading');
                                isSearching = false;
                            }
                        });
                    }, searchDelay);
                },
                minLength: 2, // Aumentado para 2 caracteres como no musicasbusca.php
                delay: 0, // Removemos o delay do autocomplete pois controlamos manualmente
                select: function(event, ui) {
                    if (ui.item.value === "") {
                        event.preventDefault();
                        return false;
                    }
                    
                    // Define o ID da música e submete o formulário automaticamente
                    $('#id_musica').val(ui.item.value);
                    
                    // Feedback visual de seleção
                    $(this).addClass('selected');
                    
                    // Submete o formulário automaticamente
                    $('#form-add-musica').submit();
                    
                    // Limpa o campo de busca
                    $(this).val('');
                    
                    return false;
                },
                focus: function(event, ui) {
                    if (ui.item.value === "") {
                        event.preventDefault();
                    }
                    return false;
                },
                open: function() {
                    // Melhora a acessibilidade
                    $(this).autocomplete('widget').addClass('custom-autocomplete');
                },
                close: function() {
                    // Limpa o timer quando o autocomplete fecha
                    clearTimeout(searchTimer);
                }
            });

            // Aguardar a inicialização completa do autocomplete antes de configurar _renderItem
            setTimeout(function() {
                if ($("#search_musica").length > 0 && $("#search_musica").data("ui-autocomplete")) {
                    $("#search_musica").data("ui-autocomplete")._renderItem = function(ul, item) {
                        if (item.value === "") {
                            return $("<li>")
                                .append($("<div>").text(item.label))
                                .appendTo(ul);
                        }
                        var highlightedLabel = highlightMatch(item.label, this.term);
                        return $("<li>")
                            .append($("<div>").html(highlightedLabel))
                            .appendTo(ul);
                    };
                    console.log("jQuery UI Autocomplete inicializado com sucesso em #search_musica");
                } else if ($("#search_musica").length === 0) {
                    console.log("Elemento #search_musica não encontrado no DOM");
                } else {
                    console.log("jQuery UI Autocomplete não inicializado em #search_musica após timeout");
                }
            }, 100);

            // Melhorias na validação do formulário com feedback visual
            $('form[action="musicas_cantores.php"][method="POST"]').on('submit', function(event) {
                const idMusicaInput = $('#id_musica');
                const searchMusicaInput = $('#search_musica');
                const submitButton = $(this).find('button[type="submit"]');

                // Remove classes de erro anteriores
                searchMusicaInput.removeClass('is-invalid');
                $('.invalid-feedback').remove();

                if (!idMusicaInput.val() || !searchMusicaInput.val()) {
                    // Adiciona feedback visual de erro
                    searchMusicaInput.addClass('is-invalid');
                    searchMusicaInput.after('<div class="invalid-feedback">Por favor, selecione uma música da lista de sugestões.</div>');
                    
                    // Foca no campo e adiciona shake animation
                    searchMusicaInput.focus();
                    searchMusicaInput.addClass('shake');
                    setTimeout(function() {
                        searchMusicaInput.removeClass('shake');
                    }, 600);
                    
                    event.preventDefault();
                    return false;
                }
                
                // Adiciona indicador de carregamento no botão
                submitButton.prop('disabled', true);
                const originalText = submitButton.html();
                submitButton.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>Adicionando...');
                
                // Restaura o botão após um tempo (caso não haja redirecionamento)
                setTimeout(function() {
                    submitButton.prop('disabled', false);
                    submitButton.html(originalText);
                }, 3000);
            });

            // Melhorias na limpeza do campo com debounce
            let inputTimer;
            $('#search_musica').on('input', function() {
                const $this = $(this);
                clearTimeout(inputTimer);
                
                // Remove classes de erro quando o usuário começa a digitar
                $this.removeClass('is-invalid');
                $('.invalid-feedback').remove();
                
                inputTimer = setTimeout(function() {
                    if ($this.val() === '') {
                        $('#id_musica').val('');
                        $this.removeClass('selected');
                    }
                }, 300);
            });
            
            // Adiciona suporte para tecla Enter no campo de busca
            $('#search_musica').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    // Se há um valor selecionado, submete o formulário
                    if ($('#id_musica').val()) {
                        $(this).closest('form').submit();
                    }
                }
            });

            // Inicialização do Sortable (mover para a função de atualização para ser reinicializado)
            const $sortableList = $("#sortable-musicas-cantor");

            const urlParams = new URLSearchParams(window.location.search);
            let refreshIntervalId; // Variável para armazenar o ID do intervalo

            // Função que encapsula a lógica de renderização e reinicialização do Sortable
            function inicializarOuAtualizarSortable() {
                // 1. Destruir o Sortable existente se ele estiver inicializado
                if ($sortableList.hasClass('ui-sortable')) {
                    $sortableList.sortable("destroy");
                    console.log("Sortable destruído.");
                }

                // 2. Inicializar o Sortable
                $sortableList.sortable({
                    axis: "y",
                    placeholder: "ui-sortable-placeholder",
                    helper: "clone",
                    cursor: "grabbing",
                    items: "li:not([data-status='cantou']):not([data-status='em_execucao']):not([data-status='selecionada_para_rodada'])",
                    tolerance: "pointer",
                    forceHelperSize: true,
                    forcePlaceholderSize: true,
                    scroll: true,
                    scrollSensitivity: 10,
                    scrollSpeed: 20,
                    start: function(event, ui) {
                        isDragging = true;
                        clearInterval(refreshIntervalId); // Desabilita o polling durante o drag
                    },
                    stop: function(event, ui) {
                        isDragging = false;
                        // Reabilita o polling após o drag parar
                        refreshIntervalId = setInterval(atualizarListaMusicasCantor, 3000);
                    },
                    update: function(event, ui) {
                        if (this === ui.item.parent()[0]) {
                            var novaOrdem = {};
                            var idCantor = idCantorAtual;

                            $sortableList.children('.queue-item').each(function(index) {
                                var musicaCantorId = $(this).data('musica-cantor-id');
                                novaOrdem[musicaCantorId] = index + 1;
                            });

                            console.log("Nova ordem das músicas para o cantor ID " + idCantor + ":", novaOrdem);

                            $.ajax({
                                url: 'api.php',
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'atualizar_ordem_musicas_cantor',
                                    id_cantor: idCantor,
                                    nova_ordem_musicas: novaOrdem
                                },
                                success: function(response) {
                                    if (response.success) {
                                        console.log('Ordem das músicas do cantor atualizada com sucesso no servidor!', response.message);
                                        // Apenas atualiza os números de ordem visualmente, sem recarregar toda a lista
                                        $sortableList.children('.queue-item').each(function(index) {
                                            $(this).find('.ordem-numero').text((index + 1) + '.');
                                        });
                                    } else {
                                        alert('Erro ao atualizar a ordem das músicas do cantor: ' + response.message);
                                        $sortableList.sortable('cancel');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error("Erro na requisição AJAX para reordenar músicas do cantor:", status, error);
                                    alert('Erro na comunicação com o servidor ao reordenar as músicas do cantor.');
                                    $sortableList.sortable('cancel');
                                }
                            });
                        }
                    }
                });
                $sortableList.disableSelection(); // Desabilita a seleção de texto para não atrapalhar o drag
                console.log("Sortable reinicializado.");
            }

            // Função principal de atualização da lista de músicas
            if (idCantorAtual) {
                function atualizarListaMusicasCantor() {
                    if (isDragging) {
                        console.log("Drag em progresso, pulando atualização da lista.");
                        return;
                    }

                    // Verificar se temos cantor e evento selecionados
                    if (!idCantorAtual || !idEventoAtual) {
                        console.log("Cantor ou evento não selecionado, pulando atualização da lista.");
                        return;
                    }

                    $.ajax({
                        url: 'api.php',
                        method: 'GET',
                        data: {
                            action: 'get_musicas_cantor_atualizadas',
                            id_cantor: idCantorAtual,
                            id_evento: idEventoAtual
                        },
                        dataType: 'json',
                        timeout: 10000, // Timeout de 10 segundos
                        beforeSend: function() {
                            $('#musicas-loading-indicator').fadeIn(200);
                        },
                        success: function(response) {
                            if (response.success) {
                                const musicasAPI = response.musicas;
                                const musicaEmExecucaoGeral = response.musica_em_execucao_geral;
                                const $musicasListContainer = $('#sortable-musicas-cantor');
                                const $noMusicasMessage = $('#no-musicas-message');

                                if (musicasAPI.length === 0) {
                                    $musicasListContainer.hide().empty();
                                    $noMusicasMessage.show();
                                } else {
                                    $musicasListContainer.show();
                                    $noMusicasMessage.hide();

                                    const currentDomItems = {};
                                    $musicasListContainer.children('.queue-item').each(function() {
                                        const id = $(this).data('musica-cantor-id');
                                        currentDomItems[id] = $(this);
                                    });

                                    let newOrderMusicaIds = [];
                                    let fragment = document.createDocumentFragment();

                                    musicasAPI.forEach(function(musica) {
                                        const musicaCantorId = musica.musica_cantor_id;
                                        let $item = currentDomItems[musicaCantorId];

                                        const isCurrentlyPlaying = (musicaEmExecucaoGeral &&
                                            musica.musica_cantor_id == musicaEmExecucaoGeral.musica_cantor_id);

                                        let statusClass = '';
                                        let statusText = '';
                                        let statusSortable = '';
                                        switch(musica.status) {
                                            case 'aguardando': statusClass = 'badge-info'; statusText = '⏳ Aguardando'; statusSortable = 'aguardando'; break;
                                            case 'cantou': statusClass = 'badge-success'; statusText = '✅ Cantou'; statusSortable = 'cantou'; break;
                                            case 'pulou': statusClass = 'badge-warning'; statusText = '⏭️ Pulou'; statusSortable = 'pulou'; break;
                                            case 'selecionada_para_rodada': statusClass = 'badge-primary'; statusText = '⏳ Na fila da rodada'; statusSortable = 'selecionada_para_rodada'; break;
                                            case 'em_execucao': statusClass = 'badge-danger'; statusText = '🎤 Sua vez!'; statusSortable = 'em_execucao'; break;
                                            default: statusClass = 'badge-muted'; statusText = 'Desconhecido'; statusSortable = 'desconhecido';
                                        }

                                        if (!$item) {
                                            // Cria um novo item se não existir no DOM
                                            // Criar botão de troca (sempre visível, mas desabilitado quando necessário)
                                            const isDisabled = (musica.status === 'em_execucao' || musica.status === 'cantou');
                                            const trocarButton = `<button class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#trocarMusicaModal" 
                                                        data-musica-cantor-id="${musicaCantorId}"
                                                        data-current-music-title="${musica.titulo} (${musica.artista})"
                                                        title="Trocar Música"
                                                        ${isDisabled ? 'disabled' : ''}>
                                                    <i class="bi bi-arrow-left-right"></i>
                                                </button>`;
                                            
                                            $item = $(`<li class="queue-item fade-in" data-musica-cantor-id="${musicaCantorId}" data-id-musica="${musica.id_musica}" data-id-cantor="${musica.id_cantor}" data-status="${statusSortable}">
                                        <div>
                                            <span class="ordem-numero"></span>
                                            <span></span>
                                            <br>
                                            <small><strong></strong> <span class="status-text badge"></span></small>
                                        </div>
                                        <div class="d-flex gap-1">
                                            ${trocarButton}
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="remove_musica_cantor">
                                                <input type="hidden" name="musica_cantor_id" value="${musicaCantorId}">
                                                <input type="hidden" name="cantor_id" value="${idCantorAtual}">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Remover música"><i class="bi bi-trash-fill"></i></button>
                                            </form>
                                        </div>
                                    </li>`);
                                            currentDomItems[musicaCantorId] = $item;
                                        }

                                        // Atualiza o conteúdo e classes do item existente ou recém-criado
                                        $item.find('.ordem-numero').text(musica.ordem_na_lista + '.');
                                        $item.find('div > span:eq(1)').text(`${musica.titulo} (${musica.artista})`);
                                        $item.find('.status-text').text(statusText).removeClass().addClass(`status-text badge ${statusClass}`);
                                        
                                        // Atualiza o badge do código
                                        let $codeBadge = $item.find('.badge.bg-secondary');
                                        if ($codeBadge.length === 0) {
                                            // Se não existe o badge do código, cria um novo antes do status
                                            $item.find('small').prepend(`<span class="badge bg-secondary me-2" style="width: 60px; text-align: right;"><strong>${musica.codigo}</strong></span>`);
                                        } else {
                                            // Se já existe, apenas atualiza o conteúdo
                                            $codeBadge.html(`<strong>${musica.codigo}</strong>`);
                                        }

                                        // As linhas cruciais para atualizar o data-status no HTML
                                        $item.data('status', statusSortable); // Atualiza o cache do jQuery
                                        $item.attr('data-status', statusSortable); // Atualiza o atributo no DOM

                                        // Atualiza o estado do botão de trocar música
                                        const isDisabled = (musica.status === 'em_execucao' || musica.status === 'cantou');
                                        const $trocarButton = $item.find('button[data-bs-target="#trocarMusicaModal"]');
                                        if (isDisabled) {
                                            $trocarButton.prop('disabled', true).attr('disabled', 'disabled');
                                        } else {
                                            $trocarButton.prop('disabled', false).removeAttr('disabled');
                                        }

                                        if (isCurrentlyPlaying) {
                                            $item.addClass('list-group-item-danger');
                                        } else {
                                            $item.removeClass('list-group-item-danger');
                                        }

                                        fragment.appendChild($item[0]);
                                        newOrderMusicaIds.push(musicaCantorId);
                                    });

                                    // Remove itens do DOM que não estão mais na lista da API
                                    Object.keys(currentDomItems).forEach(function(id) {
                                        if (newOrderMusicaIds.indexOf(parseInt(id)) === -1) {
                                            currentDomItems[id].fadeOut(300, function() {
                                                $(this).remove();
                                            });
                                        }
                                    });

                                    $musicasListContainer.empty().append(fragment);
                                    
                                    // Adiciona animação fade-in para novos itens
                                    setTimeout(function() {
                                        $('.fade-in').addClass('visible');
                                    }, 100);
                                }
                            } else {
                                console.error("Erro na resposta da API:", response.message || 'Resposta inválida');
                                showErrorMessage('Erro ao carregar a lista de músicas. Tentando novamente...');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error("Erro na requisição AJAX:", {
                                status: textStatus,
                                error: errorThrown,
                                responseText: jqXHR.responseText,
                                statusCode: jqXHR.status
                            });
                            
                            let errorMsg = 'Erro de conexão. ';
                            if (textStatus === 'timeout') {
                                errorMsg = 'Tempo limite excedido. ';
                            } else if (jqXHR.status === 0) {
                                errorMsg = 'Sem conexão com o servidor. ';
                            } else if (jqXHR.status >= 500) {
                                errorMsg = 'Erro interno do servidor. ';
                            }
                            
                            showErrorMessage(errorMsg + 'Tentando reconectar...');
                        },
                        complete: function() {
                            $('#musicas-loading-indicator').fadeOut(200);
                            // Após a atualização do DOM, reinicialize o Sortable
                            inicializarOuAtualizarSortable();
                        }
                    });
                }
                
                // Função para exibir mensagens de erro
                function showErrorMessage(message) {
                    const $errorDiv = $('#error-message');
                    if ($errorDiv.length) {
                        $errorDiv.text(message).fadeIn();
                    } else {
                        $('#sortable-musicas-cantor').before(`<div id="error-message" class="alert alert-warning text-center small" style="display: none;"><i class="bi bi-exclamation-triangle"></i> ${message}</div>`);
                        $('#error-message').fadeIn();
                    }
                    
                    // Remove a mensagem após 5 segundos
                    setTimeout(function() {
                        $('#error-message').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 5000);
                }

                // Chame a função uma vez no carregamento para popular/atualizar a lista e inicializar o Sortable
                atualizarListaMusicasCantor();

                // Configure o intervalo para atualizações futuras
                refreshIntervalId = setInterval(atualizarListaMusicasCantor, 5000); // A cada 3 segundos
            }
        });
    </script>

    <!-- Modal para trocar música -->
    <div class="modal fade" id="trocarMusicaModal" tabindex="-1" aria-labelledby="trocarMusicaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="trocarMusicaModalLabel">Trocar Música</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Container para mensagens de erro na modal -->
                    <div id="modalErrorContainer"></div>
                    
                    <p><strong>Música atual:</strong> <span id="currentMusicInfo"></span></p>
                    <hr>
                    <div class="mb-3">
                        <label for="searchNovaMusica" class="form-label">Buscar nova música:</label>
                        <input type="text" class="form-control" id="searchNovaMusica" placeholder="Digite o título, artista ou código da música...">
                    </div>
                    
                    <!-- Campos hidden para armazenar os IDs -->
                    <input type="hidden" id="musicaCantorIdParaTrocar">
                    <input type="hidden" id="idNovaMusicaSelecionada">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarTrocaMusica">Trocar Música</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configurar autocomplete para busca de músicas na modal de troca
        let searchTimerModal;
        let isSearchingModal = false;
        const searchDelayModal = 300;

        $("#searchNovaMusica").autocomplete({
            source: function(request, response) {
                // Limpa o timer anterior se existir
                clearTimeout(searchTimerModal);
                
                // Define um novo timer para a busca
                searchTimerModal = setTimeout(function() {
                    // Verifica se já está fazendo uma busca
                    if (isSearchingModal) {
                        return;
                    }
                    
                    isSearchingModal = true;
                    
                    $.ajax({
                        url: 'api.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'search_musicas',
                            term: request.term
                        },
                        beforeSend: function() {
                            // Adiciona indicador visual de carregamento
                            $('#searchNovaMusica').addClass('loading');
                        },
                        success: function(data) {
                            if (data.length === 0) {
                                response([{ label: "Nenhum resultado encontrado para '" + request.term + "'", value: "" }]);
                            } else {
                                response($.map(data, function(item) {
                                    return {
                                        label: '<strong style="display: inline-block; width: 47px; text-align: right; padding-right:4px;">' + item.codigo + '</strong> ' + item.titulo + ' - ' + item.artista,
                                        value: item.id_musica,
                                        titulo: item.titulo,
                                        artista: item.artista,
                                        codigo: item.codigo
                                    };
                                }));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Erro na busca de músicas:", {
                                status: status,
                                error: error,
                                responseText: xhr.responseText
                            });
                            response([{ label: "Erro ao buscar resultados. Tente novamente.", value: "" }]);
                        },
                        complete: function() {
                            $('#searchNovaMusica').removeClass('loading');
                            isSearchingModal = false;
                        }
                    });
                }, searchDelayModal);
            },
            minLength: 2,
            delay: 0,
            select: function(event, ui) {
                if (ui.item.value === "") {
                    return false;
                }
                
                // Armazena o ID da música selecionada
                $('#idNovaMusicaSelecionada').val(ui.item.value);
                
                // Atualiza o campo com o título e artista
                $(this).val(ui.item.titulo + ' - ' + ui.item.artista);
                
                return false;
            },
            focus: function(event, ui) {
                return false;
            }
        });

        // Customizar renderização dos itens do autocomplete para modal
        setTimeout(function() {
            if ($("#searchNovaMusica").length > 0 && $("#searchNovaMusica").data("ui-autocomplete")) {
                $("#searchNovaMusica").data("ui-autocomplete")._renderItem = function(ul, item) {
                    if (item.value === "") {
                        return $("<li>")
                            .append($("<div>").text(item.label))
                            .appendTo(ul);
                    }
                    var highlightedLabel = item.label;
                    return $("<li>")
                        .append($("<div>").html(highlightedLabel))
                        .appendTo(ul);
                };
            }
        }, 100);

        // Limpar seleção quando o usuário digita novamente
        $('#searchNovaMusica').on('input', function() {
            if ($(this).val() === '') {
                $('#idNovaMusicaSelecionada').val('');
            }
        });

        // Evento que ocorre quando o modal de troca de música é exibido
        $('#trocarMusicaModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var musicaCantorId = button.data('musica-cantor-id');
            var currentMusicTitle = button.data('current-music-title');

            var modal = $(this);
            modal.find('#musicaCantorIdParaTrocar').val(musicaCantorId);
            modal.find('#currentMusicInfo').text(currentMusicTitle);

            // Limpa o campo de busca e o hidden ID da música selecionada ao abrir o modal
            $('#searchNovaMusica').val('');
            $('#idNovaMusicaSelecionada').val('');
            
            // Limpar mensagens de erro anteriores
            $('#modalErrorContainer').empty();
        });

        // Evento de clique do botão "Trocar Música" dentro do modal
        $('#btnConfirmarTrocaMusica').on('click', function() {
            var musicaCantorId = $('#musicaCantorIdParaTrocar').val();
            var novaMusicaId = $('#idNovaMusicaSelecionada').val();

            if (!musicaCantorId || !novaMusicaId) {
                // Exibir erro na modal
                $('#modalErrorContainer').html('<div class="alert alert-warning alert-dismissible fade show" role="alert">Por favor, selecione uma música.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                return;
            }

            // Limpar mensagens anteriores
            $('#modalErrorContainer').empty();

            // Requisição AJAX para o backend (api.php)
            $.ajax({
                url: 'api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'trocar_musica_cantor',
                    musica_cantor_id: musicaCantorId,
                    nova_musica_id: novaMusicaId
                },
                success: function(response) {
                    if (response.success && response.redirect) {
                        // Redirecionar para recarregar a página e exibir a mensagem de sucesso
                        window.location.reload();
                    } else if (response.success) {
                        $('#trocarMusicaModal').modal('hide');
                        // Atualiza a lista de músicas
                        atualizarListaMusicasCantor();
                        // Exibir sucesso na página principal
                        showAlert('Música trocada com sucesso!', 'success');
                    } else {
                        // Exibir erro na modal
                        $('#modalErrorContainer').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX:", status, error);
                    // Exibir erro na modal
                    $('#modalErrorContainer').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Erro na comunicação com o servidor.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                }
            });
        });
    </script>
</body>
</html>