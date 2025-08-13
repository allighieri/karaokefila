<?php
require_once 'init.php';
require_once 'funcoes_fila.php';

if (!check_access(NIVEL_ACESSO, ['admin', 'mc'])) {
    header("Location: " . $rootPath . "login");
    exit();
}

// Removendo o bloco de fallback PDO aqui, assumindo que funcoes_fila.php já o lida.
// if (empty($pdo)) { ... } // Comentado/Removido conforme sua instrução anterior.

// A conexão $pdo deve vir de funcoes_fila.php
$todas_mesas = []; // Inicializa como array vazio
if (!empty($pdo)) {
    $todas_mesas = getTodasMesas($pdo); // Continua buscando as mesas na carga inicial da página
}

$current_page = pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Karaokê - Mesas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="css/style_index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>
<body>

<?php include_once 'inc/nav.php'; ?>

<div class="container">

    <div id="alertContainer" class="mt-3"></div>

    <h3>Adicionar Mesa</h3>
    <form method="POST" id="addMesas">
        <input type="hidden" name="action" value="add_mesa">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="input-group mb-3">
                    <input type="text" id="nome_mesa" name="nome_mesa"  class="form-control" placeholder="Nome da mesa" aria-label="Nome da mesa" aria-describedby="button-addon2" required>
                    <button class="btn btn-success" type="submit" id="button-addon2">Adicionar</button>
                </div>
            </div>
        </div>
    </form>

    <hr class="my-4">
    <h3>Mesas Cadastradas</h3>
    <div id="mesasListContainer">
        <?php if (!empty($todas_mesas)): ?>
            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    <th scope="col">Mesa</th>
                    <th scope="col">Cantores</th>
                    <th scope="col">Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($todas_mesas as $mesa): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($mesa['nome_mesa']); ?></td>
                        <td><?php echo htmlspecialchars($mesa['tamanho_mesa'] ?? 'Não Definido'); ?></td>
                        <td>
                            <div class="d-flex flex-nowrap gap-1">
                                <button class="btn btn-sm btn-warning edit-mesa-btn" data-id="<?php echo htmlspecialchars($mesa['id']); ?>" data-nome="<?php echo htmlspecialchars($mesa['nome_mesa'], ENT_QUOTES, 'UTF-8'); ?>" title="Editar Mesa">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="confirmarExclusaoMesa(<?php echo $mesa['id']; ?>, '<?php echo htmlspecialchars($mesa['nome_mesa'], ENT_QUOTES, 'UTF-8'); ?>')" title="Excluir Mesa">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhuma mesa cadastrada ainda. Adicione uma nova mesa acima.</p>
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
                Tem certeza que deseja excluir a mesa "<strong id="mesaNomeExcluir"></strong>"?
                <p class="text-danger mt-2">Atenção: A exclusão de uma mesa pode afetar cantores e filas associadas.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarExclusao">Excluir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editMesaModal" tabindex="-1" aria-labelledby="editMesaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMesaModalLabel">Editar Nome da Mesa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditMesa">
                    <input type="hidden" id="editMesaId" name="mesa_id">
                    <div class="mb-3">
                        <label for="editMesaNome" class="form-label">Novo Nome da Mesa:</label>
                        <input type="text" class="form-control" id="editMesaNome" name="novo_nome_mesa" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarEditMesa">Salvar Alterações</button>
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


        window.refreshMesasList = function() {
            $.ajax({
                url: 'api.php',
                type: 'GET', // Usamos GET para buscar dados
                dataType: 'json',
                data: { action: 'get_all_mesas' }, // Ação que vai buscar as mesas
                success: function(response) {
                    if (response.success) {
                        var tableHtml = '';
                        if (response.mesas.length > 0) {
                            tableHtml += '<table class="table table-striped table-hover">' +
                                '<thead>' +
                                '<tr>' +
                                '<th scope="col">Mesa</th>' +
                                '<th scope="col">Cantores</th>' +
                                '<th scope="col">Ações</th>' +
                                '</tr>' +
                                '</thead>' +
                                '<tbody>';
                            $.each(response.mesas, function(index, mesa) {
                                tableHtml += '<tr>' +
                                    '<td>' + htmlspecialchars(mesa.nome_mesa) + '</td>' +
                                    '<td>' + (mesa.tamanho_mesa !== null && mesa.tamanho_mesa !== undefined ? htmlspecialchars(mesa.tamanho_mesa) : 'Não Definido') + '</td>' +
                                    '<td>' +
                                    '<div class="d-flex flex-nowrap gap-1">' +
                                    // ESTA É A LINHA DO BOTÃO DE EDITAR:
                                    '<button class="btn btn-sm btn-warning edit-mesa-btn" data-id="' + htmlspecialchars(mesa.id) + '" data-nome="' + htmlspecialchars(mesa.nome_mesa) + '" title="Editar Mesa">' +
                                    '<i class="bi bi-pencil-square"></i>' +
                                    '</button> ' +
                                    '<button class="btn btn-sm btn-danger" onclick="confirmarExclusaoMesa(' + htmlspecialchars(mesa.id) + ', \'' + htmlspecialchars(mesa.nome_mesa) + '\')" title="Excluir Mesa">' +
                                    '<i class="bi bi-trash-fill"></i>' +
                                    '</button>' +
                                    '</div>' +
                                    '</td>' +
                                    '</tr>';
                            });
                            tableHtml += '</tbody></table>';
                        } else {
                            tableHtml = '<p>Nenhuma mesa cadastrada ainda. Adicione uma nova mesa acima.</p>';
                        }
                        $('#mesasListContainer').html(tableHtml); // Atualiza o conteúdo da div
                    } else {
                        showAlert('Erro ao recarregar a lista de mesas: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para recarregar mesas:", status, error);
                    showAlert('Erro na comunicação com o servidor ao recarregar mesas.', 'danger');
                }
            });
        }

        $('#mesasListContainer').on('click', '.edit-mesa-btn', function() {
            var mesaId = $(this).data('id');
            var mesaNome = $(this).data('nome');

            // Preenche o modal com os dados da mesa
            $('#editMesaId').val(mesaId);
            $('#editMesaNome').val(mesaNome);

            // Exibe o modal
            var editMesaModal = new bootstrap.Modal(document.getElementById('editMesaModal'));
            editMesaModal.show();
        });

        // Evento de clique no botão "Salvar Alterações" dentro do modal de edição
        $('#btnSalvarEditMesa').on('click', function() {
            var mesaId = $('#editMesaId').val();
            var novoNomeMesa = $('#editMesaNome').val();
            var editMesaModalInstance = bootstrap.Modal.getInstance(document.getElementById('editMesaModal'));

            if (novoNomeMesa.trim() === '') {
                showAlert('O nome da mesa não pode ser vazio!', 'warning');
                return;
            }

            $.ajax({
                url: 'api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'edit_mesa',
                    mesa_id: mesaId,
                    novo_nome_mesa: novoNomeMesa
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        editMesaModalInstance.hide(); // Fecha o modal
                        refreshMesasList(); // Recarrega a lista de mesas para mostrar a alteração
                    } else {
                        showAlert('Erro ao salvar: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para editar mesa:", status, error);
                    showAlert('Erro na comunicação com o servidor ao editar mesa.', 'danger');
                }
            });
        });

        $('#addMesas').on('submit', function(e) {
            e.preventDefault();

            var nomeMesa = $('#nome_mesa').val();

            $.ajax({
                url: 'api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'add_mesa',
                    nomeMesa: nomeMesa
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        $('#nome_mesa').val(""); // Limpa o campo
                        // *** CHAMA A NOVA FUNÇÃO DE REFRESH SEM REFRESH DE PÁGINA ***
                        refreshMesasList();
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
        window.confirmarExclusaoMesa = function(id, nome) {
            $('#mesaIdExcluir').text(id);
            $('#mesaNomeExcluir').text(nome);
            $('#btnConfirmarExclusao').data('mesa-id', id);
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
        };

        // Evento de clique no botão de confirmar exclusão dentro do modal
        $('#btnConfirmarExclusao').on('click', function() {
            var mesaId = $(this).data('mesa-id');
            var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));

            $.ajax({
                url: 'api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'excluir_mesa',
                    mesa_id: mesaId
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');

                        confirmModal.hide();
                        // *** CHAMA A NOVA FUNÇÃO DE REFRESH SEM REFRESH DE PÁGINA ***
                        refreshMesasList();
                    } else {
                        showAlert(response.message, 'danger');
                        confirmModal.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro na requisição AJAX para excluir mesa:", status, error);
                    showAlert('Erro na comunicação com o servidor ao excluir mesa.', 'danger');
                }
            });
        });

        // Função utilitária para escapar HTML (boa prática)
        function htmlspecialchars(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text == null ? '' : String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // *** IMPORTANTE: CARREGA A LISTA DE MESAS NA INICIALIZAÇÃO DA PÁGINA ***
        // Isso garante que a lista esteja atualizada mesmo na primeira carga sem precisar de PHP para popular a tabela.
        // No entanto, como você já tem o PHP populando na carga inicial, isso é redundante, mas mantém a flexibilidade
        // se decidir remover o loop PHP inicial no futuro. O loop PHP inicial é mais robusto para a primeira carga.
        // Se você quiser que o JS seja o único responsável por popular a lista:
        // refreshMesasList(); // Descomente se quiser que o JS carregue a lista na primeira carga, sobrepondo o PHP
    })
</script>
</body>
</html>