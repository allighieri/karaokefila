<?php
require_once 'init.php';
/**
 * Marca uma música na fila como 'em_execucao', 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas.
 * @param string $status Novo status ('em_execucao', 'cantou', 'pulou').
 * @return bool True em caso de sucesso, false caso contrário.
 */
function atualizarStatusMusicaFila(PDO $pdo, $filaId, $status) {
    // Removido: A constante ID_TENANTS é global e não precisa de 'global'
    // Removido: A constante ID_EVENTO_ATIVO também será usada diretamente

    error_log("DEBUG: Chamada atualizarStatusMusicaFila com filaId: " . $filaId . ", status: " . $status);
    try {
        // Primeiro, obtenha o musica_cantor_id, id_cantor e id_musica associados a este filaId
        $stmtGetInfo = $pdo->prepare("SELECT musica_cantor_id, id_cantor, id_musica FROM fila_rodadas WHERE id = ? AND id_tenants = ?");
        // Alterado: Usa a constante ID_TENANTS
        $stmtGetInfo->execute([$filaId, ID_TENANTS]);
        $filaItem = $stmtGetInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Item da fila (ID: " . $filaId . ") não encontrado ou não pertence ao tenant " . ID_TENANTS . " para atualizar status.");
            return false;
        }

        $musicaCantorId = $filaItem['musica_cantor_id'];
        $idCantor = $filaItem['id_cantor'];
        $idMusica = $filaItem['id_musica'];

        $pdo->beginTransaction();

        $successFilaUpdate = false;
        $successMusicasCantorUpdate = true;

        if ($status === 'em_execucao') {
            // CORRIGIDO: Agora a função getRodadaAtual é chamada com os 2 argumentos esperados
            // Alterado: Usa a constante ID_TENANTS
            $rodadaAtual = getRodadaAtual($pdo, ID_TENANTS);

            // 1. Resetar QUALQUER música que ESTAVA 'em_execucao' na fila_rodadas da rodada atual
            $stmtResetPreviousExecution = $pdo->prepare("UPDATE fila_rodadas SET status = 'aguardando', timestamp_inicio_canto = NULL, timestamp_fim_canto = NULL WHERE rodada = ? AND status = 'em_execucao' AND id_tenants = ?");
            // Alterado: Usa a constante ID_TENANTS
            $stmtResetPreviousExecution->execute([$rodadaAtual, ID_TENANTS]);
            error_log("DEBUG: Músicas anteriormente 'em_execucao' na fila_rodadas resetadas para 'aguardando' na rodada " . $rodadaAtual . " para o tenant " . ID_TENANTS . ".");

            // 2. Definir a nova música como 'em_execucao' na fila_rodadas.
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_inicio_canto = NOW(), timestamp_fim_canto = NULL WHERE id = ? AND id_tenants = ?");
            // Alterado: Usa a constante ID_TENANTS
            $successFilaUpdate = $stmt->execute([$status, $filaId, ID_TENANTS]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (em_execucao): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // 3. Atualizar o status NA TABELA musicas_cantor. Lembre-se que musicas_cantor usa id_eventos
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ? AND id_eventos = ?");
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (em_execucao): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } elseif ($status === 'cantou') {
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ? AND id_tenants = ?");
            // Alterado: Usa a constante ID_TENANTS
            $successFilaUpdate = $stmt->execute([$status, $filaId, ID_TENANTS]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (cantou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'cantou' WHERE id = ? AND id_eventos = ?");
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (cantou): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } elseif ($status === 'pulou') {
            // A sua lógica aqui depende da relação entre cantores e eventos. Se um cantor
            // pertence a um tenant, mas as músicas dele pertencem a um evento, a lógica
            // para encontrar o cantor certo e atualizar a ordem pode ser complexa.
            // A solução mais simples é filtrar o UPDATE por id_tenants na tabela cantores.
            $stmtGetOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_eventos = ?");
            $stmtGetOrder->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            $ordemMusicaPulada = $stmtGetOrder->fetchColumn();

            if ($ordemMusicaPulada !== false) {
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = GREATEST(1, ?) WHERE id = ? AND id_tenants = ?");
                // Alterado: Usa a constante ID_TENANTS
                $stmtUpdateCantorOrder->execute([$ordemMusicaPulada, $idCantor, ID_TENANTS]);
                error_log("DEBUG: Cantor " . $idCantor . " do tenant " . ID_TENANTS . " teve proximo_ordem_musica resetado para " . $ordemMusicaPulada . " após música pulada (fila_id: " . $filaId . ").");
            } else {
                error_log("Alerta: Música pulada (musica_cantor_id: " . $musicaCantorId . ") não encontrada na lista musicas_cantor. Não foi possível resetar o proximo_ordem_musica.");
                $successMusicasCantorUpdate = false;
            }

            // Atualiza o status na tabela fila_rodadas para 'pulou'
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ? AND id_tenants = ?");
            // Alterado: Usa a constante ID_TENANTS
            $successFilaUpdate = $stmt->execute([$status, $filaId, ID_TENANTS]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (pulou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ? AND id_eventos = ?");
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (pulou): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());
        } else {
            error_log("Erro: Status inválido na função atualizarStatusMusicaFila: " . $status);
            $pdo->rollBack();
            return false;
        }

        if ($successFilaUpdate && $successMusicasCantorUpdate) {
            $pdo->commit();
            error_log("DEBUG: Transação commitada para fila_id: " . $filaId . ", status: " . $status);
            return true;
        } else {
            $pdo->rollBack();
            error_log("DEBUG: Transação revertida para fila_id: " . $filaId . ", status: " . $status . ". Fila success: " . ($successFilaUpdate ? 'true' : 'false') . ", MC success: " . ($successMusicasCantorUpdate ? 'true' : 'false'));
            return false;
        }

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao atualizar status da música na fila: " . $e->getMessage());
        return false;
    }
}