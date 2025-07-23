<?php
require_once 'config.php'; // A variável $pdo estará disponível aqui
require_once 'montar_rodadas.php'; // Função que cria as rodadas
require_once 'reordenar_fila_rodadas.php'; // Função que reordena a fila, impede musicas da mesma mesa em sequência
require_once 'atualizar_status_musicas.php'; // Função que atualiza o status das músicas

/**
 * Adiciona uma nova mesa ao sistema.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $nomeMesa Nome/identificador da mesa.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarMesa(PDO $pdo, $nomeMesa) { // O parâmetro $tamanhoMesa foi removido
    try {
        // A coluna 'tamanho_mesa' na tabela 'mesas' DEVE ter um DEFAULT de 0 no seu schema do banco de dados.
        // Se não tiver, o MySQL/SQLite vai inserir 0 por padrão para INT ou dar erro se for NOT NULL sem default.
        // Se preferir ser explícito, poderia ser:
        // $stmt = $pdo->prepare("INSERT INTO mesas (nome_mesa, tamanho_mesa) VALUES (?, 0)");
        $stmt = $pdo->prepare("INSERT INTO mesas (nome_mesa) VALUES (?)");
        return $stmt->execute([$nomeMesa]);
    } catch (\PDOException $e) {
        error_log("Erro ao adicionar mesa: " . $e->getMessage());
        return false;
    }
}

/**
 * Adiciona um novo cantor e o associa a uma mesa.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $nomeCantor Nome do cantor.
 * @param int $idMesa ID da mesa à qual o cantor pertence.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarCantor(PDO $pdo, $nomeCantor, $idMesa) {
    try {
        $pdo->beginTransaction(); // Inicia a transação para garantir atomicidade

        // 1. Insere o novo cantor
        $stmt = $pdo->prepare("INSERT INTO cantores (nome_cantor, id_mesa) VALUES (?, ?)");
        $success = $stmt->execute([$nomeCantor, $idMesa]);

        if ($success) {
            // 2. Incrementa o 'tamanho_mesa' da mesa associada
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa + 1 WHERE id = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa]);

            if ($updateSuccess) {
                $pdo->commit(); // Confirma ambas as operações se tudo deu certo
                return true;
            } else {
                $pdo->rollBack(); // Reverte a inserção do cantor se a atualização da mesa falhar
                error_log("Erro ao incrementar tamanho_mesa para a mesa ID: " . $idMesa);
                return false;
            }
        } else {
            $pdo->rollBack(); // Reverte se a inserção do cantor falhar
            error_log("Erro ao inserir o cantor: " . $nomeCantor);
            return false;
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) { // Garante que, se a transação foi iniciada, ela seja revertida
            $pdo->rollBack();
        }
        error_log("Erro ao adicionar cantor (PDOException): " . $e->getMessage());
        return false;
    }
}

/**
 * Remove um cantor e decrementa o tamanho_mesa da mesa associada.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $idCantor ID do cantor a ser removido.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function removerCantor(PDO $pdo, $idCantor) {
    try {
        $pdo->beginTransaction();

        // 1. Obter o id_mesa do cantor antes de excluí-lo
        $stmtGetMesaId = $pdo->prepare("SELECT id_mesa FROM cantores WHERE id = ?");
        $stmtGetMesaId->execute([$idCantor]);
        $idMesa = $stmtGetMesaId->fetchColumn();

        if ($idMesa === false) { // Cantor não encontrado
            $pdo->rollBack();
            error_log("Erro: Cantor ID " . $idCantor . " não encontrado para remoção.");
            return false;
        }

        // 2. Remover o cantor
        $stmtDeleteCantor = $pdo->prepare("DELETE FROM cantores WHERE id = ?");
        $successDelete = $stmtDeleteCantor->execute([$idCantor]);

        if ($successDelete) {
            // 3. Decrementar o 'tamanho_mesa' da mesa associada (se for maior que zero)
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = GREATEST(0, tamanho_mesa - 1) WHERE id = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa]);

            if ($updateSuccess) {
                $pdo->commit();
                return true;
            } else {
                $pdo->rollBack();
                error_log("Erro ao decrementar tamanho_mesa para a mesa ID: " . $idMesa);
                return false;
            }
        } else {
            $pdo->rollBack();
            error_log("Erro ao remover o cantor ID: " . $idCantor);
            return false;
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao remover cantor (PDOException): " . $e->getMessage());
        return false;
    }
}

/**
 * Adiciona uma nova música ao repertório.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $titulo Título da música.
 * @param string $artista Artista da música.
 * @param int|null $duracaoSegundos Duração da música em segundos (opcional).
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarMusica(PDO $pdo, $titulo, $artista, $duracaoSegundos = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO musicas (titulo, artista, duracao_segundos) VALUES (?, ?, ?)");
        return $stmt->execute([$titulo, $artista, $duracaoSegundos]);
    } catch (\PDOException $e) {
        error_log("Erro ao adicionar música: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o número da rodada atual.
 * Retorna 0 se o sistema estiver em um estado "limpo" (sem rodadas ativas ou no histórico)
 * para que a próxima rodada a ser montada seja a 1.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return int O número da rodada atual (ou 0 se for a primeira rodada a ser criada).
 */
