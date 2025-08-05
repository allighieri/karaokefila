<?php
/**
 * Adiciona um novo tenant ao banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param array $dados Um array com os dados do tenant.
 * @return array Um array com 'success' (bool) e 'message' (string).
 */
function addTenant(PDO $pdo, array $dados): array {
    try {
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
        return ['success' => true, 'message' => 'Estabelecimento cadastrado com sucesso!'];
    } catch (PDOException $e) {
        error_log("Erro ao adicionar tenant: " . $e->getMessage());
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
        $status_ativo = 1; // O status correto para tenants ativos
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
 * Exclui um tenant do banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $id O ID do tenant a ser excluído.
 * @return array Um array com 'success' (bool) e 'message' (string).
 */
function deleteTenant(PDO $pdo, int $id): array {
    try {
        // Altere o status em vez de excluir a linha
        $stmt = $pdo->prepare("UPDATE tenants SET status = 0 WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Estabelecimento excluído com sucesso.'];
    } catch (PDOException $e) {
        error_log("Erro ao excluir tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao excluir estabelecimento.'];
    }
}
