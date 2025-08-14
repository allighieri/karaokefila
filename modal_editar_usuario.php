<!-- Modal Editar Usuário -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarUsuario">
                <div class="modal-body">
                    <input type="hidden" id="editar-usuario-id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editar-nome" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="editar-nome" name="nome" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editar-telefone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="editar-telefone" name="telefone" placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="editar-cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="editar-cidade" name="cidade">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editar-uf" class="form-label">UF</label>
                                <select class="form-select" id="editar-uf" name="uf">
                                    <option value="">Selecione...</option>
                                    <option value="AC">AC</option>
                                    <option value="AL">AL</option>
                                    <option value="AP">AP</option>
                                    <option value="AM">AM</option>
                                    <option value="BA">BA</option>
                                    <option value="CE">CE</option>
                                    <option value="DF">DF</option>
                                    <option value="ES">ES</option>
                                    <option value="GO">GO</option>
                                    <option value="MA">MA</option>
                                    <option value="MT">MT</option>
                                    <option value="MS">MS</option>
                                    <option value="MG">MG</option>
                                    <option value="PA">PA</option>
                                    <option value="PB">PB</option>
                                    <option value="PR">PR</option>
                                    <option value="PE">PE</option>
                                    <option value="PI">PI</option>
                                    <option value="RJ">RJ</option>
                                    <option value="RN">RN</option>
                                    <option value="RS">RS</option>
                                    <option value="RO">RO</option>
                                    <option value="RR">RR</option>
                                    <option value="SC">SC</option>
                                    <option value="SP">SP</option>
                                    <option value="SE">SE</option>
                                    <option value="TO">TO</option>
                                </select>
                            </div>
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