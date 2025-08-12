<?php
require_once 'init.php';
require_once 'funcoes_fila.php';

if (!check_access(NIVEL_ACESSO, ['admin', 'mc'])) {
    header("Location: " . $rootPath . "login");
    exit();
}


?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Karaokê - Regras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="css/style_index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>
<body>

<?php include_once 'inc/nav.php'; ?>

<div class="container">

    <div id="alertContainer" class="mt-3"></div>

    <div class="form-section">
        <h3>Configurar Regras de Mesa</h3>
        <form id="formConfigurarRegrasMesa">
            <input type="hidden" name="action" value="save_all_regras_mesa">
            <input type="hidden" name="removed_ids" id="removed_ids" value="">

            <div id="regras-container">
                <?php

                $regrasExistentes = getAllRegrasMesa($pdo); // Chama a nova função

                if (!empty($regrasExistentes)) {
                    foreach ($regrasExistentes as $index => $regra) {
                        $isLast = ($index === count($regrasExistentes) - 1);
                        ?>
                        <div class="row g-3 mb-3 align-items-end regra-row" data-id="<?= htmlspecialchars($regra['id']) ?>">
                            <input type="hidden" name="regras[<?= $index ?>][id]" value="<?= htmlspecialchars($regra['id']) ?>">
                            <div class="col-md-3">
                                <label for="min_pessoas_<?= $index ?>" class="form-label form-label-sm">Mínimo de Pessoas:</label>
                                <input type="number" id="min_pessoas_<?= $index ?>" name="regras[<?= $index ?>][min_pessoas]" class="form-control" required min="1" value="<?= htmlspecialchars($regra['min_pessoas']) ?>">

                            </div>

                            <div class="col-md-3">
                                <label for="max_pessoas_<?= $index ?>" class="form-label form-label-sm">Máximo de Pessoas:</label>
                                <input type="number" id="max_pessoas_<?= $index ?>" name="regras[<?= $index ?>][max_pessoas]" class="form-control" min="1" value="<?= htmlspecialchars($regra['max_pessoas']) ?>">

                            </div>

                            <div class="col-md-3">
                                <label for="max_musicas_por_rodada_<?= $index ?>" class="form-label form-label-sm">Músicas por Rodada:</label>
                                <div class="input-group">
                                    <input type="number" id="max_musicas_por_rodada_<?= $index ?>" name="regras[<?= $index ?>][max_musicas_por_rodada]" class="form-control" required min="1" value="<?= htmlspecialchars($regra['max_musicas_por_rodada']) ?>">

                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="acoes_<?= $index ?>" class="visually-hidden">Ações</label>
                                <div class="input-group">
                                    <button type="button" id="acaoes_<?= $index ?>" class="btn btn-danger remove-regra-btn me-1" style="<?= $index === 0 && count($regrasExistentes) === 1 ? 'display: none;' : '' ?>">-</button>
                                    <button type="button" class="btn btn-success add-regra-btn" style="<?= $isLast ? '' : 'display: none;' ?>">+</button>

                                </div>
                            </div>

                        </div>
                        <?php
                    }
                } else {
                    // Se não houver regras existentes, adicione uma linha vazia por padrão
                    ?>
                    <div class="row g-3 mb-3 align-items-end regra-row" data-id="">
                        <input type="hidden" name="regras[0][id]" value="">
                        <div class="col-md-3">
                            <label for="min_pessoas_0" class="form-label form-label-sm">Mínimo de Pessoas:</label>
                            <input type="number" id="min_pessoas_0" name="regras[0][min_pessoas]" class="form-control" required min="1">
                        </div>

                        <div class="col-md-3">
                            <label for="max_pessoas_0" class="form-label form-label-sm">Máximo de Pessoas:</label>
                            <input type="number" id="max_pessoas_0" name="regras[0][max_pessoas]" class="form-control" min="1">

                        </div>

                        <div class="col-md-3">
                            <label for="max_musicas_por_rodada_0" class="form-label form-label-sm">Músicas por Rodada:</label>
                            <input type="number" id="max_musicas_por_rodada_0" name="regras[0][max_musicas_por_rodada]" class="form-control" required min="1">
                        </div>


                        <div class="col-md-3">
                            <label for="acoes_0" class="visually-hidden">Ações</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-danger remove-regra-btn me-1">-</button>
                                <button type="button" class="btn btn-success add-regra-btn">+</button>
                            </div>
                        </div>

                    </div>
                <?php } ?>
            </div>

            <div class="alert alert-info alert-dismissible fade show" role="alert">
                Se deixar em branco a coluna <strong>Máximo de Pessoas</strong>, o sistema irá reconhecer que a regra se aplica ao máximo de pessoas a partir do Mínimo de pessoas para aquela mesa. Ex.: Se o Mínimo de Pessoas for 7 e o Máximo de Pessoas está em branco, o sistema considera que a regra se aplica a 7 ou mais.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <button type="submit" class="btn btn-primary">Salvar Regras</button>
            <button type="button" id="btnRegrasPadrao" class="btn btn-info ms-2">Resetar Padrão</button>
        </form>

        <h3>Quantidade de músicas por mesa:</h3>
        <ul>
            <?php
            $regrasExibicao = getRegrasMesaFormatadas($pdo);
            foreach ($regrasExibicao as $regraTexto) {
                echo "<li>" . htmlspecialchars($regraTexto) . "</li>"; // Use htmlspecialchars para segurança
            }
            ?>
        </ul>

    </div>


