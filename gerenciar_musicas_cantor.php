<?php
session_start();
// Ativar exibição de erros para depuração (desativar em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'funcoes_fila.php'; // Inclui as funções e a conexão PDO

$mensagem_sucesso = '';
$mensagem_erro = '';

// --- Lógica para exibir mensagens da sessão após redirecionamento ---
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem_sucesso = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']); // Limpa a mensagem após exibir
}
if (isset($_SESSION['mensagem_erro'])) {
    $mensagem_erro = $_SESSION['mensagem_erro'];
    unset($_SESSION['mensagem_erro']); // Limpa a mensagem após exibir
}
// ---------------------------------------------------------------------

// Obter o ID do cantor da URL para uso nos redirecionamentos
$cantor_selecionado_id = filter_input(INPUT_GET, 'cantor_id', FILTER_VALIDATE_INT);

// Adicionar música ao cantor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_musica_cantor') {
    $id_cantor = filter_input(INPUT_POST, 'id_cantor', FILTER_VALIDATE_INT);
    $id_musica = filter_input(INPUT_POST, 'id_musica', FILTER_VALIDATE_INT);

    // Prioriza o ID do cantor enviado pelo formulário, caso o GET não esteja presente (o que não deve acontecer aqui)
    $redirect_cantor_id = $id_cantor ?: $cantor_selecionado_id;

    if ($id_cantor && $id_musica) {
        try {
            // Encontrar a próxima ordem disponível para este cantor
            $stmtLastOrder = $pdo->prepare("SELECT MAX(ordem_na_lista) AS max_order FROM musicas_cantor WHERE id_cantor = ?");
            $stmtLastOrder->execute([$id_cantor]);
            $lastOrder = $stmtLastOrder->fetchColumn();
            $proximaOrdem = ($lastOrder !== null) ? $lastOrder + 1 : 1;

            $stmt = $pdo->prepare("INSERT INTO musicas_cantor (id_cantor, id_musica, ordem_na_lista) VALUES (?, ?, ?)");
            if ($stmt->execute([$id_cantor, $id_musica, $proximaOrdem])) {
                $_SESSION['mensagem_sucesso'] = "Música adicionada à lista do cantor com sucesso!";
                header("Location: gerenciar_musicas_cantor.php?cantor_id=" . $redirect_cantor_id);
                exit;
            } else {
                $_SESSION['mensagem_erro'] = "Erro ao adicionar música à lista do cantor.";
                header("Location: gerenciar_musicas_cantor.php?cantor_id=" . $redirect_cantor_id);
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Código SQLSTATE para violação de integridade (geralmente duplicidade)
                $_SESSION['mensagem_erro'] = "Esta música já está na lista do cantor.";
            } else {
                $_SESSION['mensagem_erro'] = "Erro de banco de dados: " . $e->getMessage();
            }
            error_log("Erro ao adicionar música ao cantor: " . $e->getMessage());
            header("Location: gerenciar_musicas_cantor.php?cantor_id=" . $redirect_cantor_id);
            exit;
        }
    } else {
        $_SESSION['mensagem_erro'] = "Dados inválidos para adicionar música ao cantor.";
        // Redireciona de volta para a mesma página, mantendo o cantor_id se possível
        header("Location: gerenciar_musicas_cantor.php" . ($redirect_cantor_id ? "?cantor_id=" . $redirect_cantor_id : ""));
        exit;
    }
}

// Obter cantores para o select
$stmtCantores = $pdo->query("SELECT id, nome_cantor FROM cantores ORDER BY nome_cantor ASC");
$cantores_disponiveis = $stmtCantores->fetchAll(PDO::FETCH_ASSOC);

