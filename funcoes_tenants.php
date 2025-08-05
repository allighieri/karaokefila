<?php
/**
 * Adiciona um novo tenant ao banco de dados e suas regras padrão.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param array $dados Um array com os dados do tenant.
 * @return array Um array com 'success' (bool) e 'message' (string).
 */
function addTenant(PDO $pdo, array $dados): array {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO tenants (nome, telefone, email, endereco, cidade, uf, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $dados['nome'],
            $dados['telefone'],
            $dados['email'],
            $dados['endereco'],
            $dados['cidade'],
            $dados['uf'],
            1 // status 1 para ativo por padrão
        ]);

        $newTenantId = $pdo->lastInsertId();

        // Chama a função para adicionar as regras padrão com o ID do novo tenant e se não for true, desfaz todas as ações de inserção do tenant
        if (!setRegrasPadrao($pdo, $newTenantId)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao cadastrar estabelecimento e suas regras padrão.'];
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'Estabelecimento cadastrado com sucesso!'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao adicionar tenant e regras: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao cadastrar estabelecimento.'];
    }
}

/**
 * Busca todos os tenants no banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @return array Um array de tenants ou um array vazio.
 */
function getAllTenants(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE status = ? ORDER BY nome ASC");
        $status_ativo = 1;
        $stmt->execute([$status_ativo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar tenants: " . $e->getMessage());
        return [];
    }
}

/**
 * Edita um tenant no banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $id O ID do tenant a ser editado.
 * @param array $dados Um array com os novos dados do tenant.
 * @return array Um array com 'success' (bool) e 'message' (string).
 */
function editTenant(PDO $pdo, int $id, array $dados): array {
    try {
        $stmt = $pdo->prepare("UPDATE tenants SET nome = ?, telefone = ?, email = ?, endereco = ?, cidade = ?, uf = ? WHERE id = ?");
        $stmt->execute([
            $dados['nome'],
            $dados['telefone'],
            $dados['email'],
            $dados['endereco'],
            $dados['cidade'],
            $dados['uf'],
            $id
        ]);
        return ['success' => true, 'message' => 'Estabelecimento atualizado com sucesso!'];
    } catch (PDOException $e) {
        error_log("Erro ao editar tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao atualizar estabelecimento.'];
    }
}

/**
 * Inativa um tenant no banco de dados (muda o status para 0).
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $id O ID do tenant a ser inativado.
 * @return array Um array indicando o sucesso ou falha da operação.
 */
function inactivateTenant(PDO $pdo, int $id): array {
    try {
        $stmt = $pdo->prepare("UPDATE tenants SET status = 0 WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Estabelecimento inativado com sucesso.'];
    } catch (PDOException $e) {
        error_log("Erro ao inativar tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao inativar estabelecimento.'];
    }
}

/**
 * Exclui um tenant PERMANENTEMENTE do banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $id O ID do tenant a ser excluído.
 * @return array Um array com 'success' (bool) e 'message' (string).
 */
function deleteTenant(PDO $pdo, int $id): array {
    try {
        $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Estabelecimento excluído permanentemente.'];
    } catch (PDOException $e) {
        error_log("Erro ao excluir tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao excluir estabelecimento.'];
    }
}

/**
 * Busca todos os tenants INATIVOS no banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @return array Um array de tenants inativos ou um array vazio.
 */
function getInactiveTenants(PDO $pdo): array {
    try {
        // Agora seleciona todos os campos para exibir na tabela de inativos
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE status = 0 ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar tenants inativos: " . $e->getMessage());
        return [];
    }
}

/**
 * Reativa um tenant no banco de dados (muda o status para 1).
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $id O ID do tenant a ser reativado.
 * @return array Um array indicando o sucesso ou falha da operação.
 */
function reactivateTenant(PDO $pdo, int $id): array {
    try {
        $stmt = $pdo->prepare("UPDATE tenants SET status = 1 WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Estabelecimento reativado com sucesso.'];
    } catch (PDOException $e) {
        error_log("Erro ao reativar tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao reativar estabelecimento.'];
    }
}

/**
 * Reseta a tabela de configuração de regras de mesa e insere regras padrão para um tenant específico.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @param int $id_tenants O ID do tenant para o qual as regras serão configuradas.
 * @return bool True em caso de sucesso, false em caso de erro.
 */
function setRegrasPadrao(PDO $pdo, int $id_tenants): bool
{
    try {
        // Usa DELETE para remover APENAS as regras do tenant fornecido
        $stmtDelete = $pdo->prepare("DELETE FROM configuracao_regras_mesa WHERE id_tenants = ?");
        $stmtDelete->execute([$id_tenants]);

        // 2. Inserir as regras padrão para o tenant fornecido
        $sql = "INSERT INTO `configuracao_regras_mesa` (`id_tenants`, `min_pessoas`, `max_pessoas`, `max_musicas_por_rodada`) VALUES 
                (?, 1, 2, 1),
                (?, 3, 4, 2),
                (?, 5, NULL, 3)";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$id_tenants, $id_tenants, $id_tenants]);

        return $result;
    } catch (PDOException $e) {
        error_log("Erro ao definir regras padrão para o tenant " . $id_tenants . ": " . $e->getMessage());
        return false;
    }
}