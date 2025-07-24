<?php
require_once 'funcoes_fila.php'; // Inclui as funções e, por sua vez, a conexão PDO

// Processa as ações do formulário
// ALTERADO: Remove a lógica de POST direto aqui, pois será tratada via AJAX no api.php
// Isso evita redirecionamentos e permite feedback dinâmico.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_mesa':
                $nomeMesa = trim($_POST['nome_mesa']);
                // REMOVIDO: $tamanhoMesa não é mais usado na função adicionarMesa
                // $tamanhoMesa = (int)$_POST['tamanho_mesa']; 
                if (!empty($nomeMesa)) { // Removida a verificação de $tamanhoMesa
                    // ALTERADO: Removido $tamanhoMesa da chamada da função
                    if (adicionarMesa($pdo, $nomeMesa)) { 
                        $mensagem_sucesso = "Mesa '{$nomeMesa}' adicionada com sucesso!";
                    } else {
                        $mensagem_erro = "Erro ao adicionar mesa. Verifique se o nome já existe.";
                    }
                } else {
                    $mensagem_erro = "Nome da mesa é obrigatório."; // Mensagem ajustada
                }
                break;
            case 'add_cantor':
                $nomeCantor = trim($_POST['nome_cantor']);
                $idMesa = (int)$_POST['id_mesa_cantor'];
                if (!empty($nomeCantor) && $idMesa > 0) {
                    if (adicionarCantor($pdo, $nomeCantor, $idMesa)) {
                        $mensagem_sucesso = "Cantor '{$nomeCantor}' adicionado à mesa com sucesso!";
                    } else {
                        $mensagem_erro = "Erro ao adicionar cantor.";
                    }
                } else {
                    $mensagem_erro = "Nome do cantor e mesa são obrigatórios.";
                }
                break;
            case 'montar_rodada':
                // NOVO: Captura o modo selecionado para montar a rodada
                $modoFila = $_POST['modo_fila'] ?? 'mesa'; // 'mesa' como padrão se nada for selecionado

                // Passa o modoFila para a função montarProximaRodada
                if (montarProximaRodada($pdo, $modoFila)) {
                    $mensagem_sucesso = "Nova rodada montada com sucesso no modo '" . htmlspecialchars($modoFila) . "'!";
                } else {
                    $mensagem_erro = "Não foi possível montar uma nova rodada. Verifique se há cantores e músicas.";
                }
                break;
            case 'resetar_ordem_cantores':
                if (resetarTudoFila($pdo)) {
                    $mensagem_sucesso = "Fila de karaokê completamente resetada!"; // Usar a variável de sucesso para consistência
                } else {
                    $mensagem_erro = "Erro ao resetar a fila de karaokê. Verifique os logs."; // Usar a variável de erro
                }
                break;      
        }
    }
    // Redireciona para evitar reenvio do formulário, APENAS para formulários que não são AJAX
    // Os formulários de ação de fila (finalizar, pular, trocar) não farão mais POST direto na index.
    header("Location: index.php");
    exit();
}


// Obter dados para exibição (ASSUMIMOS QUE ESTAS FUNÇÕES ESTÃO EM funcoes_fila.php)
$rodada_atual = getRodadaAtual($pdo);
// ANTES: $proxima_musica = getProximaMusicaFila($pdo);
// AGORA: Obter a música REALMENTE em execução para o painel principal
$musica_em_execucao = getMusicaEmExecucao($pdo);
$proxima_musica_aguardando = getProximaMusicaFila($pdo); // Esta continua sendo a primeira 'aguardando'
$fila_completa = getFilaCompleta($pdo); // Esta é a lista completa da rodada

