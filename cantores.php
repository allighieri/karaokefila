<?php
require_once 'init.php';
require_once 'funcoes_fila.php';


if (!check_access(NIVEL_ACESSO, ['admin', 'mc'])) {
    header("Location: " . $rootPath . "login");
    exit();
}
// A conexão $pdo deve vir de funcoes_fila.php
$todas_mesas = []; // Inicializa como array vazio
if (!empty($pdo)) {
    $todas_mesas = getTodasMesas($pdo); // Continua buscando as mesas na carga inicial da página

    $todos_cantores = getAllCantores($pdo);
}else {
    $todos_cantores = []; // Garante que seja um array vazio se o PDO não estiver conectado
}

$current_page = pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME);

// Obter lista de mesas para formulário de adicionar cantor


$stmtMesas = $pdo->prepare("SELECT id, nome_mesa, tamanho_mesa FROM mesas WHERE id_tenants = ? ORDER BY nome_mesa ASC");
$stmtMesas->execute([ID_TENANTS]);
$mesas_disponiveis = $stmtMesas->fetchAll(PDO::FETCH_ASSOC);




?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Karaokê - Cantores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="css/style_index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .modal-backdrop, .modal {
            z-index: 1050 !important;
        }

        .modal.fade.show {
            z-index: 1055 !important;
        }
    </style>

</head>
<body>

<?php include_once 'inc/nav.php'; ?>

