<?php
// Ativar exibição de erros para depuração (desativar em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'funcoes_fila.php'; // Inclui as funções e a conexão PDO

header('Content-Type: application/json; charset=utf-8'); // Garante que a resposta seja JSON UTF-8

// Usa $_REQUEST para pegar a 'action' tanto de GET quanto de POST
$action = $_REQUEST['action'] ?? '';

// Mensagem padrão para requisições inválidas ou não especificadas
$response = ['success' => false, 'message' => 'Requisição inválida ou ação não especificada.'];

// Assegura que $pdo está disponível. Se funcoes_fila.php não definir $pdo globalmente,
// você precisará de uma função para obtê-lo, como getDbConnection().
// Exemplo: $pdo = getDbConnection(); // Se você tiver essa função
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Se $pdo não estiver definido, tenta incluí-lo via config.php, se existir
    // ou você pode adicionar a lógica de conexão PDO diretamente aqui
    // Exemplo: require_once 'config.php'; // Supondo que config.php defina $pdo ou getDbConnection()
    // if (function_exists('getDbConnection')) {
    //     $pdo = getDbConnection();
    // } else {
        // Fallback: se não encontrar getDbConnection nem $pdo direto,
        // defina um erro e saia.
        $response['message'] = 'Erro de conexão com o banco de dados. $pdo não está definido.';
        echo json_encode($response);
        exit();
    // }
}