function getRodadaAtual(PDO $pdo) {
    try {
        // 1. Tenta obter a rodada_atual da tabela de controle.
        $stmt = $pdo->query("SELECT rodada_atual FROM controle_rodada WHERE id = 1");
        $rodadaAtualFromDB = $stmt->fetchColumn();

        // Converte para int e trata o caso de não haver registro (null/false).
        $rodadaAtualFromDB = ($rodadaAtualFromDB === false || $rodadaAtualFromDB === null) ? 0 : (int)$rodadaAtualFromDB;

        // 2. Verifica se existe *alguma* música com status 'aguardando' em *qualquer* rodada.
        // Se houver, a rodada ativa é a rodada dela.
        $stmtCheckAnyActiveFila = $pdo->query("SELECT rodada FROM fila_rodadas WHERE status = 'aguardando' OR status = 'em_execucao' ORDER BY rodada DESC LIMIT 1");
        $rodadaComMusicasAguardando = $stmtCheckAnyActiveFila->fetchColumn();

        if ($rodadaComMusicasAguardando !== false && $rodadaComMusicasAguardando !== null) {
            // Se encontrou músicas aguardando, a rodada atual é a rodada dessas músicas.
            return (int)$rodadaComMusicasAguardando;
        }

        // 3. Se não há músicas 'aguardando', verifica se existe *alguma* rodada com 'cantou' ou 'pulou'.
        // Isso indica que já houve rodadas no passado.
        $stmtMaxRodadaFinalizada = $pdo->query("SELECT MAX(rodada) FROM fila_rodadas WHERE status IN ('cantou', 'pulou')");
        $maxRodadaFinalizada = $stmtMaxRodadaFinalizada->fetchColumn();

        if ($maxRodadaFinalizada !== false && $maxRodadaFinalizada !== null) {
            // Se existem rodadas finalizadas, a "rodada atual" para fins de numeração
            // deve ser a última rodada finalizada. A próxima a ser criada será essa + 1.
            return (int)$maxRodadaFinalizada;
        }

        // 4. Se não há músicas aguardando E não há rodadas finalizadas,
        // significa que o sistema está em um estado "limpo" ou foi resetado.
        // Retorna 0 para que a próxima rodada a ser montada (0 + 1) seja a 1.
        return 0;

    } catch (\PDOException $e) {
        error_log("Erro ao obter rodada atual: " . $e->getMessage());
        // Em caso de erro, retorna 0 para garantir que o sistema possa iniciar.
        return 0;
    }
}




// Função de formatação do status
function formatarStatus($status)
{
    return match ($status) {
        'em_execucao' => '🎤 Em execução',
        'selecionada_para_rodada' => '⏳ Selecionada para a rodada',
        'cantou' => '✅ Já cantou',
        'pulou' => '⏭️ Pulou a vez',
        default => '🕒 Aguardando',
    };
}

/**
 * Obtém a próxima música a ser cantada na rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array|null Dados da próxima música e cantor, ou null se não houver.
 */
