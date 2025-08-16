<?php
// Funções atualizadas para cantores usando id_usuario
require_once 'conn.php';
require_once 'init.php';

/**
 * Obter todos os cantores com informações do usuário
 * @param PDO $pdo Objeto de conexão PDO
 * @return array Lista de cantores com dados do usuário
 */
function getAllCantoresComUsuario(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.id_usuario,
                c.id_mesa,
                c.proximo_ordem_musica,
                u.nome as nome_cantor,
                u.email,
                m.nome_mesa as nome_da_mesa_associada,
                m.tamanho_mesa
            FROM cantores c
            JOIN usuarios u ON c.id_usuario = u.id
            JOIN mesas m ON c.id_mesa = m.id
            WHERE c.id_tenants = ? AND m.id_tenants = ? AND m.id_eventos = ?
            ORDER BY m.nome_mesa, u.nome
        ");
        $stmt->execute([ID_TENANTS, ID_TENANTS, ID_EVENTO_ATIVO]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter cantores: " . $e->getMessage());
        return [];
    }
}

/**
 * Adicionar cantor usando id_usuario
 * @param PDO $pdo Objeto de conexão PDO
 * @param int $idUsuario ID do usuário a ser adicionado como cantor
 * @param int $idMesa ID da mesa
 * @return array Resultado da operação
 */
function adicionarCantorPorUsuario(PDO $pdo, $idUsuario, $idMesa) {
    try {
        $pdo->beginTransaction();

        // Verificar se a mesa existe e pertence ao tenant e evento
        $stmtGetMesa = $pdo->prepare("SELECT nome_mesa FROM mesas WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
        $stmtGetMesa->execute([$idMesa, ID_TENANTS, ID_EVENTO_ATIVO]);
        $mesaInfo = $stmtGetMesa->fetch(PDO::FETCH_ASSOC);

        if (!$mesaInfo) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Mesa não encontrada ou não pertence ao evento atual.'];
        }
        $nomeMesa = $mesaInfo['nome_mesa'];

        // Verificar se o usuário existe e pertence ao tenant
        $stmtGetUsuario = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND id_tenants = ?");
        $stmtGetUsuario->execute([$idUsuario, ID_TENANTS]);
        $usuarioInfo = $stmtGetUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioInfo) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Usuário não encontrado ou não pertence ao seu estabelecimento."];
        }
        $nomeUsuario = $usuarioInfo['nome'];

        // Verificar se o usuário já está cadastrado como cantor nesta mesa
        $stmtCheckExiste = $pdo->prepare("SELECT id FROM cantores WHERE id_usuario = ? AND id_mesa = ?");
        $stmtCheckExiste->execute([$idUsuario, $idMesa]);
        if ($stmtCheckExiste->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Este usuário já está cadastrado nesta mesa."];
        }

        // Inserir o novo cantor
        $stmt = $pdo->prepare("INSERT INTO cantores (id_tenants, id_usuario, id_mesa) VALUES (?, ?, ?)");
        $success = $stmt->execute([ID_TENANTS, $idUsuario, $idMesa]);

        if ($success) {
            // Incrementar o tamanho_mesa
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa + 1 WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa, ID_TENANTS, ID_EVENTO_ATIVO]);

            if ($updateSuccess) {
                $pdo->commit();
                return ['success' => true, 'message' => "<strong>{$nomeUsuario}</strong> adicionado(a) à mesa <strong>{$nomeMesa}</strong> com sucesso!"];
            } else {
                $pdo->rollBack();
                return ['success' => false, 'message' => "Erro ao atualizar o tamanho da mesa."];
            }
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Erro ao adicionar o cantor."];
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao adicionar cantor (PDOException): " . $e->getMessage());
        return ['success' => false, 'message' => "Erro interno do servidor ao adicionar cantor."];
    }
}

/**
 * Remover cantor (adaptado para usar id_usuario)
 * @param PDO $pdo Objeto de conexão PDO
 * @param int $idCantor ID do cantor a ser removido
 * @return array Resultado da operação
 */
