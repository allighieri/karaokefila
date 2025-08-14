<?php
require_once 'conn.php';

/**
 * Obtém todos os usuários ativos de um tenant específico
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_tenants ID do tenant (null para super_admin ver todos)
 * @return array Lista de usuários
 */
function obterUsuariosAtivos(PDO $pdo, $id_tenants = null, $nivel_usuario_logado = null): array {
    try {
        if ($id_tenants === null) {
            // Super admin vê todos os usuários
            if ($nivel_usuario_logado === 'super_admin') {
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 1 
                    ORDER BY u.nome
                ");
                $stmt->execute();
            } else {
                // Usuários não super_admin não veem outros super_admin
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 1 AND u.nivel != 'super_admin'
                    ORDER BY u.nome
                ");
                $stmt->execute();
            }
        } else {
            // Admin vê apenas usuários do seu tenant (exceto super_admin se não for super_admin)
            if ($nivel_usuario_logado === 'super_admin') {
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 1 AND u.id_tenants = ? 
                    ORDER BY u.nome
                ");
                $stmt->execute([$id_tenants]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 1 AND u.id_tenants = ? AND u.nivel != 'super_admin'
                    ORDER BY u.nome
                ");
                $stmt->execute([$id_tenants]);
            }
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter usuários ativos: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém todos os usuários inativos de um tenant específico
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_tenants ID do tenant (null para super_admin ver todos)
 * @return array Lista de usuários inativos
 */
function obterUsuariosInativos(PDO $pdo, $id_tenants = null, $nivel_usuario_logado = null): array {
    try {
        if ($id_tenants === null) {
            // Super admin vê todos os usuários inativos
            if ($nivel_usuario_logado === 'super_admin') {
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 0 
                    ORDER BY u.nome
                ");
                $stmt->execute();
            } else {
                // Usuários não super_admin não veem outros super_admin inativos
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 0 AND u.nivel != 'super_admin'
                    ORDER BY u.nome
                ");
                $stmt->execute();
            }
        } else {
            // Admin vê apenas usuários inativos do seu tenant (exceto super_admin se não for super_admin)
            if ($nivel_usuario_logado === 'super_admin') {
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 0 AND u.id_tenants = ? 
                    ORDER BY u.nome
                ");
                $stmt->execute([$id_tenants]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT u.*, t.nome as tenant_nome 
                    FROM usuarios u 
                    LEFT JOIN tenants t ON u.id_tenants = t.id 
                    WHERE u.status = 0 AND u.id_tenants = ? AND u.nivel != 'super_admin'
                    ORDER BY u.nome
                ");
                $stmt->execute([$id_tenants]);
            }
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter usuários inativos: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém todos os tenants para o dropdown (apenas para super_admin)
 * @param PDO $pdo Conexão com o banco de dados
 * @return array Lista de tenants
 */
function obterTenants(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT id, nome FROM tenants WHERE status = 1 ORDER BY nome");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter tenants: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém dados de um usuário específico
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_usuario ID do usuário
 * @param int $id_tenants ID do tenant (para validação de acesso)
 * @return array|null Dados do usuário ou null se não encontrado
 */
function obterUsuario(PDO $pdo, int $id_usuario, $id_tenants = null): ?array {
    try {
        if ($id_tenants === null) {
            // Super admin pode ver qualquer usuário
            $stmt = $pdo->prepare("
                SELECT u.*, t.nome as tenant_nome 
                FROM usuarios u 
                LEFT JOIN tenants t ON u.id_tenants = t.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$id_usuario]);
        } else {
            // Admin só pode ver usuários do seu tenant
            $stmt = $pdo->prepare("
                SELECT u.*, t.nome as tenant_nome 
                FROM usuarios u 
                LEFT JOIN tenants t ON u.id_tenants = t.id 
                WHERE u.id = ? AND u.id_tenants = ?
            ");
            $stmt->execute([$id_usuario, $id_tenants]);
        }
        
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    } catch (PDOException $e) {
        error_log("Erro ao obter usuário: " . $e->getMessage());
        return null;
    }
}

/**
 * Atualiza dados de um usuário
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_usuario ID do usuário
 * @param array $dados Dados para atualização
 * @param int $id_tenants ID do tenant (para validação de acesso)
 * @return array Resultado da operação
 */
function atualizarUsuario(PDO $pdo, int $id_usuario, array $dados, $id_tenants = null): array {
    try {
        $pdo->beginTransaction();
        
        // Verificar se o usuário existe e pertence ao tenant (se não for super_admin)
        if ($id_tenants !== null) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND id_tenants = ?");
            $stmt->execute([$id_usuario, $id_tenants]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Usuário não encontrado ou sem permissão para editar.'];
            }
        }
        
        $campos = [];
        $valores = [];
        
        if (isset($dados['nome'])) {
            $campos[] = 'nome = ?';
            $valores[] = $dados['nome'];
        }
        
        if (isset($dados['telefone'])) {
            $campos[] = 'telefone = ?';
            $valores[] = $dados['telefone'];
        }
        
        if (isset($dados['email'])) {
            $campos[] = 'email = ?';
            $valores[] = $dados['email'];
        }
        
        if (isset($dados['cidade'])) {
            $campos[] = 'cidade = ?';
            $valores[] = $dados['cidade'];
        }
        
        if (isset($dados['uf'])) {
            $campos[] = 'uf = ?';
            $valores[] = $dados['uf'];
        }
        
        if (isset($dados['nivel'])) {
            $campos[] = 'nivel = ?';
            $valores[] = $dados['nivel'];
        }
        
        if (isset($dados['password']) && !empty($dados['password'])) {
            $campos[] = 'password = ?';
            $valores[] = password_hash($dados['password'], PASSWORD_DEFAULT);
        }
        
        $campos[] = 'updated_at = NOW()';
        $valores[] = $id_usuario;
        
        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($valores);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Usuário atualizado com sucesso.'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao atualizar usuário: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()];
    }
}

/**
 * Desativa um usuário
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_usuario ID do usuário
 * @param int $id_tenants ID do tenant (para validação de acesso)
 * @return array Resultado da operação
 */
function desativarUsuario(PDO $pdo, int $id_usuario, $id_tenants = null): array {
    try {
        $pdo->beginTransaction();
        
        // Verificar se o usuário existe e pertence ao tenant (se não for super_admin)
        if ($id_tenants !== null) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND id_tenants = ?");
            $stmt->execute([$id_usuario, $id_tenants]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Usuário não encontrado ou sem permissão para desativar.'];
            }
        }
        
        $stmt = $pdo->prepare("UPDATE usuarios SET status = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id_usuario]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Usuário desativado com sucesso.'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao desativar usuário: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao desativar usuário: ' . $e->getMessage()];
    }
}

/**
 * Reativa um usuário
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_usuario ID do usuário
 * @param int $id_tenants ID do tenant (para validação de acesso)
 * @return array Resultado da operação
 */
function reativarUsuario(PDO $pdo, int $id_usuario, $id_tenants = null): array {
    try {
        $pdo->beginTransaction();
        
        // Verificar se o usuário existe e pertence ao tenant (se não for super_admin)
        if ($id_tenants !== null) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND id_tenants = ?");
            $stmt->execute([$id_usuario, $id_tenants]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Usuário não encontrado ou sem permissão para reativar.'];
            }
        }
        
        $stmt = $pdo->prepare("UPDATE usuarios SET status = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id_usuario]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Usuário reativado com sucesso.'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao reativar usuário: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao reativar usuário: ' . $e->getMessage()];
    }
}

/**
 * Exclui permanentemente um usuário
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_usuario ID do usuário
 * @param int $id_tenants ID do tenant (para validação de acesso)
 * @return array Resultado da operação
 */
function excluirUsuario(PDO $pdo, int $id_usuario, $id_tenants = null): array {
    try {
        $pdo->beginTransaction();
        
        // Verificar se o usuário existe e pertence ao tenant (se não for super_admin)
        if ($id_tenants !== null) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND id_tenants = ?");
            $stmt->execute([$id_usuario, $id_tenants]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Usuário não encontrado ou sem permissão para excluir.'];
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id_usuario]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Usuário excluído permanentemente.'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao excluir usuário: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao excluir usuário: ' . $e->getMessage()];
    }
}

/**
 * Altera o nível de acesso de um usuário
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $id_usuario ID do usuário
 * @param string $novo_nivel Novo nível de acesso
 * @param int $id_tenants ID do tenant (para validação de acesso)
 * @return array Resultado da operação
 */
function alterarNivelUsuario(PDO $pdo, int $id_usuario, string $novo_nivel, $id_tenants = null): array {
    try {
        $pdo->beginTransaction();
        
        // Verificar se o usuário existe e pertence ao tenant (se não for super_admin)
        if ($id_tenants !== null) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND id_tenants = ?");
            $stmt->execute([$id_usuario, $id_tenants]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Usuário não encontrado ou sem permissão para alterar nível.'];
            }
        }
        
        // Validar nível
        $niveis_validos = ['mc', 'user', 'admin', 'super_admin'];
        if (!in_array($novo_nivel, $niveis_validos)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Nível de acesso inválido.'];
        }
        
        $stmt = $pdo->prepare("UPDATE usuarios SET nivel = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$novo_nivel, $id_usuario]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Nível de acesso alterado com sucesso.'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao alterar nível do usuário: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao alterar nível do usuário: ' . $e->getMessage()];
    }
}
?>