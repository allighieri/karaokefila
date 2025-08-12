$('document').ready(function(){
    // Aplicar máscara alfanumérica para código global
    $('#editTenantCodeInputGlobal').on('input', function() {
        var value = $(this).val();
        // Remove caracteres especiais, mantém apenas letras, números, espaços e underscores
        value = value.replace(/[^a-zA-Z0-9\s_]/g, '');
        // Substitui espaços por underscore
        value = value.replace(/\s/g, '_');
        // Converte para maiúsculo
        value = value.toUpperCase();
        $(this).val(value);
    });
    
    // CSS para deixar o texto do código em maiúsculo
    $('#editTenantCodeInputGlobal').css('text-transform', 'uppercase');
    
    // Carregar dados do estabelecimento atual quando abrir a modal
    $('#editTenantCodeModalGlobal').on('show.bs.modal', function() {
        // Limpar alertas anteriores
        $('#alertContainerEditTenantCodeGlobal').html('');
        
        // Buscar dados do tenant atual
        $.ajax({
            url: 'api_tenants.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_current_tenant_code'
            },
            success: function(response) {
                console.log('Resposta da API get_current_tenant_code:', response);
                if (response.success) {
                    var code = response.code || '';
                    console.log('Código original:', code);
                    // Aplicar a máscara ao código carregado
                    if (code) {
                        code = code.replace(/[^a-zA-Z0-9\s_]/g, '');
                        code = code.replace(/\s/g, '_');
                        code = code.toUpperCase();
                    }
                    console.log('Código após máscara:', code);
                    $('#editTenantCodeInputGlobal').val(code);
                    $('#editTenantCodeStatusGlobal').prop('checked', response.status === 'active');
                } else {
                    console.log('Erro na resposta:', response.message);
                    $('#alertContainerEditTenantCodeGlobal').html('<div class="alert alert-warning alert-dismissible fade show" role="alert">' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('Erro na requisição AJAX:', xhr, status, error);
                console.log('Response Text:', xhr.responseText);
                $('#alertContainerEditTenantCodeGlobal').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Erro ao carregar dados do código.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
        });
    });
    
    // Evento para salvar o código
    $('#btnSalvarEditCodigoGlobal').click(function() {
        var code = $('#editTenantCodeInputGlobal').val().trim();
        var status = $('#editTenantCodeStatusGlobal').is(':checked') ? 'active' : 'expired';
        
        if (!code) {
            $('#alertContainerEditTenantCodeGlobal').html('<div class="alert alert-warning alert-dismissible fade show" role="alert">Por favor, informe o código do estabelecimento.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            return;
        }
        
        // Limpar alertas anteriores
        $('#alertContainerEditTenantCodeGlobal').html('');
        
        $.ajax({
            url: 'api_tenants.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_current_tenant_code',
                code: code,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    if (typeof showAlert === 'function') {
                        showAlert(response.message, 'success');
                    }
                    var editCodeModal = bootstrap.Modal.getInstance(document.getElementById('editTenantCodeModalGlobal'));
                    editCodeModal.hide();
                } else {
                    $('#alertContainerEditTenantCodeGlobal').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                }
            },
            error: function() {
                $('#alertContainerEditTenantCodeGlobal').html('<div class="alert alert-danger alert-dismissible fade show" role="alert">Erro na comunicação com o servidor.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
        });
    });
 });