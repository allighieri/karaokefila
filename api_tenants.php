<?php
// api_tenants.php
require_once 'init.php';
require_once 'funcoes_tenants.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ação inválida.'];

// Ação para buscar todos os tenants (via GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_all_tenants') {
    $tenants = getAllTenants($pdo);
    if ($tenants !== null) {
        $response = ['success' => true, 'tenants' => $tenants];
    } else {
        $response['message'] = 'Nenhum estabelecimento encontrado.';
    }
}

// Ações para adicionar, editar ou excluir (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_tenant':
            $dados = [
                'nome' => $_POST['nome'] ?? '',
                'telefone' => $_POST['telefone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'endereco' => $_POST['endereco'] ?? '',
                'cidade' => $_POST['cidade'] ?? '',
                'uf' => $_POST['uf'] ?? ''
            ];
            $response = addTenant($pdo, $dados);
            break;

        case 'edit_tenant':
            $dados = [
                'nome' => $_POST['nome'] ?? '',
                'telefone' => $_POST['telefone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'endereco' => $_POST['endereco'] ?? '',
                'cidade' => $_POST['cidade'] ?? '',
                'uf' => $_POST['uf'] ?? ''
            ];
            $response = editTenant($pdo, $_POST['id'] ?? 0, $dados);
            break;

        case 'delete_tenant':
            $response = deleteTenant($pdo, $_POST['id'] ?? 0);
            break;
    }
}

echo json_encode($response);