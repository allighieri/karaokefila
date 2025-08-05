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


    <form method="GET" action="musicas_cantores.php">
        <input type="hidden" name="action" value="add_cantor">
        <div class="row mb-3">
            <div class="col-md-6">
                <select id="cantor_id" name="cantor_id" class="form-select" onchange="this.form.submit()" required>
                    <option value="">Selecione um(a) cantor(a)</option>

                    <?php foreach ($cantores_disponiveis as $cantor): ?>
                        <option value="<?php echo htmlspecialchars($cantor['id']); ?>"
                            <?php echo ($cantor_selecionado_id == $cantor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cantor['nome_cantor']); ?>
                        </option>
                    <?php endforeach; ?>

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

        <form method="POST">
            <input type="hidden" name="action" value="add_musica_cantor">
            <input type="hidden" name="id_cantor" value="<?php echo htmlspecialchars($cantor_selecionado_id); ?>">
            <input type="hidden" id="id_musica" name="id_musica" required>
            <label for="search_musica"><small>Pesquisar Música por título, artista, código ou trecho...</small></label>

            <div class="row">
                <div class="col-12 col-lg-12">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="search_musica" placeholder="Digite para buscar músicas..." autocomplete="off" required>
                        <button class="btn btn-success" type="submit" id="button-addon2">Add música</button>
                    </div>
                </div>
            </div>
        </form>
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
                                <span><?php echo htmlspecialchars($musica['titulo']); ?> (<?php echo htmlspecialchars($musica['artista']); ?>) - Código: <?php echo htmlspecialchars($musica['codigo']); ?></span>
                                <br>
                                <small><span class="status-text badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></small>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove_musica_cantor">
                                <input type="hidden" name="musica_cantor_id" value="<?php echo htmlspecialchars($musica['musica_cantor_id']); ?>">
                                <input type="hidden" name="cantor_id" value="<?php echo htmlspecialchars($cantor_selecionado_id); ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    <?php elseif (empty($cantores_disponiveis)): ?>
        <p>Para escolher a música para cantar, primeiro cadastre um cantor no menu <a href="cantores.php">Cantores</a>.</p>
    <?php endif; ?>

    <?php include_once 'modal_resetar_sistema.php'?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://localhost/fila/js/resetar_sistema.js"></script>

    <script>
        $(document).ready(function() {

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

            // Inicialize o Autocomplete (manter como está, está funcional)
            $("#search_musica").autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: 'api.php', // Endpoint da sua API
                        type: 'GET',
                        dataType: 'json',
                        data: {
                            action: 'search_musicas', // Nova ação para o backend
                            term: request.term // Termo digitado pelo usuário
                        },
                        success: function(data) {
                            if (data.length === 0) {
                                response([{ label: "Nenhum resultado encontrado!", value: "" }]);
                            } else {
                                response($.map(data, function(item) {
                                    return {
                                        label: item.titulo + ' (' + item.artista + ')',
                                        value: item.id_musica,
                                        titulo: item.titulo,
                                        artista: item.artista,
                                        codigo: item.codigo
                                    };
                                }));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Erro na busca de músicas:", status, error);
                            response([{ label: "Erro ao buscar resultados.", value: "" }]);
                        }
                    });
                },
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item.value === "") {
                        event.preventDefault();
                        return false;
                    }
                    $('#id_musica').val(ui.item.value);
                    $(this).val(ui.item.label.replace(/<strong>|<\/strong>/g, ''));
                    return false;
                },
                focus: function(event, ui) {
                    if (ui.item.value === "") {
                        event.preventDefault();
                    }
                    return false;
                }
            });

            if ($("#search_musica").data("ui-autocomplete")) {
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
            } else {
                console.error("jQuery UI Autocomplete não inicializado em #search_musica.");
            }

            // Lógica de validação do formulário no momento do submit (manter como está)
            $('form[action="musicas_cantores.php"][method="POST"]').on('submit', function(event) {
                const idMusicaInput = $('#id_musica');
                const searchMusicaInput = $('#search_musica');

                if (!idMusicaInput.val()) {
                    alert("Por favor, selecione uma música da lista de sugestões.");
                    searchMusicaInput.focus();
                    event.preventDefault();
                    return false;
                }
            });

            // Limpar o campo hidden se o campo de texto for esvaziado ou alterado manualmente (manter como está)
            $('#search_musica').on('input', function() {
                if ($(this).val() === '') {
                    $('#id_musica').val('');
                }
            });

            // Inicialização do Sortable (mover para a função de atualização para ser reinicializado)
            const $sortableList = $("#sortable-musicas-cantor");

            const urlParams = new URLSearchParams(window.location.search);
            const idCantorAtual = urlParams.get('cantor_id');
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
                    //revert: 200,
                    cursor: "grabbing",
                    items: "li:not([data-status='cantou']):not([data-status='em_execucao']):not([data-status='selecionada_para_rodada'])",
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
                            var idCantor = <?php echo json_encode($cantor_selecionado_id); ?>;

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
                                        // Força uma atualização para refletir a ordem do servidor e reavaliar Sortable
                                        atualizarListaMusicasCantor();
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

                    $.ajax({
                        url: 'api.php',
                        method: 'GET',
                        data: {
                            action: 'get_musicas_cantor_atualizadas',
                            id_cantor: idCantorAtual
                        },
                        dataType: 'json',
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
                                            $item = $(`<li class="queue-item" data-musica-cantor-id="${musicaCantorId}" data-id-musica="${musica.id_musica}" data-id-cantor="${musica.id_cantor}" data-status="${statusSortable}">
                                        <div>
                                            <span class="ordem-numero"></span>
                                            <span></span>
                                            <br>
                                            <small><strong>Status:</strong> <span class="status-text badge"></span></small>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="remove_musica_cantor">
                                            <input type="hidden" name="musica_cantor_id" value="${musicaCantorId}">
                                            <input type="hidden" name="cantor_id" value="${idCantorAtual}">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </li>`);
                                            currentDomItems[musicaCantorId] = $item;
                                        }

                                        // Atualiza o conteúdo e classes do item existente ou recém-criado
                                        $item.find('.ordem-numero').text(musica.ordem_na_lista + '.');
                                        $item.find('div > span:eq(1)').text(`${musica.titulo} (${musica.artista}) - Código: ${musica.codigo}`);
                                        $item.find('.status-text').text(statusText).removeClass().addClass(`status-text badge ${statusClass}`);

                                        // As linhas cruciais para atualizar o data-status no HTML
                                        $item.data('status', statusSortable); // Atualiza o cache do jQuery
                                        $item.attr('data-status', statusSortable); // Atualiza o atributo no DOM

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
                                            currentDomItems[id].remove();
                                        }
                                    });

                                    $musicasListContainer.empty().append(fragment);
                                }
                            } else {
                                console.error("Erro na resposta da API:", response.message);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error("Erro na requisição AJAX:", textStatus, errorThrown);
                        },
                        complete: function() {
                            // Após a atualização do DOM, reinicialize o Sortable
                            inicializarOuAtualizarSortable();
                        }
                    });
                }

                // Chame a função uma vez no carregamento para popular/atualizar a lista e inicializar o Sortable
                atualizarListaMusicasCantor();

                // Configure o intervalo para atualizações futuras
                refreshIntervalId = setInterval(atualizarListaMusicasCantor, 3000); // A cada 3 segundos
            }
        });
    </script>
</body>
</html>