<?php
require_once 'init.php';
require_once 'funcoes_cantores_novo.php';

header('Content-Type: application/json');

if (!check_access(NIVEL_ACESSO, ['admin', 'mc'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_cantor':
        $id_usuario = intval($_POST['id_usuario_cantor'] ?? 0);
        $id_mesa = intval($_POST['id_mesa_cantor'] ?? 0);
        
        if ($id_usuario <= 0 || $id_mesa <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            break;
        }
        
        $resultado = adicionarCantorPorUsuario($pdo, $id_usuario, $id_mesa);
        echo json_encode($resultado);
        break;
        
    case 'remove_cantor':
        $id_cantor = intval($_POST['id_cantor'] ?? 0);
        
        if ($id_cantor <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID do cantor inválido']);
            break;
        }
        
        $resultado = removerCantorPorId($pdo, $id_cantor);
        echo json_encode($resultado);
        break;
        
    case 'get_cantores':
        $cantores = getAllCantoresComUsuario($pdo);
        echo json_encode(['success' => true, 'cantores' => $cantores]);
        break;
        
    case 'get_usuarios_disponiveis':
        $usuarios = obterUsuariosDisponiveis($pdo);
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
        break;
        
    case 'get_cantores_mesa':
        $id_mesa = intval($_POST['id_mesa'] ?? 0);
        
        if ($id_mesa <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID da mesa inválido']);
            break;
        }
        
        $cantores = getCantoresDaMesa($pdo, $id_mesa);
        echo json_encode(['success' => true, 'cantores' => $cantores]);
        break;
        
    case 'edit_cantor':
        $id_cantor = intval($_POST['cantor_id'] ?? 0);
        $novo_id_usuario = intval($_POST['novo_id_usuario'] ?? 0);
        $nova_id_mesa = intval($_POST['nova_mesa_id'] ?? 0);
        
        if ($id_cantor <= 0 || $novo_id_usuario <= 0 || $nova_id_mesa <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos para edição']);
            break;
        }
        
        $resultado = editarCantor($pdo, $id_cantor, $novo_id_usuario, $nova_id_mesa);
        echo json_encode($resultado);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
        break;
}
?>