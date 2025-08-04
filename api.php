<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'funcoes_fila.php'; // Inclui as funções e a conexão PDO
require_once 'funcoes_login.php'; // Inclui as funções e a conexão PDO

header('Content-Type: application/json; charset=utf-8'); // Garante que a resposta seja JSON UTF-8

$action = $_REQUEST['action'] ?? '';

$response = ['success' => false, 'message' => 'Requisição inválida ou ação não especificada.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
        $response['message'] = 'Erro de conexão com o banco de dados. $pdo não está definido.';
        echo json_encode($response);
        exit();
    // }
}

// O switch agora processará a $action independentemente do método HTTP
switch ($action) {
    case 'montar_rodada':
        $modoFila = $_POST['modo_fila'] ?? 'mesa'; // Pega o modo da requisição AJAX
        if (montarProximaRodada($pdo, $modoFila)) {
            $response['success'] = true;
            $response['message'] = "Nova rodada montada com sucesso no modo '" . htmlspecialchars($modoFila) . "'!";
        } else {
            $response['message'] = "Não foi possível montar uma nova rodada. Verifique os logs do servidor para mais detalhes.";
        }
        break;
    case 'add_cantor':
        $nomeCantor = $_POST['nomeCantor'] ?? '';
        $idMesa = $_POST['idMesa'] ?? '';

        $resultadoAdicao = adicionarCantor($pdo, $nomeCantor, $idMesa);

        $response['success'] = $resultadoAdicao['success'];
        $response['message'] = $resultadoAdicao['message'];
        break;
    case 'get_all_cantores':
        // Supondo que 'getTodosCantoresComNomeMesa' está definida em 'funcoes_fila.php'
        $cantores = getAllCantores($pdo);
        $response['success'] = true;
        $response['cantores'] = $cantores; // Retorna o array de cantores
        break;
    case 'add_mesa':
        $nomeMesa = $_POST['nomeMesa'] ?? ''; // Adicione ?? '' para evitar erro se 'nomeMesa' não vier

        $resultadoAdicao = adicionarMesa($pdo, $nomeMesa);

        $response['success'] = $resultadoAdicao['success'];
        $response['message'] = $resultadoAdicao['message'];
        break;
    case 'edit_mesa': // NOVA AÇÃO PARA EDITAR MESA
        if (isset($_POST['mesa_id']) && is_numeric($_POST['mesa_id']) && isset($_POST['novo_nome_mesa'])) {
            $mesaId = (int)$_POST['mesa_id'];
            $novoNomeMesa = trim($_POST['novo_nome_mesa']);

            if (empty($novoNomeMesa)) {
                $response['message'] = 'O nome da mesa não pode ser vazio.';
                break;
            }

            try {
                $stmt = $pdo->prepare("UPDATE mesas SET nome_mesa = :nome_mesa WHERE id = :id");
                $stmt->bindParam(':nome_mesa', $novoNomeMesa, PDO::PARAM_STR);
                $stmt->bindParam(':id', $mesaId, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    if ($stmt->rowCount() > 0) {
                        $response['success'] = true;
                        $response['message'] = 'Nome da mesa atualizado com sucesso!';
                    } else {
                        $response['message'] = 'Nenhuma alteração feita ou mesa não encontrada.';
                    }
                } else {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception("Falha na execução do UPDATE: " . $errorInfo[2]);
                }
            } catch (Exception $e) {
                $response['message'] = 'Erro ao atualizar nome da mesa: ' . $e->getMessage();
                error_log("ERRO (API - Edit Mesa): " . $e->getMessage());
            }
        } else {
            $response['message'] = 'Dados de edição da mesa inválidos.';
        }
        break;
    case 'get_all_mesas':
        // Esta função deve retornar um array de mesas (id, nome_mesa, tamanho_mesa)
        $mesas = getTodasMesas($pdo);
        $response['success'] = true;
        $response['mesas'] = $mesas; // Retorna o array de mesas
        break;
    case 'excluir_mesa':
        $mesaId = (int)($_POST['mesa_id'] ?? 0);
        if ($mesaId > 0) {
            $resultadoExclusao = excluirMesa($pdo, $mesaId);
            $response['success'] = $resultadoExclusao['success'];
            $response['message'] = $resultadoExclusao['message'];
        } else {
            $response['message'] = 'ID da mesa inválido para exclusão.';
        }
        break;
    case 'excluir_cantor':
        $cantorId = (int)($_POST['cantorId'] ?? 0);

        if ($cantorId <= 0) {
            $response['message'] = 'ID do cantor inválido.';
        } else {
            // Chama a função atualizada removerCantor e usa seu retorno
            $resultadoRemocao = removerCantor($pdo, $cantorId);
            $response['success'] = $resultadoRemocao['success'];
            $response['message'] = $resultadoRemocao['message'];
        }
        break;
    case 'edit_cantor':
        if (isset($_POST['cantor_id'], $_POST['novo_nome_cantor'], $_POST['nova_mesa_id'])) {
            $cantorId = (int)$_POST['cantor_id'];
            $novoNomeCantor = trim($_POST['novo_nome_cantor']);
            $novaMesaId = (int)$_POST['nova_mesa_id'];

            if (empty($novoNomeCantor)) {
                $response['message'] = 'O nome do cantor não pode ser vazio.';
                break;
            }

            if (empty($novaMesaId)) {
                $response['message'] = 'Por favor, selecione uma mesa para o cantor.';
                break;
            }

            $pdo->beginTransaction(); // Inicia a transação
            try {
                // 1. Obter a ID da mesa antiga do cantor
                $stmtOldMesa = $pdo->prepare("SELECT id_mesa FROM cantores WHERE id = :cantor_id");
                $stmtOldMesa->bindParam(':cantor_id', $cantorId, PDO::PARAM_INT);
                $stmtOldMesa->execute();
                $oldMesaId = $stmtOldMesa->fetchColumn(); // Pega apenas a coluna id_mesa

                // 2. Atualizar os dados do cantor
                $stmtUpdateCantor = $pdo->prepare("UPDATE cantores SET nome_cantor = :nome_cantor, id_mesa = :id_mesa WHERE id = :id");
                $stmtUpdateCantor->bindParam(':nome_cantor', $novoNomeCantor, PDO::PARAM_STR);
                $stmtUpdateCantor->bindParam(':id_mesa', $novaMesaId, PDO::PARAM_INT);
                $stmtUpdateCantor->bindParam(':id', $cantorId, PDO::PARAM_INT);
                $stmtUpdateCantor->execute();

                // 3. Lógica para atualizar tamanho_mesa nas tabelas de mesas
                if ($oldMesaId !== false && (int)$oldMesaId !== $novaMesaId) { // Se a mesa foi realmente alterada
                    // Decrementar tamanho_mesa da mesa antiga
                    $stmtDecrement = $pdo->prepare("UPDATE mesas SET tamanho_mesa = GREATEST(0, tamanho_mesa - 1) WHERE id = :old_mesa_id");
                    $stmtDecrement->bindParam(':old_mesa_id', $oldMesaId, PDO::PARAM_INT);
                    $stmtDecrement->execute();

                    // Incrementar tamanho_mesa da nova mesa
                    $stmtIncrement = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa + 1 WHERE id = :new_mesa_id");
                    $stmtIncrement->bindParam(':new_mesa_id', $novaMesaId, PDO::PARAM_INT);
                    $stmtIncrement->execute();
                }

                // Verifica se alguma linha foi realmente afetada pelo UPDATE do cantor
                // (Pode ser 0 se o nome e mesa forem os mesmos de antes, o que é aceitável)
                if ($stmtUpdateCantor->rowCount() > 0 || ($oldMesaId !== false && (int)$oldMesaId !== $novaMesaId)) {
                    $pdo->commit(); // Confirma a transação
                    $response['success'] = true;
                    $response['message'] = 'Cantor e mesa associada atualizados com sucesso!';
                } else {
                    $pdo->rollBack(); // Reverte a transação se nada foi alterado
                    $response['message'] = 'Nenhuma alteração feita ou cantor não encontrado.';
                }

            } catch (Exception $e) {
                $pdo->rollBack(); // Em caso de erro, reverte todas as operações
                $response['message'] = 'Erro ao atualizar cantor: ' . $e->getMessage();
                error_log("ERRO (API - Edit Cantor Transaction): " . $e->getMessage());
            }
        } else {
            $response['message'] = 'Dados de edição do cantor inválidos.';
        }
        break;

    case 'resetar_sistema':
        if (resetarSistema($pdo)) {
            $response['success'] = true; // DEVE SER BOOLEANO TRUE
            $response['message'] = "Sistema de karaokê resetado!"; // A MENSAGEM VAI AQUI
        } else {
            $response['success'] = false; // DEVE SER BOOLEANO FALSE
            $response['message'] = "Erro ao resetar o sistema de karaokê. Verifique os logs."; // A MENSAGEM DE ERRO AQUI
        }
        break;
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
            // Código corrigido
            global $id_tenants_logado; // Se a variável for global
            $rodadaAtual = getRodadaAtual($pdo, $id_tenants_logado);
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
    case 'cadastrar_usuario':
        $nome = trim(filter_input(INPUT_POST, 'nome', FILTER_DEFAULT));
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $telefone = trim(filter_input(INPUT_POST, 'telefone', FILTER_DEFAULT));
        $cidade = trim(filter_input(INPUT_POST, 'cidade', FILTER_DEFAULT));
        $uf = trim(filter_input(INPUT_POST, 'uf', FILTER_DEFAULT));

        // CORREÇÃO AQUI: Remove os espaços em branco da senha antes de criar o hash
        $senha = trim($_POST['senha'] ?? '');

        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        $code = $_POST['code'] ?? '';

        $resultado = cadastrarUsuario($pdo, $nome, $email, $telefone, $cidade, $uf, $senha_hash, $code);

        $response['success'] = $resultado['success'];
        $response['message'] = $resultado['message'];
        break;

    case 'validar_codigo':
        $codigo = $_POST['codigo'] ?? '';

        if (!empty($codigo)) {
            // Chamando a função com apenas o código
            $response = validar_codigo($pdo, $codigo);
        } else {
            $response = ["success" => false, "message" => "O código de acesso é obrigatório."];
        }
        break;
    case 'logar':
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';

        $resultado_login = logar_usuario($email, $senha);

        if ($resultado_login === 'sucesso') {
            $response = ['success' => true, 'message' => 'Login realizado com sucesso!'];
        } else {
            $response = ['success' => false, 'message' => $resultado_login];
        }
        break;



    default:
        // Se a ação não for reconhecida, a $response padrão já é retornada
        // A mensagem já foi definida no topo como 'Requisição inválida ou ação não especificada.'
        break;
}

echo json_encode($response);
exit();
