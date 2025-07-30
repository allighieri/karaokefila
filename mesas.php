<?php
require_once 'funcoes_fila.php';

// Removendo o bloco de fallback PDO aqui, assumindo que funcoes_fila.php já o lida.
// if (empty($pdo)) { ... } // Comentado/Removido conforme sua instrução anterior.

// A conexão $pdo deve vir de funcoes_fila.php
$todas_mesas = []; // Inicializa como array vazio
if (!empty($pdo)) {
    $todas_mesas = getTodasMesas($pdo); // Continua buscando as mesas na carga inicial da página
}
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

<nav class="navbar navbar-expand-lg navbar-dark fixed-top bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Gerenciador de Filas Karaokê</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="rodadas.php">Gerenciar Fila</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="mesas.php">Mesas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#sectionThree">Serviços</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#sectionFour">Contato</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#sectionRegras">Regras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="resetarSistema" href="#sectionResetarSistema">Resetar Sistema</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">

    <div id="alertContainer" class="mt-3"></div>

    <h3>Adicionar Nova Mesa</h3>
    <form method="POST" id="addMesas">
        <input type="hidden" name="action" value="add_mesa">
        <div class="row g-3 align-items-end">
            <div class="col-md-4 col-lg-3">
                <label for="nome_mesa" class="form-label">Nome da Mesa:</label>
                <input type="text" id="nome_mesa" name="nome_mesa" class="form-control" required>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary">Adicionar Mesa</button>
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
                    <th scope="col">Nome da Mesa</th>
                    <th scope="col">Tamanho da Mesa</th>
                    <th scope="col">Ações</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($todas_mesas as $mesa): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($mesa['nome_mesa']); ?></td>
                        <td><?php echo htmlspecialchars($mesa['tamanho_mesa'] ?? 'Não Definido'); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-id="<?php echo $mesa['id']; ?>" title="Editar Mesa">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="confirmarExclusaoMesa(<?php echo $mesa['id']; ?>, '<?php echo htmlspecialchars($mesa['nome_mesa'], ENT_QUOTES, 'UTF-8'); ?>')" title="Excluir Mesa">
                                <i class="bi bi-trash-fill"></i>
                            </button>
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

<?php include_once 'modal_resetar_sistema.php'?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://localhost/fila/js/resetar_sistema.js"></script>



<script>
    function showAlert(message, type) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            '<span>' + message + '</span>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        $('#alertContainer').html(alertHtml);
        setTimeout(function() {
            $('#alertContainer .alert').alert('close');
        }, 5000); // Alerta desaparece após 5 segundos
    }


    $(document).ready(function() {



        // *** FUNÇÃO PARA ATUALIZAR A LISTA DE MESAS VIA AJAX (NOVA IMPLEMENTAÇÃO) ***
        function refreshMesasList() {
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
                                '<th scope="col">Nome da Mesa</th>' +
                                '<th scope="col">Tamanho da Mesa</th>' +
                                '<th scope="col">Ações</th>' +
                                '</tr>' +
                                '</thead>' +
                                '<tbody>';
                            $.each(response.mesas, function(index, mesa) {
                                tableHtml += '<tr>' +
                                    '<td>' + htmlspecialchars(mesa.id) + '</td>' +
                                    '<td>' + htmlspecialchars(mesa.nome_mesa) + '</td>' +
                                    '<td>' + (mesa.tamanho_mesa !== null && mesa.tamanho_mesa !== undefined ? htmlspecialchars(mesa.tamanho_mesa) : 'Não Definido') + '</td>' +
                                    '<td>' +
                                    // Botão Editar com ícone no JavaScript
                                    '<button class="btn btn-sm btn-warning" data-id="' + htmlspecialchars(mesa.id) + '" title="Editar Mesa">' +
                                    '<i class="bi bi-pencil-square"></i>' +
                                    '</button> ' +
                                    // Botão Excluir com ícone no JavaScript
                                    '<button class="btn btn-sm btn-danger" onclick="confirmarExclusaoMesa(' + htmlspecialchars(mesa.id) + ', \'' + htmlspecialchars(mesa.nome_mesa) + '\')" title="Excluir Mesa">' +
                                    '<i class="bi bi-trash-fill"></i>' +
                                    '</button>' +
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
        // *** FIM DA FUNÇÃO PARA ATUALIZAR A LISTA DE MESAS VIA AJAX ***


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
                        var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                        confirmModal.hide();
                        // *** CHAMA A NOVA FUNÇÃO DE REFRESH SEM REFRESH DE PÁGINA ***
                        refreshMesasList();
                    } else {
                        showAlert('Erro ao excluir mesa: ' + response.message, 'danger');
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