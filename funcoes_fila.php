<?php
require_once 'conn.php'; // A variável $pdo estará disponível aqui
require_once 'montar_rodadas.php'; // Função que cria as rodadas
require_once 'reordenar_fila_rodadas.php'; // Função que reordena a fila, impede musicas da mesma mesa em sequência
require_once 'atualizar_status_musicas.php'; // Função que atualiza o status das músicas
require_once 'config_regras_mesas.php'; // Funções para configurar número de musicas por mesa por rodadas

// --- Variáveis estáticas para simular o tenant e o evento logados para fins de teste ---
$id_tenants_logado = 1;
$id_evento_ativo = 1;
// --- FIM das variáveis estáticas ---

/**
 * Retorna todos os cantores cadastrados, incluindo o nome da mesa associada.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return array Um array de arrays associativos contendo os dados dos cantores,
 * ou um array vazio em caso de nenhum cantor ou erro.
 */
function getAllCantores(PDO $pdo): array
{
    global $id_tenants_logado; // Usa a variável global para o tenant
    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.nome_cantor,
                c.id_mesa,
                m.nome_mesa AS nome_da_mesa_associada,
                c.proximo_ordem_musica
            FROM
                cantores c
            LEFT JOIN
                mesas m ON c.id_mesa = m.id
            WHERE
                c.id_tenants = :id_tenants -- AQUI: Filtra por tenant
            ORDER BY
                c.nome_cantor ASC
        ");
        $stmt->execute([':id_tenants' => $id_tenants_logado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao buscar todos os cantores com nome da mesa: " . $e->getMessage());
        return [];
    }
}

function getTodasMesas(PDO $pdo) {
    global $id_tenants_logado; // Usa a variável global para o tenant
    try {
        $stmt = $pdo->prepare("SELECT id, nome_mesa, tamanho_mesa FROM mesas WHERE id_tenants = ? ORDER BY nome_mesa"); // AQUI: Filtra por tenant
        $stmt->execute([$id_tenants_logado]);
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
    global $id_tenants_logado; // Usa a variável global para o tenant
    try {
        $pdo->beginTransaction();

        // 1. Verificar se a mesa possui alguma música em status 'em_execucao' na fila_rodadas
        $stmtCheckFila = $pdo->prepare("
            SELECT COUNT(fr.id)
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            WHERE c.id_mesa = :mesaId
            AND fr.status = 'em_execucao'
            AND fr.id_tenants = :id_tenants -- AQUI: Filtra por tenant
        ");
        $stmtCheckFila->execute([':mesaId' => $mesaId, ':id_tenants' => $id_tenants_logado]);
        $isMesaInExecution = $stmtCheckFila->fetchColumn();

        if ($isMesaInExecution > 0) {
            $pdo->rollBack();
            error_log("Alerta: Tentativa de excluir mesa (ID: " . $mesaId . ") do tenant " . $id_tenants_logado . " que tem música(s) em 'em_execucao' na fila. Exclusão não permitida.");
            return ['success' => false, 'message' => "Não é possível remover a mesa. Há uma música desta mesa atualmente em execução."];
        }

        // 2. Se a verificação passou, obtenha o nome da mesa para a mensagem de sucesso/erro
        $stmtGetMesaNome = $pdo->prepare("SELECT nome_mesa FROM mesas WHERE id = :mesaId AND id_tenants = :id_tenants"); // AQUI: Filtra por tenant
        $stmtGetMesaNome->execute([':mesaId' => $mesaId, ':id_tenants' => $id_tenants_logado]);
        $mesaInfo = $stmtGetMesaNome->fetch(PDO::FETCH_ASSOC);
        $nomeMesa = $mesaInfo['nome_mesa'] ?? 'Mesa Desconhecida';

        // 3. Exclua a mesa
        $stmtDeleteMesa = $pdo->prepare("DELETE FROM mesas WHERE id = :id AND id_tenants = :id_tenants"); // AQUI: Filtra por tenant
        $stmtDeleteMesa->execute([':id' => $mesaId, ':id_tenants' => $id_tenants_logado]);

        if ($stmtDeleteMesa->rowCount() > 0) {
            $pdo->commit();
            return ['success' => true, 'message' => "Mesa <strong>{$nomeMesa}</strong> excluída!"];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Mesa não encontrada, não pertence ao seu tenant ou já excluída.'];
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
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
    global $id_tenants_logado; // Usa a variável global para o tenant
    try {
        // 1. Verificar se a mesa já existe para ESTE tenant
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM mesas WHERE nome_mesa = ? AND id_tenants = ?"); // AQUI: Filtra por tenant
        $stmtCheck->execute([$nomeMesa, $id_tenants_logado]);
        $count = $stmtCheck->fetchColumn();

        if ($count > 0) {
            return ['success' => false, 'message' => "Já existe uma mesa com esse nome para este tenant!"];
        }
    } catch (\PDOException $e) {
        error_log("Erro ao verificar existência da mesa: " . $e->getMessage());
        return ['success' => false, 'message' => "Erro ao verificar existência da mesa."];
    }

    // 2. Se não existe, inserir a nova mesa com o id_tenants
    try {
        $stmtInsert = $pdo->prepare("INSERT INTO mesas (id_tenants, nome_mesa) VALUES (?, ?)"); // AQUI: Insere o id_tenants
        if ($stmtInsert->execute([$id_tenants_logado, $nomeMesa])) {
            return ['success' => true, 'message' => "Mesa <strong>{$nomeMesa}</strong> adicionada!"];
        } else {
            return ['success' => false, 'message' => "Não foi possível adicionar a mesa <strong>{$nomeMesa}</strong> por um motivo desconhecido."];
        }
    } catch (\PDOException $e) {
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
    global $id_tenants_logado; // Usa a variável global para o tenant
    try {
        $pdo->beginTransaction();

        $stmtGetMesa = $pdo->prepare("SELECT nome_mesa FROM mesas WHERE id = ? AND id_tenants = ?"); // AQUI: Filtra por tenant
        $stmtGetMesa->execute([$idMesa, $id_tenants_logado]);
        $mesaInfo = $stmtGetMesa->fetch(PDO::FETCH_ASSOC);

        if (!$mesaInfo) {
            $pdo->rollBack();
            error_log("Erro: Mesa com ID {$idMesa} não encontrada para o tenant " . $id_tenants_logado . ".");
            return ['success' => false, 'message' => "Erro: Mesa não encontrada ou não pertence ao seu tenant."];
        }
        $nomeMesa = $mesaInfo['nome_mesa'];

        // Insere o novo cantor
        $stmt = $pdo->prepare("INSERT INTO cantores (id_tenants, nome_cantor, id_mesa) VALUES (?, ?, ?)"); // AQUI: Insere o id_tenants
        $success = $stmt->execute([$id_tenants_logado, $nomeCantor, $idMesa]);

        if ($success) {
            // 2. Incrementa o 'tamanho_mesa' da mesa associada
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa + 1 WHERE id = ? AND id_tenants = ?"); // AQUI: Filtra por tenant
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa, $id_tenants_logado]);

            if ($updateSuccess) {
                $pdo->commit();
                return ['success' => true, 'message' => "<strong>{$nomeCantor}(a)</strong> adicionado(a) à mesa <strong>{$nomeMesa}</strong> com sucesso!"];
            } else {
                $pdo->rollBack();
                error_log("Erro ao incrementar tamanho_mesa para a mesa ID: " . $idMesa . " do tenant " . $id_tenants_logado);
                return ['success' => false, 'message' => "Erro ao atualizar o tamanho da mesa."];
            }
        } else {
            $pdo->rollBack();
            error_log("Erro ao inserir o cantor: " . $nomeCantor);
            return ['success' => false, 'message' => "Erro ao adicionar o cantor."];
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao adicionar cantor (PDOException): " . $e->getMessage());
        return ['success' => false, 'message' => "Erro interno do servidor ao adicionar cantor."];
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
    global $id_tenants_logado; // Usa a variável global para o tenant
    try {
        $pdo->beginTransaction();

        // 1. Obter o id_mesa e o nome do cantor antes de excluí-lo
        $stmtGetCantorInfo = $pdo->prepare("SELECT id_mesa, nome_cantor FROM cantores WHERE id = ? AND id_tenants = ?"); // AQUI: Filtra por tenant
        $stmtGetCantorInfo->execute([$idCantor, $id_tenants_logado]);
        $cantorInfo = $stmtGetCantorInfo->fetch(PDO::FETCH_ASSOC);

        if (!$cantorInfo) {
            $pdo->rollBack();
            error_log("Erro: Cantor ID " . $idCantor . " não encontrado para remoção no tenant " . $id_tenants_logado . ".");
            return ['success' => false, 'message' => 'Cantor não encontrado ou não pertence ao seu tenant.'];
        }

        $idMesa = $cantorInfo['id_mesa'];
        $nomeCantor = $cantorInfo['nome_cantor'];

        // NOVO PASSO: 2. Verificar se o cantor tem alguma música em 'em_execucao' na fila_rodadas
        $stmtCheckFila = $pdo->prepare(
            "SELECT COUNT(*) FROM fila_rodadas
             WHERE id_cantor = ?
               AND status = 'em_execucao'
               AND id_tenants = ?" // AQUI: Filtra por tenant
        );
        $stmtCheckFila->execute([$idCantor, $id_tenants_logado]);
        $isInFilaAtiva = $stmtCheckFila->fetchColumn();

        if ($isInFilaAtiva > 0) {
            $pdo->rollBack();
            error_log("Alerta: Tentativa de excluir cantor (ID: " . $idCantor . ", Nome: " . $nomeCantor . ") que possui música(s) em execução na fila. Exclusão não permitida.");
            return ['success' => false, 'message' => "Não é possível remover o cantor '{$nomeCantor}'. Ele(a) tem música(s) atualmente em execução."];
        }

        // 3. Remover o cantor (apenas se não estiver na fila ativa)
        $stmtDeleteCantor = $pdo->prepare("DELETE FROM cantores WHERE id = ? AND id_tenants = ?"); // AQUI: Filtra por tenant
        $successDelete = $stmtDeleteCantor->execute([$idCantor, $id_tenants_logado]);

        if ($successDelete) {
            // 4. Decrementar o 'tamanho_mesa' da mesa associada (se for maior que zero)
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = GREATEST(0, tamanho_mesa - 1) WHERE id = ? AND id_tenants = ?"); // AQUI: Filtra por tenant
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa, $id_tenants_logado]);

            if ($updateSuccess) {
                $pdo->commit();
                return ['success' => true, 'message' => "Cantor(a) '{$nomeCantor}' removido(a) com sucesso."];
            } else {
                $pdo->rollBack();
                error_log("Erro ao decrementar tamanho_mesa para a mesa ID: " . $idMesa . " do tenant " . $id_tenants_logado . " após remover cantor ID: " . $idCantor);
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
 * Obtém o número da rodada atual.
 * Retorna 0 se o sistema estiver em um estado "limpo" (sem rodadas ativas ou no histórico)
 * para que a próxima rodada a ser montada seja a 1.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $id_tenants_logado O ID do tenant logado.
 * @return int O número da rodada atual (ou 0 se for a primeira rodada a ser criada).
 */
function getRodadaAtual(PDO $pdo, int $id_tenants_logado) {
    try {
        // 1. Tenta obter a rodada_atual da tabela de controle.
        $stmt = $pdo->prepare("SELECT rodada_atual FROM controle_rodada WHERE id_tenants = ?"); // AQUI: Filtra por tenant
        $stmt->execute([$id_tenants_logado]);
        $rodadaAtualFromDB = $stmt->fetchColumn();

        $rodadaAtualFromDB = ($rodadaAtualFromDB === false || $rodadaAtualFromDB === null) ? 0 : (int)$rodadaAtualFromDB;

        // 2. Verifica se existe *alguma* música com status 'aguardando' em *qualquer* rodada.
        $stmtCheckAnyActiveFila = $pdo->prepare("SELECT rodada FROM fila_rodadas WHERE id_tenants = ? AND (status = 'aguardando' OR status = 'em_execucao') ORDER BY rodada DESC LIMIT 1"); // AQUI: Filtra por tenant
        $stmtCheckAnyActiveFila->execute([$id_tenants_logado]);
        $rodadaComMusicasAguardando = $stmtCheckAnyActiveFila->fetchColumn();

        if ($rodadaComMusicasAguardando !== false && $rodadaComMusicasAguardando !== null) {
            return (int)$rodadaComMusicasAguardando;
        }

        // 3. Se não há músicas 'aguardando', verifica se existe *alguma* rodada com 'cantou' ou 'pulou'.
        $stmtMaxRodadaFinalizada = $pdo->prepare("SELECT MAX(rodada) FROM fila_rodadas WHERE id_tenants = ? AND status IN ('cantou', 'pulou')"); // AQUI: Filtra por tenant
        $stmtMaxRodadaFinalizada->execute([$id_tenants_logado]);
        $maxRodadaFinalizada = $stmtMaxRodadaFinalizada->fetchColumn();

        if ($maxRodadaFinalizada !== false && $maxRodadaFinalizada !== null) {
            return (int)$maxRodadaFinalizada;
        }

        // 4. Se não há nada, retorna 0.
        return 0;

    } catch (\PDOException $e) {
        error_log("Erro ao obter rodada atual: " . $e->getMessage());
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
    global $id_tenants_logado; // Usa a variável global para o tenant
    $rodadaAtual = getRodadaAtual($pdo, $id_tenants_logado); // AQUI: Passa o id_tenants
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                fr.musica_cantor_id,
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
            WHERE fr.rodada = ? AND fr.status = 'aguardando' AND fr.id_tenants = ?
            ORDER BY fr.ordem_na_rodada ASC
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual, $id_tenants_logado]);
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
    global $id_tenants_logado; // Usa a variável global para o tenant
    $rodadaAtual = getRodadaAtual($pdo, $id_tenants_logado); // AQUI: Passa o id_tenants
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                fr.id_cantor,
                fr.id_musica,
                fr.musica_cantor_id,
                c.nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                m.codigo AS codigo_musica,
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ? AND fr.status = 'em_execucao' AND fr.id_tenants = ?
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual, $id_tenants_logado]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter música em execução: " . $e->getMessage());
        return null;
    }
}


/**
 * Troca a música de um item na fila de rodadas.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas a ser atualizado.
 * @param int $novaMusicaId ID da nova música a ser definida para o item da fila.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function trocarMusicaNaFilaAtual(PDO $pdo, $filaId, $novaMusicaId) {
    global $id_tenants_logado; // Usa a variável global para o tenant
    try {
        $pdo->beginTransaction();

        // 1. Obter informações do item da fila original, JOIN com cantores para filtrar por tenant
        // CORREÇÃO: Usamos um JOIN para acessar o id_tenants na tabela 'cantores'.
        $stmtGetOldMusicInfo = $pdo->prepare("
            SELECT fr.id_cantor, fr.id_musica, fr.musica_cantor_id, c.id_tenants
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            WHERE fr.id = ? AND c.id_tenants = ? AND (fr.status = 'aguardando' OR fr.status = 'em_execucao')
        ");
        $stmtGetOldMusicInfo->execute([$filaId, $id_tenants_logado]);
        $filaItem = $stmtGetOldMusicInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Tentativa de trocar música em item da fila inexistente ou já finalizado (ID: " . $filaId . ") para o tenant " . $id_tenants_logado . ".");
            $pdo->rollBack();
            return false;
        }

        $idCantor = $filaItem['id_cantor'];
        $musicaOriginalId = $filaItem['id_musica'];
        $musicaCantorOriginalId = $filaItem['musica_cantor_id'];

        // --- Lógica para a MÚSICA ORIGINAL (saindo da fila) ---
        if ($musicaCantorOriginalId !== null) {
            // CORREÇÃO: Usamos um JOIN para acessar o id_tenants na tabela 'cantores'.
            // A tabela musicas_cantor não tem id_tenants, então filtramos pelo cantor.
            $stmtGetOriginalOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_cantor = ?");
            $stmtGetOriginalOrder->execute([$musicaCantorOriginalId, $idCantor]);
            $ordemMusicaOriginal = $stmtGetOriginalOrder->fetchColumn();

            if ($ordemMusicaOriginal !== false) {
                // Esta query já estava correta, pois a tabela cantores tem id_tenants
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ? AND id_tenants = ?");
                $stmtUpdateCantorOrder->execute([$ordemMusicaOriginal, $idCantor, $id_tenants_logado]);
                error_log("DEBUG: Cantor " . $idCantor . " teve proximo_ordem_musica resetado para " . $ordemMusicaOriginal . " após troca de música (fila_id: " . $filaId . ").");

                // CORREÇÃO: A tabela musicas_cantor não tem id_tenants, então filtramos pelo cantor.
                $stmtUpdateOriginalMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ? AND id_cantor = ?");
                $stmtUpdateOriginalMusicaCantorStatus->execute([$musicaCantorOriginalId, $idCantor]);
                error_log("DEBUG: Status da música original (musicas_cantor_id: " . $musicaCantorOriginalId . ") do cantor " . $idCantor . " resetado para 'aguardando' na tabela musicas_cantor.");

            } else {
                error_log("Alerta: ID de musica_cantor_id (" . $musicaCantorOriginalId . ") para o item da fila (ID: " . $filaId . ") não encontrado na tabela musicas_cantor (tenant " . $id_tenants_logado . "). Não foi possível resetar o proximo_ordem_musica ou o status.");
            }
        } else {
            error_log("DEBUG: Música original (ID: " . $musicaOriginalId . ") do item da fila (ID: " . $filaId . ") não possui um musica_cantor_id associado, não há status para resetar em musicas_cantor.");
        }

        // --- Lógica para a NOVA MÚSICA (entrando na fila) ---
        $novaMusicaCantorId = null;
        $novaMusicaStatusExistente = null;

        // CORREÇÃO: A tabela musicas_cantor não tem id_tenants, então filtramos pelo cantor.
        $stmtCheckNewMusicInCantorList = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id_cantor = ? AND id_musica = ? LIMIT 1");
        $stmtCheckNewMusicInCantorList->execute([$idCantor, $novaMusicaId]);
        $newMusicInCantorList = $stmtCheckNewMusicInCantorList->fetch(PDO::FETCH_ASSOC);

        if ($newMusicInCantorList) {
            $novaMusicaCantorId = $newMusicInCantorList['id'];
            $novaMusicaStatusExistente = $newMusicInCantorList['status'];

            if ($novaMusicaStatusExistente == 'aguardando') {
                // CORREÇÃO: A tabela musicas_cantor não tem id_tenants, então filtramos pelo cantor.
                $stmtUpdateNewMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ? AND id_cantor = ?");
                $stmtUpdateNewMusicaCantorStatus->execute([$novaMusicaCantorId, $idCantor]);
                error_log("DEBUG: Status da nova música (musicas_cantor_id: " . $novaMusicaCantorId . ") do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");
            } else {
                error_log("DEBUG: Status da nova música (musicas_cantor_id: " . $novaMusicaCantorId . ", status: " . $novaMusicaStatusExistente . ") do cantor " . $idCantor . " NÃO foi alterado em musicas_cantor.");
            }
        } else {
            error_log("DEBUG: Nova música (ID: " . $novaMusicaId . ") não encontrada na lista musicas_cantor para o cantor " . $idCantor . " no tenant " . $id_tenants_logado . ".");
        }

        // 4. Atualiza o id_musica e musica_cantor_id na tabela fila_rodadas com a nova música
        // CORREÇÃO: A tabela fila_rodadas não tem id_tenants. Usamos um JOIN com a tabela cantores para filtrar.
        $stmtUpdateFila = $pdo->prepare("
            UPDATE fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            SET fr.id_musica = ?, fr.musica_cantor_id = ?
            WHERE fr.id = ? AND c.id_tenants = ?
        ");
        $result = $stmtUpdateFila->execute([$novaMusicaId, $novaMusicaCantorId, $filaId, $id_tenants_logado]);

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
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $rodada O número da rodada a ser atualizada.
 * @param array $novaOrdemFila Um array onde a chave é o ID do item da fila (fila_rodadas.id)
 * e o valor é a nova posição (ordem_na_rodada).
 * @return bool True se a atualização for bem-sucedida, false caso contrário.
 */
function atualizarOrdemFila(PDO $pdo, int $rodada, array $novaOrdemFila): bool {
    global $id_tenants_logado; // Usa a variável global para o tenant
    if (empty($novaOrdemFila)) {
        error_log("DEBUG: Array de nova ordem da fila vazio. Nenhuma atualização realizada.");
        return true;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE fila_rodadas SET ordem_na_rodada = ? WHERE id = ? AND rodada = ? AND id_tenants = ?"); // AQUI: Filtra por tenant

        foreach ($novaOrdemFila as $filaItemId => $novaPosicao) {
            $novaPosicaoInt = (int)$novaPosicao;
            if (!$stmt->execute([$novaPosicaoInt, $filaItemId, $rodada, $id_tenants_logado])) {
                error_log("ERRO: Falha ao atualizar ordem do item " . $filaItemId . " para posição " . $novaPosicaoInt);
                $pdo->rollBack();
                return false;
            }
        }

        $pdo->commit();
        error_log("DEBUG: Ordem da fila da rodada " . $rodada . " (usando ordem_na_rodada) para o tenant " . $id_tenants_logado . " atualizada com sucesso.");
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
    global $id_tenants_logado; // Adiciona a variável global
    if (empty($novaOrdemMusicas)) {
        return true;
    }

    try {
        $pdo->beginTransaction();

        // VALIDAÇÃO DE SEGURANÇA MULTI-TENANT:
        // Verifica se o ID do cantor realmente pertence ao tenant logado.
        $stmtCheckCantorTenant = $pdo->prepare("SELECT COUNT(*) FROM cantores WHERE id = ? AND id_tenants = ?");
        $stmtCheckCantorTenant->execute([$idCantor, $id_tenants_logado]);
        if ($stmtCheckCantorTenant->fetchColumn() == 0) {
            error_log("Alerta de Segurança: Tentativa de reordenar músicas de um cantor que não pertence ao tenant logado. Cantor ID: $idCantor, Tenant ID: $id_tenants_logado");
            $pdo->rollBack();
            return false;
        }

        $restricted_statuses = ['cantou', 'em_execucao', 'selecionada_para_rodada'];

        // 1. Obter os status atuais das músicas, filtrando APENAS por id_cantor
        $ids_musicas_cantor = array_keys($novaOrdemMusicas);
        $placeholders = implode(',', array_fill(0, count($ids_musicas_cantor), '?'));

        // CORREÇÃO AQUI: Removemos o filtro 'id_tenants' da tabela 'musicas_cantor'.
        $stmtCheckStatus = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id IN ($placeholders) AND id_cantor = ?");
        // Combina os IDs das músicas e o ID do cantor
        $stmtCheckStatus->execute(array_merge($ids_musicas_cantor, [$idCantor]));
        $currentStatuses = $stmtCheckStatus->fetchAll(PDO::FETCH_KEY_PAIR);

        // Prepara a query para atualizar a ordem
        // CORREÇÃO AQUI: Removemos o filtro 'id_tenants' da tabela 'musicas_cantor'.
        $stmtUpdate = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = ? WHERE id = ? AND id_cantor = ?");

        // 2. Iterar sobre a nova ordem e aplicar as atualizações APENAS se o status permitir
        foreach ($novaOrdemMusicas as $musicaCantorId => $novaPosicao) {
            $musicaCantorId = (int) $musicaCantorId;
            $novaPosicao = (int) $novaPosicao;

            if (isset($currentStatuses[$musicaCantorId]) && !in_array($currentStatuses[$musicaCantorId], $restricted_statuses)) {
                // Se o status permitir, executa a atualização
                // CORREÇÃO: Removemos o parâmetro do tenant daqui também
                if (!$stmtUpdate->execute([$novaPosicao, $musicaCantorId, $idCantor])) {
                    $pdo->rollBack();
                    error_log("Erro ao executar UPDATE para musicas_cantor ID: $musicaCantorId, nova_posicao: $novaPosicao, cantor ID: $idCantor");
                    return false;
                }
            } else {
                error_log("Tentativa de reordenar música com status restrito ou ID inválido para o cantor $idCantor: musica_cantor_id=$musicaCantorId, status=" . ($currentStatuses[$musicaCantorId] ?? 'N/A'));
            }
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
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
    global $id_tenants_logado; // Adiciona a variável global
    try {
        // Adiciona a cláusula WHERE para filtrar por tenant
        $stmt = $pdo->prepare("SELECT id, titulo, artista FROM musicas WHERE id_tenants = ? ORDER BY titulo ASC");
        $stmt->execute([$id_tenants_logado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter todas as músicas para o tenant " . $id_tenants_logado . ": " . $e->getMessage());
        return [];
    }
}


/**
 * Obtém a lista completa da fila para a rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array Lista de itens da fila.
 */
function getFilaCompleta(PDO $pdo) {
    global $id_tenants_logado; // Adiciona a variável global
    // Agora a função getRodadaAtual precisa do ID do tenant
    $rodadaAtual = getRodadaAtual($pdo, $id_tenants_logado);
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
                WHERE fr.rodada = ? AND fr.id_tenants = ? -- Adiciona o filtro por tenant
                ORDER BY
                    CASE
                        WHEN fr.status = 'em_execucao' THEN 0
                        WHEN fr.status = 'aguardando' THEN 1
                        WHEN fr.status = 'selecionada_para_rodada' THEN 2
                        WHEN fr.status = 'pulou' THEN 3
                        WHEN fr.status = 'cantou' THEN 4
                        ELSE 5
                    END,
                    fr.ordem_na_rodada ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual, $id_tenants_logado]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Erro ao obter fila completa para o tenant " . $id_tenants_logado . ": " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se todas as músicas da rodada atual foram marcadas como 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return bool True se a rodada atual estiver finalizada, false caso contrário.
 */
function isRodadaAtualFinalizada(PDO $pdo) {
    global $id_tenants_logado; // Adiciona a variável global
    // Agora a função getRodadaAtual precisa do ID do tenant
    $rodadaAtual = getRodadaAtual($pdo, $id_tenants_logado);
    try {
        $sql = "SELECT COUNT(*) FROM fila_rodadas WHERE rodada = ? AND id_tenants = ? AND (status = 'aguardando' OR status = 'em_execucao')"; // Adiciona o filtro por tenant
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual, $id_tenants_logado]);
        $musicasPendentes = $stmt->fetchColumn();

        return $musicasPendentes === 0;
    } catch (\PDOException $e) {
        error_log("Erro ao verificar status da rodada atual para o tenant " . $id_tenants_logado . ": " . $e->getMessage());
        return false;
    }
}


/**
 * Função auxiliar para obter um ID de música aleatória.
 * Em um sistema real, o MC escolheria a música.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return int O ID de uma música aleatória, ou 0 se não houver músicas.
 */
function getRandomMusicaId(PDO $pdo) {
    global $id_tenants_logado; // Adiciona a variável global
    try {
        // Adiciona a cláusula WHERE para filtrar por tenant
        $stmt = $pdo->prepare("SELECT id FROM musicas WHERE id_tenants = ? ORDER BY RAND() LIMIT 1");
        $stmt->execute([$id_tenants_logado]);
        $row = $stmt->fetch();
        return $row ? $row['id'] : 0;
    } catch (\PDOException $e) {
        error_log("Erro ao obter música aleatória para o tenant " . $id_tenants_logado . ": " . $e->getMessage());
        return 0;
    }
}



/**
 * Reseta o 'proximo_ordem_musica' de todos os cantores para 1,
 * e trunca as tabelas 'controle_rodada' e 'fila_rodadas' APENAS para o tenant logado.
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True se o reset completo foi bem-sucedido, false caso contrário.
 */
function resetarSistema(PDO $pdo): bool {
    global $id_tenants_logado; // Adiciona a variável global
    global $id_evento_ativo; // Adiciona a nova variável global para eventos

    try {
        $pdo->beginTransaction();

        // 1. Resetar 'proximo_ordem_musica' dos cantores (somente do tenant logado)
        $stmtCantores = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = 1 WHERE id_tenants = ?");
        $stmtCantores->execute([$id_tenants_logado]);
        error_log("DEBUG: Todos os 'proximo_ordem_musica' dos cantores do tenant " . $id_tenants_logado . " foram resetados para 1.");

        // 2. Resetar 'status' de todas as músicas para 'aguardando' na tabela musicas_cantor (somente do evento logado)
        $stmtMusicasCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id_eventos = ?");
        $stmtMusicasCantorStatus->execute([$id_evento_ativo]);
        error_log("DEBUG: Todos os 'status' na tabela musicas_cantor do evento " . $id_evento_ativo . " foram resetados para 'aguardando'.");

        // 3. Resetar 'timestamp_ultima_execucao' para NULL na tabela musicas_cantor (somente do evento logado)
        $stmtMusicasCantorTimestamp = $pdo->prepare("UPDATE musicas_cantor SET timestamp_ultima_execucao = NULL WHERE id_eventos = ?");
        $stmtMusicasCantorTimestamp->execute([$id_evento_ativo]);
        error_log("DEBUG: Todos os 'timestamp_ultima_execucao' na tabela musicas_cantor do evento " . $id_evento_ativo . " foram resetados para NULL.");

        // 4. Remover registros da fila de rodadas (somente do tenant logado)
        $stmtFila = $pdo->prepare("DELETE FROM fila_rodadas WHERE id_tenants = ?");
        $stmtFila->execute([$id_tenants_logado]);
        error_log("DEBUG: Tabela 'fila_rodadas' do tenant " . $id_tenants_logado . " limpa.");

        // 5. Resetar controle_rodada (somente do tenant logado)
        $stmtControle = $pdo->prepare("UPDATE controle_rodada SET rodada_atual = 1 WHERE id_tenants = ?");
        $stmtControle->execute([$id_tenants_logado]);
        error_log("DEBUG: Tabela 'controle_rodada' do tenant " . $id_tenants_logado . " resetada com rodada 1.");

        $pdo->commit();
        error_log("DEBUG: Reset completo da fila (cantores, musicas_cantor, fila_rodadas, controle_rodada) realizado com sucesso para o tenant " . $id_tenants_logado . ".");
        return true;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao realizar o reset completo da fila para o tenant " . $id_tenants_logado . ": " . $e->getMessage());
        return false;
    }
}