<div class="container">

    <div id="alertContainer" class="mt-3"></div>

        <h3>Adicionar Cantores</h3>
        <form method="POST" id="addCantores">
            <input type="hidden" name="action" value="add_cantor">
            <div class="row mb-3">
                <div class="col-md-6">
                    <select id="id_mesa_cantor" name="id_mesa_cantor" class="form-select" required> <option value="">Selecione uma mesa</option>
                        <?php
                        if (isset($mesas_disponiveis)) {
                            foreach ($mesas_disponiveis as $mesa): ?>
                                <option value="<?php echo htmlspecialchars($mesa['id']); ?>"><?php echo htmlspecialchars($mesa['nome_mesa']); ?></option>
                            <?php endforeach;
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-lg-6">
                    <div class="input-group mb-3">
                        <input type="text" id="nome_cantor" name="nome_cantor" class="form-control" placeholder="Nome do cantor" aria-label="Nome do cantor" aria-describedby="button-addon2" required>
                        <button class="btn btn-success" type="submit" id="button-addon2">Adicionar</button>
                    </div>
                </div>
            </div>

        </form>

    <hr class="my-4">
    <h3>Cantores Cadastrados</h3>
    <div id="cantoresListContainer">
        <?php if (!empty($todos_cantores)): ?>
            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    <th scope="col">Cantor</th>
                    <th scope="col">Mesa</th>
                    <th scope="col">Prior</th>
                    <th scope="col">Ações</th> </tr>
                </thead>
                <tbody>
                <?php foreach ($todos_cantores as $cantor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cantor['nome_cantor']); ?></td>
                        <td><?php echo htmlspecialchars($cantor['nome_da_mesa_associada'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($cantor['proximo_ordem_musica']); ?></td>
                        <td>
                            <div class="d-flex flex-nowrap gap-1">
                                <button class="btn btn-sm btn-warning edit-cantor-btn"
                                        data-id="<?php echo htmlspecialchars($cantor['id']); ?>"
                                        data-nome="<?php echo htmlspecialchars($cantor['nome_cantor'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-mesa-id="<?php echo htmlspecialchars($cantor['id_mesa']); ?>"
                                        data-prioridade="<?php echo htmlspecialchars($cantor['proximo_ordem_musica']); ?>"
                                        title="Editar Cantor">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="confirmarExclusaoCantor(<?php echo $cantor['id']; ?>, '<?php echo htmlspecialchars($cantor['nome_cantor'], ENT_QUOTES, 'UTF-8'); ?>')" title="Excluir Cantor">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhum cantor cadastrado ainda.</p>
        <?php endif; ?>
    </div>

</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Tem certeza que deseja excluir o cantor(a) "<strong id="cantorNomeExcluir"></strong>"?
                <p class="text-danger mt-2">Atenção: A exclusão de um cantor pode afetar músicas e filas associadas.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarExclusao">Excluir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editCantorModal" tabindex="-1" aria-labelledby="editCantorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCantorModalLabel">Editar Cantor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditCantor">
                    <input type="hidden" id="editCantorId" name="cantor_id">
                    <div class="mb-3">
                        <label for="editCantorNome" class="form-label">Nome do Cantor:</label>
                        <input type="text" class="form-control" id="editCantorNome" name="novo_nome_cantor" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCantorMesa" class="form-label">Mesa Associada:</label>
                        <select id="editCantorMesa" name="nova_mesa_id" class="form-select" required>
                            <option value="">Selecione uma mesa</option>
                            <?php
                            // Reutiliza as mesas_disponiveis já carregadas
                            if (isset($mesas_disponiveis)) {
                                foreach ($mesas_disponiveis as $mesa): ?>
                                    <option value="<?php echo htmlspecialchars($mesa['id']); ?>"><?php echo htmlspecialchars($mesa['nome_mesa']); ?></option>
                                <?php endforeach;
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarEditCantor">Salvar Alterações</button>
            </div>
        </div>
    </div>
</div>

<?php include_once 'modal_resetar_sistema.php'?>
<?php include_once 'modal_editar_codigo.php'?>
<?php include_once 'modal_add_repertorio.php'?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="/fila/js/resetar_sistema.js"></script>
<script src="/fila/js/gerenciar_codigo.js"></script>
<script src="/fila/js/add_repertorio.js"></script>



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
            }, 5000); // Alerta desaparece após 5 segundos
        }



        window.refreshCantoresList = function() {
            $.ajax({
                url: 'api.php',
                type: 'GET',
                dataType: 'json',
                data: { action: 'get_all_cantores' },
                success: function(response) {
                    if (response.success) {
                        var tableHtml = '';
                        if (response.cantores.length > 0) {
                            tableHtml += '<table class="table table-striped table-hover">' +
                                '<thead>' +
                                '<tr>' +
                                '<th scope="col">Cantor</th>' +
                                '<th scope="col">Mesa</th>' +
                                '<th scope="col">Prior</th>' +
                                '<th scope="col">Ações</th>' +
                                '</tr>' +
                                '</thead>' +
                                '<tbody>';
                            $.each(response.cantores, function(index, cantor) {
                                tableHtml += '<tr>' +
                                    '<td>' + htmlspecialchars(cantor.nome_cantor) + '</td>' +
                                    '<td>' + htmlspecialchars(cantor.nome_da_mesa_associada !== null && cantor.nome_da_mesa_associada !== undefined ? cantor.nome_da_mesa_associada : 'N/A') + '</td>' +
                                    '<td>' + htmlspecialchars(cantor.proximo_ordem_musica !== null && cantor.proximo_ordem_musica !== undefined ? cantor.proximo_ordem_musica : '0') + '</td>' +
                                    '<td>' +
                                    '<div class="d-flex flex-nowrap gap-1">' +
                                    // Manter data-prioridade aqui no botão para a tabela continuar exibindo.
                                    // Apenas não será usado para preencher o modal de edição.
                                    '<button class="btn btn-sm btn-warning edit-cantor-btn" ' +
                                    'data-id="' + htmlspecialchars(cantor.id) + '" ' +
                                    'data-nome="' + htmlspecialchars(cantor.nome_cantor) + '" ' +
                                    'data-mesa-id="' + htmlspecialchars(cantor.id_mesa) + '" ' +
                                    'data-prioridade="' + htmlspecialchars(cantor.proximo_ordem_musica) + '" ' + // MANTIDO AQUI
                                    'title="Editar Cantor">' +
                                    '<i class="bi bi-pencil-square"></i>' +
                                    '</button> ' +
                                    '<button class="btn btn-sm btn-danger" onclick="confirmarExclusaoCantor(' + htmlspecialchars(cantor.id) + ', \'' + htmlspecialchars(cantor.nome_cantor) + '\')" title="Excluir Cantor">' +
                                    '<i class="bi bi-trash-fill"></i>' +
                                    '</button>' +
                                    '</div>' +
                                    '</td>' +
                                    '</tr>';
                            });
                            tableHtml += '</tbody></table>';
                        } else {
                            tableHtml = '<p>Nenhum cantor cadastrado ainda.</p>';
                        }
                        $('#cantoresListContainer').html(tableHtml);
                    } else {
                        showAlert('Erro ao recarregar a lista de cantores: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para recarregar cantores:", status, error);
                    showAlert('Erro na comunicação com o servidor ao recarregar cantores.', 'danger');
                }
            });
        }


        $('#addCantores').on('submit', function(e) {
            e.preventDefault();

            var nomeCantor = $('#nome_cantor').val();
            var idMesa = $('#id_mesa_cantor').val();

            $.ajax({
                url: 'api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'add_cantor',
                    nomeCantor: nomeCantor,
                    idMesa: idMesa
                },
                success: function(response) {
                    if (response.success) {
                        $('#nome_cantor').val("");
                        refreshCantoresList();
                    } else {
                        showAlert('Erro: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX:", status, error);
                    showAlert('Erro na comunicação com o servidor ao adicionar mesa.', 'danger');
                }
            });
        });

        // Função para configurar e exibir o modal de confirmação de exclusão
        window.confirmarExclusaoCantor = function(id, nome) {
            $('#cantorIdExcluir').text(id);
            $('#cantorNomeExcluir').text(nome);
            $('#btnConfirmarExclusao').data('cantor-id', id);
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
        };

        // Evento de clique no botão de confirmar exclusão dentro do modal
        $('#btnConfirmarExclusao').on('click', function() {
            var cantorId = $(this).data('cantor-id'); // Pega o ID do cantor
            var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
            $.ajax({
                url: 'api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'excluir_cantor', // Ação correta para API
                    cantorId: cantorId // Parâmetro correto para API
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');

                        confirmModal.hide();
                        refreshCantoresList(); // NOVO: Atualiza a lista de cantores após a exclusão
                    } else {
                        // CORRIGIDO: Mensagem de erro para cantor
                        showAlert('Erro ao excluir cantor: ' + response.message, 'danger');
                        confirmModal.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para excluir cantor:", status, error); // CORRIGIDO
                    // CORRIGIDO: Mensagem de erro para cantor
                    showAlert('Erro na comunicação com o servidor ao excluir cantor.', 'danger');
                }
            });
        });

        $('#cantoresListContainer').on('click', '.edit-cantor-btn', function() {
            var cantorId = $(this).data('id');
            var cantorNome = $(this).data('nome');
            var cantorMesaId = $(this).data('mesa-id');
            // REMOVIDO: var cantorPrioridade = $(this).data('prioridade');

            // Preenche o modal com os dados do cantor
            $('#editCantorId').val(cantorId);
            $('#editCantorNome').val(cantorNome);
            $('#editCantorMesa').val(cantorMesaId);
            // REMOVIDO: $('#editCantorPrior').prop('checked', cantorPrioridade == 1);

            // Exibe o modal
            var editCantorModal = new bootstrap.Modal(document.getElementById('editCantorModal'));
            editCantorModal.show();
        });

        // Evento de clique no botão "Salvar Alterações" dentro do modal de edição de cantor
        $('#btnSalvarEditCantor').on('click', function() {
            var cantorId = $('#editCantorId').val();
            var novoNomeCantor = $('#editCantorNome').val();
            var novaMesaId = $('#editCantorMesa').val();
            // REMOVIDO: var proximoOrdemMusica = $('#editCantorPrior').is(':checked') ? 1 : 0;

            var editCantorModalInstance = bootstrap.Modal.getInstance(document.getElementById('editCantorModal'));

            if (novoNomeCantor.trim() === '') {
                showAlert('O nome do cantor não pode ser vazio!', 'warning');
                return;
            }
            if (novaMesaId === '' || novaMesaId === null) {
                showAlert('Por favor, selecione uma mesa para o cantor!', 'warning');
                return;
            }

            $.ajax({
                url: 'api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'edit_cantor',
                    cantor_id: cantorId,
                    novo_nome_cantor: novoNomeCantor,
                    nova_mesa_id: novaMesaId
                    // REMOVIDO: proximo_ordem_musica: proximoOrdemMusica
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        editCantorModalInstance.hide();
                        refreshCantoresList();
                    } else {
                        showAlert('Erro ao salvar: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para editar cantor:", status, error);
                    showAlert('Erro na comunicação com o servidor ao editar cantor.', 'danger');
                }
            });
        });

        // Função utilitária para escapar HTML (boa prática)
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

    })
</script>
</body>
</html>