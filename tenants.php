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

    <h4 class="mt-4">Estabelecimentos Cadastrados</h4>
    <div id="tenantsListContainer"></div>

    <h4 class="mt-4">Estabelecimentos Inativos</h4>
    <div id="inactiveTenantsListContainer"></div>
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

<div class="modal fade" id="viewTenantModal" tabindex="-1" aria-labelledby="viewTenantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTenantModalLabel">Detalhes do Estabelecimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Nome:</strong> <span id="viewNome"></span></p>
                <p><strong>Telefone:</strong> <span id="viewTelefone"></span></p>
                <p><strong>E-mail:</strong> <span id="viewEmail"></span></p>
                <p><strong>Endereço:</strong> <span id="viewEndereco"></span></p>
                <p><strong>Cidade/UF:</strong> <span id="viewCidadeUf"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="/fila/js/resetar_sistema.js"></script>

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

        // Função para recarregar a lista de tenants ATIVOS
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
                                '<th scope="col" style="width: 1%;">Ações</th>' + // ALTERADO AQUI
                                '</tr></thead><tbody>';
                            $.each(response.tenants, function(index, tenant) {
                                // Limpa o número de telefone para o formato do WhatsApp
                                var whatsappNumber = htmlspecialchars(tenant.telefone).replace(/\D/g, '');

                                tableHtml += '<tr>' +
                                    '<td>' + htmlspecialchars(tenant.nome) + '</td>' +
                                    '<td>' +
                                    '<a class="text-decoration-none" href="https://wa.me/' + whatsappNumber + '" target="_blank" title="Enviar mensagem via WhatsApp">' +
                                    htmlspecialchars(tenant.telefone) + ' <i class="bi bi-whatsapp text-success"></i>' +
                                    '</a>' +
                                    '</td>' +
                                    '<td>' +
                                    '<div class="d-flex flex-nowrap gap-1">' +
                                    '<button class="btn btn-sm btn-info view-tenant-btn" ' +
                                    'data-id="' + htmlspecialchars(tenant.id) + '" ' +
                                    'data-nome="' + htmlspecialchars(tenant.nome) + '" ' +
                                    'data-telefone="' + htmlspecialchars(tenant.telefone) + '" ' +
                                    'data-email="' + htmlspecialchars(tenant.email) + '" ' +
                                    'data-endereco="' + htmlspecialchars(tenant.endereco) + '" ' +
                                    'data-cidade="' + htmlspecialchars(tenant.cidade) + '" ' +
                                    'data-uf="' + htmlspecialchars(tenant.uf) + '" ' +
                                    'title="Visualizar Detalhes">' +
                                    '<i class="bi bi-eye"></i></button> ' +
                                    '<button class="btn btn-sm btn-warning edit-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" data-nome="' + htmlspecialchars(tenant.nome) + '" data-telefone="' + htmlspecialchars(tenant.telefone) + '" data-email="' + htmlspecialchars(tenant.email) + '" data-endereco="' + htmlspecialchars(tenant.endereco) + '" data-cidade="' + htmlspecialchars(tenant.cidade) + '" data-uf="' + htmlspecialchars(tenant.uf) + '" title="Editar Estabelecimento">' +
                                    '<i class="bi bi-pencil-square"></i></button> ' +
                                    '<button class="btn btn-sm btn-secondary inactivate-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" data-nome="' + htmlspecialchars(tenant.nome) + '" title="Inativar Estabelecimento">' +
                                    '<i class="bi bi-box-arrow-down"></i></button>' +
                                    '<button class="btn btn-sm btn-danger delete-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" data-nome="' + htmlspecialchars(tenant.nome) + '" title="Excluir Permanentemente">' +
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

        // Função para recarregar a lista de tenants INATIVOS
        window.refreshInactiveTenantsList = function() {
            $.ajax({
                url: 'api_tenants.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_inactive_tenants' },
                success: function(response) {
                    if (response.success) {
                        var tableHtml = '';
                        if (response.tenants.length > 0) {
                            tableHtml += '<div class="table-responsive-sm">' +
                                '<table class="table table-striped table-hover table-sm">' +
                                '<thead><tr>' +
                                '<th scope="col">Nome</th>' +
                                '<th scope="col">Telefone</th>' +
                                '<th scope="col" style="width: 1%;">Ações</th>' + // ALTERADO AQUI
                                '</tr></thead><tbody>';
                            $.each(response.tenants, function(index, tenant) {
                                // Limpa o número de telefone para o formato do WhatsApp
                                var whatsappNumber = htmlspecialchars(tenant.telefone).replace(/\D/g, '');

                                tableHtml += '<tr>' +
                                    '<td>' + htmlspecialchars(tenant.nome) + '</td>' +
                                    '<td>' +
                                    '<a class="text-decoration-none" href="https://wa.me/' + whatsappNumber + '" target="_blank" title="Enviar mensagem via WhatsApp">' +
                                    htmlspecialchars(tenant.telefone) + ' <i class="bi bi-whatsapp text-success"></i>' +
                                    '</a>' +
                                    '</td>' +
                                    '<td>' +
                                    '<div class="d-flex flex-nowrap gap-1">' +
                                    '<button class="btn btn-sm btn-info view-tenant-btn" ' +
                                    'data-id="' + htmlspecialchars(tenant.id) + '" ' +
                                    'data-nome="' + htmlspecialchars(tenant.nome) + '" ' +
                                    'data-telefone="' + htmlspecialchars(tenant.telefone) + '" ' +
                                    'data-email="' + htmlspecialchars(tenant.email) + '" ' +
                                    'data-endereco="' + htmlspecialchars(tenant.endereco) + '" ' +
                                    'data-cidade="' + htmlspecialchars(tenant.cidade) + '" ' +
                                    'data-uf="' + htmlspecialchars(tenant.uf) + '" ' +
                                    'title="Visualizar Detalhes">' +
                                    '<i class="bi bi-eye"></i></button>' +
                                    '<button class="btn btn-sm btn-warning edit-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" data-nome="' + htmlspecialchars(tenant.nome) + '" data-telefone="' + htmlspecialchars(tenant.telefone) + '" data-email="' + htmlspecialchars(tenant.email) + '" data-endereco="' + htmlspecialchars(tenant.endereco) + '" data-cidade="' + htmlspecialchars(tenant.cidade) + '" data-uf="' + htmlspecialchars(tenant.uf) + '" title="Editar Estabelecimento">' +
                                    '<i class="bi bi-pencil-square"></i></button>' +
                                    '<button class="btn btn-sm btn-success reactivate-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" title="Reativar Estabelecimento">' +
                                    '<i class="bi bi-box-arrow-up"></i></button>' +
                                    '<button class="btn btn-sm btn-danger delete-inactive-tenant-btn" data-id="' + htmlspecialchars(tenant.id) + '" data-nome="' + htmlspecialchars(tenant.nome) + '" title="Excluir Permanentemente">' +
                                    '<i class="bi bi-trash-fill"></i></button>' +
                                    '</div></td></tr>';
                            });
                            tableHtml += '</tbody></table></div>';
                        } else {
                            tableHtml = '<p>Nenhum estabelecimento inativo encontrado.</p>';
                        }
                        $('#inactiveTenantsListContainer').html(tableHtml);
                    } else {
                        showAlert('Erro ao recarregar a lista de estabelecimentos inativos: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para recarregar tenants inativos:", status, error);
                    showAlert('Erro na comunicação com o servidor ao recarregar estabelecimentos inativos.', 'danger');
                }
            });
        }

        // Os manipuladores de evento para os botões de visualização e edição foram movidos para "document"
        // para que funcionem em ambas as tabelas (ativas e inativas)
        $(document).on('click', '.view-tenant-btn', function() {
            var tenant = $(this).data();
            $('#viewNome').text(tenant.nome);
            $('#viewTelefone').text(tenant.telefone);
            $('#viewEmail').text(tenant.email);
            $('#viewEndereco').text(tenant.endereco);
            $('#viewCidadeUf').text(tenant.cidade + '/' + tenant.uf);

            var viewModal = new bootstrap.Modal(document.getElementById('viewTenantModal'));
            viewModal.show();
        });

        $(document).on('click', '.edit-tenant-btn', function() {
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
                        refreshInactiveTenantsList();
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

        // Evento para inativar o tenant (sem modal de confirmação)
        $('#tenantsListContainer').on('click', '.inactivate-tenant-btn', function() {
            var tenantId = $(this).data('id');
            var tenantNome = $(this).data('nome');
            $.ajax({
                url: 'api_tenants.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'inactivate_tenant', id: tenantId },
                success: function(response) {
                    if (response.success) {
                        showAlert('Estabelecimento "' + tenantNome + '" inativado com sucesso.', 'success');
                        refreshTenantsList();
                        refreshInactiveTenantsList();
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para inativar tenant:", status, error);
                    showAlert('Erro na comunicação com o servidor ao inativar estabelecimento.', 'danger');
                }
            });
        });

        // Evento para abrir o modal de exclusão (agora para exclusão permanente, ativos)
        $('#tenantsListContainer').on('click', '.delete-tenant-btn', function() {
            var id = $(this).data('id');
            var nome = $(this).data('nome');
            $('#tenantNomeExcluir').text(nome);
            $('#btnConfirmarExclusao').data('tenant-id', id);
            // Adiciona um atributo para saber de qual lista a exclusão veio
            $('#btnConfirmarExclusao').data('list-type', 'active');
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
        });

        // Evento para abrir o modal de exclusão (agora para exclusão permanente, inativos)
        $('#inactiveTenantsListContainer').on('click', '.delete-inactive-tenant-btn', function() {
            var id = $(this).data('id');
            var nome = $(this).data('nome');
            $('#tenantNomeExcluir').text(nome);
            $('#btnConfirmarExclusao').data('tenant-id', id);
            // Adiciona um atributo para saber de qual lista a exclusão veio
            $('#btnConfirmarExclusao').data('list-type', 'inactive');
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
        });

        // Evento para confirmar a exclusão PERMANENTE do tenant
        $('#btnConfirmarExclusao').on('click', function() {
            var tenantId = $(this).data('tenant-id');
            var listType = $(this).data('list-type');
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
                        if(listType === 'active') {
                            refreshTenantsList();
                        } else {
                            refreshInactiveTenantsList();
                        }
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

        // Evento para reativar o tenant
        $('#inactiveTenantsListContainer').on('click', '.reactivate-tenant-btn', function() {
            var tenantId = $(this).data('id');
            $.ajax({
                url: 'api_tenants.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'reactivate_tenant', id: tenantId },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        refreshTenantsList();
                        refreshInactiveTenantsList();
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para reativar tenant:", status, error);
                    showAlert('Erro na comunicação com o servidor ao reativar estabelecimento.', 'danger');
                }
            });
        });

        refreshTenantsList();
        refreshInactiveTenantsList();
    });
</script>
</body>
</html>