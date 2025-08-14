<?php
require_once 'init.php';
require_once 'funcoes_usuarios.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

// Verificar se o usuário tem permissão (admin ou super_admin)
if (!check_access(NIVEL_ACESSO, ['admin', 'super_admin'])) {
    header('Location: index.php');
    exit;
}

$is_super_admin = NIVEL_ACESSO === 'super_admin';
$tenants = $is_super_admin ? obterTenants($pdo) : [];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Karaokê Fila</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style_index.css" rel="stylesheet">
</head>
<body>
    <?php 
    $current_page = pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME);
    include_once 'inc/nav.php'; 
    ?>
<div class="container">    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h3>Gerenciar Usuários</h3>
                
                <?php if ($is_super_admin): ?>
                <!-- Filtro por Tenant (apenas para super_admin) -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="filtroTenant" class="form-label">Filtrar por Estabelecimento:</label>
                        <select class="form-select" id="filtroTenant">
                            <option value="">Todos os estabelecimentos</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                
                <h4 class="mt-4">Usuários Ativos</h4>
                <p class="text-danger"><i class="bi bi-info-circle-fill"></i> Clique no nome para visualizar detalhes.</p>
                
                <div id="usuariosAtivosContainer"></div>
                
                <h4 class="mt-4">Usuários Inativos</h4>
                <div id="usuariosInativosContainer"></div>
            </div>
        </div>
    </div>
</div>    
    <!-- Modal Visualizar Usuário -->
    <div class="modal fade" id="modalVisualizarUsuario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye"></i> Visualizar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nome:</strong> <span id="visualizar-nome"></span></p>
                            <p><strong>Telefone:</strong> <span id="visualizar-telefone"></span></p>
                            <p><strong>Cidade:</strong> <span id="visualizar-cidade"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>UF:</strong> <span id="visualizar-uf"></span></p>
                            <p><strong>Estabelecimento:</strong> <span id="visualizar-tenant"></span></p>
                            <p><strong>Nível:</strong> <span id="visualizar-nivel"></span></p>
                            <p><strong>Status:</strong> <span id="visualizar-status"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Alterar Nível -->
    <div class="modal fade" id="modalAlterarNivel" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-check"></i> Alterar Nível de Acesso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formAlterarNivel">
                    <div class="modal-body">
                        <input type="hidden" id="alterar-nivel-id" name="id">
                        <p>Usuário: <strong id="alterar-nivel-nome"></strong></p>
                        <div class="mb-3">
                            <label for="novoNivel" class="form-label">Novo Nível:</label>
                            <select class="form-select" id="novoNivel" name="nivel" required>
                                <option value="mc">MC</option>
                                <option value="user">Usuário</option>
                                <option value="admin">Administrador</option>
                                <?php if ($is_super_admin): ?>
                                <option value="super_admin">Super Administrador</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Alterar Nível</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Incluir Modal de Edição -->
    <?php include 'modal_editar_usuario.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/gerenciar_usuarios.js"></script>
    
    <script>
        // Configurações globais
        window.isSuperAdmin = <?= $is_super_admin ? 'true' : 'false' ?>;
        
        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            carregarUsuarios();
            
            <?php if ($is_super_admin): ?>
            // Event listener para filtro de tenant
            document.getElementById('filtroTenant').addEventListener('change', function() {
                carregarUsuarios();
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>