<?php
header('Content-Type: application/json');
require_once 'init.php';
require_once 'funcoes_usuarios.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Verificar se o usuário tem permissão (admin ou super_admin)
if (!check_access(NIVEL_ACESSO, ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$id_tenants_usuario = NIVEL_ACESSO === 'super_admin' ? null : ID_TENANTS;

try {
    switch ($acao) {
        case 'listar_usuarios_ativos':
            $tenant_filtro = null;
            
            // Se for super_admin e especificou um tenant, filtrar por ele
            if (NIVEL_ACESSO === 'super_admin' && isset($_GET['id_tenants']) && !empty($_GET['id_tenants'])) {
                $tenant_filtro = (int)$_GET['id_tenants'];
            } elseif (NIVEL_ACESSO === 'admin') {
                // Admin só vê usuários do seu tenant
                $tenant_filtro = ID_TENANTS;
            }
            
            $usuarios = obterUsuariosAtivos($pdo, $tenant_filtro, NIVEL_ACESSO);
            echo json_encode(['success' => true, 'usuarios' => $usuarios]);
            break;
            
        case 'listar_usuarios_inativos':
            $tenant_filtro = null;
            
            // Se for super_admin e especificou um tenant, filtrar por ele
            if (NIVEL_ACESSO === 'super_admin' && isset($_GET['id_tenants']) && !empty($_GET['id_tenants'])) {
                $tenant_filtro = (int)$_GET['id_tenants'];
            } elseif (NIVEL_ACESSO === 'admin') {
                // Admin só vê usuários do seu tenant
                $tenant_filtro = ID_TENANTS;
            }
            
            $usuarios = obterUsuariosInativos($pdo, $tenant_filtro, NIVEL_ACESSO);
            echo json_encode(['success' => true, 'usuarios' => $usuarios]);
            break;
            
        case 'listar_tenants':
            // Apenas super_admin pode listar tenants
            if (NIVEL_ACESSO !== 'super_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
                exit;
            }
            
            $tenants = obterTenants($pdo);
            echo json_encode(['success' => true, 'tenants' => $tenants]);
            break;
            
        case 'obter_usuario':
            $id_usuario = (int)($_GET['id'] ?? 0);
            
            if ($id_usuario <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID do usuário inválido.']);
                break;
            }
            
            $usuario = obterUsuario($pdo, $id_usuario, $id_tenants_usuario);
            
            if ($usuario) {
                echo json_encode(['success' => true, 'usuario' => $usuario]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            }
            break;
            
        case 'editar_usuario':
            $id_usuario = (int)($_POST['id'] ?? 0);
            
            if ($id_usuario <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID do usuário inválido.']);
                break;
            }
            
            $dados = [
                'nome' => trim($_POST['nome'] ?? ''),
                'telefone' => trim($_POST['telefone'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'cidade' => trim($_POST['cidade'] ?? ''),
                'uf' => trim($_POST['uf'] ?? ''),
            ];
            
            // Validar campos obrigatórios
            if (empty($dados['nome'])) {
                echo json_encode(['success' => false, 'message' => 'Nome é obrigatório.']);
                break;
            }
            
            // Se foi informada uma nova senha
            if (!empty($_POST['password'])) {
                $dados['password'] = $_POST['password'];
            }
            
            $resultado = atualizarUsuario($pdo, $id_usuario, $dados, $id_tenants_usuario);
            echo json_encode($resultado);
            break;
            
        case 'desativar_usuario':
            $id_usuario = (int)($_POST['id'] ?? 0);
            
            if ($id_usuario <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID do usuário inválido.']);
                break;
            }
            
            // Não permitir desativar a si mesmo
            if ($id_usuario == $_SESSION['usuario_logado']) {
                echo json_encode(['success' => false, 'message' => 'Você não pode desativar sua própria conta.']);
                break;
            }
            
            $resultado = desativarUsuario($pdo, $id_usuario, $id_tenants_usuario);
            echo json_encode($resultado);
            break;
            
        case 'reativar_usuario':
            $id_usuario = (int)($_POST['id'] ?? 0);
            
            if ($id_usuario <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID do usuário inválido.']);
                break;
            }
            
            $resultado = reativarUsuario($pdo, $id_usuario, $id_tenants_usuario);
            echo json_encode($resultado);
            break;
            
        case 'excluir_usuario':
            $id_usuario = (int)($_POST['id'] ?? 0);
            
            if ($id_usuario <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID do usuário inválido.']);
                break;
            }
            
            // Não permitir excluir a si mesmo
            if ($id_usuario == $_SESSION['usuario_logado']) {
                echo json_encode(['success' => false, 'message' => 'Você não pode excluir sua própria conta.']);
                break;
            }
            
            $resultado = excluirUsuario($pdo, $id_usuario, $id_tenants_usuario);
            echo json_encode($resultado);
            break;
            
        case 'alterar_nivel':
            $id_usuario = (int)($_POST['id'] ?? 0);
            $novo_nivel = trim($_POST['nivel'] ?? '');
            
            if ($id_usuario <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID do usuário inválido.']);
                break;
            }
            
            if (empty($novo_nivel)) {
                echo json_encode(['success' => false, 'message' => 'Nível é obrigatório.']);
                break;
            }
            
            // Não permitir alterar o próprio nível
            if ($id_usuario == $_SESSION['usuario_logado']) {
                echo json_encode(['success' => false, 'message' => 'Você não pode alterar seu próprio nível de acesso.']);
                break;
            }
            
            // Admin não pode criar super_admin
            if (NIVEL_ACESSO === 'admin' && $novo_nivel === 'super_admin') {
                echo json_encode(['success' => false, 'message' => 'Você não tem permissão para criar usuários super_admin.']);
                break;
            }
            
            $resultado = alterarNivelUsuario($pdo, $id_usuario, $novo_nivel, $id_tenants_usuario);
            echo json_encode($resultado);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida.']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro na API de usuários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>