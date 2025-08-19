<?php
require_once 'init.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_logado'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o usuário é admin ou super_admin
if (!in_array(NIVEL_ACESSO, ['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem usar esta funcionalidade.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'listar_eventos_para_admin':
        try {
            if (NIVEL_ACESSO === 'super_admin') {
                // Super admin vê todos os eventos
                $stmt = $pdo->prepare("
                    SELECT e.*, u.nome as nome_mc, t.nome as nome_tenant
                    FROM eventos e 
                    JOIN usuarios u ON e.id_usuario_mc = u.id 
                    JOIN tenants t ON e.id_tenants = t.id 
                    ORDER BY e.status DESC, e.nome ASC
                ");
                $stmt->execute();
            } else {
                // Admin vê apenas eventos do seu tenant
                // Consulta mais restritiva para garantir que apenas eventos do tenant correto sejam retornados
                $stmt = $pdo->prepare("
                    SELECT e.*, u.nome as nome_mc, t.nome as nome_tenant
                    FROM eventos e 
                    JOIN usuarios u ON e.id_usuario_mc = u.id 
                    JOIN tenants t ON e.id_tenants = t.id 
                    WHERE e.id_tenants = ? AND t.id = ? AND u.id_tenants = ?
                    ORDER BY e.status DESC, e.nome ASC
                ");
                $stmt->execute([ID_TENANTS, ID_TENANTS, ID_TENANTS]);
            }
            
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'eventos' => $eventos]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao listar eventos: ' . $e->getMessage()]);
        }
        break;
        
    case 'selecionar_evento_admin':
        $evento_id = $_POST['evento_id'] ?? null;
        
        if (empty($evento_id)) {
            echo json_encode(['success' => false, 'message' => 'ID do evento é obrigatório']);
            exit;
        }
        
        try {
            // Verificar se o evento existe e se o admin tem permissão para acessá-lo
            if (NIVEL_ACESSO === 'super_admin') {
                $stmt = $pdo->prepare("SELECT e.*, u.nome as nome_mc FROM eventos e JOIN usuarios u ON e.id_usuario_mc = u.id WHERE e.id = ?");
                $stmt->execute([$evento_id]);
            } else {
                $stmt = $pdo->prepare("SELECT e.*, u.nome as nome_mc FROM eventos e JOIN usuarios u ON e.id_usuario_mc = u.id WHERE e.id = ? AND e.id_tenants = ?");
                $stmt->execute([$evento_id, ID_TENANTS]);
            }
            
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$evento) {
                echo json_encode(['success' => false, 'message' => 'Evento não encontrado ou sem permissão']);
                exit;
            }
            
            // Armazenar o evento selecionado na sessão
            $_SESSION['admin_evento_selecionado'] = [
                'id' => $evento['id'],
                'nome' => $evento['nome'],
                'nome_mc' => $evento['nome_mc'],
                'id_usuario_mc' => $evento['id_usuario_mc'],
                'status' => $evento['status'],
                'id_tenants' => $evento['id_tenants']
            ];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Evento selecionado com sucesso',
                'evento' => $_SESSION['admin_evento_selecionado']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao selecionar evento: ' . $e->getMessage()]);
        }
        break;
        
    case 'obter_evento_selecionado':
        if (isset($_SESSION['admin_evento_selecionado'])) {
            echo json_encode([
                'success' => true,
                'evento_selecionado' => $_SESSION['admin_evento_selecionado']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'evento_selecionado' => null
            ]);
        }
        break;
        
    case 'limpar_evento_selecionado':
        unset($_SESSION['admin_evento_selecionado']);
        echo json_encode(['success' => true, 'message' => 'Seleção de evento limpa']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        break;
}
?>