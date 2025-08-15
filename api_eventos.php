<?php
require_once 'init.php';
require_once 'funcoes_eventos.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_logado'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o usuário tem permissão para gerenciar eventos
if (!podeGerenciarEventos(NIVEL_ACESSO)) {
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para gerenciar eventos']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'criar_evento':
        $nome = trim($_POST['nome'] ?? '');
        $id_usuario_mc = $_POST['id_usuario_mc'] ?? null;
        
        if (empty($nome)) {
            echo json_encode(['success' => false, 'message' => 'Nome do evento é obrigatório']);
            exit;
        }
        
        // Se for MC, usar o próprio ID
        if (NIVEL_ACESSO === 'mc') {
            $id_usuario_mc = $_SESSION['usuario_logado']['usuario']['id'];
        } else {
            // Admin ou super_admin devem especificar o MC
            if (empty($id_usuario_mc)) {
                echo json_encode(['success' => false, 'message' => 'MC é obrigatório']);
                exit;
            }
            
            // Verificar se o MC pertence ao tenant (exceto super_admin)
            if (NIVEL_ACESSO !== 'super_admin') {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND id_tenants = ? AND nivel = 'mc'");
                $stmt->execute([$id_usuario_mc, ID_TENANTS]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'MC inválido']);
                    exit;
                }
            }
            
            // Verificar limite de 10 eventos por MC
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM eventos WHERE id_usuario_mc = ?");
            $stmt->execute([$id_usuario_mc]);
            $total_eventos = $stmt->fetch()['total'];
            
            if ($total_eventos >= 10) {
                echo json_encode(['success' => false, 'message' => 'Limite máximo de 10 eventos por MC atingido']);
                exit;
            }
        }
        
        $resultado = criarEvento($nome, $id_usuario_mc, ID_TENANTS);
        echo json_encode($resultado);
        break;
        
    case 'listar_eventos':
        if (NIVEL_ACESSO === 'mc') {
            // MC só vê seus próprios eventos
            $id_usuario_mc = $_SESSION['usuario_logado']['usuario']['id'];
            $stmt = $pdo->prepare("
                SELECT e.*, u.nome as nome_mc, t.nome as nome_tenant
                FROM eventos e 
                JOIN usuarios u ON e.id_usuario_mc = u.id 
                JOIN tenants t ON e.id_tenants = t.id 
                WHERE e.id_usuario_mc = ?
                ORDER BY e.created_at DESC
            ");
            $stmt->execute([$id_usuario_mc]);
            $eventos = $stmt->fetchAll();
        } else {
            // Admin vê eventos do seu tenant, super_admin vê todos
            if (NIVEL_ACESSO === 'super_admin') {
                $stmt = $pdo->prepare("
                    SELECT e.*, u.nome as nome_mc, t.nome as nome_tenant
                    FROM eventos e 
                    JOIN usuarios u ON e.id_usuario_mc = u.id 
                    JOIN tenants t ON e.id_tenants = t.id 
                    ORDER BY e.created_at DESC
                ");
                $stmt->execute();
                $eventos = $stmt->fetchAll();
            } else {
                $eventos = listarEventosTenant(ID_TENANTS);
            }
        }
        
        echo json_encode(['success' => true, 'eventos' => $eventos]);
        break;
        
    case 'alterar_status':
        $id_evento = $_POST['id_evento'] ?? null;
        $novo_status = $_POST['novo_status'] ?? '';
        
        if (empty($id_evento) || empty($novo_status)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
            exit;
        }
        
        if (!in_array($novo_status, ['ativo', 'inativo'])) {
            echo json_encode(['success' => false, 'message' => 'Status inválido']);
            exit;
        }
        
        // Verificar permissões
        if (NIVEL_ACESSO === 'mc') {
            $id_usuario_mc = $_SESSION['usuario_logado']['usuario']['id'];
            $resultado = alterarStatusEvento($id_evento, $id_usuario_mc, $novo_status);
        } else {
            // Admin e super_admin podem alterar qualquer evento (com verificações de tenant)
            $stmt = $pdo->prepare("SELECT id_usuario_mc FROM eventos WHERE id = ?" . 
                (NIVEL_ACESSO !== 'super_admin' ? " AND id_tenants = ?" : ""));
            
            if (NIVEL_ACESSO !== 'super_admin') {
                $stmt->execute([$id_evento, ID_TENANTS]);
            } else {
                $stmt->execute([$id_evento]);
            }
            
            $evento = $stmt->fetch();
            if (!$evento) {
                echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
                exit;
            }
            
            $resultado = alterarStatusEvento($id_evento, $evento['id_usuario_mc'], $novo_status);
        }
        
        echo json_encode($resultado);
        break;
        
    case 'buscar_evento':
        $id_evento = $_POST['id_evento'] ?? null;
        
        if (empty($id_evento)) {
            echo json_encode(['success' => false, 'message' => 'ID do evento é obrigatório']);
            exit;
        }
        
        // Verificar permissões e buscar evento
        if (NIVEL_ACESSO === 'mc') {
            $id_usuario_mc = $_SESSION['usuario_logado']['usuario']['id'];
            $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ? AND id_usuario_mc = ?");
            $stmt->execute([$id_evento, $id_usuario_mc]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?" . 
                (NIVEL_ACESSO !== 'super_admin' ? " AND id_tenants = ?" : ""));
            
            if (NIVEL_ACESSO !== 'super_admin') {
                $stmt->execute([$id_evento, ID_TENANTS]);
            } else {
                $stmt->execute([$id_evento]);
            }
        }
        
        $evento = $stmt->fetch();
        if (!$evento) {
            echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
            exit;
        }
        
        echo json_encode(['success' => true, 'evento' => $evento]);
        break;
        
    case 'atualizar_evento':
        $id_evento = $_POST['id_evento'] ?? null;
        $nome = trim($_POST['nome'] ?? '');
        $id_usuario_mc = $_POST['id_usuario_mc'] ?? null;
        
        if (empty($id_evento) || empty($nome)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
            exit;
        }
        
        // Verificar se o evento existe e se o usuário tem permissão
        if (NIVEL_ACESSO === 'mc') {
            $id_usuario_logado = $_SESSION['usuario_logado']['usuario']['id'];
            $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ? AND id_usuario_mc = ?");
            $stmt->execute([$id_evento, $id_usuario_logado]);
            $evento = $stmt->fetch();
            
            if (!$evento) {
                echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
                exit;
            }
            
            // MC não pode alterar o MC responsável
            $id_usuario_mc = $id_usuario_logado;
        } else {
            // Admin e super_admin podem alterar
            $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?" . 
                (NIVEL_ACESSO !== 'super_admin' ? " AND id_tenants = ?" : ""));
            
            if (NIVEL_ACESSO !== 'super_admin') {
                $stmt->execute([$id_evento, ID_TENANTS]);
            } else {
                $stmt->execute([$id_evento]);
            }
            
            $evento = $stmt->fetch();
            if (!$evento) {
                echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
                exit;
            }
            
            if (empty($id_usuario_mc)) {
                echo json_encode(['success' => false, 'message' => 'MC é obrigatório']);
                exit;
            }
        }
        
        // Verificar se já existe outro evento com o mesmo nome no tenant (excluindo o evento atual)
        $stmt = $pdo->prepare("SELECT id FROM eventos WHERE nome = ? AND id_tenants = ? AND id != ?");
        $stmt->execute([$nome, $evento['id_tenants'], $id_evento]);
        $result = $stmt->fetchAll();
        
        if (count($result) > 0) {
            echo json_encode(['success' => false, 'message' => 'O nome do evento já existe!']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE eventos SET nome = ?, id_usuario_mc = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nome, $id_usuario_mc, $id_evento]);
            
            echo json_encode(['success' => true, 'message' => 'Evento atualizado com sucesso']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar evento']);
        }
        break;
        
    case 'deletar_evento':
        $id_evento = $_POST['id_evento'] ?? null;
        
        if (empty($id_evento)) {
            echo json_encode(['success' => false, 'message' => 'ID do evento é obrigatório']);
            exit;
        }
        
        // Verificar se o evento existe e se o usuário tem permissão
        if (NIVEL_ACESSO === 'mc') {
            $id_usuario_mc = $_SESSION['usuario_logado']['usuario']['id'];
            $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ? AND id_usuario_mc = ?");
            $stmt->execute([$id_evento, $id_usuario_mc]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?" . 
                (NIVEL_ACESSO !== 'super_admin' ? " AND id_tenants = ?" : ""));
            
            if (NIVEL_ACESSO !== 'super_admin') {
                $stmt->execute([$id_evento, ID_TENANTS]);
            } else {
                $stmt->execute([$id_evento]);
            }
        }
        
        $evento = $stmt->fetch();
        if (!$evento) {
            echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
            $stmt->execute([$id_evento]);
            
            echo json_encode(['success' => true, 'message' => 'Evento deletado com sucesso']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar evento']);
        }
        break;
        
    case 'obter_mcs':
        // Listar MCs disponíveis para admin e super_admin
        if (NIVEL_ACESSO === 'mc') {
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }
        
        if (NIVEL_ACESSO === 'super_admin') {
            $stmt = $pdo->prepare("SELECT u.id, u.nome, u.id_tenants, t.nome as nome_tenant FROM usuarios u JOIN tenants t ON u.id_tenants = t.id WHERE u.nivel = 'mc' AND u.status = 1 ORDER BY t.nome, u.nome");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE nivel = 'mc' AND id_tenants = ? AND status = 1 ORDER BY nome");
            $stmt->execute([ID_TENANTS]);
        }
        
        $mcs = $stmt->fetchAll();
        echo json_encode(['success' => true, 'mcs' => $mcs]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        break;
}
?>