</div>

<div class="modal fade" id="confirmResetRulesModal" tabindex="-1" aria-labelledby="confirmResetRulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmResetRulesModalLabel">Confirmar Reset de Regras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Tem certeza que deseja **resetar todas as regras para o padrão**?
                <p class="text-danger mt-2">Esta ação removerá todas as regras personalizadas e aplicará as configurações padrão do sistema.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarResetRegras">Resetar Regras</button>
            </div>
        </div>
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
<?php include_once 'modal_editar_codigo.php'?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://localhost/fila/js/resetar_sistema.js"></script>
<script src="https://localhost/fila/js/gerenciar_codigo.js"></script>




<script>
    $(document).ready(function() {

        window.showAlert = function(message, type) {
            var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                '<span>' + message + '</span>' +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>';
            $('#alertContainer').html(alertHtml);
            // Reduzir o tempo para 3 segundos para não atrapalhar, mas ainda ser visível
            setTimeout(function() {
                $('#alertContainer .alert').alert('close');
            }, 3000);
        }

        const regrasContainer = $('#regras-container');
        const formConfigurarRegrasMesa = $('#formConfigurarRegrasMesa');
        // A variável regraIndex será redefinida em refreshRegrasForm
        let regraIndex = 0; // Inicializamos com 0, pois será atualizado na função de refresh

        // --- NOVA FUNÇÃO: Atualiza a lista de regras formatadas (ul) ---
        function refreshRegrasDisplay() {
            $.ajax({
                url: 'api_processar_regras.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'get_regras_formatadas' },
                success: function(response) {
                    if (response.success) {
                        const ulElement = $('div.form-section > ul'); // Seleciona a <ul> logo após o h3 "Quantidade de músicas por mesa:"
                        ulElement.empty(); // Limpa a lista existente
                        if (response.regras && response.regras.length > 0) {
                            response.regras.forEach(function(regraTexto) {
                                ulElement.append(`<li>${htmlspecialchars(regraTexto)}</li>`);
                            });
                        } else {
                            ulElement.append('<li>Nenhuma regra configurada.</li>');
                        }
                    } else {
                        console.error('Erro ao recarregar exibição das regras:', response.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Erro AJAX ao recarregar exibição das regras:', textStatus, errorThrown);
                }
            });
        }

        // --- NOVA FUNÇÃO: Atualiza o formulário de regras (#regras-container) ---
        function refreshRegrasForm() {
            $.ajax({
                url: 'api_processar_regras.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'get_all_regras_data' },
                success: function(response) {
                    if (response.success) {
                        regrasContainer.empty(); // Limpa todas as linhas existentes
                        regraIndex = 0; // Reseta o índice para começar do zero

                        if (response.regras_data && response.regras_data.length > 0) {
                            response.regras_data.forEach(function(regra) {
                                // Reutiliza a função addRegraRow para redesenhar cada linha
                                addRegraRow(regra.id, regra.min_pessoas, regra.max_pessoas !== null ? regra.max_pessoas : '', regra.max_musicas_por_rodada, true); // true para isReset, para não esconder o '+' do último.
                                regraIndex++; // Incrementa o índice após adicionar cada regra do banco
                            });
                        } else {
                            // Se não houver regras, adiciona uma linha vazia padrão
                            addRegraRow('', '', '', '', true); // true para isReset
                        }
                        // Após redesenhar tudo, atualiza a visibilidade dos botões
                        updateRemoveButtonsVisibility();
                    } else {
                        console.error('Erro ao recarregar formulário de regras:', response.message);
                        showAlert('Não foi possível recarregar o formulário de regras.', 'danger');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Erro AJAX ao recarregar formulário de regras:', textStatus, errorThrown);
                    showAlert('Ocorreu um erro de comunicação ao recarregar o formulário de regras.', 'danger');
                }
            });
        }


        // Função para adicionar uma nova linha de regra ou REINICIALIZAR uma existente
        // Adicionado um parâmetro 'initialMin' para preencher o campo Mínimo de Pessoas
        function addRegraRow(id = '', min = '', max = '', musicas = '', isReset = false, initialMin = '') {
            // Se initialMin for fornecido, ele tem precedência sobre o 'min' padrão (que viria do banco)
            const finalMin = initialMin !== '' ? initialMin : min;

            // Use o valor atual de regraIndex para o name/id
            const currentRegraIndex = regrasContainer.children('.regra-row').length; // Conta as linhas já presentes
            const newRow = $(`
            <div class="row g-3 mb-3 align-items-end regra-row" data-id="${htmlspecialchars(id)}">
               <input type="hidden" name="regras[${currentRegraIndex}][id]" value="${htmlspecialchars(id)}">

               <div class="col-md-3"> <label for="min_pessoas_${currentRegraIndex}" class="form-label form-label-sm">Mínimo de Pessoas:</label>
                  <input type="number" id="min_pessoas_${currentRegraIndex}" name="regras[${currentRegraIndex}][min_pessoas]" class="form-control" required min="1" value="${htmlspecialchars(finalMin)}">
               </div>

               <div class="col-md-3"> <label for="max_pessoas_${currentRegraIndex}" class="form-label form-label-sm">Máximo de Pessoas:</label>
                  <input type="number" id="max_pessoas_${currentRegraIndex}" name="regras[${currentRegraIndex}][max_pessoas]" class="form-control" min="1" value="${htmlspecialchars(max)}">

               </div>

               <div class="col-md-3"> <label for="max_musicas_por_rodada_${currentRegraIndex}" class="form-label form-label-sm">Músicas por Rodada:</label>
                  <div class="input-group"> <input type="number" id="max_musicas_por_rodada_${currentRegraIndex}" name="regras[${currentRegraIndex}][max_musicas_por_rodada]" class="form-control" required min="1" value="${htmlspecialchars(musicas)}">
                  </div>
               </div>

               <div class="col-md-3"> <label for="acoes_${currentRegraIndex}" class="invisible visually-hidden">Ações</label>
                  <div class="input-group"> <button type="button" class="btn btn-danger remove-regra-btn mx-1">-</button>
                     <button type="button" class="btn btn-success add-regra-btn mx-1">+</button>
                  </div>
               </div>
            </div>
            `);

            // A lógica de esconder o '+' do botão 'add-regra-btn' para as linhas anteriores
            // precisa ser refeita após o .append(), pois agora sempre haverá um último elemento.
            const lastRow = regrasContainer.children().last();
            if (lastRow.length) {
                lastRow.find('.add-regra-btn').hide(); // Esconde o '+' da "antiga" última linha
            }

            regrasContainer.append(newRow); // Adiciona a nova linha
            // O updateRemoveButtonsVisibility() agora será chamado no refreshRegrasForm() para ser executado uma vez.
        }


        // Função para atualizar a visibilidade dos botões de remover e adicionar
        function updateRemoveButtonsVisibility() {
            const allRows = regrasContainer.children('.regra-row');
            const totalRows = allRows.length;

            allRows.find('.remove-regra-btn').show(); // Mostra todos os botões de remover por padrão
            allRows.find('.add-regra-btn').hide(); // Esconde todos os botões de adicionar por padrão

            // Lógica para esconder o botão de remover se houver apenas uma linha e ela não tiver ID (nova)
            if (totalRows === 1) {
                const singleRow = allRows.first();
                if (!singleRow.data('id')) { // Se é a única linha e é nova (sem ID)
                    singleRow.find('.remove-regra-btn').hide();
                }
            }

            // Sempre mostra o botão de adicionar na última linha
            if (totalRows > 0) {
                allRows.last().find('.add-regra-btn').show();
            }
        }


        // Event Delegation para botões de adicionar
        regrasContainer.on('click', '.add-regra-btn', function() {
            const lastRow = regrasContainer.children().last();
            let nextMin = '';

            if (lastRow.length) {
                const maxPessoasLastRow = lastRow.find('input[name$="[max_pessoas]"]').val();
                if (maxPessoasLastRow !== '') {
                    nextMin = parseInt(maxPessoasLastRow) + 1;
                }
            }
            addRegraRow('', '', '', '', false, nextMin);
            updateRemoveButtonsVisibility(); // Atualiza a visibilidade após adicionar uma nova linha
        });

        // Event Delegation para botões de REMOVER
        regrasContainer.on('click', '.remove-regra-btn', function() {
            const rowToRemove = $(this).closest('.regra-row');
            const idToRemove = rowToRemove.data('id');
            const totalRows = regrasContainer.children('.regra-row').length;
            const clickedButton = $(this);

            if (idToRemove) {
                clickedButton.prop('disabled', true);

                $.ajax({
                    url: 'api_processar_regras.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'delete_regra_mesa',
                        id: idToRemove
                    },
                    success: function(data) {
                        if (data.success) {
                            showAlert('Regra removida com sucesso!', 'success');
                            if (totalRows === 1) {
                                // Se for a última e for removida do banco, recarrega o formulário do zero
                                refreshRegrasForm();
                                refreshRegrasDisplay();
                            } else {
                                rowToRemove.remove();
                                updateRemoveButtonsVisibility();
                                refreshRegrasDisplay(); // Atualiza a lista de exibição
                            }
                        } else {
                            showAlert('Erro ao remover regra: ' + data.message, 'danger');
                            clickedButton.prop('disabled', false);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Erro na requisição AJAX de remoção:', textStatus, errorThrown, jqXHR.responseText);
                        showAlert('Ocorreu um erro na comunicação com o servidor ao tentar remover a regra.', 'danger');
                        clickedButton.prop('disabled', false);
                    }
                });
            } else { // Se a linha NÃO tem um ID (é uma regra nova, ainda não salva)
                if (totalRows === 1) {
                    rowToRemove.find('input[type="number"]').val('');
                    // Se a única linha for "zerada", assegure que o botão de remover desapareça
                    rowToRemove.find('.remove-regra-btn').hide();
                } else {
                    rowToRemove.remove();
                }
                updateRemoveButtonsVisibility();
            }
        });

        // Inicializa: Garante que os botões estejam corretos ao carregar a página
        // A populção inicial via PHP já chama getAllRegrasMesa, então não precisamos chamar refreshRegrasForm aqui
        // se o PHP estiver configurando o DOM corretamente na carga inicial.
        // updateRemoveButtonsVisibility(); // Já é chamado pelo PHP ao final da renderização inicial.

        // --- Lógica para o envio do formulário via AJAX (apenas salvar/atualizar) ---
        formConfigurarRegrasMesa.on('submit', function(e) {
            e.preventDefault();

            const regrasData = [];
            $('.regra-row').each(function(index, element) {
                const $row = $(element);
                const id = $row.data('id');
                const minPessoas = $row.find('input[name$="[min_pessoas]"]').val();
                let maxPessoas = $row.find('input[name$="[max_pessoas]"]').val();
                const maxMusicas = $row.find('input[name$="[max_musicas_por_rodada]"]').val();

                if (maxPessoas === '') {
                    maxPessoas = null;
                }

                if (minPessoas !== '' || id) { // Só inclui regras com min_pessoas preenchido ou com ID existente
                    regrasData.push({
                        id: id,
                        min_pessoas: minPessoas,
                        max_pessoas: maxPessoas,
                        max_musicas_por_rodada: maxMusicas
                    });
                }
            });

            const dataToSend = {
                action: 'save_all_regras_mesa',
                regras_json: JSON.stringify(regrasData)
            };

            $.ajax({
                url: 'api_processar_regras.php',
                type: 'POST',
                dataType: 'json',
                data: dataToSend,
                success: function(data) {
                    if (data.success) {
                        showAlert('Regras salvas com sucesso!', 'success');
                        refreshRegrasForm();    // Recarrega o formulário com os IDs atualizados e novos elementos
                        refreshRegrasDisplay(); // Recarrega a lista de exibição
                    } else {
                        showAlert('Erro ao salvar regras: ' + data.message, 'danger');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Erro na requisição AJAX de salvar:', textStatus, errorThrown, jqXHR.responseText);
                    showAlert('Ocorreu um erro na comunicação com o servidor ao tentar salvar as regras.', 'danger');
                }
            });
        });

        // Lógica para o botão "Regra Padrão"
        $('#btnRegrasPadrao').on('click', function() {
            var resetRulesModal = new bootstrap.Modal(document.getElementById('confirmResetRulesModal'));
            resetRulesModal.show();
        });

        $('#btnConfirmarResetRegras').on('click', function() {
            var resetRulesModalInstance = bootstrap.Modal.getInstance(document.getElementById('confirmResetRulesModal'));

            $.ajax({
                url: 'api_processar_regras.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'set_regras_padrao'
                },
                success: function(data) {
                    if (data.success) {
                        showAlert('Regras padrão aplicadas com sucesso!', 'success');
                        resetRulesModalInstance.hide();
                        refreshRegrasForm();     // Atualiza o formulário com as regras padrão
                        refreshRegrasDisplay();  // Atualiza a lista de exibição
                    } else {
                        showAlert('Erro ao aplicar regras padrão: ' + data.message, 'danger');
                        resetRulesModalInstance.hide();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Erro na requisição AJAX:', textStatus, errorThrown, jqXHR.responseText);
                    showAlert('Ocorreu um erro na comunicação ao aplicar as regras padrão.', 'danger');
                    resetRulesModalInstance.hide();
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

        // Chamadas iniciais para garantir que ambos os elementos estejam atualizados no carregamento da página
        // REMOVIDO: updateRemoveButtonsVisibility() daqui, pois refreshRegrasForm já vai chamar.
        // A populção inicial do PHP já faz a renderização, mas se quiser ter certeza que JS controla tudo:
        // refreshRegrasForm();
        // refreshRegrasDisplay();
        // No seu caso, o PHP já popula, então as chamadas acima podem ser redundantes na carga inicial.
        // O importante é que elas sejam chamadas após as ações AJAX.

        // Uma correção para o initialMin na função addRegraRow
        // Na primeira carga, se não houver regras, a função addRegraRow é chamada com initialMin vazio.
        // O PHP já está populando, mas se fosse via JS na carga inicial, seria importante.
        // O `updateRemoveButtonsVisibility()` na primeira carga já está ok, pois ele é chamado
        // ao final do PHP que renderiza o HTML inicial.
    });
</script>
</body>
</html>