function getProximaMusicaFila(PDO $pdo) {
    $rodadaAtual = getRodadaAtual($pdo);
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
				fr.musica_cantor_id, -- Adicionar este campo também
                c.nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ? AND fr.status = 'aguardando'
            ORDER BY fr.ordem_na_rodada ASC
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
        return $stmt->fetch();
    } catch (\PDOException $e) {
        error_log("Erro ao obter próxima música da fila: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtém a música que está atualmente 'em_execucao' na rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array|null Dados da música em execução, ou null se não houver.
 */
function getMusicaEmExecucao(PDO $pdo) {
    $rodadaAtual = getRodadaAtual($pdo); // Assume que getRodadaAtual existe e funciona
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                fr.id_cantor,         -- Adicionar para consistência
                fr.id_musica,         -- Adicionar para consistência
                fr.musica_cantor_id,  -- <<< ADICIONAR ESTA LINHA AQUI
                c.nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                m.codigo AS codigo_musica, -- Adicionar código da música se precisar
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ? AND fr.status = 'em_execucao'
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter música em execução: " . $e->getMessage());
        return null;
    }
}


/**
 * Troca a música de um item na fila de rodadas.
 * A música original não é removida da lista pré-selecionada do cantor.
 * O proximo_ordem_musica do cantor é decrementado para que a música original possa ser considerada novamente.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas a ser atualizado.
 * @param int $novaMusicaId ID da nova música a ser definida para o item da fila.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function trocarMusicaNaFilaAtual(PDO $pdo, $filaId, $novaMusicaId) {
    try {
        $pdo->beginTransaction(); // Inicia transação para garantir atomicidade

        // 1. Obter informações do item da fila original
        $stmtGetOldMusicInfo = $pdo->prepare("SELECT id_cantor, id_musica, musica_cantor_id FROM fila_rodadas WHERE id = ? AND (status = 'aguardando' OR status = 'em_execucao')");
        $stmtGetOldMusicInfo->execute([$filaId]);
        $filaItem = $stmtGetOldMusicInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Tentativa de trocar música em item da fila inexistente ou já finalizado (ID: " . $filaId . ").");
            $pdo->rollBack();
            return false;
        }

        $idCantor = $filaItem['id_cantor'];
        $musicaOriginalId = $filaItem['id_musica'];
        $musicaCantorOriginalId = $filaItem['musica_cantor_id']; // ID da tabela musicas_cantor, se aplicável
        
        // --- Lógica para a MÚSICA ORIGINAL (saindo da fila) ---
        // APENAS se a música original veio de musicas_cantor, tentamos resetar seu status para 'aguardando'
        if ($musicaCantorOriginalId !== null) { 
            $stmtGetOriginalOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ?");
            $stmtGetOriginalOrder->execute([$musicaCantorOriginalId]);
            $ordemMusicaOriginal = $stmtGetOriginalOrder->fetchColumn();

            if ($ordemMusicaOriginal !== false) {
                // Atualizar o proximo_ordem_musica do cantor para a ordem da música original
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
                $stmtUpdateCantorOrder->execute([$ordemMusicaOriginal, $idCantor]);
                error_log("DEBUG: Cantor " . $idCantor . " teve proximo_ordem_musica resetado para " . $ordemMusicaOriginal . " após troca de música (fila_id: " . $filaId . ").");
                
                // Atualiza o status da música ORIGINAL na tabela musicas_cantor de volta para 'aguardando'
                $stmtUpdateOriginalMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ?");
                $stmtUpdateOriginalMusicaCantorStatus->execute([$musicaCantorOriginalId]);
                error_log("DEBUG: Status da música original (musicas_cantor_id: " . $musicaCantorOriginalId . ") do cantor " . $idCantor . " resetado para 'aguardando' na tabela musicas_cantor.");
                
            } else {
                error_log("Alerta: ID de musica_cantor_id (" . $musicaCantorOriginalId . ") para o item da fila (ID: " . $filaId . ") não encontrado na tabela musicas_cantor. Não foi possível resetar o proximo_ordem_musica ou o status.");
            }
        } else {
            error_log("DEBUG: Música original (ID: " . $musicaOriginalId . ") do item da fila (ID: " . $filaId . ") não possui um musica_cantor_id associado, não há status para resetar em musicas_cantor.");
        }

        // --- Lógica para a NOVA MÚSICA (entrando na fila) ---
        // Antes de atualizar a fila_rodadas, precisamos decidir o musica_cantor_id da nova música.
        $novaMusicaCantorId = null;
        $novaMusicaStatusExistente = null;

        // Verificar se a nova música existe na lista musicas_cantor para este cantor
        $stmtCheckNewMusicInCantorList = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id_cantor = ? AND id_musica = ? LIMIT 1");
        $stmtCheckNewMusicInCantorList->execute([$idCantor, $novaMusicaId]);
        $newMusicInCantorList = $stmtCheckNewMusicInCantorList->fetch(PDO::FETCH_ASSOC);

        if ($newMusicInCantorList) {
            $novaMusicaCantorId = $newMusicInCantorList['id'];
            $novaMusicaStatusExistente = $newMusicInCantorList['status'];
            
            // Atualizar o status da NOVA música na tabela musicas_cantor
            // SOMENTE se não for 'cantou' ou 'em_execucao' (se você quiser evitar sobrescrever esses)
            // Ou, para o seu caso, se o status existente for 'aguardando', 'selecionada_para_rodada'
            if ($novaMusicaStatusExistente == 'aguardando') { // ou outros status que podem ser sobrescritos
                 $stmtUpdateNewMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ?");
                 $stmtUpdateNewMusicaCantorStatus->execute([$novaMusicaCantorId]);
                 error_log("DEBUG: Status da nova música (musicas_cantor_id: " . $novaMusicaCantorId . ") do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");
            } else {
                 error_log("DEBUG: Status da nova música (musicas_cantor_id: " . $novaMusicaCantorId . ", status: " . $novaMusicaStatusExistente . ") do cantor " . $idCantor . " NÃO foi alterado em musicas_cantor, pois já tinha um status final ou não elegível para mudança.");
            }
        } else {
            error_log("DEBUG: Nova música (ID: " . $novaMusicaId . ") não encontrada na lista musicas_cantor para o cantor " . $idCantor . ". Não há status para atualizar em musicas_cantor.");
            // Se a música não está na lista do cantor, ela não tem um musica_cantor_id para ser atualizado.
            // Aqui você poderia, opcionalmente, inseri-la na musicas_cantor com status 'selecionada_para_rodada'
            // se o comportamento desejado for que qualquer música selecionada para a fila seja adicionada à lista do cantor.
            // Por enquanto, ela só existirá na fila_rodadas.
        }

        // 4. Atualiza o id_musica e musica_cantor_id na tabela fila_rodadas com a nova música
        $stmtUpdateFila = $pdo->prepare("UPDATE fila_rodadas SET id_musica = ?, musica_cantor_id = ? WHERE id = ?");
        $result = $stmtUpdateFila->execute([$novaMusicaId, $novaMusicaCantorId, $filaId]); // Passa o novaMusicaCantorId

        if ($result) {
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false;
        }

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao trocar música na fila atual: " . $e->getMessage());
        return false;
    }
}


/**
 * Atualiza a ordem dos itens na fila de uma rodada específica.
 * Utiliza a coluna 'ordem_na_rodada' da tabela 'fila_rodadas'.
 *
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $rodada O número da rodada a ser atualizada.
 * @param array $novaOrdemFila Um array onde a chave é o ID do item da fila (fila_rodadas.id)
 * e o valor é a nova posição (ordem_na_rodada).
 * Ex: [101 => 1, 105 => 2, 103 => 3] onde 101, 105, 103 são IDs da tabela fila_rodadas.
 * @return bool True se a atualização for bem-sucedida, false caso contrário.
 */
function atualizarOrdemFila(PDO $pdo, int $rodada, array $novaOrdemFila): bool {
    if (empty($novaOrdemFila)) {
        error_log("DEBUG: Array de nova ordem da fila vazio. Nenhuma atualização realizada.");
        return true; // Nada para atualizar, considera sucesso
    }

    try {
        $pdo->beginTransaction();

        // Altera a coluna para 'ordem_na_rodada'
        $stmt = $pdo->prepare("UPDATE fila_rodadas SET ordem_na_rodada = ? WHERE id = ? AND rodada = ?");

        foreach ($novaOrdemFila as $filaItemId => $novaPosicao) {
            // Garante que a novaPosicao seja um inteiro
            $novaPosicaoInt = (int)$novaPosicao;
            if (!$stmt->execute([$novaPosicaoInt, $filaItemId, $rodada])) {
                error_log("ERRO: Falha ao atualizar ordem do item " . $filaItemId . " para posição " . $novaPosicaoInt);
                $pdo->rollBack();
                return false;
            }
        }

        $pdo->commit();
        error_log("DEBUG: Ordem da fila da rodada " . $rodada . " (usando ordem_na_rodada) atualizada com sucesso.");
        return true;

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERRO: PDOException ao atualizar ordem da fila: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza a coluna 'ordem_na_lista' na tabela 'musicas_cantor'
 * para um cantor específico com base em uma nova ordem.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @param int $idCantor O ID do cantor cujas músicas serão reordenadas.
 * @param array $novaOrdemMusicas Um array associativo onde a chave é o 'id'
 * da tabela 'musicas_cantor' e o valor é a nova 'ordem_na_lista'.
 * @return bool True em caso de sucesso, false em caso de falha.
 */
function atualizarOrdemMusicasCantor(PDO $pdo, int $idCantor, array $novaOrdemMusicas): bool {
    // Se não há músicas para reordenar, retorna sucesso.
    if (empty($novaOrdemMusicas)) {
        return true;
    }

    $pdo->beginTransaction();
    try {
        // Define os status que impedem a reordenação
        $restricted_statuses = ['cantou', 'em_execucao', 'selecionada_para_rodada'];

        // 1. Obter os status atuais de todas as músicas que estão sendo potencialmente reordenadas
        $ids_musicas_cantor = array_keys($novaOrdemMusicas);
        // Cria placeholders para a query IN clause (?, ?, ?, ...)
        $placeholders = implode(',', array_fill(0, count($ids_musicas_cantor), '?'));

        $stmtCheckStatus = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id IN ($placeholders) AND id_cantor = ?");
        // Combina os IDs das músicas e o ID do cantor para a execução da query
        $stmtCheckStatus->execute(array_merge($ids_musicas_cantor, [$idCantor]));
        // Busca os resultados como um array associativo [id => status] para fácil lookup
        $currentStatuses = $stmtCheckStatus->fetchAll(PDO::FETCH_KEY_PAIR);

        // Prepara a query para atualizar a ordem de um item específico
        $stmtUpdate = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = ? WHERE id = ? AND id_cantor = ?");

        // 2. Iterar sobre a nova ordem e aplicar as atualizações APENAS se o status permitir
        foreach ($novaOrdemMusicas as $musicaCantorId => $novaPosicao) {
            // Garante que os IDs e posições são inteiros válidos.
            $musicaCantorId = (int) $musicaCantorId;
            $novaPosicao = (int) $novaPosicao;

            // Verifica se a música existe e se seu status NÃO é um dos restritos
            if (isset($currentStatuses[$musicaCantorId]) && !in_array($currentStatuses[$musicaCantorId], $restricted_statuses)) {
                // Se o status permitir, executa a atualização
                if (!$stmtUpdate->execute([$novaPosicao, $musicaCantorId, $idCantor])) {
                    $pdo->rollBack(); // Se uma atualização falhar, reverte todas
                    error_log("Erro ao executar UPDATE para musicas_cantor ID: $musicaCantorId, nova_posicao: $novaPosicao, cantor ID: $idCantor");
                    return false;
                }
            } else {
                // Se a música tem um status restrito ou não foi encontrada para este cantor,
                // vamos logar isso e continuar, ou você pode optar por reverter tudo aqui.
                // Como o frontend já impede o arrasto, isso serve mais como uma camada de segurança.
                error_log("Tentativa de reordenar música com status restrito ou ID inválido para o cantor $idCantor: musica_cantor_id=$musicaCantorId, status=" . ($currentStatuses[$musicaCantorId] ?? 'N/A'));
                // Se você quiser que a transação inteira falhe se qualquer item restrito for enviado:
                // $pdo->rollBack();
                // return false;
            }
        }

        $pdo->commit(); // Confirma todas as atualizações se tudo correu bem
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack(); // Em caso de exceção, reverte a transação
        error_log("Erro no banco de dados ao atualizar ordem das músicas do cantor: " . $e->getMessage());
        return false;
    }
}


/**
 * Obtém todas as músicas cadastradas no sistema.
 * Útil para popular um dropdown de seleção de músicas.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array Lista de músicas (id, titulo, artista).
 */
function getAllMusicas(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id, titulo, artista FROM musicas ORDER BY titulo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter todas as músicas: " . $e->getMessage());
        return [];
    }
}


/**
 * Obtém a lista completa da fila para a rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array Lista de itens da fila.
 */
function getFilaCompleta(PDO $pdo) {
    $rodadaAtual = getRodadaAtual($pdo);
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                c.nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ?
            ORDER BY
                CASE
                    WHEN fr.status = 'em_execucao' THEN 0 -- A música em execução deve vir primeiro
                    WHEN fr.status = 'aguardando' THEN 1
                    WHEN fr.status = 'selecionada_para_rodada' THEN 2
                    WHEN fr.status = 'pulou' THEN 3
                    WHEN fr.status = 'cantou' THEN 4
                    ELSE 5 -- Para qualquer outro status futuro
                END,
                fr.ordem_na_rodada ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Erro ao obter fila completa: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se todas as músicas da rodada atual foram marcadas como 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return bool True se a rodada atual estiver finalizada, false caso contrário.
 */
function isRodadaAtualFinalizada(PDO $pdo) {
    $rodadaAtual = getRodadaAtual($pdo);
    try {
        // Verifica se existe alguma música com status 'aguardando' ou 'em_execucao'
        $sql = "SELECT COUNT(*) FROM fila_rodadas WHERE rodada = ? AND (status = 'aguardando' OR status = 'em_execucao')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
        $musicasPendentes = $stmt->fetchColumn();

        return $musicasPendentes === 0; // Se não houver músicas 'aguardando' ou 'em_execucao', a rodada está finalizada
    } catch (\PDOException $e) {
        error_log("Erro ao verificar status da rodada atual: " . $e->getMessage());
        return false; // Em caso de erro, consideramos que não está finalizada para evitar problemas
    }
}


/**
 * Função auxiliar para obter um ID de música aleatória.
 * Em um sistema real, o MC escolheria a música.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return int O ID de uma música aleatória, ou 0 se não houver músicas.
 */
function getRandomMusicaId(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id FROM musicas ORDER BY RAND() LIMIT 1");
        $row = $stmt->fetch();
        return $row ? $row['id'] : 0;
    } catch (\PDOException $e) {
        error_log("Erro ao obter música aleatória: " . $e->getMessage());
        return 0;
    }
}

// Inicializa a tabela controle_rodada com ID 1 e rodada 1, se não existir
try {
    $pdo->exec("INSERT IGNORE INTO controle_rodada (id, rodada_atual) VALUES (1, 1)");
} catch (\PDOException $e) {
    error_log("Erro ao inicializar controle_rodada na inicialização do script: " . $e->getMessage());
}

// Popula algumas músicas de exemplo se o banco estiver vazio
try {
    $stmtMusicas = $pdo->query("SELECT COUNT(*) FROM musicas");
    $countMusicas = $stmtMusicas->fetchColumn();
    if ($countMusicas == 0) {
        adicionarMusica($pdo, "Bohemian Rhapsody", "Queen", 354);
        adicionarMusica($pdo, "Evidências", "Chitãozinho & Xororó", 270);
        adicionarMusica($pdo, "Billie Jean", "Michael Jackson", 294);
        adicionarMusica($pdo, "Garota de Ipanema", "Tom Jobim & Vinicius de Moraes", 180);
        adicionarMusica($pdo, "Anunciação", "Alceu Valença", 190);
        adicionarMusica($pdo, "Música teste 1", "Cantor Teste 1", 100);
        adicionarMusica($pdo, "Música teste 2", "Cantor Teste 2", 100);
        adicionarMusica($pdo, "Música teste 3", "Cantor Teste 3", 100);
        adicionarMusica($pdo, "Música teste 4", "Cantor Teste 4", 100);
        adicionarMusica($pdo, "Música teste 5", "Cantor Teste 5", 100);
    }
} catch (\PDOException $e) {
    error_log("Erro ao popular músicas de exemplo na inicialização do script: " . $e->getMessage());
}

/**
 * Adiciona ou atualiza uma regra de configuração de mesa.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @param int $minPessoas Número mínimo de pessoas para esta regra.
 * @param int|null $maxPessoas Número máximo de pessoas para esta regra (null para "ou mais").
 * @param int $maxMusicasPorRodada Número máximo de músicas permitida por rodada para esta regra.
 * @return bool|string True em caso de sucesso, ou uma string com a mensagem de erro.
 */
function adicionarOuAtualizarRegraMesa(PDO $pdo, int $minPessoas, ?int $maxPessoas, int $maxMusicasPorRodada) // Retorna bool|string para erros de validação
{
    error_log("DEBUG (Regra Mesa): INÍCIO da função adicionarOuAtualizarRegraMesa.");
    error_log("DEBUG (Regra Mesa): minPessoas (nova): " . $minPessoas . ", maxPessoas (nova): " . ($maxPessoas !== null ? $maxPessoas : 'NULL') . ", maxMusicasPorRodada (nova): " . $maxMusicasPorRodada);

    try {
        // Validação 1: max_pessoas não pode ser menor que min_pessoas na mesma regra
        if ($maxPessoas !== null && $maxPessoas < $minPessoas) {
            error_log("DEBUG (Regra Mesa): Validação 1 falhou: maxPessoas (" . $maxPessoas . ") < minPessoas (" . $minPessoas . ").");
            return "O valor de 'Máximo de Pessoas' não pode ser menor que o 'Mínimo de Pessoas' para esta regra.";
        }

        // Verifica se a regra já existe para decidir entre INSERT ou UPDATE
        $stmtCheckExists = $pdo->prepare("SELECT id FROM configuracao_regras_mesa WHERE min_pessoas = :min_pessoas");
        $stmtCheckExists->execute([':min_pessoas' => $minPessoas]);
        $existingRule = $stmtCheckExists->fetch(PDO::FETCH_ASSOC);
        $isUpdate = (bool)$existingRule;
        $currentId = $isUpdate ? $existingRule['id'] : null;

        if ($isUpdate) {
            error_log("DEBUG (Regra Mesa): Regra existente encontrada para min_pessoas=" . $minPessoas . ". ID: " . $currentId . ". Será um UPDATE.");
        } else {
            error_log("DEBUG (Regra Mesa): Nenhuma regra existente encontrada para min_pessoas=" . $minPessoas . ". Será um INSERT.");
        }

        // Validação 2: Verificar sobreposição com OUTRAS regras existentes
        $sqlFetchExisting = "SELECT id, min_pessoas, max_pessoas FROM configuracao_regras_mesa";
        $paramsFetchExisting = [];
        if ($isUpdate) {
            $sqlFetchExisting .= " WHERE id != :current_id_exclude";
            $paramsFetchExisting[':current_id_exclude'] = $currentId;
        }

        $stmtFetchExisting = $pdo->prepare($sqlFetchExisting);
        $stmtFetchExisting->execute($paramsFetchExisting);
        $regrasExistentes = $stmtFetchExisting->fetchAll(PDO::FETCH_ASSOC);

        error_log("DEBUG (Regra Mesa): Verificando sobreposição com " . count($regrasExistentes) . " regras existentes (excluindo a regra sendo atualizada, se houver).");

        $newMaxAdjusted = $maxPessoas !== null ? $maxPessoas : PHP_INT_MAX; // Tratar NULL da nova regra como "infinito"

        foreach ($regrasExistentes as $regraExistente) {
            $existingMin = (int)$regraExistente['min_pessoas'];
            $existingMax = $regraExistente['max_pessoas'] !== null ? (int)$regraExistente['max_pessoas'] : PHP_INT_MAX; // Tratar NULL da regra existente como "infinito"

            error_log("DEBUG (Regra Mesa): Comparando com regra existente ID " . $regraExistente['id'] . ": Min: " . $existingMin . ", Max: " . ($regraExistente['max_pessoas'] !== null ? $regraExistente['max_pessoas'] : 'NULL/Infinity') . ".");

            // Condição de sobreposição: os intervalos [minPessoas, newMaxAdjusted] e [existingMin, existingMax] se cruzam.
            // Isso acontece se (novoMin <= existenteMax E novoMax >= existenteMin)
            if ($minPessoas <= $existingMax && $newMaxAdjusted >= $existingMin) {
                // Formata a descrição da regra existente para a mensagem de erro
                $descricaoRegraExistente = "";
                if ($regraExistente['max_pessoas'] === null) {
                    $descricaoRegraExistente = "com " . $existingMin . " ou mais pessoas";
                } elseif ($existingMin === (int)$regraExistente['max_pessoas']) {
                    $descricaoRegraExistente = "com " . $existingMin . " " . ($existingMin === 1 ? "pessoa" : "pessoas");
                } else {
                    $descricaoRegraExistente = "com " . $existingMin . " a " . $regraExistente['max_pessoas'] . " pessoas";
                }

                // Formata a descrição da nova regra para a mensagem de erro
                $descricaoNovaRegra = "";
                if ($maxPessoas === null) {
                    $descricaoNovaRegra = "com " . $minPessoas . " ou mais pessoas";
                } elseif ($minPessoas === $maxPessoas) {
                    $descricaoNovaRegra = "com " . $minPessoas . " " . ($minPessoas === 1 ? "pessoa" : "pessoas");
                } else {
                    $descricaoNovaRegra = "com " . $minPessoas . " a " . $maxPessoas . " pessoas";
                }

                $msg = "Não foi possível salvar a regra. O intervalo {$descricaoNovaRegra} já está coberto por uma regra existente {$descricaoRegraExistente}. Por favor, ajuste os valores para que não haja sobreposição.";
                error_log("DEBUG (Regra Mesa): Validação 2 falhou: Sobreposição detectada. Mensagem amigável: " . $msg);
                return $msg;
            }
        }
        
        // --- Lógica de INSERT/UPDATE ---
        $stmt = null;
        $params = [
            ':min_pessoas' => $minPessoas,
            ':max_pessoas' => $maxPessoas,
            ':max_musicas_por_rodada' => $maxMusicasPorRodada
        ];

        if ($isUpdate) {
            $sql = "UPDATE configuracao_regras_mesa SET max_pessoas = :max_pessoas, max_musicas_por_rodada = :max_musicas_por_rodada WHERE min_pessoas = :min_pessoas";
            error_log("DEBUG (Regra Mesa): Preparando UPDATE SQL.");
        } else {
            $sql = "INSERT INTO configuracao_regras_mesa (min_pessoas, max_pessoas, max_musicas_por_rodada) VALUES (:min_pessoas, :max_pessoas, :max_musicas_por_rodada)";
            error_log("DEBUG (Regra Mesa): Preparando INSERT SQL.");
        }
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            error_log("DEBUG (Regra Mesa): Operação de banco de dados (INSERT/UPDATE) bem-sucedida.");
            return true;
        } else {
            $errorInfo = $stmt->errorInfo();
            $msg = "Erro desconhecido ao salvar a regra de mesa. Código SQLSTATE: " . $errorInfo[0] . ", Código Erro: " . $errorInfo[1] . ", Mensagem Erro: " . $errorInfo[2];
            error_log("ERRO (Regra Mesa): Falha no execute. Detalhes: " . $msg);
            return $msg;
        }

    } catch (PDOException $e) {
        error_log("ERRO (Regra Mesa): Exceção PDO ao adicionar/atualizar regra de mesa: " . $e->getMessage());
        return "Erro interno do servidor ao processar a regra: " . $e->getMessage();
    } finally {
        error_log("DEBUG (Regra Mesa): FIM da função adicionarOuAtualizarRegraMesa.");
    }
}

/**
 * Reseta a tabela de configuração de regras de mesa e insere regras padrão.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True em caso de sucesso, false em caso de erro.
 */
function setRegrasPadrao(PDO $pdo): bool
{
    try {
        // 1. Truncate na tabela para remover todas as regras existentes
        // MOVIDO PARA FORA DA TRANSAÇÃO, POIS TRUNCATE FAZ COMMIT IMPLICITAMENTE
        $pdo->exec("TRUNCATE TABLE configuracao_regras_mesa");

        // Agora sim, inicia a transação para proteger os INSERTs
        $pdo->beginTransaction();

        // 2. Inserir as regras padrão
        $sql = "INSERT INTO `configuracao_regras_mesa` (`min_pessoas`, `max_pessoas`, `max_musicas_por_rodada`) VALUES 
                (1, 2, 1),
                (3, 4, 2),
                (5, NULL, 3)"; // NULL para o campo max_pessoas quando é 'ou mais'

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute();

        if ($result) {
            $pdo->commit();
            return true;
        } else {
            // Se o INSERT falhar, faz rollback da transação (que está ativa)
            $pdo->rollBack();
            return false;
        }

    } catch (PDOException $e) {
        // Verifica se há uma transação ativa antes de tentar um rollback
        // Isso é uma medida de segurança, pois se o erro ocorrer antes do beginTransaction, não haverá transação.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao definir regras padrão: " . $e->getMessage());
        return false;
    }
}




/**
 * Busca e formata as regras de configuração de mesa do banco de dados.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return array Um array de strings com as regras formatadas, ou um array vazio em caso de erro.
 */
function getRegrasMesaFormatadas(PDO $pdo): array
{
    $regrasFormatadas = [];
    try {
        $stmt = $pdo->query("SELECT min_pessoas, max_pessoas, max_musicas_por_rodada FROM configuracao_regras_mesa ORDER BY min_pessoas ASC");
        $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($regras)) {
            return ["Nenhuma regra de mesa configurada."];
        }

        foreach ($regras as $regra) {
            $min = (int)$regra['min_pessoas'];
            $max = $regra['max_pessoas']; // Pode ser NULL
            $musicas = (int)$regra['max_musicas_por_rodada'];

            $descricaoPessoas = "";
            if ($max === null) {
                $descricaoPessoas = "com {$min} ou mais cantores";
            } elseif ($min === $max) {
                // Se min e max são iguais, trata como um número específico (singular ou plural)
                $descricaoPessoas = "com {$min} " . ($min === 1 ? "cantor" : "cantores");
            } else {
                // Caso geral: min e max são diferentes e não nulos. Use "a"
                $descricaoPessoas = "com {$min} a {$max} cantores";
            }
            
            $descricaoMusicas = "música";
            if ($musicas > 1) {
                $descricaoMusicas = "músicas";
            }

            $regrasFormatadas[] = "Mesas {$descricaoPessoas}, têm direito a {$musicas} {$descricaoMusicas} por rodada.";
        }

    } catch (PDOException $e) {
        error_log("Erro ao buscar regras de mesa: " . $e->getMessage());
        return ["Erro ao carregar as regras de mesa."];
    }
    return $regrasFormatadas;
}



