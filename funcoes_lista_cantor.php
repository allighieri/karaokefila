<?php



$mensagem_sucesso = '';
$mensagem_erro = '';

// --- REMOVIDO: Variáveis estáticas para simular o tenant e o evento logados para fins de teste ---
// As constantes ID_TENANTS e ID_EVENTO_ATIVO já fornecem esses valores de forma segura.

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

// Obter o ID do evento selecionado da URL
$evento_selecionado_id = filter_input(INPUT_GET, 'evento_id', FILTER_VALIDATE_INT);

// Obter eventos ativos do tenant
$eventos_ativos = obterEventosAtivosPorTenant(ID_TENANTS);

// Adicionar música ao cantor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_musica_cantor') {
    $id_cantor = filter_input(INPUT_POST, 'id_cantor', FILTER_VALIDATE_INT);
    $id_musica = filter_input(INPUT_POST, 'id_musica', FILTER_VALIDATE_INT);
    $id_evento = filter_input(INPUT_POST, 'id_evento', FILTER_VALIDATE_INT);

    $redirect_cantor_id = $id_cantor ?: $cantor_selecionado_id;
    $redirect_evento_id = $id_evento ?: $evento_selecionado_id;

    // Removido: global $id_evento_ativo;

    if ($id_cantor && $id_musica && $id_evento) {
        try {
            // Buscar a menor ordem entre as músicas com status 'cantou' para inserir antes delas
            $stmtMinCantou = $pdo->prepare(
                "SELECT MIN(ordem_na_lista) 
                 FROM musicas_cantor 
                 WHERE id_cantor = ? AND id_eventos = ? AND status = 'cantou'"
            );
            $stmtMinCantou->execute([$id_cantor, $id_evento]);
            $minOrdemCantou = $stmtMinCantou->fetchColumn();
            
            if ($minOrdemCantou !== false && $minOrdemCantou !== null) {
                // Há músicas cantadas - inserir antes da primeira música cantada
                $proximaOrdem = $minOrdemCantou;
                
                // Incrementar a ordem de todas as músicas a partir da posição de inserção
                $stmtIncrementOrder = $pdo->prepare(
                    "UPDATE musicas_cantor 
                     SET ordem_na_lista = ordem_na_lista + 1 
                     WHERE id_cantor = ? AND id_eventos = ? AND ordem_na_lista >= ?"
                );
                $stmtIncrementOrder->execute([$id_cantor, $id_evento, $proximaOrdem]);
                error_log("DEBUG: Ordens incrementadas para inserir nova música na posição " . $proximaOrdem . " do cantor " . $id_cantor);
            } else {
                // Não há músicas cantadas - usar a lógica original (final da lista)
                $stmtLastOrder = $pdo->prepare("SELECT MAX(ordem_na_lista) AS max_order FROM musicas_cantor WHERE id_cantor = ? AND id_eventos = ?");
                $stmtLastOrder->execute([$id_cantor, $id_evento]);
                $lastOrder = $stmtLastOrder->fetchColumn();
                $proximaOrdem = ($lastOrder !== null) ? $lastOrder + 1 : 1;
            }

            $stmt = $pdo->prepare("INSERT INTO musicas_cantor (id_eventos, id_cantor, id_musica, ordem_na_lista) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$id_evento, $id_cantor, $id_musica, $proximaOrdem])) {
                $_SESSION['mensagem_sucesso'] = "Música adicionada à lista do cantor com sucesso!";
                $redirect_url = "musicas_cantores.php?cantor_id=" . $redirect_cantor_id;
                if ($redirect_evento_id) {
                    $redirect_url .= "&evento_id=" . $redirect_evento_id;
                }
                header("Location: " . $redirect_url);
                exit;
            } else {
                $_SESSION['mensagem_erro'] = "Erro ao adicionar música à lista do cantor.";
                $redirect_url = "musicas_cantores.php?cantor_id=" . $redirect_cantor_id;
                if ($redirect_evento_id) {
                    $redirect_url .= "&evento_id=" . $redirect_evento_id;
                }
                header("Location: " . $redirect_url);
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $_SESSION['mensagem_erro'] = "Esta música já está na lista do cantor.";
            } else {
                $_SESSION['mensagem_erro'] = "Erro de banco de dados: " . $e->getMessage();
            }
            error_log("Erro ao adicionar música ao cantor: " . $e->getMessage());
            header("Location: musicas_cantores.php?cantor_id=" . $redirect_cantor_id);
            exit;
        }
    } else {
        $_SESSION['mensagem_erro'] = "Dados inválidos para adicionar música ao cantor.";
        header("Location: musicas_cantores.php" . ($redirect_cantor_id ? "?cantor_id=" . $redirect_cantor_id : ""));
        exit;
    }
}