// Remova a condição $_SERVER['REQUEST_METHOD'] === 'POST'
// O switch agora processará a $action independentemente do método HTTP
switch ($action) {
    case 'search_musicas':
        $term = $_GET['term'] ?? '';
        $term = '%' . $term . '%'; // Adiciona curingas para a busca LIKE

        try {
            // ALTEARÇÃO AQUI: Adicione 'OR trecho LIKE ?' na sua cláusula WHERE
            $stmt = $pdo->prepare("SELECT id AS id_musica, titulo, artista, codigo FROM musicas WHERE titulo LIKE ? OR artista LIKE ? OR codigo LIKE ? OR trecho LIKE ? ORDER BY titulo ASC LIMIT 20");
            // Certifique-se de passar o $term uma vez a mais para o novo placeholder
            $stmt->execute([$term, $term, $term, $term]); // Adicione $term para o campo 'trecho'
            $musicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formatted_musicas = [];
            foreach ($musicas as $musica) {
                $formatted_musicas[] = [
                    'id_musica' => $musica['id_musica'],
                    'label' => $musica['titulo'] . ' (' . $musica['artista'] . ')',
                    'value' => $musica['id_musica'],
                    'titulo' => $musica['titulo'],
                    'artista' => $musica['artista'],
                    'codigo' => $musica['codigo'] ?? ''
                ];
            }
            echo json_encode($formatted_musicas);
            exit;
        } catch (PDOException $e) {
            $response['message'] = "Erro de banco de dados na busca: " . $e->getMessage();
            echo json_encode($response);
            error_log("Erro search_musicas: " . $e->getMessage());
            exit;
        }
        break;
        
    case 'finalizar_musica':
    $filaId = filter_var($_POST['fila_id'], FILTER_VALIDATE_INT);
    if ($filaId !== false) {
        // Passo 1: Marcar a música atual como 'cantou'
        if (atualizarStatusMusicaFila($pdo, $filaId, 'cantou')) {
            // Passo 2: Tentar encontrar e iniciar a próxima música
            // Primeiro, garantir que a música que acabou de ser cantada não seja a "próxima"
            // (A função getProximaMusicaFila já deveria lidar com isso, mas vale a pena ter certeza)
            $proximaMusicaFila = getProximaMusicaFila($pdo); // Assume que esta função busca a próxima "aguardando"

            if ($proximaMusicaFila && $proximaMusicaFila['fila_id'] != $filaId) { // Garante que não é a mesma música
                // Se existe uma próxima música e ela não é a que acabamos de finalizar
                if (atualizarStatusMusicaFila($pdo, $proximaMusicaFila['fila_id'], 'em_execucao')) {
                    $response['success'] = true;
                    $response['message'] = 'Música marcada como "cantou" e próxima música iniciada.';
                } else {
                    $response['success'] = false; // Falha no segundo passo (iniciar próxima)
                    $response['message'] = 'Música marcada como "cantou", mas falha ao iniciar a próxima música.';
                }
            } else {
                // Não há próxima música para iniciar, mas a atual foi finalizada com sucesso.
                $response['success'] = true;
                $response['message'] = 'Música marcada como "cantou". Nenhuma próxima música para iniciar automaticamente.';
            }
        } else {
            $response['message'] = 'Erro ao marcar música como cantada.';
        }
    } else {
        $response['message'] = 'ID da fila inválido.';
    }
    break;

    case 'pular_musica':
        // Ação para POST
        $filaId = filter_var($_POST['fila_id'], FILTER_VALIDATE_INT);
        if ($filaId !== false) {
            // Passo 1: Marcar a música atual como 'pulou'
            if (atualizarStatusMusicaFila($pdo, $filaId, 'pulou')) {
                // Passo 2: Tentar encontrar e iniciar a próxima música
                // Primeiro, garantir que a música que acabou de ser pulada não seja a "próxima"
                $proximaMusicaFila = getProximaMusicaFila($pdo); // Assume que esta função busca a próxima "aguardando"

                if ($proximaMusicaFila) { // Se existe uma próxima música para ser iniciada
                    if (atualizarStatusMusicaFila($pdo, $proximaMusicaFila['fila_id'], 'em_execucao')) {
                        $response['success'] = true;
                        $response['message'] = 'Música pulada e próxima música iniciada.';
                    } else {
                        $response['success'] = false; // Falha no segundo passo (iniciar próxima)
                        $response['message'] = 'Música pulada, mas falha ao iniciar a próxima música.';
                    }
                } else {
                    // Não há próxima música para iniciar, mas a atual foi pulada com sucesso.
                    $response['success'] = true;
                    $response['message'] = 'Música pulada. Nenhuma próxima música para iniciar automaticamente.';
                }
            } else {
                $response['message'] = 'Erro ao pular música.';
            }
        } else {
            $response['message'] = 'ID da fila inválido.';
        }
        break;

    case 'trocar_musica':
        // Ação para POST
        $filaId = filter_var($_POST['fila_id'], FILTER_VALIDATE_INT);
        $novaMusicaId = filter_var($_POST['nova_musica_id'], FILTER_VALIDATE_INT);

        if ($filaId !== false && $novaMusicaId !== false) {
            if (trocarMusicaNaFilaAtual($pdo, $filaId, $novaMusicaId)) {
                $response['success'] = true;
                $response['message'] = 'Música trocada com sucesso!';
            } else {
                $response['message'] = 'Falha ao trocar a música.';
            }
        } else {
            $response['message'] = 'Dados inválidos para a troca de música.';
        }
        break;

    case 'atualizar_ordem_fila':
        // Ação para POST
        $rodada = filter_var($_POST['rodada'], FILTER_VALIDATE_INT);
        $novaOrdemFila = $_POST['nova_ordem_fila'] ?? [];

        if ($rodada !== false && $rodada > 0 && is_array($novaOrdemFila) && !empty($novaOrdemFila)) {
            if (atualizarOrdemFila($pdo, $rodada, $novaOrdemFila)) {
                $response['success'] = true;
                $response['message'] = 'Ordem da fila atualizada com sucesso!';
            } else {
                $response['message'] = 'Falha ao atualizar a ordem da fila no banco de dados.';
            }
        } else {
            $response['message'] = 'Dados inválidos ou incompletos para atualizar a ordem da fila.';
        }
        break;
            
    case 'atualizar_ordem_musicas_cantor':
        // Ação para POST
        $idCantor = filter_var($_POST['id_cantor'], FILTER_VALIDATE_INT);
        $novaOrdemMusicas = $_POST['nova_ordem_musicas'] ?? [];

        if ($idCantor !== false && $idCantor > 0 && is_array($novaOrdemMusicas) && !empty($novaOrdemMusicas)) {
            if (atualizarOrdemMusicasCantor($pdo, $idCantor, $novaOrdemMusicas)) {
                $response['success'] = true;
                $response['message'] = 'Ordem das músicas do cantor atualizada com sucesso!';
            } else {
                $response['message'] = 'Falha ao atualizar a ordem das músicas do cantor no banco de dados.';
            }
        } else {
            $response['message'] = 'Dados inválidos ou incompletos para atualizar a ordem das músicas do cantor.';
        }
        break;  
	case 'get_musicas_cantor_atualizadas':
		$idCantor = filter_var($_GET['id_cantor'] ?? $_POST['id_cantor'], FILTER_VALIDATE_INT);

		if ($idCantor !== false) {
			try {
				// Busque as músicas do cantor, de forma similar ao que você faz no gerenciar_musicas_cantor.php
				// Reutilize ou crie uma função em funcoes_fila.php para isso, se ainda não tiver.
				// Exemplo: function getMusicasDoCantor($pdo, $idCantor) { ... }
				$stmt = $pdo->prepare("
					SELECT mc.id, mc.id_musica, mc.ordem_na_lista, mc.status,
						   m.titulo, m.artista, m.codigo, c.nome_cantor, c.id_mesa
					FROM musicas_cantor mc
					JOIN musicas m ON mc.id_musica = m.id
					JOIN cantores c ON mc.id_cantor = c.id -- Adicionado join para pegar id_cantor da fila_rodadas corretamente
					WHERE mc.id_cantor = ?
					ORDER BY mc.ordem_na_lista ASC
				");
				$stmt->execute([$idCantor]);
				$musicasCantor = $stmt->fetchAll(PDO::FETCH_ASSOC);

				// Adicione também a informação de qual música está em execução na fila principal
				// Certifique-se de que getMusicaEmExecucao($pdo) esteja disponível em funcoes_fila.php
				$musicaEmExecucaoGeral = getMusicaEmExecucao($pdo);

				$response = [
					'success' => true,
					'musicas' => $musicasCantor,
					'musica_em_execucao_geral' => $musicaEmExecucaoGeral // Adiciona esta informação
				];
				echo json_encode($response);
				exit();

			} catch (PDOException $e) {
				$response['message'] = "Erro ao buscar músicas do cantor: " . $e->getMessage();
				echo json_encode($response);
				exit();
			}
		} else {
			$response['message'] = 'ID do cantor inválido.';
			echo json_encode($response);
			exit();
		}
		break;		

    default:
        // Se a ação não for reconhecida, a $response padrão já é retornada
        // A mensagem já foi definida no topo como 'Requisição inválida ou ação não especificada.'
        break;
}

echo json_encode($response);
exit();
?>