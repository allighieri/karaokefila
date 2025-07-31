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

    $redirect_cantor_id = $id_cantor ?: $cantor_selecionado_id;

    if ($id_cantor && $id_musica) {
        try {
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
            if ($e->getCode() == '23000') {
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
        header("Location: gerenciar_musicas_cantor.php" . ($redirect_cantor_id ? "?cantor_id=" . $redirect_cantor_id : ""));
        exit;
    }
}

// Obter cantores para o select
$stmtCantores = $pdo->query("SELECT id, nome_cantor FROM cantores ORDER BY nome_cantor ASC");
$cantores_disponiveis = $stmtCantores->fetchAll(PDO::FETCH_ASSOC);

// Obter músicas do cantor selecionado (para exibição inicial)
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

    $redirect_cantor_id = $cantor_id ?: $cantor_selecionado_id;

    if ($musica_cantor_id && $cantor_id) {
        try {
            $pdo->beginTransaction();

            // 1. Obter id_musica e ordem_na_lista da tabela musicas_cantor
            $stmtGetMusicInfo = $pdo->prepare("SELECT id_musica, ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_cantor = ?");
            $stmtGetMusicInfo->execute([$musica_cantor_id, $cantor_id]);
            $musicaInfo = $stmtGetMusicInfo->fetch(PDO::FETCH_ASSOC);

            if (!$musicaInfo) {
                $pdo->rollBack();
                $_SESSION['mensagem_erro'] = "Música não encontrada ou não pertence a este cantor.";
                header("Location: gerenciar_musicas_cantor.php?cantor_id=" . $redirect_cantor_id);
                exit;
            }

            $idMusicaParaRemover = $musicaInfo['id_musica'];
            $ordemRemovida = $musicaInfo['ordem_na_lista'];

            error_log("Tentando remover musica_cantor_id: " . $musica_cantor_id . ", id_cantor: " . $cantor_id . ", id_musica (real): " . $idMusicaParaRemover);

            // 2. Verificar se a música está na fila em status ativo
            $stmtCheckFila = $pdo->prepare(
                "SELECT COUNT(*) FROM fila_rodadas
                 WHERE id_cantor = ?
                   AND musica_cantor_id = ?
                   AND (status = 'aguardando' OR status = 'em_execucao')"
            );
            $stmtCheckFila->execute([$cantor_id, $musica_cantor_id]);
            $isInFila = $stmtCheckFila->fetchColumn();

            error_log("Verificação da fila - id_cantor: " . $cantor_id . ", musica_cantor_id: " . $musica_cantor_id . ", Status na Fila: " . ($isInFila > 0 ? "TRUE" : "FALSE") . " (Count: " . $isInFila . ")");

            if ($isInFila > 0) {
                $pdo->rollBack();
                $_SESSION['mensagem_erro'] = "Não é possível remover a música. Ela está atualmente na fila (selecionada para rodada ou em execução).";
                error_log("Alerta: Tentativa de excluir música (musica_cantor_id: " . $musica_cantor_id . ", Cantor ID: " . $cantor_id . ") que está atualmente na fila. Exclusão não permitida.");
                header("Location: gerenciar_musicas_cantor.php?cantor_id=" . $redirect_cantor_id);
                exit;
            }

            // Se chegou até aqui, a música não está em uso na fila, pode prosseguir com a exclusão
            $stmt = $pdo->prepare("DELETE FROM musicas_cantor WHERE id = ? AND id_cantor = ?");
            if ($stmt->execute([$musica_cantor_id, $cantor_id])) {
                error_log("DEBUG: Música (musica_cantor_id: " . $musica_cantor_id . ") removida com sucesso da musicas_cantor.");

                // Reajusta a ordem das músicas restantes
                $stmtUpdateOrder = $pdo->prepare("
                    UPDATE musicas_cantor
                    SET ordem_na_lista = ordem_na_lista - 1
                    WHERE id_cantor = ? AND ordem_na_lista > ?
                ");
                $stmtUpdateOrder->execute([$cantor_id, $ordemRemovida]);
                error_log("DEBUG: Ordens de musicas_cantor para o cantor " . $cantor_id . " ajustadas. Músicas com ordem > " . $ordemRemovida . " foram decrementadas.");

                // --- INÍCIO DA CORREÇÃO ADICIONAL PARA CUIDAR DO CENÁRIO DE RESET ---

                // 1. Obter o valor atual de proximo_ordem_musica para o cantor
                $stmtGetProximoOrdemCantor = $pdo->prepare("SELECT proximo_ordem_musica FROM cantores WHERE id = ?");
                $stmtGetProximoOrdemCantor->execute([$cantor_id]);
                $proximoOrdemCantorAtual = $stmtGetProximoOrdemCantor->fetchColumn();
                error_log("DEBUG: proximo_ordem_musica atual do cantor " . $cantor_id . ": " . ($proximoOrdemCantorAtual !== false ? $proximoOrdemCantorAtual : 'NULL/false'));

                // 2. Encontrar a menor ordem_na_lista disponível para o cantor (status 'aguardando' ou 'pulou')
                $stmtGetMinOrdemDisponivel = $pdo->prepare("
                    SELECT MIN(ordem_na_lista)
                    FROM musicas_cantor
                    WHERE id_cantor = ? AND status IN ('aguardando', 'pulou')
                ");
                $stmtGetMinOrdemDisponivel->execute([$cantor_id]);
                $minOrdemDisponivel = $stmtGetMinOrdemDisponivel->fetchColumn(); // Retorna NULL se não houver registros

                error_log("DEBUG: Menor ordem disponível (aguardando/pulou) para o cantor " . $cantor_id . ": " . ($minOrdemDisponivel !== false ? ($minOrdemDisponivel ?? 'NULL') : 'NULL/false'));

                $novaProximaOrdemCantor = $proximoOrdemCantorAtual; // Inicializa com o valor atual

                if ($minOrdemDisponivel === null) {
                    // Cenario: Cantor ficou sem músicas 'aguardando' ou 'pulou'.
                    // Precisamos garantir que proximo_ordem_musica seja 1 para que,
                    // ao adicionar novas músicas, elas sejam selecionáveis a partir da ordem 1.
                    if ($proximoOrdemCantorAtual === null || $proximoOrdemCantorAtual > 1) { // Verifica se já não é 1
                        $novaProximaOrdemCantor = 1;
                        error_log("DEBUG: Cantor " . $cantor_id . " sem músicas aguardando/pulou. proximo_ordem_musica será ajustado para 1 para futuras adições.");
                    }
                } else {
                    // Cenário normal: há músicas 'aguardando' ou 'pulou'.
                    // Se o proximo_ordem_musica atual for NULL ou maior que a menor ordem disponível, ajuste-o.
                    if ($proximoOrdemCantorAtual === null || $proximoOrdemCantorAtual > $minOrdemDisponivel) {
                        $novaProximaOrdemCantor = $minOrdemDisponivel;
                        error_log("DEBUG: Ajustando proximo_ordem_musica do cantor " . $cantor_id . " de " . ($proximoOrdemCantorAtual !== false ? $proximoOrdemCantorAtual : 'NULL') . " para " . $novaProximaOrdemCantor . " (menor ordem disponível).");
                    }
                }

                // Apenas atualize se o valor realmente mudou para evitar writes desnecessários
                // E garanta que o valor não é NULL (embora a lógica acima previna isso para $novaProximaOrdemCantor)
                if ($proximoOrdemCantorAtual != $novaProximaOrdemCantor && $novaProximaOrdemCantor !== false && $novaProximaOrdemCantor !== null) {
                    $stmtUpdateCantorProximaOrdem = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
                    $stmtUpdateCantorProximaOrdem->execute([$novaProximaOrdemCantor, $cantor_id]);
                    error_log("INFO: proximo_ordem_musica do cantor " . $cantor_id . " finalizado em: " . $novaProximaOrdemCantor . ".");
                } else {
                    error_log("DEBUG: proximo_ordem_musica do cantor " . $cantor_id . " permaneceu em " . ($proximoOrdemCantorAtual !== false ? $proximoOrdemCantorAtual : 'NULL') . " (nenhuma mudança necessária).");
                }

                // --- FIM DA CORREÇÃO ADICIONAL ---


                $pdo->commit();
                $_SESSION['mensagem_sucesso'] = "Música removida com sucesso!";
            } else {
                $pdo->rollBack();
                $_SESSION['mensagem_erro'] = "Erro ao remover música da lista do cantor.";
                error_log("Erro: Falha na execução do DELETE para musica_cantor_id: " . $musica_cantor_id . ", cantor_id: " . $cantor_id);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['mensagem_erro'] = "Erro de banco de dados ao remover música: " . $e->getMessage();
            error_log("Erro ao remover música do cantor (PDOException): " . $e->getMessage());
        }
    } else {
        $_SESSION['mensagem_erro'] = "ID de música do cantor ou ID do cantor inválido para remoção.";
        error_log("Alerta: Tentativa de remover música com ID de música do cantor ou ID do cantor inválido. MC ID: " . $musica_cantor_id . ", Cantor ID: " . $cantor_id);
    }
    header("Location: gerenciar_musicas_cantor.php?cantor_id=" . $redirect_cantor_id);
    exit;
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
</head>
<body>
    <?php include_once 'inc/nav.php'; ?>
    <div class="container mt-5">
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
                                $statusSortable = '';
                                switch($musica['status']) {
                                    case 'aguardando': $statusClass = 'badge-info'; $statusText = 'Aguardando'; $statusSortable = 'aguardando'; break;
                                    case 'cantou': $statusClass = 'badge-success'; $statusText = 'Cantou'; $statusSortable = 'cantou'; break;
                                    case 'pulou': $statusClass = 'badge-warning'; $statusText = 'Pulou'; $statusSortable = 'pulou'; break;
                                    case 'selecionada_para_rodada': $statusClass = 'badge-primary'; $statusText = 'Selecionada para a rodada atual'; $statusSortable = 'selecionada_para_rodada'; break;
                                    case 'em_execucao': $statusClass = 'badge-danger'; $statusText = 'EM EXECUÇÃO'; $statusSortable = 'em_execucao'; break;
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
                                    case 'selecionada_para_rodada': statusClass = 'badge-primary'; statusText = '⏳ Selecionada para a rodada atual'; statusSortable = 'selecionada_para_rodada'; break;
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
                                            <button type="submit">Remover</button>
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