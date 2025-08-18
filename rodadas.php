<?php
require_once 'init.php';
require_once 'funcoes_fila.php';
require_once 'funcoes_music_history.php';


if (!check_access(NIVEL_ACESSO, ['admin', 'mc'])) {
    header("Location: " . $rootPath . "login");
    exit();
}

if (!empty($pdo)) {
    $rodada_atual = getRodadaAtual($pdo, ID_TENANTS);
}
$musica_em_execucao = getMusicaEmExecucao($pdo);
$proxima_musica_aguardando = getProximaMusicaFila($pdo);
$fila_completa = getFilaCompleta($pdo); // Esta é a lista completa da rodada

$current_page = pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="icos/android-icon-144x144.png" itemprop="image">
    <title>Gerenciador de Karaokê - MC Panel</title>
    <link rel="shortcut icon" href="icos/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="57x57" href="icos/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="icos/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="icos/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="icos/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="icos/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="icos/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="icos/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="icos/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icos/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="icos/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icos/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="icos/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icos/favicon-16x16.png">
    <link rel="manifest" href="icos/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="css/style_index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Estilo para adicionar rolagem ao dropdown do autocomplete */
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1050; /* Garante que fique acima do modal */
        }
        
        /* Previne que o dropdown seja cortado pelo modal */
        .ui-front {
            z-index: 1050;
        }
    </style>

</head>
<body>

<?php include_once 'inc/nav.php'; ?>