// Removido: global $id_tenants_logado;
// Obter cantores para o select, filtrando pelo id_tenants e evento ativo
$stmtCantores = $pdo->prepare("
    SELECT c.id, u.nome as nome_cantor 
    FROM cantores c 
    JOIN usuarios u ON c.id_usuario = u.id 
    JOIN mesas m ON c.id_mesa = m.id
    WHERE c.id_tenants = ? AND m.id_eventos = ?
    ORDER BY u.nome ASC
");
// Alterado: Usa a constante ID_TENANTS e ID_EVENTO_ATIVO
$stmtCantores->execute([ID_TENANTS, ID_EVENTO_ATIVO]);
$cantores_disponiveis = $stmtCantores->fetchAll(PDO::FETCH_ASSOC);


// Obter músicas do cantor selecionado (para exibição inicial)
$musicas_do_cantor = [];
if ($cantor_selecionado_id && $evento_selecionado_id) {
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
        WHERE mc.id_cantor = ? AND mc.id_eventos = ?
        ORDER BY mc.ordem_na_lista ASC
    ");
    $stmtMusicasCantor->execute([$cantor_selecionado_id, $evento_selecionado_id]);
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

            // VALIDAÇÃO DE SEGURANÇA: Verificar se o cantor pertence ao tenant logado
            $stmtCheckCantor = $pdo->prepare("SELECT COUNT(*) FROM cantores WHERE id = ? AND id_tenants = ?");
            // Alterado: Usa a constante ID_TENANTS
            $stmtCheckCantor->execute([$cantor_id, ID_TENANTS]);
            if ($stmtCheckCantor->fetchColumn() == 0) {
                $pdo->rollBack();
                $_SESSION['mensagem_erro'] = "Operação não permitida: Cantor não pertence ao seu tenant.";
                header("Location: musicas_cantores.php?cantor_id=" . $redirect_cantor_id);
                exit;
            }

            // 1. Obter id_musica e ordem_na_lista da tabela musicas_cantor
            // CORRIGIDO: Adicionado filtro por id_eventos para segurança
            $stmtGetMusicInfo = $pdo->prepare("SELECT id_musica, ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_cantor = ? AND id_eventos = ?");
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $stmtGetMusicInfo->execute([$musica_cantor_id, $cantor_id, ID_EVENTO_ATIVO]);
            $musicaInfo = $stmtGetMusicInfo->fetch(PDO::FETCH_ASSOC);

            if (!$musicaInfo) {
                $pdo->rollBack();
                $_SESSION['mensagem_erro'] = "Música não encontrada ou não pertence a este cantor.";
                header("Location: musicas_cantores.php?cantor_id=" . $redirect_cantor_id);
                exit;
            }

            $idMusicaParaRemover = $musicaInfo['id_musica'];
            $ordemRemovida = $musicaInfo['ordem_na_lista'];

            error_log("Tentando remover musica_cantor_id: " . $musica_cantor_id . ", id_cantor: " . $cantor_id . ", id_musica (real): " . $idMusicaParaRemover);

            // 2. Verificar se a música está na fila em status ativo
            // CORRIGIDO: Adicionado filtro por id_tenants para segurança
            $stmtCheckFila = $pdo->prepare(
                "SELECT COUNT(*) FROM fila_rodadas
                 WHERE id_cantor = ?
                   AND musica_cantor_id = ?
                   AND (status = 'aguardando' OR status = 'em_execucao')
                   AND id_tenants = ?"
            );
            // Alterado: Usa a constante ID_TENANTS
            $stmtCheckFila->execute([$cantor_id, $musica_cantor_id, ID_TENANTS]);
            $isInFila = $stmtCheckFila->fetchColumn();

            error_log("Verificação da fila - id_cantor: " . $cantor_id . ", musica_cantor_id: " . $musica_cantor_id . ", Status na Fila: " . ($isInFila > 0 ? "TRUE" : "FALSE") . " (Count: " . $isInFila . ")");

            if ($isInFila > 0) {
                $pdo->rollBack();
                $_SESSION['mensagem_erro'] = "Não é possível remover a música. Ela está atualmente na fila (selecionada para rodada ou em execução).";
                error_log("Alerta: Tentativa de excluir música (musica_cantor_id: " . $musica_cantor_id . ", Cantor ID: " . $cantor_id . ") que está atualmente na fila. Exclusão não permitida.");
                header("Location: musicas_cantores.php?cantor_id=" . $redirect_cantor_id);
                exit;
            }

            // Se chegou até aqui, a música não está em uso ativo na fila, pode prosseguir com a exclusão
            // Primeiro, remover qualquer referência desta música na tabela fila_rodadas (especialmente com status 'cantou')
            $stmtDeleteFilaRefs = $pdo->prepare(
                "DELETE FROM fila_rodadas 
                 WHERE id_cantor = ? AND musica_cantor_id = ? AND id_tenants = ?"
            );
            $stmtDeleteFilaRefs->execute([$cantor_id, $musica_cantor_id, ID_TENANTS]);
            error_log("DEBUG: Referências da música (musica_cantor_id: " . $musica_cantor_id . ") removidas da tabela fila_rodadas.");
            
            // Agora pode excluir da tabela musicas_cantor sem violação de chave estrangeira
            $stmt = $pdo->prepare("DELETE FROM musicas_cantor WHERE id = ? AND id_cantor = ?");
            if ($stmt->execute([$musica_cantor_id, $cantor_id])) {
                error_log("DEBUG: Música (musica_cantor_id: " . $musica_cantor_id . ") removida com sucesso da musicas_cantor.");

                // Reajusta a ordem das músicas restantes
                // CORRIGIDO: Adicionado filtro por id_eventos para segurança
                $stmtUpdateOrder = $pdo->prepare("
                    UPDATE musicas_cantor
                    SET ordem_na_lista = ordem_na_lista - 1
                    WHERE id_cantor = ? AND id_eventos = ? AND ordem_na_lista > ?
                ");
                // Alterado: Usa a constante ID_EVENTO_ATIVO
                $stmtUpdateOrder->execute([$cantor_id, ID_EVENTO_ATIVO, $ordemRemovida]);
                error_log("DEBUG: Ordens de musicas_cantor para o cantor " . $cantor_id . " ajustadas. Músicas com ordem > " . $ordemRemovida . " foram decrementadas.");

                // --- INÍCIO DA CORREÇÃO ADICIONAL PARA CUIDAR DO CENÁRIO DE RESET ---

                // 1. Obter o valor atual de proximo_ordem_musica para o cantor
                // CORRIGIDO: Adicionado filtro por id_tenants
                $stmtGetProximoOrdemCantor = $pdo->prepare("SELECT proximo_ordem_musica FROM cantores WHERE id = ? AND id_tenants = ?");
                // Alterado: Usa a constante ID_TENANTS
                $stmtGetProximoOrdemCantor->execute([$cantor_id, ID_TENANTS]);
                $proximoOrdemCantorAtual = $stmtGetProximoOrdemCantor->fetchColumn();
                error_log("DEBUG: proximo_ordem_musica atual do cantor " . $cantor_id . ": " . ($proximoOrdemCantorAtual !== false ? $proximoOrdemCantorAtual : 'NULL/false'));

                // 2. Encontrar a menor ordem_na_lista disponível para o cantor (status 'aguardando' ou 'pulou')
                // CORRIGIDO: Adicionado filtro por id_eventos para segurança
                $stmtGetMinOrdemDisponivel = $pdo->prepare("
                    SELECT MIN(ordem_na_lista)
                    FROM musicas_cantor
                    WHERE id_cantor = ? AND id_eventos = ? AND status IN ('aguardando', 'pulou')
                ");
                // Alterado: Usa a constante ID_EVENTO_ATIVO
                $stmtGetMinOrdemDisponivel->execute([$cantor_id, ID_EVENTO_ATIVO]);
                $minOrdemDisponivel = $stmtGetMinOrdemDisponivel->fetchColumn(); // Retorna NULL se não houver registros

                error_log("DEBUG: Menor ordem disponível (aguardando/pulou) para o cantor " . $cantor_id . ": " . ($minOrdemDisponivel !== false ? ($minOrdemDisponivel ?? 'NULL') : 'NULL/false'));

                $novaProximaOrdemCantor = $proximoOrdemCantorAtual; // Inicializa com o valor atual

                if ($minOrdemDisponivel === null) {
                    if ($proximoOrdemCantorAtual === null || $proximoOrdemCantorAtual > 1) {
                        $novaProximaOrdemCantor = 1;
                        error_log("DEBUG: Cantor " . $cantor_id . " sem músicas aguardando/pulou. proximo_ordem_musica será ajustado para 1 para futuras adições.");
                    }
                } else {
                    if ($proximoOrdemCantorAtual === null || $proximoOrdemCantorAtual > $minOrdemDisponivel) {
                        $novaProximaOrdemCantor = $minOrdemDisponivel;
                        error_log("DEBUG: Ajustando proximo_ordem_musica do cantor " . $cantor_id . " de " . ($proximoOrdemCantorAtual !== false ? $proximoOrdemCantorAtual : 'NULL') . " para " . $novaProximaOrdemCantor . " (menor ordem disponível).");
                    }
                }

                if ($proximoOrdemCantorAtual != $novaProximaOrdemCantor && $novaProximaOrdemCantor !== false && $novaProximaOrdemCantor !== null) {
                    // CORRIGIDO: Adicionado filtro por id_tenants na atualização
                    $stmtUpdateCantorProximaOrdem = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ? AND id_tenants = ?");
                    // Alterado: Usa a constante ID_TENANTS
                    $stmtUpdateCantorProximaOrdem->execute([$novaProximaOrdemCantor, $cantor_id, ID_TENANTS]);
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
    header("Location: musicas_cantores.php?cantor_id=" . $redirect_cantor_id);
    exit;
}

// Função para trocar música na lista do cantor
function trocarMusicaCantor($musica_cantor_id, $nova_musica_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Verificar se a música atual existe e obter informações
        $stmtMusicaAtual = $pdo->prepare("
            SELECT mc.id_cantor, mc.id_eventos, mc.ordem_na_lista, mc.status,
                   m.titulo as titulo_atual, m.artista as artista_atual
            FROM musicas_cantor mc
            JOIN musicas m ON mc.id_musica = m.id
            WHERE mc.id = ? AND mc.id_cantor IN (
                SELECT id FROM cantores WHERE id_tenants = ?
            )
        ");
        $stmtMusicaAtual->execute([$musica_cantor_id, ID_TENANTS]);
        $musicaAtual = $stmtMusicaAtual->fetch(PDO::FETCH_ASSOC);
        
        if (!$musicaAtual) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Música não encontrada ou não pertence ao seu tenant.'];
        }
        
        // 2. Verificar se a música não está em execução ou já foi cantada
        if ($musicaAtual['status'] === 'em_execucao') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Não é possível trocar uma música que está em execução.'];
        }
        
        if ($musicaAtual['status'] === 'cantou') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Não é possível trocar uma música que já foi cantada.'];
        }
        
        // 3. Verificar se a nova música existe e pertence ao mesmo tenant
        $stmtNovaMusica = $pdo->prepare("
            SELECT id, titulo, artista, codigo
            FROM musicas
            WHERE id = ? AND id_tenants = ?
        ");
        $stmtNovaMusica->execute([$nova_musica_id, ID_TENANTS]);
        $novaMusica = $stmtNovaMusica->fetch(PDO::FETCH_ASSOC);
        
        if (!$novaMusica) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Nova música não encontrada ou não pertence ao seu tenant.'];
        }
        
        // 4. Verificar se a nova música já está na lista do cantor (excluindo a música atual)
        $stmtVerificarDuplicata = $pdo->prepare("
            SELECT COUNT(*) FROM musicas_cantor
            WHERE id_cantor = ? AND id_musica = ? AND id_eventos = ? AND id != ?
        ");
        $stmtVerificarDuplicata->execute([
            $musicaAtual['id_cantor'], 
            $nova_musica_id, 
            $musicaAtual['id_eventos'],
            $musica_cantor_id
        ]);
        
        if ($stmtVerificarDuplicata->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Esta música já está na lista do cantor.'];
        }
        
        // 5. Atualizar a música mantendo a mesma posição e status
        $stmtTrocarMusica = $pdo->prepare("
            UPDATE musicas_cantor 
            SET id_musica = ?
            WHERE id = ?
        ");
        
        if (!$stmtTrocarMusica->execute([$nova_musica_id, $musica_cantor_id])) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao atualizar a música na lista.'];
        }
        
        // 6. Atualizar também a referência na tabela fila_rodadas se existir
        $stmtTrocarFilaRodadas = $pdo->prepare("
            UPDATE fila_rodadas 
            SET id_musica = ?
            WHERE musica_cantor_id = ? AND id_tenants = ? AND id_eventos = ?
        ");
        
        $stmtTrocarFilaRodadas->execute([
            $nova_musica_id, 
            $musica_cantor_id, 
            ID_TENANTS, 
            $musicaAtual['id_eventos']
        ]);
        
        // Log para debug
        $linhasAfetadasFila = $stmtTrocarFilaRodadas->rowCount();
        error_log("DEBUG: Troca de música - Linhas afetadas na fila_rodadas: " . $linhasAfetadasFila);
        
        $pdo->commit();
        
        $_SESSION['mensagem_sucesso'] = 'Música trocada com sucesso!';
        return [
            'success' => true, 
            'redirect' => true,
            'musica_anterior' => $musicaAtual['titulo_atual'] . ' (' . $musicaAtual['artista_atual'] . ')',
            'nova_musica' => $novaMusica['titulo'] . ' (' . $novaMusica['artista'] . ')'
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao trocar música do cantor: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()];
    }
}

// Função para buscar eventos ativos por tenant
function obterEventosAtivosPorTenant($id_tenants) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT e.id, e.nome, u.nome as nome_mc
        FROM eventos e
        JOIN usuarios u ON e.id_usuario_mc = u.id
        WHERE e.id_tenants = ? AND e.status = 'ativo' AND e.id_usuario_mc = ?
        ORDER BY e.nome ASC
    ");
    $stmt->execute([$id_tenants, ID_USUARIO]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}