<?php
// Funções para gerenciamento de eventos
require_once 'conn.php';
require_once 'init.php';

/**
 * Criar um novo evento
 * @param string $nome Nome do evento
 * @param int $id_usuario_mc ID do usuário MC que está criando o evento
 * @param int $id_tenants ID do tenant
 * @return array Resultado da operação
 */
function criarEvento($nome, $id_usuario_mc, $id_tenants) {
    global $pdo;
    
    try {
        // Desativar evento ativo anterior do MC
        $stmt = $pdo->prepare("UPDATE eventos SET status = 'inativo' WHERE id_usuario_mc = ? AND status = 'ativo'");
        $stmt->execute([$id_usuario_mc]);
        
        // Verificar se já existe um evento com o mesmo nome no tenant
        $stmt = $pdo->prepare("SELECT id FROM eventos WHERE nome = ? AND id_tenants = ?");
        $stmt->execute([$nome, $id_tenants]);
        $result = $stmt->fetchAll();
        
        if (count($result) > 0) {
            return [
                'success' => false,
                'message' => 'Já existe um evento com este nome neste estabelecimento.'
            ];
        }
        
        // Criar o evento
        $stmt = $pdo->prepare("INSERT INTO eventos (nome, id_usuario_mc, id_tenants, status) VALUES (?, ?, ?, 'ativo')");
        
        if ($stmt->execute([$nome, $id_usuario_mc, $id_tenants])) {
            return [
                'success' => true,
                'message' => 'Evento criado com sucesso!',
                'id_evento' => $pdo->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erro ao criar evento'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erro interno: ' . $e->getMessage()
        ];
    }
}

/**
 * Obter evento ativo do MC
 * @param int $id_usuario_mc ID do usuário MC
 * @return array|null Dados do evento ativo ou null
 */
function obterEventoAtivoMC($id_usuario_mc) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT e.*, t.nome as nome_tenant 
        FROM eventos e 
        JOIN tenants t ON e.id_tenants = t.id 
        WHERE e.id_usuario_mc = ? AND e.status = 'ativo'
    ");
    $stmt->execute([$id_usuario_mc]);
    
    return $stmt->fetch();
}

/**
 * Listar todos os eventos do tenant
 * @param int $id_tenants ID do tenant
 * @param string $status Filtro por status (opcional)
 * @return array Lista de eventos
 */
function listarEventosTenant($id_tenants, $status = null) {
    global $pdo;
    
    $sql = "
        SELECT e.*, u.nome as nome_mc, t.nome as nome_tenant
        FROM eventos e 
        JOIN usuarios u ON e.id_usuario_mc = u.id 
        JOIN tenants t ON e.id_tenants = t.id 
        WHERE e.id_tenants = ?
    ";
    
    $params = [$id_tenants];
    
    if ($status) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY e.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Alterar status do evento
 * @param int $id_evento ID do evento
 * @param int $id_usuario_mc ID do MC (para verificação de permissão)
 * @param string $novo_status Novo status ('ativo' ou 'inativo')
 * @return array Resultado da operação
 */
function alterarStatusEvento($id_evento, $id_usuario_mc, $novo_status) {
    global $pdo;
    
    try {
        // Verificar se o evento pertence ao MC
        $stmt = $pdo->prepare("SELECT id FROM eventos WHERE id = ? AND id_usuario_mc = ?");
        $stmt->execute([$id_evento, $id_usuario_mc]);
        $result = $stmt->fetchAll();
        
        if (count($result) === 0) {
            return [
                'success' => false,
                'message' => 'Evento não encontrado ou você não tem permissão para alterá-lo.'
            ];
        }
        
        // Se está ativando, desativar automaticamente outros eventos ativos do MC
        if ($novo_status === 'ativo') {
            $stmt = $pdo->prepare("UPDATE eventos SET status = 'inativo' WHERE id_usuario_mc = ? AND status = 'ativo' AND id != ?");
            $stmt->execute([$id_usuario_mc, $id_evento]);
        }
        
        // Alterar status
        $stmt = $pdo->prepare("UPDATE eventos SET status = ? WHERE id = ?");
        
        if ($stmt->execute([$novo_status, $id_evento])) {
            $mensagem = $novo_status === 'ativo' ? 'Evento ativado com sucesso!' : 'Evento desativado com sucesso!';
            return [
                'success' => true,
                'message' => $mensagem
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erro ao alterar status'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erro interno: ' . $e->getMessage()
        ];
    }
}

/**
 * Verificar se usuário pode criar eventos (deve ser MC ou Admin)
 * @param string $nivel_acesso Nível de acesso do usuário
 * @return bool
 */
function podeGerenciarEventos($nivel_acesso) {
    return in_array($nivel_acesso, ['mc', 'admin', 'super_admin']);
}

/**
 * Obter evento ativo do tenant (para compatibilidade com código existente)
 * @param int $id_tenants ID do tenant
 * @return int|null ID do evento ativo ou null
 */
function obterEventoAtivoTenant($id_tenants) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM eventos WHERE id_tenants = ? AND status = 'ativo' LIMIT 1");
    $stmt->execute([$id_tenants]);
    
    if ($row = $stmt->fetch()) {
        return $row['id'];
    }
    
    return null;
}
?>