<div class="container">

    <h1><?php echo NOME_TENANT ;?></h1>



    <div id="alertContainer" class="mt-3"></div>
    <?php if (!$musica_em_execucao): ?>
        <p><strong><?php echo count($fila_completa); ?></strong> <?php echo (count($fila_completa) > 1) ? 'músicas foram cantadas' : 'música foi cantada'; ?> nessa rodada.</p>
    <?php else: ?>
        <p><strong><?php echo count($fila_completa); ?></strong> <?php echo (count($fila_completa) > 1) ? 'músicas' : 'música'; ?> nessa rodada.</p>
    <?php endif; ?>

    <?php if ($musica_em_execucao): ?>
        <div class="current-song">
            <h3 class="text-danger h2"><strong>CANTANDO AGORA</strong></h3>
            <h4 class="h4 text-uppercase"><i class="bi bi-file-earmark-person h5"></i> <strong><?php echo htmlspecialchars($musica_em_execucao['nome_cantor']); ?></strong></h4>
            <p class="mt-2 mb-0"><i class="bi bi-pin-angle h5"></i> <?php echo htmlspecialchars($musica_em_execucao['nome_mesa']); ?></p>
            <p class="mt-2 mb-0"><i class="bi bi-file-music h5"></i> <strong><?php echo htmlspecialchars($musica_em_execucao['titulo_musica']); ?> - <?php echo htmlspecialchars($musica_em_execucao['codigo_musica']); ?></strong></p>
            <p class="mt-2 mb-0"><i class="bi bi-mic  h5"></i> <?php echo htmlspecialchars($musica_em_execucao['artista_musica']); ?></p>
            <div class="actions btn-group-sm mt-3">
                <button type="button" class="btn btn-success me-2" onclick="finalizarMusica(<?php echo $musica_em_execucao['fila_id']; ?>)" title="Próxima"><i class="bi bi-arrow-right"></i></button>
                <button type="button" class="btn btn-danger me-2" onclick="pularMusica(<?php echo $musica_em_execucao['fila_id']; ?>)" title="Pular"><i class="bi bi-arrow-up-right"></i></button>
                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#trocarMusicaModal" data-fila-id="<?php echo $musica_em_execucao['fila_id']; ?>" data-current-music-title="<?php echo htmlspecialchars($musica_em_execucao['titulo_musica'] . ' (' . $musica_em_execucao['artista_musica'] . ')'); ?>" title="Trocar Música"><i class="bi bi-arrow-left-right"></i></button>
            </div>
        </div>
    <?php else: ?>

        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <span>Monte uma nova rodada!</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>



        <div class="form-section">
            <form id="formMontarRodada">
                <div class="mb-3">
                    <label class="form-label fs-4">Escolha o modo da rodada:</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modo_fila" id="modoMesa" value="mesa" checked>
                        <label class="form-check-label" for="modoMesa">Por Mesa</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modo_fila" id="modoCantor" value="cantor">
                        <label class="form-check-label" for="modoCantor">Por Cantores</label>
                    </div>
                    <div class="form-text">
                        <p class="mb-0">A escolha por mesa limita o número de músicas que uma mesa tem direito na rodada...</p>

                        <div class="collapse" id="collapseHelpText">
                            <p class="mb-1">Se uma mesa tiver direito a 3 músicas por rodada e tiver mais de 3 pessoas cantando, as demais pessoas cantarão somente na próxima rodada.</p>
                            <p>As 3 primeiras pessoas que cantaram, só voltarão a cantar em uma rodada em que todos da sua mesa já tiverem cantado.</p>
                        </div>

                        <a class="btn btn-link p-0 text-decoration-none" data-bs-toggle="collapse" href="#collapseHelpText" role="button" aria-expanded="false" aria-controls="collapseHelpText">
                            <span class="collapsed-text">Ler mais &#9660;</span>
                            <span class="expanded-text d-none">Ler menos &#9650;</span>
                        </a>
                    </div>
                </div>
                <button type="submit" class="btn btn-success mb-3" id="btnMontarRodada">Montar Nova Rodada</button>
            </form>
        </div>



    <?php endif; ?>

    <hr class="mt-3"/>


    <h2>Fila Completa da Rodada <?php echo $rodada_atual; ?></h2>
    <?php if (empty($fila_completa)): ?>
        <p>A fila está vazia. Adicione cantores ou monte a próxima rodada.</p>
    <?php else: ?>

        <ul class="queue-list" id="sortable-queue">
            <?php
            $musica_em_execucao_na_fila = null;
            foreach ($fila_completa as $idx => $item_check) {
                if ($item_check['status'] === 'em_execucao') {
                    $musica_em_execucao_na_fila = $item_check;
                    break;
                }
            }

            $next_music_to_display_id = null;
            $found_current_song_or_skipped = false;
            foreach ($fila_completa as $item_for_next_check) {
                if ($musica_em_execucao_na_fila && $item_for_next_check['fila_id'] == $musica_em_execucao_na_fila['fila_id']) {
                    $found_current_song_or_skipped = true;
                    continue;
                }

                if ($found_current_song_or_skipped && $item_for_next_check['status'] === 'aguardando') {
                    $next_music_to_display_id = $item_for_next_check['fila_id'];
                    break;
                } elseif (!$musica_em_execucao_na_fila && $item_for_next_check['status'] === 'aguardando') {
                    $next_music_to_display_id = $item_for_next_check['fila_id'];
                    break;
                }
            }
            ?>
            <?php foreach ($fila_completa as $item): ?>
                <?php
                if ($musica_em_execucao_na_fila && $item['fila_id'] == $musica_em_execucao_na_fila['fila_id']) {
                    continue;
                }

                $item_class = 'queue-item';

                if ($item['status'] == 'cantou') {
                    $item_class .= ' completed';
                } elseif ($item['status'] == 'pulou') {
                    $item_class .= ' skipped';
                }

                if ($item['fila_id'] === $next_music_to_display_id && $item['status'] === 'aguardando') {
                    $item_class .= ' next-up';
                }
                ?>
                <li class="<?php echo trim($item_class); ?>" data-id="<?php echo htmlspecialchars($item['fila_id']); ?>">
                    <div>
                        <?php if ($item['fila_id'] === $next_music_to_display_id) { echo '<p class="proxima_musica mb-0 text-danger"><strong>PRÓXIMA MÚSICA</strong></p>'; } ?>
                        <p class="text-uppercase mb-0"><i class="bi bi-file-earmark-person h5"></i>  <strong><?php echo htmlspecialchars($item['nome_cantor']); ?></strong></p>
                        <p class="mb-0"><i class="bi bi-pin-angle h5"></i> <?php echo htmlspecialchars($item['nome_mesa']); ?></p>
                        <p class="mb-0"><i class="bi bi-file-music h5"></i> <strong><?php echo htmlspecialchars($item['titulo_musica']); ?> - <?php echo htmlspecialchars($item['codigo_musica'] ?? 'N/A'); ?></strong></p>
                        <p class="mb-0"><i class="bi bi-mic  h5"></i> <?php echo htmlspecialchars($item['artista_musica']); ?></p>
                        <p class="mt-2 mb-0 fs-8 bg-white rounded-3 d-inline-block px-2 py-1 status"><?php echo htmlspecialchars(ucfirst($item['status'])); ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>




