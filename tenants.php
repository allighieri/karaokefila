<?php
require_once 'init.php';
require_once 'funcoes_tenants.php'; // Incluímos o novo arquivo de funções

// Apenas usuários 'admin' e 'mc' podem acessar esta página
if (!check_access(NIVEL_ACESSO, ['super_admin'])) {
    header("Location: " . $rootPath . "login");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Karaokê - Estabelecimentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="css/style_index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php include_once 'inc/nav.php'; ?>

<div class="container">
    <div id="alertContainer" class="mt-3"></div>

    <h3>Adicionar Estabelecimentos</h3>
    <form method="POST" id="addTenants">
        <div class="col-md-6">
            <input type="hidden" name="action" value="add_tenant">
            <div class="mb-3 row">
                <label for="nome" class="col-sm-2 col-form-label">Nome</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
            </div>
            <div class="mb-3 row">
                <label for="telefone" class="col-sm-2 col-form-label">Telefone</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control" id="telefone" name="telefone" required>
                </div>
            </div>
            <div class="mb-3 row">
                <label for="email" class="col-sm-2 col-form-label">E-mail</label>
                <div class="col-sm-10">
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
            <div class="mb-3 row">
                <label for="endereco" class="col-sm-2 col-form-label">Endereço</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control" id="endereco" name="endereco" required>
                </div>
            </div>
            <div class="row">
                <label for="cidade" class="col-sm-2 col-form-label">Cidade</label>
                <div class="col">
                    <input type="text" class="form-control" id="cidade" name="cidade" required>
                </div>
                <label for="uf" class="col-sm-1 col-form-label">UF</label>
                <div class="col col-md-2 col-sm-2">
                    <input type="text" class="form-control" id="uf" name="uf" required maxlength="2">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-sm-10">
                    <button class="btn btn-primary" id="btn_cadastrar" type="submit">Cadastrar</button>
                </div>
            </div>
        </div>
    </form>

    <hr class="my-4">
    <h3>Estabelecimentos Cadastrados</h3>
    <div id="tenantsListContainer"></div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Tem certeza que deseja excluir o estabelecimento "<strong id="tenantNomeExcluir"></strong>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarExclusao">Excluir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editTenantModal" tabindex="-1" aria-labelledby="editTenantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTenantModalLabel">Editar Estabelecimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditTenant">
                    <input type="hidden" id="editTenantId" name="id">
                    <div class="mb-3 row">
                        <label for="editNome" class="col-sm-2 col-form-label">Nome</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="editNome" name="nome" required>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="editTelefone" class="col-sm-2 col-form-label">Telefone</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="editTelefone" name="telefone" required>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="editEmail" class="col-sm-2 col-form-label">E-mail</label>
                        <div class="col-sm-10">
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="editEndereco" class="col-sm-2 col-form-label">Endereço</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="editEndereco" name="endereco" required>
                        </div>
                    </div>
                    <div class="row">
                        <label for="editCidade" class="col-sm-2 col-form-label">Cidade</label>
                        <div class="col">
                            <input type="text" class="form-control" id="editCidade" name="cidade" required>
                        </div>
                        <label for="editUf" class="col-sm-1 col-form-label">UF</label>
                        <div class="col col-md-2 col-sm-2">
                            <input type="text" class="form-control" id="editUf" name="uf" required maxlength="2">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarEditTenant">Salvar Alterações</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://localhost/fila/js/resetar_sistema.js"></script>

<script>
    $(document).ready(function() {

        window.showAlert = function(message, type) {
            var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                '<span>' + message + '</span>' +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>';
            $('#alertContainer').html(alertHtml);
            setTimeout(function() {
                $('#alertContainer .alert').alert('close');
            }, 5000);
        }

        window.htmlspecialchars = function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text == null ? '' : String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Função para recarregar a lista de tenants via AJAX
        window.refreshTenantsList = function() {
            $.ajax({
                url: 'api_tenants.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_all_tenants' },
                success: function(response) {
                    if (response.success) {
                        var tableHtml = '';
                        if (response.tenants.length > 0) {
                            tableHtml += '<div class="table-responsive-sm">' +
                                '<table class="table table-striped table-hover table-sm">' +
                                '<thead><tr>' +
                                '<th scope="col">Nome</th>' +
                                '<th scope="col">Telefone</th>' +
                                '<th scope="col">Ações</th>' +
                                '</tr></thead><tbody>';
                            $.each(response.tenants, function(index, tenant) {
                                tableHtml += '<tr>' +
                                    '<td>' + htmlspecialchars(tenant.nome) + '</td>' +
                                    '<td>' + htmlspecialchars(tenant.telefone) + '</td>' +
                                    '<td>' +
                                    '<div class="d-flex flex-nowrap gap-1">' +
                                    '<button class="btn btn-sm btn-warning edit-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" data-nome="' + htmlspecialchars(tenant.nome) + '" data-telefone="' + htmlspecialchars(tenant.telefone) + '" data-email="' + htmlspecialchars(tenant.email) + '" data-endereco="' + htmlspecialchars(tenant.endereco) + '" data-cidade="' + htmlspecialchars(tenant.cidade) + '" data-uf="' + htmlspecialchars(tenant.uf) + '" title="Editar Estabelecimento">' +
                                    '<i class="bi bi-pencil-square"></i></button> ' +
                                    '<button class="btn btn-sm btn-danger delete-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" data-nome="' + htmlspecialchars(tenant.nome) + '" title="Excluir Estabelecimento">' +
                                    '<i class="bi bi-trash-fill"></i></button>' +
                                    '</div></td></tr>';
                            });
                            tableHtml += '</tbody></table></div>';
                        } else {
                            tableHtml = '<p>Nenhum estabelecimento cadastrado ainda. Adicione um novo acima.</p>';
                        }
                        $('#tenantsListContainer').html(tableHtml);
                    } else {
                        showAlert('Erro ao recarregar a lista de estabelecimentos: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para recarregar tenants:", status, error);
                    showAlert('Erro na comunicação com o servidor ao recarregar estabelecimentos.', 'danger');
                }
            });
        }

        // Evento de envio do formulário de adicionar tenant
        $('#addTenants').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            $.ajax({
                url: 'api_tenants.php',
                type: 'POST',
                dataType: 'json',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        form[0].reset();
                        refreshTenantsList();
                    } else {
                        showAlert('Erro: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX:", status, error);
                    showAlert('Erro na comunicação com o servidor ao adicionar estabelecimento.', 'danger');
                }
            });
        });

        // Evento para abrir o modal de edição
        $('#tenantsListContainer').on('click', '.edit-tenant-btn', function() {
            var tenant = $(this).data();
            $('#editTenantId').val(tenant.id);
            $('#editNome').val(tenant.nome);
            $('#editTelefone').val(tenant.telefone);
            $('#editEmail').val(tenant.email);
            $('#editEndereco').val(tenant.endereco);
            $('#editCidade').val(tenant.cidade);
            $('#editUf').val(tenant.uf);
            var editModal = new bootstrap.Modal(document.getElementById('editTenantModal'));
            editModal.show();
        });

        // Evento para salvar a edição do tenant
        $('#btnSalvarEditTenant').on('click', function() {
            var formData = $('#formEditTenant').serialize() + '&action=edit_tenant';
            var editModalInstance = bootstrap.Modal.getInstance(document.getElementById('editTenantModal'));
            $.ajax({
                url: 'api_tenants.php',
                type: 'POST',
                dataType: 'json',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        editModalInstance.hide();
                        refreshTenantsList();
                    } else {
                        showAlert('Erro ao salvar: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para editar tenant:", status, error);
                    showAlert('Erro na comunicação com o servidor ao editar estabelecimento.', 'danger');
                }
            });
        });

        // Evento para abrir o modal de exclusão
        $('#tenantsListContainer').on('click', '.delete-tenant-btn', function() {
            var id = $(this).data('id');
            var nome = $(this).data('nome');
            $('#tenantNomeExcluir').text(nome);
            $('#btnConfirmarExclusao').data('tenant-id', id);
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
        });

        // Evento para confirmar a exclusão do tenant
        $('#btnConfirmarExclusao').on('click', function() {
            var tenantId = $(this).data('tenant-id');
            var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
            $.ajax({
                url: 'api_tenants.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'delete_tenant', id: tenantId },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        confirmModal.hide();
                        refreshTenantsList();
                    } else {
                        showAlert(response.message, 'danger');
                        confirmModal.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para excluir tenant:", status, error);
                    showAlert('Erro na comunicação com o servidor ao excluir estabelecimento.', 'danger');
                }
            });
        });

        // Carrega a lista de tenants na inicialização da página
        refreshTenantsList();
    });
</script>
</body>
</html>