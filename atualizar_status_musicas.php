<?php
/**
 * Marca uma música na fila como 'em_execucao', 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas.
 * @param string $status Novo status ('em_execucao', 'cantou', 'pulou').
 * @return bool True em caso de sucesso, false caso contrário.
 */
function atualizarStatusMusicaFila(PDO $pdo, $filaId, $status) {
    error_log("DEBUG: Chamada atualizarStatusMusicaFila com filaId: " . $filaId . ", status: " . $status); // ADICIONADO
    try {
        // Primeiro, obtenha o musica_cantor_id, id_cantor e id_musica associados a este filaId
        $stmtGetInfo = $pdo->prepare("SELECT musica_cantor_id, id_cantor, id_musica FROM fila_rodadas WHERE id = ?");
        $stmtGetInfo->execute([$filaId]);
        $filaItem = $stmtGetInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Item da fila (ID: " . $filaId . ") não encontrado para atualizar status.");
            return false;
        }

        $musicaCantorId = $filaItem['musica_cantor_id']; // <-- NOVO: Obter o ID da tabela musicas_cantor
        $idCantor = $filaItem['id_cantor']; // Manter, pode ser útil para outras lógicas, mas não para o UPDATE final
        $idMusica = $filaItem['id_musica']; // Manter, pode ser útil

        $pdo->beginTransaction();

        $successFilaUpdate = false;
        $successMusicasCantorUpdate = true; // Assume true a menos que falhe

        if ($status === 'em_execucao') {
            $rodadaAtual = getRodadaAtual($pdo);

            // 1. Resetar QUALQUER música que ESTAVA 'em_execucao' na fila_rodadas da rodada atual
            // para 'aguardando'. Isso garante que apenas uma música esteja 'em_execucao' por vez.
            $stmtResetPreviousExecution = $pdo->prepare("UPDATE fila_rodadas SET status = 'aguardando', timestamp_inicio_canto = NULL, timestamp_fim_canto = NULL WHERE rodada = ? AND status = 'em_execucao'");
            $stmtResetPreviousExecution->execute([$rodadaAtual]);
            error_log("DEBUG: Músicas anteriormente 'em_execucao' na fila_rodadas resetadas para 'aguardando' na rodada " . $rodadaAtual . ".");

            // 2. Definir a nova música como 'em_execucao' na fila_rodadas.
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_inicio_canto = NOW(), timestamp_fim_canto = NULL WHERE id = ?");
            $successFilaUpdate = $stmt->execute([$status, $filaId]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (em_execucao): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // 3. Atualizar o status NA TABELA musicas_cantor para 'em_execucao' SOMENTE para o registro ESPECÍFICO (musica_cantor_id).
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ?"); // <-- ALTERADO AQUI!
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (em_execucao): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } elseif ($status === 'cantou') {
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ?");
            $successFilaUpdate = $stmt->execute([$status, $filaId]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (cantou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // Atualiza o status na tabela musicas_cantor para 'cantou' para o registro ESPECÍFICO (musica_cantor_id).
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'cantou' WHERE id = ?"); // <-- ALTERADO AQUI!
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (cantou): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } elseif ($status === 'pulou') {
            // Reobter a ordem da música pulada para a atualização do proximo_ordem_musica
            // ATENÇÃO: Se houver duplicatas em musicas_cantor, esta lógica de ordem pode precisar de mais refinamento.
            // O ideal seria que musica_cantor_id fosse o suficiente para identificar a ordem.
            // Para manter a consistência com o ID específico, podemos buscar a ordem pela musica_cantor_id
            $stmtGetOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ?"); // <-- ALTERADO AQUI!
            $stmtGetOrder->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
            $ordemMusicaPulada = $stmtGetOrder->fetchColumn();

            if ($ordemMusicaPulada !== false) {
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = GREATEST(1, ?) WHERE id = ?");
                $stmtUpdateCantorOrder->execute([$ordemMusicaPulada, $idCantor]);
                error_log("DEBUG: Cantor " . $idCantor . " teve proximo_ordem_musica resetado para " . $ordemMusicaPulada . " após música pulada (fila_id: " . $filaId . ").");
            } else {
                error_log("Alerta: Música pulada (musica_cantor_id: " . $musicaCantorId . ") não encontrada na lista musicas_cantor. Não foi possível resetar o proximo_ordem_musica.");
                $successMusicasCantorUpdate = false; // Sinaliza falha na parte de resetar a ordem
            }

            // Atualiza o status na tabela fila_rodadas para 'pulou'
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ?");
            $successFilaUpdate = $stmt->execute([$status, $filaId]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (pulou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // Atualiza o status na tabela musicas_cantor para 'aguardando' para o registro ESPECÍFICO (musica_cantor_id).
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ?"); // <-- ALTERADO AQUI!
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
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