// Obter lista de mesas para formulário de adicionar cantor
$stmtMesas = $pdo->query("SELECT id, nome_mesa FROM mesas ORDER BY nome_mesa ASC");
$mesas_disponiveis = $stmtMesas->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Karaokê - MC Panel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="css/style_index.css">
</head>
<body>
    <div class="container">
        <h1>Gerenciador de Karaokê (Rodada Atual: <?php echo $rodada_atual; ?>)</h1>

        <?php if (isset($mensagem_sucesso)): ?>
            <div class="alert success"><?php echo htmlspecialchars($mensagem_sucesso); ?></div>
        <?php endif; ?>
        <?php if (isset($mensagem_erro)): ?>
            <div class="alert error"><?php echo htmlspecialchars($mensagem_erro); ?></div>
        <?php endif; ?>
                
       
		<?php if ($musica_em_execucao): ?>
			<div class="current-song">
				<h3>CANTANDO AGORA</h3>
				<p>Música: <strong><?php echo htmlspecialchars($musica_em_execucao['titulo_musica']); ?></strong> (<?php echo htmlspecialchars($musica_em_execucao['artista_musica']); ?>)</p>
				<p>Mesa <?php echo htmlspecialchars($musica_em_execucao['nome_mesa']); ?> - <?php echo htmlspecialchars($musica_em_execucao['nome_cantor']); ?></p>
				<div class="actions">
					<button type="button" class="btn btn-primary" onclick="finalizarMusica(<?php echo $musica_em_execucao['fila_id']; ?>)">Finalizar Música (Próxima)</button>
					<button type="button" class="btn btn-danger" onclick="pularMusica(<?php echo $musica_em_execucao['fila_id']; ?>)">Pular Música</button>
					<button type="button" class="btn btn-info" data-toggle="modal" data-target="#trocarMusicaModal" data-fila-id="<?php echo $musica_em_execucao['fila_id']; ?>" data-current-music-title="<?php echo htmlspecialchars($musica_em_execucao['titulo_musica'] . ' (' . $musica_em_execucao['artista_musica'] . ')'); ?>">Trocar Música</button>
				</div>
			</div>
		<?php else: ?>
			<p>Nenhuma música na fila atual. Monte uma nova rodada!</p>
		<?php endif; ?>

        <h2>Fila Completa da Rodada <?php echo $rodada_atual; ?></h2>
		<?php if (empty($fila_completa)): ?>
			<p>A fila está vazia. Adicione cantores ou monte a próxima rodada.</p>
		<?php else: ?>
		<p>Total de músicas nesta rodada: <strong><?php echo count($fila_completa); ?></strong></p>
		<ul class="queue-list" id="sortable-queue">
        <?php
        // Encontra a música que está em execução no array $fila_completa
        // Isso é mais seguro para garantir que a lógica de "pular" seja baseada no item correto
        $musica_em_execucao_na_fila = null;
        foreach ($fila_completa as $idx => $item_check) {
            if ($item_check['status'] === 'em_execucao') {
                $musica_em_execucao_na_fila = $item_check;
                break; // Encontrou, pode sair do loop
            }
        }

        // Determina qual é a "próxima música" na lista de exibição.
        // Se há uma música em execução, a próxima é a primeira "aguardando" *depois* dela.
        // Se não há música em execução, a próxima é a primeira "aguardando" geral.
        $next_music_to_display_id = null;
        $found_current_song_or_skipped = false; // Flag para saber se já passamos pela música em execução
        foreach ($fila_completa as $item_for_next_check) {
            // Se já encontramos a música em execução ou se a fila_completa está vazia
            if ($musica_em_execucao_na_fila && $item_for_next_check['fila_id'] == $musica_em_execucao_na_fila['fila_id']) {
                $found_current_song_or_skipped = true;
                continue; // Pula a própria música em execução para encontrar a "próxima"
            }
            
            // A primeira música com status 'aguardando' APÓS a música em execução (ou a primeira geral se não houver em execução)
            if ($found_current_song_or_skipped && $item_for_next_check['status'] === 'aguardando') {
                $next_music_to_display_id = $item_for_next_check['fila_id'];
                break; // Encontrou a próxima, pode sair
            } elseif (!$musica_em_execucao_na_fila && $item_for_next_check['status'] === 'aguardando') {
                // Caso não haja música em execução, a primeira 'aguardando' é a "próxima"
                $next_music_to_display_id = $item_for_next_check['fila_id'];
                break;
            }
        }
        ?>
        <?php foreach ($fila_completa as $item): ?>
            <?php
            // ADIÇÃO: Pula a música que está sendo cantada agora
            // Usa o ID da música em execução encontrada anteriormente
            if ($musica_em_execucao_na_fila && $item['fila_id'] == $musica_em_execucao_na_fila['fila_id']) {
                continue; // Pula este item na renderização da lista da fila
            }

            // Inicializa $item_class com a classe base 'queue-item'
            $item_class = 'queue-item';

            // Aplica as classes de status primeiro
            if ($item['status'] == 'cantou') {
                $item_class .= ' completed';
            } elseif ($item['status'] == 'pulou') {
                $item_class .= ' skipped';
            }
            
            // ADIÇÃO: Adiciona a classe 'next-up' SOMENTE se o item estiver "aguardando"
            // e for o próximo a ser exibido (determinado pela lógica acima)
            if ($item['fila_id'] === $next_music_to_display_id && $item['status'] === 'aguardando') {
                $item_class .= ' next-up';
            }
            ?>
            <li class="<?php echo trim($item_class); ?>" data-id="<?php echo htmlspecialchars($item['fila_id']); ?>">
                <div class="queue-info">
                    <?php
                    // Lógica para exibir "Próxima música" na lista
                    if ($item['fila_id'] === $next_music_to_display_id) {
                        echo '<strong>Próxima música</strong><br>';
                    }
                    ?>
                    <strong><?php echo htmlspecialchars($item['nome_mesa']); ?></strong>: <?php echo htmlspecialchars($item['nome_cantor']); ?>
                    <br>
                    <small>Música: <?php echo htmlspecialchars($item['titulo_musica']); ?> (<?php echo htmlspecialchars($item['artista_musica']); ?>)</small>
                    <br>
                    <small>Status: <?php echo htmlspecialchars(ucfirst($item['status'])); ?></small>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

		<hr>
		
		<div class="form-section">
            <h3>Montar Próxima Rodada</h3>
            <p>Isso irá gerar uma nova fila com base nas prioridades e regras de mesa para a próxima rodada.</p>
            <form method="POST">
                <input type="hidden" name="action" value="montar_rodada">
                <div class="form-group">
                    <label>Escolha o modo da rodada:</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modo_fila" id="modoMesa" value="mesa" checked>
                        <label class="form-check-label" for="modoMesa">Por Mesa</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modo_fila" id="modoCantor" value="cantor">
                        <label class="form-check-label" for="modoCantor">Por Cantores</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Montar Nova Rodada</button>
            </form>
        </div>


        <hr>
        
        <div class="form-section">
            <h3>Resetar Sistema</h3>
            <p>Reseta o sistema</p>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="resetar_ordem_cantores">
                <button type="submit" class="btn btn-secondary">Reseta o sistema</button>
            </form>
        </div>
                
        <div class="form-section">
            <h3>Perguntas Frequentes</h3>
            <p>Tire suas dúvidas</p>
            <form method="POST" action="perguntas.php">
                <button type="submit" class="btn btn-secondary">Tire suas dúvidas</button>
            </form>
        </div>

        <h2>Controles da Rodada e Cadastro</h2>
                
        <div class="form-section my-5">
            <h3>Gerenciar Músicas dos Cantores</h3>
            <p>Adicione e visualize a lista de músicas que cada cantor deseja cantar.</p>
            <form method="POST" action="gerenciar_musicas_cantor.php">
                <button type="submit" class="btn btn-secondary">Acessar Gerenciador de Músicas do Cantor</button>
            </form>
        </div>
		
		
			
		<hr>
	
		
		<div class="form-section my-5">
			<h3>Configurar Regras de Mesa</h3>
			<form id="formConfigurarRegrasMesa">
				<input type="hidden" name="action" value="save_all_regras_mesa">
				<input type="hidden" name="removed_ids" id="removed_ids" value="">
				
				<div id="regras-container">
					<?php
					
					$regrasExistentes = getAllRegrasMesa($pdo); // Chama a nova função

					if (!empty($regrasExistentes)) {
						foreach ($regrasExistentes as $index => $regra) {
							$isLast = ($index === count($regrasExistentes) - 1);
					?>
						<div class="form-row regra-row" data-id="<?= htmlspecialchars($regra['id']) ?>">
							<input type="hidden" name="regras[<?= $index ?>][id]" value="<?= htmlspecialchars($regra['id']) ?>">
							<div class="form-group col-md-3">
								<label for="min_pessoas_<?= $index ?>" class="col-form-label col-form-label-sm">Mínimo de Pessoas:</label>
								<input type="number" id="min_pessoas_<?= $index ?>" name="regras[<?= $index ?>][min_pessoas]" class="form-control" required min="1" value="<?= htmlspecialchars($regra['min_pessoas']) ?>">
							</div>
							
							<div class="form-group col-md-3">
								<label for="max_pessoas_<?= $index ?>" class="col-form-label col-form-label-sm">Máximo de Pessoas:</label>
								<input type="number" id="max_pessoas_<?= $index ?>" name="regras[<?= $index ?>][max_pessoas]" class="form-control" min="1" value="<?= htmlspecialchars($regra['max_pessoas']) ?>">
								<small class="form-text text-muted">Deixe em branco para "ou mais".</small>
							</div>
							
							<div class="form-group col-md-3">
								<label for="max_musicas_por_rodada_<?= $index ?>" class="col-form-label col-form-label-sm">Músicas por Rodada:</label>
								<div class="input-group">
									<input type="number" id="max_musicas_por_rodada_<?= $index ?>" name="regras[<?= $index ?>][max_musicas_por_rodada]" class="form-control" required min="1" value="<?= htmlspecialchars($regra['max_musicas_por_rodada']) ?>">
									
								</div>
							</div>
							
							<div class="form-group col-md-3">
							<label for="teste" class="invisible visually-hidden col-form-label col-form-label-sm">Ações</label>
								<div class="input-group">
									<button type="button" class="btn btn-danger remove-regra-btn mx-1" style="<?= $index === 0 && count($regrasExistentes) === 1 ? 'display: none;' : '' ?>">-</button>
									<button type="button" class="btn btn-success add-regra-btn mx-1" style="<?= $isLast ? '' : 'display: none;' ?>">+</button>
								</div>
							</div>

						</div>
					<?php
						}
					} else {
						// Se não houver regras existentes, adicione uma linha vazia por padrão
					?>
						<div class="form-row regra-row mb-3" data-id="">
							<input type="hidden" name="regras[0][id]" value="">
							<div class="form-group col-md-3">
								<label for="min_pessoas_0">Mínimo de Pessoas:</label>
								<input type="number" id="min_pessoas_0" name="regras[0][min_pessoas]" class="form-control" required min="1">
							</div>
							
							<div class="form-group col-md-3">
								<label for="max_pessoas_0">Máximo de Pessoas:</label>
								<input type="number" id="max_pessoas_0" name="regras[0][max_pessoas]" class="form-control" min="1">
								<small class="form-text text-muted">Deixe em branco para "ou mais".</small>
							</div>
							
							<div class="form-group col-md-3">
								<label for="max_musicas_por_rodada_0">Músicas por Rodada:</label>
								<input type="number" id="max_musicas_por_rodada_0" name="regras[0][max_musicas_por_rodada]" class="form-control" required min="1">
							</div>
							
							
							<div class="form-group col-md-3">
							<label for="teste" class="invisible visually-hidden col-form-label col-form-label-sm">Ações</label>
								<div class="input-group">
									<button type="button" class="btn btn-danger remove-regra-btn mx-1">-</button>
									<button type="button" class="btn btn-success add-regra-btn mx-1">+</button>
								</div>
							</div>
							
						</div>
					<?php } ?>
				</div>
				
				<button type="submit" class="btn btn-primary">Salvar Regras</button>
				<button type="button" id="btnRegrasPadrao" class="btn btn-info ml-2">Resetar Padrão</button>
			</form>
			
		
			<h3>Quantidade de músicas por mesa:</h3>
			<ul>
				<?php
				$regrasExibicao = getRegrasMesaFormatadas($pdo);
				foreach ($regrasExibicao as $regraTexto) {
					echo "<li>" . htmlspecialchars($regraTexto) . "</li>"; // Use htmlspecialchars para segurança
				}
				?>
			</ul>
			
		</div>
		
		<div class="form-section">
            <h3>Adicionar Nova Mesa</h3>
            <form method="POST">
				<input type="hidden" name="action" value="add_mesa">
				<div class="form-row">
					<div class="form-group col-md-6 mb-3">
						<label for="nome_mesa">Nome da Mesa:</label>
						<input type="text" id="nome_mesa" name="nome_mesa" class="form-control" required>
						
					</div>	
				</div>	
				<div class="form-row">
					<div class="col-12 mt-3"> 
						<button type="submit" class="btn btn-primary">Adicionar Mesa</button>
					</div>
				</div>		
            </form>
        </div>


		
		<div class="form-section">
			<h3>Adicionar Cantor a uma Mesa</h3>
			<form method="POST">
				<input type="hidden" name="action" value="add_cantor"> 
				
				<div class="form-row">
					<div class="form-group col-md-6 mb-3">
						<label for="nome_cantor">Nome do Cantor:</label>
						<input type="text" id="nome_cantor" name="nome_cantor" class="form-control" required>
					</div>
					
					<div class="form-group col-md-6 mb-3">
						<label for="id_mesa_cantor">Mesa:</label>
						<select id="id_mesa_cantor" name="id_mesa_cantor" class="form-control" required>
							<option value="">Selecione uma mesa</option>
							<option value="">Selecione uma mesa</option>
							<?php foreach ($mesas_disponiveis as $mesa): ?>
								<option value="<?php echo htmlspecialchars($mesa['id']); ?>"><?php echo htmlspecialchars($mesa['nome_mesa']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				
				<div class="form-row">
					<div class="col-12 mt-3"> 
						<button type="submit" class="btn btn-primary">Adicionar Cantor</button>
					</div>
				</div>
			</form>
		</div>

    </div> <!-- container -->

    <div class="modal fade" id="trocarMusicaModal" tabindex="-1" role="dialog" aria-labelledby="trocarMusicaModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="trocarMusicaModalLabel">Trocar Música na Fila</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="filaIdParaTrocar">
            <p>Música atual: <strong id="currentMusicInfo"></strong></p> <div class="form-group">
              <label for="searchNovaMusica">Selecione a Nova Música:</label>
              <input type="text" class="form-control" id="searchNovaMusica" placeholder="Digite para buscar músicas..." autocomplete="off">
              <input type="hidden" id="idNovaMusicaSelecionada" name="id_nova_musica" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnConfirmarTrocaMusica">Trocar Música</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
	
	
    

    <script>
    $(document).ready(function() {
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
			// 1. Primeiro, remove a classe 'next-up' de TODOS os itens.
			// Isso garante que a estilização vermelha (se associada a 'next-up') seja removida.
			$("#sortable-queue li").removeClass('next-up');

			// 2. Remove o elemento 'strong' que contém 'Próxima música' e o '<br>' adjacente de TODOS os itens.
			// Usamos uma iteração para garantir que removemos APENAS o strong que contém "Próxima música".
			$("#sortable-queue li .queue-info strong").each(function() {
				var $thisStrong = $(this);
				// Verifica se o conteúdo do strong é exatamente "Próxima música"
				// Ou se começa com "Próxima música" para ser mais flexível, dependendo de como o PHP gera.
				if ($thisStrong.text().trim() === 'Próxima música') {
					// Remove o elemento strong e a quebra de linha adjacente se ela existir
					$thisStrong.next('br').remove(); // Remove o <br> que vem logo depois (se houver)
					$thisStrong.remove(); // Remove o <strong>Próxima música</strong>
				}
			});

			// 3. Encontra o novo primeiro item "aguardando" e adiciona o status "Próxima música".
			var foundNext = false;
			$("#sortable-queue li").each(function() {
				var $item = $(this);

				// Verifica se o item não é 'completed', nem 'skipped', nem 'active'
				// e se ainda não encontramos o primeiro "próximo".
				if (!$item.hasClass('completed') && !$item.hasClass('skipped') && !$item.hasClass('active') && !foundNext) {
					// Adiciona a classe visual 'next-up'
					$item.addClass('next-up');

					// Adiciona o texto "Próxima música" no início do conteúdo da div .queue-info
					var $queueInfo = $item.find('.queue-info').first();
					
					// Cria o elemento <strong> com o texto e o <br>
					var $newNextUpText = $('<strong>Próxima música</strong><br>');

					// Verifica se o texto já não está lá para evitar duplicação acidental
					// (Embora o passo 2 deva ter removido, é uma verificação de segurança)
					if (!$queueInfo.html().includes('Próxima música')) {
						// Adiciona o novo texto no início do .queue-info
						$queueInfo.prepend($newNextUpText);
					}
					
					foundNext = true; // Marca que o próximo foi encontrado
				}
			});
		}
		
		
		
		// Adicionar e gerenciar campos para criação das regras de quantitdade de músicas por mesa na rodada
		
		const regrasContainer = $('#regras-container');
		const formConfigurarRegrasMesa = $('#formConfigurarRegrasMesa');
		let regraIndex = <?= !empty($regrasExistentes) ? count($regrasExistentes) : 1 ?>;

		// Função para adicionar uma nova linha de regra ou REINICIALIZAR uma existente
		// Adicionado um parâmetro 'initialMin' para preencher o campo Mínimo de Pessoas
		function addRegraRow(id = '', min = '', max = '', musicas = '', isReset = false, initialMin = '') {
			// Se initialMin for fornecido, ele tem precedência sobre o 'min' padrão (que viria do banco)
			const finalMin = initialMin !== '' ? initialMin : min;

			const newRow = $(`
				<div class="form-row regra-row mb-3" data-id="${id}">
					<input type="hidden" name="regras[${regraIndex}][id]" value="${id}">
					
					<div class="form-group col-md-3"> <label for="min_pessoas_${regraIndex}" class="col-form-label col-form-label-sm">Mínimo de Pessoas:</label>
						<input type="number" id="min_pessoas_${regraIndex}" name="regras[${regraIndex}][min_pessoas]" class="form-control" required min="1" value="${finalMin}">
					</div>
					
					<div class="form-group col-md-3"> <label for="max_pessoas_${regraIndex}" class="col-form-label col-form-label-sm">Máximo de Pessoas:</label>
						<input type="number" id="max_pessoas_${regraIndex}" name="regras[${regraIndex}][max_pessoas]" class="form-control" min="1" value="${max}">
						<small class="form-text text-muted">Deixe em branco para "ou mais".</small>
					</div>
					
					<div class="form-group col-md-3"> <label for="max_musicas_por_rodada_${regraIndex}" class="col-form-label col-form-label-sm">Músicas por Rodada:</label>
						<div class="input-group"> <input type="number" id="max_musicas_por_rodada_${regraIndex}" name="regras[${regraIndex}][max_musicas_por_rodada]" class="form-control" required min="1" value="${musicas}">
						</div>
					</div>
					
					<div class="form-group col-md-3"> <label for="acoes_${regraIndex}" class="invisible visually-hidden col-form-label col-form-label-sm">Ações</label> 
						<div class="input-group"> <button type="button" class="btn btn-danger remove-regra-btn mx-1">-</button>
							<button type="button" class="btn btn-success add-regra-btn mx-1">+</button>
						</div>
					</div>
				</div>
			`);
			
			if (!isReset) {
				const lastRow = regrasContainer.children().last();
				if (lastRow.length) {
					lastRow.find('.add-regra-btn').hide();
				}
			}

			regrasContainer.append(newRow);
			regraIndex++;
			updateRemoveButtonsVisibility();
		}

		// Função para atualizar a visibilidade dos botões de remover e adicionar
		function updateRemoveButtonsVisibility() {
			const removeButtons = $('.remove-regra-btn');
			const totalRows = regrasContainer.children('.regra-row').length;

			if (totalRows > 0) {
				removeButtons.show();
			} else {
				removeButtons.hide();
			}

			$('.add-regra-btn').hide();
			const lastRow = regrasContainer.children().last();
			if (lastRow.length) {
				lastRow.find('.add-regra-btn').show();
			}
		}

		// Event Delegation para botões de adicionar
		regrasContainer.on('click', '.add-regra-btn', function() {
			// Lógica para pegar o valor do Máximo de Pessoas da linha anterior
			const lastRow = regrasContainer.children().last();
			let nextMin = ''; // Valor padrão vazio

			if (lastRow.length) {
				const maxPessoasLastRow = lastRow.find('input[name$="[max_pessoas]"]').val();
				if (maxPessoasLastRow !== '') {
					nextMin = parseInt(maxPessoasLastRow) + 1;
				}
			}
			addRegraRow('', '', '', '', false, nextMin); // Passa o valor calculado para initialMin
		});

		// Event Delegation para botões de REMOVER (AGORA COM LÓGICA DE EXCLUSÃO IMEDIATA E RESET DA ÚLTIMA LINHA)
		regrasContainer.on('click', '.remove-regra-btn', function() {
			const rowToRemove = $(this).closest('.regra-row');
			const idToRemove = rowToRemove.data('id');
			const totalRows = regrasContainer.children('.regra-row').length;
			const clickedButton = $(this);

			if (idToRemove) { // Se a linha tem um ID (é uma regra existente no banco)
				//if (confirm('Tem certeza que deseja remover esta regra permanentemente?')) {
					clickedButton.prop('disabled', true).text('Removendo...');

					$.ajax({
						url: 'processar_regras.php',
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'delete_regra_mesa',
							id: idToRemove
						},
						success: function(data) {
							if (data.success) {
								//alert('Regra removida com sucesso!');
								if (totalRows === 1) { // Se esta é a ÚLTIMA regra existente
									// Reinicializa a linha com campos em branco
									rowToRemove.find('input[type="number"]').val('');
									rowToRemove.find('input[type="hidden"][name$="[id]"]').val('');
									rowToRemove.data('id', '');
									clickedButton.prop('disabled', false).text('-');
								} else {
									rowToRemove.remove();
								}
								updateRemoveButtonsVisibility();
							} else {
								alert('Erro ao remover regra: ' + data.message);
								clickedButton.prop('disabled', false).text('-');
							}
						},
						error: function(jqXHR, textStatus, errorThrown) {
							console.error('Erro na requisição AJAX de remoção:', textStatus, errorThrown, jqXHR.responseText);
							alert('Ocorreu um erro na comunicação com o servidor ao tentar remover a regra.');
							clickedButton.prop('disabled', false).text('-');
						}
					});
				//}
			} else { // Se a linha NÃO tem um ID (é uma regra nova, ainda não salva)
				if (totalRows === 1) {
					rowToRemove.find('input[type="number"]').val('');
				} else {
					rowToRemove.remove();
				}
				updateRemoveButtonsVisibility();
			}
		});

		// Inicializa: Garante que os botões estejam corretos ao carregar a página
		updateRemoveButtonsVisibility();
		
		// --- Lógica para o envio do formulário via AJAX (apenas salvar/atualizar) ---
		formConfigurarRegrasMesa.on('submit', function(e) {
			e.preventDefault();

			const regrasData = [];
			$('.regra-row').each(function(index, element) {
				const $row = $(element);
				const id = $row.data('id');
				const minPessoas = $row.find('input[name$="[min_pessoas]"]').val();
				let maxPessoas = $row.find('input[name$="[max_pessoas]"]').val();
				const maxMusicas = $row.find('input[name$="[max_musicas_por_rodada]"]').val();

				if (maxPessoas === '') {
					maxPessoas = null;
				}

				if (minPessoas !== '' || id) {
					regrasData.push({
						id: id,
						min_pessoas: minPessoas,
						max_pessoas: maxPessoas,
						max_musicas_por_rodada: maxMusicas
					});
				}
			});

			const dataToSend = {
				action: 'save_all_regras_mesa',
				regras_json: JSON.stringify(regrasData)
			};

			$.ajax({
				url: 'processar_regras.php',
				type: 'POST',
				dataType: 'json',
				data: dataToSend,
				success: function(data) {
					if (data.success) {
						//alert('Regras salvas com sucesso!');
						location.reload(); 
					} else {
						alert('Erro ao salvar regras: ' + data.message);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					console.error('Erro na requisição AJAX de salvar:', textStatus, errorThrown, jqXHR.responseText);
					alert('Ocorreu um erro na comunicação com o servidor ao tentar salvar as regras.');
				}
			});
		});

		// Lógica para o botão "Regra Padrão"
		$('#btnRegrasPadrao').on('click', function() {
			if (confirm('Deseja realmente aplicar as regras padrão? Todas as regras atuais serão removidas e substituídas.')) {
				$.ajax({
					url: 'processar_regras.php',
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'set_regras_padrao'
					},
					success: function(data) {
						if (data.success) {
							//alert('Regras padrão aplicadas com sucesso!');
							location.reload();
						} else {
							alert('Erro ao aplicar regras padrão: ' + data.message);
						}
					},
					error: function(jqXHR, textStatus, errorThrown) {
						console.error('Erro na requisição AJAX:', textStatus, errorThrown, jqXHR.responseText);
						alert('Ocorreu um erro na comunicação ao aplicar as regras padrão.');
					}
				});
			}
		});
	})	
    </script>
</body>
</html>