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

    case 'get_musicas_cantor_atualizadas':
    header('Content-Type: application/json'); // Garante que a resposta seja JSON

    $idCantor = filter_var($_GET['id_cantor'] ?? $_POST['id_cantor'], FILTER_VALIDATE_INT);

    if ($idCantor !== false) {
        try {
            // Obter a rodada atual do sistema para filtrar a fila
            $rodadaAtual = getRodadaAtual($pdo);

            // 1. Obter a música que está "em_execucao" na tabela 'fila_rodadas'
            //    Esta é a ÚNICA fonte de verdade para a música em execução global.
            $musicaEmExecucaoGeral = getMusicaEmExecucao($pdo);
            $mcIdEmExecucao = $musicaEmExecucaoGeral['musica_cantor_id'] ?? null; // ID da musicas_cantor que está em execução

            // 2. Busque as músicas do cantor.
            //    AQUI, removemos a subconsulta COALESCE para o status_final,
            //    pois vamos DETERMINAR o status no PHP com base na regra de prioridade.
            $stmt = $pdo->prepare("
                SELECT
                    mc.id AS musica_cantor_id,
                    mc.id_musica,
                    mc.id_cantor,
                    mc.ordem_na_lista,
                    m.titulo,
                    m.artista,
                    m.codigo,
                    mc.status AS status_musicas_cantor, -- Pega o status diretamente da musicas_cantor
                    COALESCE(fr.status, 'N/A') AS status_fila_rodadas -- Pega o status da fila_rodadas se existir
                FROM musicas_cantor mc
                JOIN musicas m ON mc.id_musica = m.id
                LEFT JOIN fila_rodadas fr ON fr.musica_cantor_id = mc.id AND fr.rodada = :current_rodada
                WHERE mc.id_cantor = :id_cantor
                ORDER BY mc.ordem_na_lista ASC
            ");
            
            $stmt->execute([
                ':id_cantor' => $idCantor,
                ':current_rodada' => $rodadaAtual // Passa a rodada atual para o JOIN
            ]);
            $musicasCantor = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Processar e ajustar o status para o frontend com base na prioridade
            foreach ($musicasCantor as &$musica) { // Usamos & para modificar o array original
                // A prioridade é SEMPRE a música que está de fato em execução GLOBALMENTE na fila
                if ($mcIdEmExecucao !== null && $musica['musica_cantor_id'] == $mcIdEmExecucao) {
                    $musica['status'] = 'em_execucao';
                } 
                // Se não é a música globalmente em execução, então olhamos para os status locais
                else {
                    // Se a música está na fila da rodada atual e tem um status específico lá
                    if ($musica['status_fila_rodadas'] !== 'N/A') { // 'N/A' se não encontrou na fila_rodadas
                        $musica['status'] = $musica['status_fila_rodadas'];
                    } else {
                        // Caso contrário, usa o status da tabela musicas_cantor
                        $musica['status'] = $musica['status_musicas_cantor'];
                    }
                }
                // Remover as colunas auxiliares
                unset($musica['status_musicas_cantor']);
                unset($musica['status_fila_rodadas']);
            }
            unset($musica); // Quebra a referência do último elemento

            $response = [
                'success' => true,
                'musicas' => $musicasCantor,
                'musica_em_execucao_geral' => $musicaEmExecucaoGeral // Mantém esta informação, útil para o frontend
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

    default:
        // Se a ação não for reconhecida, a $response padrão já é retornada
        // A mensagem já foi definida no topo como 'Requisição inválida ou ação não especificada.'
        break;
}

echo json_encode($response);
exit();
?>