// Obter músicas do cantor selecionado (para exibição inicial)
// Já foi definido acima: $cantor_selecionado_id = filter_input(INPUT_GET, 'cantor_id', FILTER_VALIDATE_INT);
$musicas_do_cantor = [];
if ($cantor_selecionado_id) {
    $stmtMusicasCantor = $pdo->prepare("
        SELECT
            mc.id AS musica_cantor_id,
            m.id AS id_musica,
            m.titulo,
            m.artista,
            m.codigo,
            mc.ordem_na_lista,
            mc.status,
            mc.timestamp_ultima_execucao
        FROM musicas_cantor mc
        JOIN musicas m ON mc.id_musica = m.id
        WHERE mc.id_cantor = ?
        ORDER BY mc.ordem_na_lista ASC
    ");
    $stmtMusicasCantor->execute([$cantor_selecionado_id]);
    $musicas_do_cantor = $stmtMusicasCantor->fetchAll(PDO::FETCH_ASSOC);
}

// Remover música do cantor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_musica_cantor') {
    $musica_cantor_id = filter_input(INPUT_POST, 'musica_cantor_id', FILTER_VALIDATE_INT);
    $cantor_id = filter_input(INPUT_POST, 'cantor_id', FILTER_VALIDATE_INT);

    if ($musica_cantor_id && $cantor_id) {
        try {
            $pdo->beginTransaction();

            $stmtGetOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_cantor = ?");
            $stmtGetOrder->execute([$musica_cantor_id, $cantor_id]);
            $ordemRemovida = $stmtGetOrder->fetchColumn();

            if ($ordemRemovida) {
                $stmt = $pdo->prepare("DELETE FROM musicas_cantor WHERE id = ? AND id_cantor = ?");
                if ($stmt->execute([$musica_cantor_id, $cantor_id])) {
                    $stmtUpdateOrder = $pdo->prepare("
                        UPDATE musicas_cantor
                        SET ordem_na_lista = ordem_na_lista - 1
                        WHERE id_cantor = ? AND ordem_na_lista > ?
                    ");
                    $stmtUpdateOrder->execute([$cantor_id, $ordemRemovida]);

                    $pdo->commit();
                    $_SESSION['mensagem_sucesso'] = "Música removida com sucesso!";
                } else {
                    $pdo->rollBack();
                    $_SESSION['mensagem_erro'] = "Erro ao remover música da lista do cantor.";
                }
            } else {
                $pdo->rollBack();
                $_SESSION['mensagem_erro'] = "Música não encontrada ou não pertence a este cantor.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['mensagem_erro'] = "Erro de banco de dados ao remover música: " . $e->getMessage();
            error_log("Erro ao remover música do cantor: " . $e->getMessage());
        }
    } else {
        $_SESSION['mensagem_erro'] = "ID de música do cantor ou ID do cantor inválido para remoção.";
    }
    header("Location: gerenciar_musicas_cantor.php?cantor_id=" . $cantor_id);
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Músicas do Cantor</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style_gerenciar_musicas.css">
</head>
<body>
    <div class="container">
        <h1>Gerenciar Músicas por Cantor</h1>

        <?php if ($mensagem_sucesso != ''): ?>
            <div class="alert success"><?php echo htmlspecialchars($mensagem_sucesso); ?></div>
        <?php endif; ?>
        <?php if ($mensagem_erro != ''): ?>
            <div class="alert error"><?php echo htmlspecialchars($mensagem_erro); ?></div>
        <?php endif; ?>

        <p><a href="index.php">&larr; Voltar para o Painel Principal</a></p>

        <h2>Selecionar Cantor</h2>
        <form method="GET" action="gerenciar_musicas_cantor.php">
            <label for="cantor_id">Selecione o Cantor:</label>
            <select id="cantor_id" name="cantor_id" onchange="this.form.submit()">
                <option value="">-- Selecione um Cantor --</option>
                <?php foreach ($cantores_disponiveis as $cantor): ?>
                    <option value="<?php echo htmlspecialchars($cantor['id']); ?>"
                        <?php echo ($cantor_selecionado_id == $cantor['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cantor['nome_cantor']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

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
                                switch($musica['status']) {
                                    case 'aguardando': $statusClass = 'badge-info'; $statusText = 'Aguardando'; break;
                                    case 'cantou': $statusClass = 'badge-success'; $statusText = 'Cantou'; break;
                                    case 'pulou': $statusClass = 'badge-warning'; $statusText = 'Pulou'; break;
                                    case 'selecionada_para_rodada': $statusClass = 'badge-primary'; $statusText = 'Selecionada para a rodada atual'; break;
                                    case 'em_execucao': $statusClass = 'badge-danger'; $statusText = 'EM EXECUÇÃO'; break;
                                    default: $statusClass = 'badge-muted'; $statusText = 'Desconhecido';
                                }
                                $isCurrentlyPlaying = ($musica['status'] == 'em_execucao');
                            ?>
                            <li class="queue-item <?php echo $isCurrentlyPlaying ? 'list-group-item-danger' : ''; ?>"
                                data-musica-cantor-id="<?php echo htmlspecialchars($musica['musica_cantor_id']); ?>"
                                data-id-musica="<?php echo htmlspecialchars($musica['id_musica']); ?>"
                                data-id-cantor="<?php echo htmlspecialchars($cantor_selecionado_id); ?>">
                                <div>
                                    <span class="ordem-numero"><?php echo htmlspecialchars($musica['ordem_na_lista']); ?>.</span>
                                    <span><?php echo htmlspecialchars($musica['titulo']); ?> (<?php echo htmlspecialchars($musica['artista']); ?>) - Código: <?php echo htmlspecialchars($musica['codigo']); ?></span>
                                    <br>
                                    <small><strong>Status:</strong> <span class="status-text badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></small>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="remove_musica_cantor">
                                    <input type="hidden" name="musica_cantor_id" value="<?php echo htmlspecialchars($musica['musica_cantor_id']); ?>">
                                    <input type="hidden" name="cantor_id" value="<?php echo htmlspecialchars($cantor_selecionado_id); ?>">
                                    <button type="submit">Remover</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <h3>Adicionar Nova Música para <?php echo htmlspecialchars($nome_cantor_selecionado); ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_musica_cantor">
                <input type="hidden" name="id_cantor" value="<?php echo htmlspecialchars($cantor_selecionado_id); ?>">
                <label for="search_musica">Pesquisar Música (Título, Artista, Código ou Trecho...):</label>
                <input type="text" id="search_musica" placeholder="Digite para buscar músicas..." autocomplete="off">
                <input type="hidden" id="id_musica" name="id_musica" required>
                <button type="submit">Adicionar Música ao Cantor</button>
            </form>
        <?php elseif (empty($cantores_disponiveis)): ?>
            <p>Por favor, adicione cantores primeiro no <a href="index.php">Painel Principal</a>.</p>
        <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <script>
    $(document).ready(function() {
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
                        // MODIFICADO: Verifica se o array de dados está vazio
                        if (data.length === 0) {
                            response([{ label: "Nenhum resultado encontrado!", value: "" }]); // Exibe a mensagem
                        } else {
                            response($.map(data, function(item) {
                                return {
                                    // O label original, que será destacado posteriormente pelo _renderItem
                                    label: item.titulo + ' (' + item.artista + ')',
                                    value: item.id_musica,
                                    titulo: item.titulo,
                                    artista: item.artista,
                                    codigo: item.codigo // Adicione o código se for usar
                                };
                            }));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro na busca de músicas:", status, error);
                        // MODIFICADO: Exibir mensagem de erro ao usuário se houver problema na requisição
                        response([{ label: "Erro ao buscar resultados.", value: "" }]);
                    }
                });
            },
            minLength: 1, // Mudei para 2 para ser mais eficiente na busca por autocomplete
            select: function(event, ui) {
                // MODIFICADO: Se o item selecionado for a mensagem "Nenhum resultado encontrado!", não faz nada
                if (ui.item.value === "") {
                    event.preventDefault(); // Impede que o valor seja inserido no campo
                    return false;
                }
                // Quando uma música é selecionada, preenche o campo hidden com o ID
                $('#id_musica').val(ui.item.value);
                // MODIFICADO: Remove as tags <strong> do label antes de colocar no campo de texto
                $(this).val(ui.item.label.replace(/<strong>|<\/strong>/g, ''));
                return false; // Previne que o valor do label seja automaticamente colocado no campo de texto
            },
            focus: function(event, ui) {
                // MODIFICADO: Previne que o valor do label seja automaticamente colocado no campo de texto ao navegar com setas
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
    $('form[action="gerenciar_musicas_cantor.php"][method="POST"]').on('submit', function(event) {
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

    // Inicialização e lógica do Sortable
    const $sortableList = $("#sortable-musicas-cantor");

    if ($sortableList.length) {
        $sortableList.sortable({
            axis: "y",
            placeholder: "ui-sortable-placeholder",
            helper: "clone",
            revert: 200,
            cursor: "grabbing",
            start: function(event, ui) {
                isDragging = true;
                // Desabilita as atualizações da lista quando o arrasto começa
                clearInterval(refreshIntervalId); 
            },
            stop: function(event, ui) {
                isDragging = false;
                // Reabilita as atualizações após o arrasto parar
                refreshIntervalId = setInterval(atualizarListaMusicasCantor, 3000); 
            },
            update: function(event, ui) {
                // Se a atualização foi disparada por drag and drop (e não por um refresh externo)
                if (this === ui.item.parent()[0]) { // Verifica se a atualização é no próprio sortable
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
                                // Força uma atualização para refletir a ordem do servidor
                                // Isso é crucial para sincronizar o DOM com o banco de dados após o drag
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
        $sortableList.disableSelection();
    }

    const urlParams = new URLSearchParams(window.location.search);
    const idCantorAtual = urlParams.get('cantor_id');
    let refreshIntervalId; // Variável para armazenar o ID do intervalo

    // Só tenta buscar atualizações se um cantor estiver selecionado
    if (idCantorAtual) {
        function atualizarListaMusicasCantor() {
            // Se estiver arrastando, não faça nada. O intervalo foi parado em `start`.
            if (isDragging) {
                console.log("Drag em progresso, pulando atualização da lista.");
                return;
            }
            
            // É seguro desabilitar o sortable aqui, já que o isDragging garante que não estamos no meio de um drag
            // Mas reabilitaremos no `complete`
            if ($sortableList.sortable("instance") && $sortableList.sortable("option", "disabled") === false) {
                 $sortableList.sortable("disable");
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
                            let fragment = document.createDocumentFragment(); // Para otimizar a atualização do DOM

                            musicasAPI.forEach(function(musica) {
                                const musicaCantorId = musica.id; // Isso vem do banco como mc.id
                                let $item = currentDomItems[musicaCantorId];

                                const isCurrentlyPlaying = (musicaEmExecucaoGeral &&
                                                            musica.id_musica == musicaEmExecucaoGeral.id_musica &&
                                                            musica.id_cantor == musicaEmExecucaoGeral.id_cantor);
                                                            
                                let statusClass = '';
                                let statusText = '';
                                switch(musica.status) {
                                    case 'aguardando': statusClass = 'badge-info'; statusText = 'Aguardando'; break;
                                    case 'cantou': statusClass = 'badge-success'; statusText = 'Cantou'; break;
                                    case 'pulou': statusClass = 'badge-warning'; statusText = 'Pulou'; break;
                                    case 'selecionada_para_rodada': statusClass = 'badge-primary'; statusText = 'Selecionada para a rodada atual'; break;
                                    case 'em_execucao': statusClass = 'badge-danger'; statusText = 'EM EXECUÇÃO'; break;
                                    default: statusClass = 'badge-muted'; statusText = 'Desconhecido';
                                }

                                if (!$item) {
                                    // Cria um novo item se não existir no DOM
                                    $item = $(`<li class="queue-item" data-musica-cantor-id="${musicaCantorId}" data-id-musica="${musica.id_musica}" data-id-cantor="${musica.id_cantor}">
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
                                                <button type="submit">Remover</button>
                                            </form>
                                        </li>`);
                                    currentDomItems[musicaCantorId] = $item; // Adiciona ao cache
                                }

                                // Atualiza o conteúdo e classes do item existente ou recém-criado
                                $item.find('.ordem-numero').text(musica.ordem_na_lista + '.');
                                $item.find('div > span:eq(1)').text(`${musica.titulo} (${musica.artista}) - Código: ${musica.codigo}`);
                                $item.find('.status-text').text(statusText).removeClass().addClass(`status-text badge ${statusClass}`);
                                
                                if (isCurrentlyPlaying) {
                                    $item.addClass('list-group-item-danger');
                                } else {
                                    $item.removeClass('list-group-item-danger');
                                }

                                // Adiciona o item (existente ou novo) ao fragmento na ordem correta
                                fragment.appendChild($item[0]); // Pega o elemento DOM nativo do jQuery object
                                newOrderMusicaIds.push(musicaCantorId);
                            });

                            // Remove itens do DOM que não estão mais na lista da API
                            Object.keys(currentDomItems).forEach(function(id) {
                                if (newOrderMusicaIds.indexOf(parseInt(id)) === -1) {
                                    currentDomItems[id].remove();
                                }
                            });

                            // Limpa a lista atual e anexa o fragmento (melhor performance)
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
                    // Reabilitar o sortable APÓS todas as manipulações do DOM
                    if ($sortableList.sortable("instance")) {
                        $sortableList.sortable("enable");
                    }
                }
            });
        }

        // Chame a função uma vez no carregamento para popular/atualizar a lista
        atualizarListaMusicasCantor();

        // Configure o intervalo para atualizações futuras
        refreshIntervalId = setInterval(atualizarListaMusicasCantor, 3000); // A cada 3 segundos
    }
});
    </script>
</body>
</html>