/**
 * Reseta o 'proximo_ordem_musica' de todos os cantores para 1,
 * e trunca as tabelas 'controle_rodada' e 'fila_rodadas'.
 * Isso efetivamente reinicia todo o estado da fila do karaokê.
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True se o reset completo foi bem-sucedido, false caso contrário.
 */
function resetarTudoFila(PDO $pdo): bool {
    try {
        // Não usamos transação aqui porque TRUNCATE TABLE faz um COMMIT implícito.
        // Se uma falhar, as anteriores já foram commitadas.
        // Se precisasse ser tudo ou nada, teríamos que usar DELETE FROM e transação.
        // Para um reset, TRUNCATE é mais eficiente.

        // 1. Resetar 'proximo_ordem_musica' dos cantores
        $stmtCantores = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = 1");
        $stmtCantores->execute();
        error_log("DEBUG: Todos os 'proximo_ordem_musica' dos cantores foram resetados para 1.");

        // 2. Resetar 'status' de todas as músicas para 'aguardando' na tabela musicas_cantor
        $stmtMusicasCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando'");
        $stmtMusicasCantorStatus->execute();
        error_log("DEBUG: Todos os 'status' na tabela musicas_cantor foram resetados para 'aguardando'.");

        // 3. Resetar 'timestamp_ultima_execucao' para NULL na tabela musicas_cantor
        $stmtMusicasCantorTimestamp = $pdo->prepare("UPDATE musicas_cantor SET timestamp_ultima_execucao = NULL");
        $stmtMusicasCantorTimestamp->execute();
        error_log("DEBUG: Todos os 'timestamp_ultima_execucao' na tabela musicas_cantor foram resetados para NULL.");

        // 4. Truncar tabela 'fila_rodadas'
        $stmtFila = $pdo->prepare("TRUNCATE TABLE fila_rodadas");
        $stmtFila->execute();
        error_log("DEBUG: Tabela 'fila_rodadas' truncada.");

        // 5. Truncar tabela 'controle_rodada'
        $stmtControle = $pdo->prepare("TRUNCATE TABLE controle_rodada");
        $stmtControle->execute();
        error_log("DEBUG: Tabela 'controle_rodada' truncada.");
        
        // Reinicializa controle_rodada, pois TRUNCATE a esvazia.
        $stmtControleInsert = $pdo->prepare("INSERT IGNORE INTO controle_rodada (id, rodada_atual) VALUES (1, 1)");
        $stmtControleInsert->execute();
        error_log("DEBUG: Tabela 'controle_rodada' reinicializada com rodada 1.");

        error_log("DEBUG: Reset completo da fila (cantores, musicas_cantor, fila_rodadas, controle_rodada) realizado com sucesso.");
        return true;

    } catch (PDOException $e) {
        error_log("Erro ao realizar o reset completo da fila: " . $e->getMessage());
        return false;
    }
}