function removerCantorPorId(PDO $pdo, $idCantor): array {
    try {
        $pdo->beginTransaction();

        // Obter informações do cantor antes de excluí-lo
        $stmtGetCantorInfo = $pdo->prepare("
            SELECT c.id_mesa, c.id_usuario, u.nome as nome_usuario 
            FROM cantores c 
            JOIN usuarios u ON c.id_usuario = u.id 
            WHERE c.id = ? AND c.id_tenants = ?
        ");
        $stmtGetCantorInfo->execute([$idCantor, ID_TENANTS]);
        $cantorInfo = $stmtGetCantorInfo->fetch(PDO::FETCH_ASSOC);

        if (!$cantorInfo) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Cantor não encontrado ou não pertence ao seu estabelecimento.'];
        }

        $idMesa = $cantorInfo['id_mesa'];
        $nomeUsuario = $cantorInfo['nome_usuario'];

        // Verificar se o cantor tem alguma música em execução na fila
        $stmtCheckFila = $pdo->prepare("
            SELECT COUNT(*) FROM fila_rodadas
            WHERE id_cantor = ? AND status = 'em_execucao' AND id_tenants = ?
        ");
        $stmtCheckFila->execute([$idCantor, ID_TENANTS]);
        $isInFilaAtiva = $stmtCheckFila->fetchColumn();

        if ($isInFilaAtiva > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Não é possível remover <strong>{$nomeUsuario}</strong>. Este cantor possui uma música em execução na fila."];
        }

        // Verificar se o cantor tem música selecionada para a próxima rodada
        $stmtCheckSelecionada = $pdo->prepare("
            SELECT COUNT(*) FROM fila_rodadas
            WHERE id_cantor = ? AND status = 'selecionada' AND id_tenants = ?
        ");
        $stmtCheckSelecionada->execute([$idCantor, ID_TENANTS]);
        $isSelecionada = $stmtCheckSelecionada->fetchColumn();

        if ($isSelecionada > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Não é possível remover <strong>{$nomeUsuario}</strong>. Este cantor possui uma música selecionada para a próxima rodada."];
        }

        // Remover o cantor
        $stmtDeleteCantor = $pdo->prepare("DELETE FROM cantores WHERE id = ? AND id_tenants = ?");
        $deleteSuccess = $stmtDeleteCantor->execute([$idCantor, ID_TENANTS]);

        if ($deleteSuccess) {
            // Decrementar o tamanho_mesa
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa - 1 WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa, ID_TENANTS, ID_EVENTO_ATIVO]);

            if ($updateSuccess) {
                $pdo->commit();
                return ['success' => true, 'message' => "<strong>{$nomeUsuario}</strong> removido(a) com sucesso!"];
            } else {
                $pdo->rollBack();
                return ['success' => false, 'message' => "Erro ao atualizar o tamanho da mesa."];
            }
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => "Erro ao remover o cantor."];
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao remover cantor (PDOException): " . $e->getMessage());
        return ['success' => false, 'message' => "Erro interno do servidor ao remover cantor."];
    }
}

/**
 * Obter usuários do tenant que ainda não são cantores
 * @param PDO $pdo Objeto de conexão PDO
 * @return array Lista de usuários disponíveis
 */
function obterUsuariosDisponiveis(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nome, u.email, u.nivel
            FROM usuarios u
            WHERE u.id_tenants = ? 
            AND u.status = 1
            AND u.id NOT IN (
                SELECT DISTINCT c.id_usuario 
                FROM cantores c 
                JOIN mesas m ON c.id_mesa = m.id
                WHERE c.id_tenants = ? AND m.id_eventos = ?
            )
            ORDER BY u.nome
        ");
        $stmt->execute([ID_TENANTS, ID_TENANTS, ID_EVENTO_ATIVO]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter usuários disponíveis: " . $e->getMessage());
        return [];
    }
}

/**
 * Editar cantor (atualizar id_usuario e id_mesa)
 * @param PDO $pdo Objeto de conexão PDO
 * @param int $idCantor ID do cantor a ser editado
 * @param int $novoIdUsuario Novo ID do usuário
 * @param int $novaIdMesa Nova ID da mesa
 * @return array Resultado da operação
 */
