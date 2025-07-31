<?php
require_once 'conn.php'; // A variável $pdo estará disponível aqui
require_once 'montar_rodadas.php'; // Função que cria as rodadas
require_once 'reordenar_fila_rodadas.php'; // Função que reordena a fila, impede musicas da mesma mesa em sequência
require_once 'atualizar_status_musicas.php'; // Função que atualiza o status das músicas
require_once 'config_regras_mesas.php'; // Funções para configurar número de musicas por mesa por rodadas

/**
 * Retorna todos os cantores cadastrados, incluindo o nome da mesa associada.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return array Um array de arrays associativos contendo os dados dos cantores,
 * ou um array vazio em caso de nenhum cantor ou erro.
 */
function getAllCantores(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.nome_cantor,
                c.id_mesa,
                m.nome_mesa AS nome_da_mesa_associada, -- Alias para evitar conflito de nome e clareza
                c.proximo_ordem_musica
            FROM
                cantores c
            LEFT JOIN
                mesas m ON c.id_mesa = m.id
            ORDER BY
                c.nome_cantor ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao buscar todos os cantores com nome da mesa: " . $e->getMessage());
        return []; // Retorna um array vazio em caso de erro
    }
}

function getTodasMesas(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id, nome_mesa, tamanho_mesa FROM mesas ORDER BY nome_mesa");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao buscar mesas: " . $e->getMessage());
        return [];
    }
}

/**
 * Exclui uma mesa do banco de dados, impedindo a exclusão se a mesa tiver
 * alguma música associada em status 'em_execucao' na fila_rodadas.
 *
 * @param PDO $pdo Objeto PDO da conexão com o banco de dados.
 * @param int $mesaId O ID da mesa a ser excluída.
 * @return array Um array associativo com 'success' (bool) e 'message' (string).
 */
