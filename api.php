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

    case 'get_musicas_cantor_atualizadas':
    header('Content-Type: application/json');

    $idCantor = filter_var($_GET['id_cantor'] ?? $_POST['id_cantor'], FILTER_VALIDATE_INT);

    if ($idCantor !== false) {
        try {
            $rodadaAtual = getRodadaAtual($pdo);
            error_log("DEBUG get_musicas_cantor_atualizadas (START): Rodada Atual Detectada: " . $rodadaAtual);

            $musicaEmExecucaoGeral = getMusicaEmExecucao($pdo);
            $mcIdEmExecucao = $musicaEmExecucaoGeral['musica_cantor_id'] ?? null;
            error_log("DEBUG get_musicas_cantor_atualizadas: Musica em Execucao Geral (mc_id): " . ($mcIdEmExecucao ?? 'Nenhuma'));

            $stmt = $pdo->prepare("
                SELECT
                    mc.id AS musica_cantor_id,
                    mc.id_musica,
                    mc.id_cantor,
                    mc.ordem_na_lista,
                    m.titulo,
                    m.artista,
                    m.codigo,
                    mc.status AS status_musicas_cantor,
                    COALESCE(
                        (SELECT fr.status
                         FROM fila_rodadas fr
                         WHERE fr.musica_cantor_id = mc.id
                           AND fr.rodada >= :current_rodada
                         ORDER BY fr.rodada DESC, fr.timestamp_adicao DESC LIMIT 1
                        ),
                        'N/A'
                    ) AS status_fila_rodadas_recente
                FROM musicas_cantor mc
                JOIN musicas m ON mc.id_musica = m.id
                WHERE mc.id_cantor = :id_cantor
                ORDER BY mc.ordem_na_lista ASC
            ");
            
            $stmt->execute([
                ':id_cantor' => $idCantor,
                ':current_rodada' => $rodadaAtual
            ]);
            $musicasCantor = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // LOG DE DEBUG ADICIONAL AQUI:
            // Itere sobre os resultados brutos da query para ver o status_fila_rodadas_recente
            error_log("DEBUG get_musicas_cantor_atualizadas: Detalhes dos status da query:");
            foreach ($musicasCantor as $m) {
                error_log("  MC ID: " . $m['musica_cantor_id'] .
                          ", Titulo: " . $m['titulo'] .
                          ", MC_status: " . $m['status_musicas_cantor'] .
                          ", Fila_status_recente: " . $m['status_fila_rodadas_recente']);
            }
            // Fim do LOG ADICIONAL

            foreach ($musicasCantor as &$musica) {
                $finalStatus = $musica['status_musicas_cantor']; // Começa com o status da tabela musicas_cantor como base

                // 1. Prioridade máxima: Música em execução global (getMusicaEmExecucao)
                if ($mcIdEmExecucao !== null && $musica['musica_cantor_id'] == $mcIdEmExecucao) {
                    $finalStatus = 'em_execucao';
                    error_log("DEBUG: Musica " . $musica['titulo'] . " (" . $musica['musica_cantor_id'] . ") definida como 'em_execucao' (prioridade global).");
                } 
                // 2. Prioridade secundária: Status da fila_rodadas, mas SÓ SE não for 'N/A' e não for 'aguardando'
                //    ou se for 'cantou'/'pulou' (estados finais da fila que devem ter prioridade sobre o status base de MC)
                else if ($musica['status_fila_rodadas_recente'] !== 'N/A') {
                    // Se o status da fila for 'cantou' ou 'pulou', ele deve sobrescrever o de musicas_cantor
                    if ($musica['status_fila_rodadas_recente'] === 'cantou' || $musica['status_fila_rodadas_recente'] === 'pulou') {
                        $finalStatus = $musica['status_fila_rodadas_recente'];
                        error_log("DEBUG: Musica " . $musica['titulo'] . " (" . $musica['musica_cantor_id'] . ") definida como '" . $finalStatus . "' (da fila_rodadas - cantou/pulou).");
                    } 
                    // Se o status da fila for 'aguardando', ele NÃO DEVE sobrescrever 'selecionada_para_rodada' de musicas_cantor
                    // Ele só sobrescreverá se o MC_status for algo como 'aguardando_na_lista' ou similar.
                    // Mas como você disse que MC_status já é 'selecionada_para_rodada', não queremos que 'aguardando' da fila sobrescreva.
                    // Se houver outros status relevantes na fila (além de em_execucao, cantou, pulou, aguardando), adicione-os aqui.
                    // Por agora, com 'aguardando' como o único outro status na fila para fins de exibição, 
                    // mantemos a prioridade do MC_status para 'selecionada_para_rodada'.
                    else if ($musica['status_fila_rodadas_recente'] === 'aguardando' && $musica['status_musicas_cantor'] === 'selecionada_para_rodada') {
                         $finalStatus = 'selecionada_para_rodada'; // Força o status correto se a fila disse 'aguardando' mas MC disse 'selecionada'
                         error_log("DEBUG: Musica " . $musica['titulo'] . " (" . $musica['musica_cantor_id'] . ") mantida como 'selecionada_para_rodada' (MC_status tem precedência sobre aguardando da fila).");
                    }
                     // Em outros casos onde o status da fila é relevante e não é aguardando, mas também não é cantou/pulou/em_execucao (ex: proxima_na_fila)
                     else {
                         $finalStatus = $musica['status_fila_rodadas_recente'];
                         error_log("DEBUG: Musica " . $musica['titulo'] . " (" . $musica['musica_cantor_id'] . ") definida como '" . $finalStatus . "' (da fila_rodadas - outro status).");
                     }
                }
                // Se a fila_rodadas_recente for 'N/A', já estamos usando o status_musicas_cantor como base, o que é correto.

                $musica['status'] = $finalStatus;

                unset($musica['status_musicas_cantor']);
                unset($musica['status_fila_rodadas_recente']);
            }
            unset($musica);

            $response = [
                'success' => true,
                'musicas' => $musicasCantor,
                'musica_em_execucao_geral' => $musicaEmExecucaoGeral
            ];
            echo json_encode($response);
            exit();

        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => "Erro ao buscar músicas do cantor: " . $e->getMessage()];
            error_log("Erro em get_musicas_cantor_atualizadas: " . $e->getMessage());
            echo json_encode($response);
            exit();
        }
    } else {
        $response = ['success' => false, 'message' => 'ID do cantor inválido.'];
        echo json_encode($response);
        exit();
    }
    break;	

	case 'add_regra_mesa':
		$minPessoas = filter_input(INPUT_POST, 'min_pessoas', FILTER_VALIDATE_INT);
		$maxPessoas = filter_input(INPUT_POST, 'max_pessoas', FILTER_VALIDATE_INT);
		$maxMusicasPorRodada = filter_input(INPUT_POST, 'max_musicas_por_rodada', FILTER_VALIDATE_INT);

		if ($maxPessoas === 0 || $maxPessoas === false) { 
			$maxPessoas = null;
		}

		if ($minPessoas === false || $minPessoas < 1 || $maxMusicasPorRodada === false || $maxMusicasPorRodada < 1) {
			$response['message'] = 'Dados inválidos para a regra de mesa. Mínimo de pessoas e máximo de músicas são obrigatórios e devem ser números positivos.';
		} else {
			// ALTERADO: Captura o resultado da função
			$result = adicionarOuAtualizarRegraMesa($pdo, $minPessoas, $maxPessoas, $maxMusicasPorRodada);
			
			if ($result === true) { // Se o retorno for true, é sucesso
				$response['success'] = true;
				$response['message'] = 'Regra de mesa salva com sucesso!';
			} else { // Se o retorno for uma string, é uma mensagem de erro
				$response['message'] = $result; // A string de erro é a mensagem
			}
		}
		break;
		
	case 'set_regras_padrao':
		if (setRegrasPadrao($pdo)) {
			$response['success'] = true;
			$response['message'] = 'Regras padrão definidas com sucesso! A fila foi resetada.';
		} else {
			$response['message'] = 'Erro ao definir regras padrão.';
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