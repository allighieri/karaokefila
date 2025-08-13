<?php
// api_add_repertorio.php
require_once 'init.php';
require_once 'funcoes_repertorio.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ação inválida.'];

// Verificar se o usuário tem permissão
if (!check_access(NIVEL_ACESSO, ['mc', 'admin'])) {
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ler dados JSON do corpo da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        $response['message'] = 'Dados inválidos.';
        echo json_encode($response);
        exit;
    }
    
    switch ($input['action']) {
        case 'import_repertorio':
            if (!isset($input['musicas']) || !is_array($input['musicas'])) {
                $response['message'] = 'Dados de músicas inválidos.';
                break;
            }
            
            $musicas = $input['musicas'];
            $idTenants = ID_TENANTS;
            
            if (empty($musicas)) {
                $response['message'] = 'Nenhuma música para importar.';
                break;
            }
            
            $response = importarRepertorio($pdo, $musicas, $idTenants);
            break;
            
        case 'get_estatisticas_repertorio':
            $response = [
                'success' => true,
                'estatisticas' => obterEstatisticasRepertorio($pdo, ID_TENANTS)
            ];
            break;
            
        default:
            $response['message'] = 'Ação não reconhecida.';
            break;
    }
} else {
    $response['message'] = 'Método não permitido.';
}

echo json_encode($response);
?>