function excluirMesa(PDO $pdo, int $mesaId): array {
    try {
        $pdo->beginTransaction(); // Inicia uma transação para garantir atomicidade

        // 1. Verificar se a mesa possui alguma música em status 'em_execucao' na fila_rodadas
        $stmtCheckFila = $pdo->prepare("
            SELECT COUNT(fr.id)
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            WHERE c.id_mesa = :mesaId
            AND fr.status = 'em_execucao'
        ");
        $stmtCheckFila->execute([':mesaId' => $mesaId]);
        $isMesaInExecution = $stmtCheckFila->fetchColumn();

        if ($isMesaInExecution > 0) {
            $pdo->rollBack(); // Reverte a transação se a condição for verdadeira
            error_log("Alerta: Tentativa de excluir mesa (ID: " . $mesaId . ") que tem música(s) em 'em_execucao' na fila. Exclusão não permitida.");
            return ['success' => false, 'message' => "Não é possível remover a mesa. Há uma música desta mesa atualmente em execução."];
        }

        // 2. Se a verificação passou, obtenha o nome da mesa para a mensagem de sucesso/erro
        $stmtGetMesaNome = $pdo->prepare("SELECT nome_mesa FROM mesas WHERE id = :mesaId");
        $stmtGetMesaNome->execute([':mesaId' => $mesaId]);
        $mesaInfo = $stmtGetMesaNome->fetch(PDO::FETCH_ASSOC);
        $nomeMesa = $mesaInfo['nome_mesa'] ?? 'Mesa Desconhecida';


        // 3. Exclua a mesa
        // Lembre-se: se você configurou ON DELETE CASCADE nas chaves estrangeiras de 'cantores' e 'musicas_cantor'
        // para 'mesas', os cantores e suas músicas associadas serão excluídos automaticamente.
        // Caso contrário, você precisará excluir cantores e músicas manualmente aqui ANTES de excluir a mesa.
        $stmtDeleteMesa = $pdo->prepare("DELETE FROM mesas WHERE id = :id");
        $stmtDeleteMesa->execute([':id' => $mesaId]);

        if ($stmtDeleteMesa->rowCount() > 0) {
            $pdo->commit(); // Confirma a transação
            return ['success' => true, 'message' => "Mesa <strong>{$nomeMesa}</strong> excluída!"];
        } else {
            $pdo->rollBack(); // Reverte a transação se a mesa não foi encontrada
            return ['success' => false, 'message' => 'Mesa não encontrada ou já excluída.'];
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack(); // Garante que a transação seja revertida em caso de exceção
        }
        error_log("Erro ao excluir mesa: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno do servidor ao excluir mesa: ' . $e->getMessage()];
    }
}

/**
 * Adiciona uma nova mesa ao sistema.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $nomeMesa Nome/identificador da mesa.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarMesa(PDO $pdo, $nomeMesa) {
    // 1. Verificar se a mesa já existe
    try {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM mesas WHERE nome_mesa = ?");
        $stmtCheck->execute([$nomeMesa]);
        $count = $stmtCheck->fetchColumn();

        if ($count > 0) {
            // A mesa já existe
            // Retorna a mensagem específica solicitada
            return ['success' => false, 'message' => "Já existe uma mesa com esse nome!"];
        }
    } catch (\PDOException $e) {
        error_log("Erro ao verificar existência da mesa: " . $e->getMessage());
        return ['success' => false, 'message' => "Erro ao verificar existência da mesa."];
    }

    // 2. Se não existe, inserir a nova mesa
    try {
        $stmtInsert = $pdo->prepare("INSERT INTO mesas (nome_mesa) VALUES (?)");
        if ($stmtInsert->execute([$nomeMesa])) {
            return ['success' => true, 'message' => "Mesa <strong>{$nomeMesa}</strong> adicionada!"];
        } else {
            // Isso pode acontecer se houver alguma outra restrição no banco de dados,
            // embora com a verificação de COUNT(*) seja menos provável para nome_mesa.
            return ['success' => false, 'message' => "Não foi possível adicionar a mesa <strong>{$nomeMesa}</strong> por um motivo desconhecido."];
        }
    } catch (\PDOException $e) {
        // Este catch é para erros durante a INSERÇÃO (ex: falha de conexão, restrição de DB inesperada)
        error_log("Erro ao adicionar mesa: " . $e->getMessage());
        return ['success' => false, 'message' => "Erro no banco de dados ao adicionar mesa."];
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

        $stmtGetMesa = $pdo->prepare("SELECT nome_mesa FROM mesas WHERE id = ?");
        $stmtGetMesa->execute([$idMesa]);
        $mesaInfo = $stmtGetMesa->fetch(PDO::FETCH_ASSOC);

        if (!$mesaInfo) {
            $pdo->rollBack(); // Reverte se a mesa não for encontrada
            error_log("Erro: Mesa com ID {$idMesa} não encontrada.");
            return ['success' => false, 'message' => "Erro: Mesa não encontrada para adicionar cantor."];
        }
        $nomeMesa = $mesaInfo['nome_mesa']; // Pega o nome da mesa

        // Insere o novo cantor
        $stmt = $pdo->prepare("INSERT INTO cantores (nome_cantor, id_mesa) VALUES (?, ?)");
        $success = $stmt->execute([$nomeCantor, $idMesa]);

        if ($success) {
            // 2. Incrementa o 'tamanho_mesa' da mesa associada
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa + 1 WHERE id = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa]);

            if ($updateSuccess) {
                $pdo->commit(); // Confirma ambas as operações se tudo deu certo
                return ['success' => true, 'message' => "<strong>{$nomeCantor}(a)</strong> adicionado(a) à mesa <strong>{$nomeMesa}</strong> com sucesso!"];
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
 * Remove um cantor e decrementa o tamanho_mesa da mesa associada,
 * impedindo a remoção se o cantor tiver uma música em execução ou selecionada na fila.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $idCantor ID do cantor a ser removido.
 * @return array Um array associativo com 'success' (bool) e 'message' (string).
 */
function removerCantor(PDO $pdo, $idCantor): array
{
    try {
        $pdo->beginTransaction();

        // 1. Obter o id_mesa e o nome do cantor antes de excluí-lo
        $stmtGetCantorInfo = $pdo->prepare("SELECT id_mesa, nome_cantor FROM cantores WHERE id = ?");
        $stmtGetCantorInfo->execute([$idCantor]);
        $cantorInfo = $stmtGetCantorInfo->fetch(PDO::FETCH_ASSOC);

        if (!$cantorInfo) { // Cantor não encontrado
            $pdo->rollBack();
            error_log("Erro: Cantor ID " . $idCantor . " não encontrado para remoção.");
            return ['success' => false, 'message' => 'Cantor não encontrado.'];
        }

        $idMesa = $cantorInfo['id_mesa'];
        $nomeCantor = $cantorInfo['nome_cantor'];

        // NOVO PASSO: 2. Verificar se o cantor tem alguma música em 'em_execucao' ou 'selecionada_para_rodada' na fila_rodadas
        $stmtCheckFila = $pdo->prepare(
            "SELECT COUNT(*) FROM fila_rodadas
             WHERE id_cantor = ?
               AND (status = 'em_execucao')"
        );
        $stmtCheckFila->execute([$idCantor]);
        $isInFilaAtiva = $stmtCheckFila->fetchColumn();

        if ($isInFilaAtiva > 0) {
            $pdo->rollBack();
            error_log("Alerta: Tentativa de excluir cantor (ID: " . $idCantor . ", Nome: " . $nomeCantor . ") que possui música(s) em execução ou selecionada(s) na fila. Exclusão não permitida.");
            return ['success' => false, 'message' => "Não é possível remover o cantor '{$nomeCantor}'. Ele(a) tem música(s) atualmente em execução ou selecionada(s) para a rodada."];
        }

        // 3. Remover o cantor (apenas se não estiver na fila ativa)
        $stmtDeleteCantor = $pdo->prepare("DELETE FROM cantores WHERE id = ?");
        $successDelete = $stmtDeleteCantor->execute([$idCantor]);

        if ($successDelete) {
            // 4. Decrementar o 'tamanho_mesa' da mesa associada (se for maior que zero)
            // Lembre-se que se você configurou ON DELETE CASCADE na FK de musicas_cantor para cantores,
            // as músicas do cantor serão excluídas automaticamente, então não precisa se preocupar aqui.
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = GREATEST(0, tamanho_mesa - 1) WHERE id = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa]);

            if ($updateSuccess) {
                $pdo->commit();
                return ['success' => true, 'message' => "Cantor(a) '{$nomeCantor}' removido(a) com sucesso."];
            } else {
                $pdo->rollBack();
                error_log("Erro ao decrementar tamanho_mesa para a mesa ID: " . $idMesa . " após remover cantor ID: " . $idCantor);
                return ['success' => false, 'message' => "Erro ao atualizar o tamanho da mesa após remover cantor."];
            }
        } else {
            $pdo->rollBack();
            error_log("Erro ao remover o cantor ID: " . $idCantor . ". PDO Error: " . implode(" ", $stmtDeleteCantor->errorInfo()));
            return ['success' => false, 'message' => "Erro ao remover o cantor."];
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao remover cantor (PDOException): " . $e->getMessage());
        return ['success' => false, 'message' => "Erro interno do servidor ao remover cantor."];
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
        $sql = "SELECT
                    fr.id AS fila_id,
                    c.nome_cantor,
                    m.titulo AS titulo_musica,
                    m.artista AS artista_musica,
                    m.codigo as codigo_musica,
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
 * Reseta o 'proximo_ordem_musica' de todos os cantores para 1,
 * e trunca as tabelas 'controle_rodada' e 'fila_rodadas'.
 * Isso efetivamente reinicia todo o estado da fila do karaokê.
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True se o reset completo foi bem-sucedido, false caso contrário.
 */
function resetarSistema(PDO $pdo): bool {
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