<div class="modal fade" id="trocarMusicaModal" tabindex="-1" aria-labelledby="trocarMusicaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trocarMusicaModalLabel">Trocar Música na Fila</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> </div>
            <div class="modal-body">
                <input type="hidden" id="filaIdParaTrocar">
                <p>Música atual: <strong id="currentMusicInfo"></strong></p>
                <div class="mb-3"> <label for="searchNovaMusica" class="form-label">Selecione a Nova Música:</label>
                    <input type="text" class="form-control" id="searchNovaMusica" placeholder="Digite para buscar músicas..." autocomplete="off">
                    <input type="hidden" id="idNovaMusicaSelecionada" name="id_nova_musica" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button> <button type="button" class="btn btn-success" id="btnConfirmarTrocaMusica">Trocar Música</button>
            </div>
        </div>
    </div>
</div>

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




<script>

    var currentPageName = "<?php echo $current_page; ?>";

    function showAlert(message, type) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            '<span>' + message + '</span>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        $('#alertContainer').html(alertHtml);
        setTimeout(function() {
            $('#alertContainer .alert').alert('close');
        }, 10000); // Alerta desaparece após 5 segundos
    }

    $(document).ready(function() {

        // --- Event Listener para o formulário "Montar Nova Rodada" ---
        $('#formMontarRodada').on('submit', function(e) {
            e.preventDefault(); // Impede o envio padrão do formulário (que causaria o redirecionamento)

            var modoFila = $('input[name="modo_fila"]:checked').val();

            $.ajax({
                url: 'api.php', // O endpoint que vai lidar com a ação
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'montar_rodada', // Ação para o backend identificar
                    modo_fila: modoFila
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        setTimeout(function() {
                            location.reload(); // Recarrega a página após sucesso para mostrar a nova fila
                        }, 0); // Pequeno atraso para o usuário ver a mensagem
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX:", status, error);
                    showAlert('Erro na comunicação com o servidor ao montar rodada.', 'danger');
                }
            });
        });





        // Funções auxiliares para autocomplete (copiadas do gerenciar_musicas_cantor.php)
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

        // Inicializar o Autocomplete para o campo de troca de música no modal
        $("#searchNovaMusica").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: 'api.php', // Seu endpoint de API
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'search_musicas', // Ação para buscar músicas
                        term: request.term
                    },
                    success: function(data) {
                        if (data.length === 0) {
                            response([{ label: "Nenhum resultado encontrado!", value: "" }]);
                        } else {
                            response($.map(data, function(item) {
                                return {
                                    label: item.titulo + ' (' + item.artista + ')',
                                    value: item.id_musica, // ID da música
                                    titulo: item.titulo,
                                    artista: item.artista,
                                    codigo: item.codigo
                                };
                            }));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro na busca de músicas para troca:", status, error);
                        response([{ label: "Erro ao buscar resultados.", value: "" }]);
                    }
                });
            },
            minLength: 1, // Começa a buscar após 1 caractere
            select: function(event, ui) {
                if (ui.item.value === "") { // Se for a mensagem "Nenhum resultado encontrado!"
                    event.preventDefault();
                    return false;
                }
                $('#idNovaMusicaSelecionada').val(ui.item.value); // Preenche o hidden input com o ID da música
                $(this).val(ui.item.label.replace(/<strong>|<\/strong>/g, '')); // Coloca o label no campo de texto, sem negrito
                return false;
            },
            focus: function(event, ui) {
                if (ui.item.value === "") {
                    event.preventDefault();
                }
                return false;
            }
        });

        // Sobrescrever _renderItem para o autocomplete de troca de música
        $("#searchNovaMusica").data("ui-autocomplete")._renderItem = function(ul, item) {
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

        // Limpar o campo hidden e o texto se o campo de busca for alterado manualmente
        $('#searchNovaMusica').on('input', function() {
            if ($(this).val() === '') {
                $('#idNovaMusicaSelecionada').val('');
            }
        });

        // Evento que ocorre quando o modal de troca de música é exibido
        $('#trocarMusicaModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Botão que acionou o modal
            var filaId = button.data('fila-id'); // Extrai o ID da fila do atributo data-fila-id
            var currentMusicTitle = button.data('current-music-title'); // Pega a música atual do botão

            var modal = $(this);
            modal.find('#filaIdParaTrocar').val(filaId); // Define o ID da fila no campo hidden do modal
            modal.find('#currentMusicInfo').text(currentMusicTitle); // Exibe a música atual no modal

            // Limpa o campo de busca e o hidden ID da música selecionada ao abrir o modal
            $('#searchNovaMusica').val('');
            $('#idNovaMusicaSelecionada').val('');
        });

        // Evento de clique do botão "Trocar Música" dentro do modal
        $('#btnConfirmarTrocaMusica').on('click', function() {
            var filaId = $('#filaIdParaTrocar').val();
            var novaMusicaId = $('#idNovaMusicaSelecionada').val(); // Pega o ID do campo hidden

            if (!filaId || !novaMusicaId) {
                alert('Por favor, selecione uma música.');
                return;
            }

            // Requisição AJAX para o backend (api.php)
            $.ajax({
                url: 'api.php', // Caminho para o seu novo arquivo api.php
                type: 'POST',
                dataType: 'json', // Espera uma resposta JSON
                data: {
                    action: 'trocar_musica', // Ação para o backend identificar
                    fila_id: filaId,
                    nova_musica_id: novaMusicaId
                },
                success: function(response) {
                    if (response.success) {
                        $('#trocarMusicaModal').modal('hide'); // Fecha o modal
                        location.reload(); // Recarrega a página para exibir a fila atualizada
                    } else {
                        alert('Erro ao trocar música: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX:", status, error);
                    alert('Erro na comunicação com o servidor.');
                }
            });
        });

        // Função para finalizar música via AJAX
        window.finalizarMusica = function(filaId) {
            $.ajax({
                url: 'api.php', // Caminho para o seu novo arquivo api.php
                type: 'POST',
                dataType: 'json', // Espera uma resposta JSON
                data: {
                    action: 'finalizar_musica',
                    fila_id: filaId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Erro ao finalizar música: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX:", status, error);
                    alert('Erro na comunicação com o servidor.');
                }
            });
        };

        // Função para pular música via AJAX
        window.pularMusica = function(filaId) {
            $.ajax({
                url: 'api.php', // Caminho para o seu novo arquivo api.php
                type: 'POST',
                dataType: 'json', // Espera uma resposta JSON
                data: {
                    action: 'pular_musica',
                    fila_id: filaId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Erro ao pular música: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX:", status, error);
                    alert('Erro na comunicação com o servidor.');
                }
            });
        };



        // NOVO: Inicializa o jQuery UI Sortable para a fila
        $("#sortable-queue").sortable({
            axis: "y", // Permite arrastar apenas na vertical
            // Definindo 'items' para que a música 'active' (cantando agora) não possa ser arrastada,
            // e nem as que já cantaram/pularam.
            items: "li:not(.completed):not(.skipped):not(.active)",
            placeholder: "ui-sortable-placeholder", // Classe CSS para o espaço reservado
            helper: "clone", // Cria uma cópia visual do item enquanto arrasta
            //revert: 200, // Efeito de retorno suave



            // Evento disparado quando um item para de ser arrastado e a ordem é alterada
            update: function(event, ui) {
                var novaOrdem = {};
                var rodadaAtual = <?php echo json_encode($rodada_atual); ?>; // Pega a rodada atual do PHP

                // Coleta os IDs dos itens da fila na nova ordem
                $("#sortable-queue li").each(function(index) {
                    var filaId = $(this).data('id');
                    // A nova posição será o índice + 1 (para ser baseado em 1)
                    novaOrdem[filaId] = index + 1;
                });

                console.log("Nova ordem da fila:", novaOrdem);

                // Envia a nova ordem para o backend via AJAX
                $.ajax({
                    url: 'api.php', // O endpoint que vai lidar com a atualização
                    type: 'POST',
                    dataType: 'json', // Espera uma resposta JSON
                    data: {
                        action: 'atualizar_ordem_fila', // Nova ação para o backend
                        rodada: rodadaAtual,
                        nova_ordem_fila: novaOrdem // Array associativo ID => Nova Posição
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Ordem da fila atualizada com sucesso no servidor!', response.message);
                            // Opcional: Atualizar apenas a exibição sem recarregar a página inteira
                            updateQueueVisualStatus();
                            //location.reload(); // Se preferir recarregar a página para garantir consistência
                        } else {
                            alert('Erro ao atualizar a ordem da fila: ' + response.message);
                            // Se der erro no backend, reverte a ordem visual no frontend
                            $("#sortable-queue").sortable('cancel');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro na requisição AJAX para reordenar:", status, error);
                        alert('Erro na comunicação com o servidor ao reordenar a fila.');
                        $("#sortable-queue").sortable('cancel'); // Reverte a ordem visual
                    }
                });
            }
        });

        // Opcional: Adiciona um estilo visual para o item fantasma quando arrastado
        // (Isso pode ser ajustado no CSS diretamente)
        $("#sortable-queue").on("sortstart", function(event, ui) {
            ui.item.addClass("ui-state-highlight");
        });
        $("#sortable-queue").on("sortstop", function(event, ui) {
            ui.item.removeClass("ui-state-highlight");
        });

        // Previne a seleção de texto ao arrastar, um problema comum com sortable
        $("#sortable-queue").disableSelection();

        function updateQueueVisualStatus() {
            // 1. Remove a classe 'next-up' de TODOS os itens da fila.
            $("#sortable-queue li").removeClass('next-up');

            // 2. Remove o parágrafo que contém a "Próxima música" de TODOS os itens.
            // O seletor agora busca a classe '.proxima_musica' que você adicionou no PHP.
            $("#sortable-queue li .proxima_musica").remove();

            // 3. Encontra o novo primeiro item "aguardando" e adiciona o status "Próxima música".
            var foundNext = false;
            $("#sortable-queue li").each(function() {
                var $item = $(this);

                // Verifica se o item não é 'completed', nem 'skipped', nem 'active'
                // e se ainda não encontramos o primeiro "próximo".
                if (!$item.hasClass('completed') && !$item.hasClass('skipped') && !$item.hasClass('active') && !foundNext) {
                    // Adiciona a classe visual 'next-up' para estilização.
                    $item.addClass('next-up');

                    // Encontra o div que contém o conteúdo do item.
                    var $itemDiv = $item.find('div').first();

                    // Cria o novo elemento <p> com a classe correta e o texto.
                    var $newNextUpText = $('<p class="proxima_musica  mb-0 text-danger"><strong>PRÓXIMA MÚSICA</strong></p>');

                    // Verifica se o parágrafo já não está lá para evitar duplicação.
                    if ($itemDiv.find('.proxima_musica').length === 0) {
                        // Adiciona o novo parágrafo no início do <div> do item.
                        $itemDiv.prepend($newNextUpText);
                    }

                    foundNext = true; // Marca que o próximo foi encontrado.
                }
            });
        }

        // Sistema de polling para atualizar a fila automaticamente
        var refreshIntervalId;
        var filaHash = '';
        
        function calcularHashFila(fila) {
            if (!fila || fila.length === 0) return '';
            return fila.map(function(item) {
                return item.fila_id + '_' + item.titulo_musica + '_' + item.status;
            }).join('|');
        }
        
        function atualizarFilaRodadas() {
            var rodadaAtual = <?php echo json_encode($rodada_atual); ?>;
            
            $.ajax({
                url: 'api.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'get_fila_rodadas_atualizada',
                    rodada: rodadaAtual
                },
                success: function(response) {
                    if (response.success) {
                        // Calcular hash da nova fila
                        var novoHash = calcularHashFila(response.fila_completa);
                        
                        // Se o hash mudou, recarregar a página
                        if (filaHash !== '' && filaHash !== novoHash) {
                            location.reload();
                            return;
                        }
                        
                        // Atualizar o hash para a próxima comparação
                        filaHash = novoHash;
                        
                        // Verificar mudança na música em execução
                        if (response.musica_em_execucao) {
                            var musicaAtual = $('.music-info h3').text().trim();
                            var novaMusicaTitulo = response.musica_em_execucao.titulo + ' (' + response.musica_em_execucao.artista + ')';
                            
                            if (musicaAtual !== '' && musicaAtual !== novaMusicaTitulo) {
                                location.reload();
                                return;
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    // Silenciar erros para não poluir o console
                }
            });
        }
        
        // Iniciar polling a cada 3 segundos
        refreshIntervalId = setInterval(atualizarFilaRodadas, 5000);
        
        // Parar polling quando a página for descarregada
        $(window).on('beforeunload', function() {
            if (refreshIntervalId) {
                clearInterval(refreshIntervalId);
            }
        });

    })
</script>
</body>
</html>