<?php
include_once 'funcoes_fila.php'; // Onde suas funções como 'adicionarOuAtualizarRegraMesa' e 'getAllRegrasMesa' estão

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Garante que $pdo está definido para todas as ações que precisam de banco
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $response['message'] = 'Erro interno do servidor: Conexão com o banco de dados não disponível.';
        echo json_encode($response);
        exit;
    }

    if ($action === 'save_all_regras_mesa') {
        if (isset($_POST['regras_json'])) {
            $regrasEnviadas = json_decode($_POST['regras_json'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $response['message'] = 'Erro ao decodificar dados das regras: ' . json_last_error_msg();
                echo json_encode($response);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // Obter IDs existentes no banco (apenas se for relevante para outras validações,
                // já que a exclusão agora é feita pelo botão REMOVER)
                // $existingDbRules = getAllRegrasMesa($pdo);
                // $existingDbIds = array_column($existingDbRules, 'id');
                // $sentIds = []; // Não precisamos mais disso para a exclusão em massa aqui

                foreach ($regrasEnviadas as $regraData) {
                    $id = !empty($regraData['id']) ? (int)$regraData['id'] : null;
                    $minPessoas = (int)$regraData['min_pessoas'];
                    $maxPessoas = ($regraData['max_pessoas'] === '' || $regraData['max_pessoas'] === null) ? null : (int)$regraData['max_pessoas'];
                    $maxMusicas = (int)$regraData['max_musicas_por_rodada'];

                    if (empty($minPessoas) && $minPessoas !== 0) {
                        continue; 
                    }
                    
                    $result = adicionarOuAtualizarRegraMesa($pdo, $id, $minPessoas, $maxPessoas, $maxMusicas);

                    if ($result !== true) {
                        throw new Exception($result);
                    }
                }

                // REMOVIDA A LÓGICA DE EXCLUIR REGRAS POR array_diff AQUI,
                // POIS A EXCLUSÃO AGORA É IMEDIATA PELO BOTÃO "REMOVER".
                // O campo 'removed_ids_array' NÃO É MAIS ENVIADO com 'save_all_regras_mesa'.
                
                $pdo->commit();
                $response['success'] = true;
                $response['message'] = 'Todas as regras foram salvas/atualizadas com sucesso!';

            } catch (Exception $e) {
                $pdo->rollBack();
                $response['message'] = $e->getMessage();
                error_log("ERRO (Processar Regras - Save All): Erro na transação de regras: " . $e->getMessage());
            }

        } else {
            $response['message'] = 'Nenhum dado de regras recebido para salvar.';
        }
    } elseif ($action === 'delete_regra_mesa') { // ** NOVA AÇÃO PARA EXCLUSÃO IMEDIATA **
        if (isset($_POST['id']) && is_numeric($_POST['id'])) {
            $idToDelete = (int)$_POST['id'];

            try {
                $stmtDelete = $pdo->prepare("DELETE FROM configuracao_regras_mesa WHERE id = :id");
                $stmtDelete->bindParam(':id', $idToDelete, PDO::PARAM_INT);
                
                if ($stmtDelete->execute()) {
                    if ($stmtDelete->rowCount() > 0) { // Verifica se alguma linha foi realmente deletada
                        $response['success'] = true;
                        $response['message'] = 'Regra removida do banco de dados com sucesso!';
                        error_log("DEBUG (Processar Regras - Delete): Regra ID {$idToDelete} removida com sucesso.");
                    } else {
                        $response['message'] = 'Regra não encontrada no banco de dados.';
                        error_log("DEBUG (Processar Regras - Delete): Tentativa de remover ID {$idToDelete}, mas não foi encontrada.");
                    }
                } else {
                    $errorInfo = $stmtDelete->errorInfo();
                    throw new Exception("Falha na execução do DELETE: " . $errorInfo[2]);
                }
            } catch (Exception $e) {
                $response['message'] = 'Erro ao remover regra do banco de dados: ' . $e->getMessage();
                error_log("ERRO (Processar Regras - Delete): Exceção ao remover regra ID {$idToDelete}: " . $e->getMessage());
            }
        } else {
            $response['message'] = 'ID da regra para remoção inválido.';
        }

    } elseif ($action === 'set_regras_padrao') {
        if (function_exists('setRegrasPadrao') && setRegrasPadrao($pdo)) {
            $response['success'] = true;
            $response['message'] = 'Regras padrão aplicadas com sucesso!';
        } else {
            $response['message'] = 'Erro ao aplicar regras padrão. Função setRegrasPadrao não encontrada ou falhou.';
            error_log("ERRO (Processar Regras): setRegrasPadrao não existe ou retornou false.");
        }
    } else {
        $response['message'] = 'Ação inválida.';
    }
} else {
    $response['message'] = 'Requisição inválida.';
}

echo json_encode($response);