function editarCantor(PDO $pdo, $idCantor, $novoIdUsuario, $novaIdMesa): array {
    try {
        $pdo->beginTransaction();

        // Verificar se o cantor existe e pertence ao tenant
        $stmtGetCantor = $pdo->prepare("SELECT id_mesa, id_usuario FROM cantores WHERE id = ? AND id_tenants = ?");
        $stmtGetCantor->execute([$idCantor, ID_TENANTS]);
        $cantorInfo = $stmtGetCantor->fetch(PDO::FETCH_ASSOC);

        if (!$cantorInfo) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Cantor não encontrado ou não pertence ao seu estabelecimento.'];
        }

        $mesaAntigaId = $cantorInfo['id_mesa'];
        $usuarioAntigoId = $cantorInfo['id_usuario'];

        // Verificar se o novo usuário existe e pertence ao tenant
        $stmtGetUsuario = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND id_tenants = ?");
        $stmtGetUsuario->execute([$novoIdUsuario, ID_TENANTS]);
        $usuarioInfo = $stmtGetUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioInfo) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Usuário não encontrado ou não pertence ao seu estabelecimento.'];
        }
        $nomeUsuario = $usuarioInfo['nome'];

        // Verificar se a nova mesa existe e pertence ao tenant e evento
        $stmtGetMesa = $pdo->prepare("SELECT nome_mesa FROM mesas WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
        $stmtGetMesa->execute([$novaIdMesa, ID_TENANTS, ID_EVENTO_ATIVO]);
        $mesaInfo = $stmtGetMesa->fetch(PDO::FETCH_ASSOC);

        if (!$mesaInfo) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Mesa não encontrada ou não pertence ao evento atual.'];
        }
        $nomeMesa = $mesaInfo['nome_mesa'];

        // Verificar se o usuário já está cadastrado nesta mesa (exceto o próprio cantor)
        if ($novoIdUsuario != $usuarioAntigoId || $novaIdMesa != $mesaAntigaId) {
            $stmtCheckExiste = $pdo->prepare("SELECT id FROM cantores WHERE id_usuario = ? AND id_mesa = ? AND id != ?");
            $stmtCheckExiste->execute([$novoIdUsuario, $novaIdMesa, $idCantor]);
            if ($stmtCheckExiste->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Este usuário já está cadastrado nesta mesa.'];
            }
        }

        // Atualizar o cantor
        $stmtUpdate = $pdo->prepare("UPDATE cantores SET id_usuario = ?, id_mesa = ? WHERE id = ? AND id_tenants = ?");
        $updateSuccess = $stmtUpdate->execute([$novoIdUsuario, $novaIdMesa, $idCantor, ID_TENANTS]);

        if (!$updateSuccess) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao atualizar o cantor.'];
        }

        // Atualizar tamanho das mesas se a mesa foi alterada
        if ($mesaAntigaId != $novaIdMesa) {
            // Decrementar tamanho da mesa antiga
            $stmtDecrement = $pdo->prepare("UPDATE mesas SET tamanho_mesa = GREATEST(0, tamanho_mesa - 1) WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
            $stmtDecrement->execute([$mesaAntigaId, ID_TENANTS, ID_EVENTO_ATIVO]);

            // Incrementar tamanho da nova mesa
            $stmtIncrement = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa + 1 WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
            $stmtIncrement->execute([$novaIdMesa, ID_TENANTS, ID_EVENTO_ATIVO]);
        }

        $pdo->commit();
        return ['success' => true, 'message' => "Cantor <strong>{$nomeUsuario}</strong> atualizado com sucesso na mesa <strong>{$nomeMesa}</strong>!"];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao editar cantor (PDOException): " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno do servidor ao editar cantor.'];
    }
}

/**
 * Obter cantores de uma mesa específica
 * @param PDO $pdo Objeto de conexão PDO
 * @param int $idMesa ID da mesa
 * @return array Lista de cantores da mesa
 */
function getCantoresDaMesa(PDO $pdo, $idMesa): array {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.id_usuario,
                c.proximo_ordem_musica,
                u.nome as nome_usuario,
                u.email
            FROM cantores c
            JOIN usuarios u ON c.id_usuario = u.id
            JOIN mesas m ON c.id_mesa = m.id
            WHERE c.id_mesa = ? AND c.id_tenants = ? AND m.id_eventos = ?
            ORDER BY u.nome
        ");
        $stmt->execute([$idMesa, ID_TENANTS, ID_EVENTO_ATIVO]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter cantores da mesa: " . $e->getMessage());
        return [];
    }
}
?>