<!-- Modal Editar Usuário -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarUsuario">
                <div class="modal-body">
                    <input type="hidden" id="editar-usuario-id" name="id">


                    
                        
                        <div class="mb-3 row">
                            <label for="editar-nome" class="col-sm-2 col-form-label">Nome</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="editar-nome" name="nome" required>
                            </div>
                        </div>
                      
                        <div class="mb-3 row">
                            <label for="editar-telefone" class="col-sm-2 col-form-label">Telefone</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="editar-telefone" name="telefone" placeholder="">
                            </div>
                        </div>

                        <div class="row">
                            <label for="editar-cidade" class="col-sm-2 col-form-label">Cidade</label>
                            <div class="col">
                                <input type="text" class="form-control" id="editar-cidade" name="cidade" required>
                            </div>
                            <label for="editar-uf" class="col-sm-1 col-form-label">UF</label>
                            <div class="col col-md-2 col-sm-2">
                                 <input type="text" class="form-control" id="editar-uf" name="uf" required maxlength="2">
                            </div>
                        </div>
                   
                    
                    
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="editar-password" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="editar-password" name="password" placeholder="Deixe em branco para manter a senha atual">
                                <div class="form-text">Deixe em branco se não quiser alterar a senha</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Informações do Sistema</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Estabelecimento:</strong> <span id="editar-tenant-nome"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Nível Atual:</strong> <span id="editar-nivel-atual"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para a modal de edição */
#modalEditarUsuario .form-label {
    font-weight: 600;
    color: #495057;
}

#modalEditarUsuario .form-control:focus,
#modalEditarUsuario .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

#modalEditarUsuario .modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

#modalEditarUsuario .modal-footer {
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

#modalEditarUsuario .btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

#modalEditarUsuario .btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}
</style>