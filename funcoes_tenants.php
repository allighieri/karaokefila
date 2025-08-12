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
        return ['success' => true, 'message' => 'Estabelecimento cadastrado com sucesso!', 'tenant_id' => $newTenantId];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao adicionar tenant e regras: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao cadastrar estabelecimento.'];
    }
}

/**
 * Adiciona um código para um tenant na tabela tenant_codes.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $tenantId O ID do tenant.
 * @param string $code O código do estabelecimento.
 * @return array Um array com 'success' (bool) e 'message' (string).
 */
function addTenantCode(PDO $pdo, int $tenantId, string $code): array {
    try {
        // Verifica se o código já existe
        $stmt = $pdo->prepare("SELECT id FROM tenant_codes WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Este código já está em uso.'];
        }

        // Insere o novo código
        $stmt = $pdo->prepare("INSERT INTO tenant_codes (id_tenants, code, status) VALUES (?, ?, 'active')");
        $stmt->execute([$tenantId, $code]);
        
        return ['success' => true, 'message' => 'Código do estabelecimento salvo com sucesso!'];
    } catch (PDOException $e) {
        error_log("Erro ao adicionar código do tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno do servidor.'];
    }
}

/**
 * Atualiza o código de um tenant no banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $tenantId O ID do tenant.
 * @param string $code O novo código do tenant.
 * @return array Um array com 'success' (bool) e 'message' (string).
 */
function updateTenantCode(PDO $pdo, int $tenantId, string $code, string $status = 'active'): array {
    try {
        // Verifica se já existe um código ativo para outro tenant (apenas se o status for 'active')
        if ($status === 'active') {
            $stmt_check = $pdo->prepare("SELECT id_tenants FROM tenant_codes WHERE code = ? AND status = 'active' AND id_tenants != ?");
            $stmt_check->execute([$code, $tenantId]);
            if ($stmt_check->fetch()) {
                return ['success' => false, 'message' => 'Este código já existe em outro estabelecimento.'];
            }
        }

        // Verifica se já existe um código para este tenant
        $stmt_existing = $pdo->prepare("SELECT id FROM tenant_codes WHERE id_tenants = ?");
        $stmt_existing->execute([$tenantId]);
        $existing = $stmt_existing->fetch();

        if ($existing) {
            // Atualiza o código existente
            $stmt_update = $pdo->prepare("UPDATE tenant_codes SET code = ?, status = ?, updated_at = NOW() WHERE id_tenants = ?");
            $stmt_update->execute([$code, $status, $tenantId]);
            $statusMessage = $status === 'active' ? 'ativado' : 'desativado';
            return ['success' => true, 'message' => "Código do estabelecimento atualizado e {$statusMessage} com sucesso."];
        } else {
            // Cria um novo código se não existir
            $stmt_insert = $pdo->prepare("INSERT INTO tenant_codes (id_tenants, code, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt_insert->execute([$tenantId, $code, $status]);
            $statusMessage = $status === 'active' ? 'ativo' : 'inativo';
            return ['success' => true, 'message' => "Código do estabelecimento definido como {$statusMessage} com sucesso."];
        }
    } catch (PDOException $e) {
        error_log("Erro ao atualizar código do tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno do servidor.'];
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
        $stmt = $pdo->prepare("
            SELECT t.*, tc.code as tenant_code, tc.status as code_status
            FROM tenants t 
            LEFT JOIN tenant_codes tc ON t.id = tc.id_tenants
            WHERE t.status = ? 
            ORDER BY t.nome ASC
        ");
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
        $stmt = $pdo->prepare("
            SELECT t.*, tc.code as tenant_code, tc.status as code_status
            FROM tenants t 
            LEFT JOIN tenant_codes tc ON t.id = tc.id_tenants
            WHERE t.status = 0 
            ORDER BY t.nome ASC
        ");
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

/**
 * Busca o código do tenant atual.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $tenantId O ID do tenant.
 * @return array Um array indicando o sucesso ou falha da operação.
 */
function getCurrentTenantCode(PDO $pdo, int $tenantId): array {
    try {
        $stmt = $pdo->prepare("SELECT code, status FROM tenant_codes WHERE id_tenants = ?");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'success' => true,
                'code' => $result['code'],
                'status' => $result['status']
            ];
        } else {
            return [
                'success' => true,
                'code' => '',
                'status' => 'active',
                'message' => 'Nenhum código cadastrado para este estabelecimento.'
            ];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar código do tenant: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao buscar código do estabelecimento.'];
    }
}