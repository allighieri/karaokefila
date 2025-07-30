$('document').ready(function(){
    $('#resetarSistema').on('click', function(e) {
        e.preventDefault(); // Impede o comportamento padrão do link

        var resetModal = new bootstrap.Modal(document.getElementById('confirmResetModal'));
        resetModal.show(); // Exibe o modal de confirmação
    });

    // Evento de clique no botão "Resetar Sistema Agora" dentro do modal
    $('#btnConfirmarResetSistema').on('click', function() {
        // Fecha o modal de reset
        var resetModal = bootstrap.Modal.getInstance(document.getElementById('confirmResetModal'));
        resetModal.hide();

        // Realiza a chamada AJAX para resetar o sistema
        $.ajax({
            url: 'api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'resetar_sistema',
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                } else {
                    showAlert('Erro: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error("Erro na requisição AJAX:", status, error);
                showAlert('Erro na comunicação com o servidor ao resetar o sistema.', 'danger');
            }
        });
    });
})



