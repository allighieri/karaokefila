<?php
// api_tenants.php
require_once 'init.php';
require_once 'funcoes_tenants.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ação inválida.'];

if (isset($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'get_all_tenants':
            $tenants = getAllTenants($pdo);
            if ($tenants !== null) {
                $response = ['success' => true, 'tenants' => $tenants];
            } else {
                $response['message'] = 'Nenhum estabelecimento encontrado.';
            }
            break;

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

        // AÇÃO INATIVAR: Usa a nova função inactivateTenant (antiga função deleteTenant)
        case 'inactivate_tenant':
            $response = inactivateTenant($pdo, $_POST['id'] ?? 0);
            break;

        // AÇÃO EXCLUIR: Usa a função deleteTenant atualizada para exclusão permanente
        case 'delete_tenant':
            $response = deleteTenant($pdo, $_POST['id'] ?? 0);
            break;

        case 'get_inactive_tenants':
            $tenants = getInactiveTenants($pdo);
            if ($tenants !== null) {
                $response = ['success' => true, 'tenants' => $tenants];
            } else {
                $response['message'] = 'Nenhum estabelecimento inativo encontrado.';
            }
            break;

        case 'reactivate_tenant':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $response = reactivateTenant($pdo, $id);
            } else {
                $response['message'] = 'ID de estabelecimento inválido.';
            }
            break;

        case 'add_tenant_code':
            $tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
            $code = trim($_POST['code'] ?? '');
            if ($tenantId && !empty($code)) {
                $response = addTenantCode($pdo, $tenantId, $code);
            } else {
                $response['message'] = 'ID do estabelecimento ou código inválido.';
            }
            break;

        case 'update_tenant_code':
            $tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);
            $code = trim($_POST['code'] ?? '');
            $status = trim($_POST['status'] ?? 'active');
            if ($tenantId && !empty($code)) {
                $response = updateTenantCode($pdo, $tenantId, $code, $status);
            } else {
                $response['message'] = 'ID do estabelecimento ou código inválido.';
            }
            break;

        case 'get_current_tenant_code':
            // Buscar o código do tenant atual (baseado na sessão)
            if (defined('ID_TENANTS') && ID_TENANTS) {
                $response = getCurrentTenantCode($pdo, ID_TENANTS);
            } else {
                $response['message'] = 'Sessão inválida. Faça login novamente.';
            }
            break;

        case 'update_current_tenant_code':
            // Atualizar o código do tenant atual (baseado na sessão)
            if (defined('ID_TENANTS') && ID_TENANTS) {
                $code = trim($_POST['code'] ?? '');
                $status = trim($_POST['status'] ?? 'active');
                if (!empty($code)) {
                    $response = updateTenantCode($pdo, ID_TENANTS, $code, $status);
                } else {
                    $response['message'] = 'Código inválido.';
                }
            } else {
                $response['message'] = 'Sessão inválida. Faça login novamente.';
            }
            break;
    }
}

echo json_encode($response);