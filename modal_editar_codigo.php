<?php
// Verificar se o usuário tem acesso para editar código (apenas 'mc')
if (check_access(NIVEL_ACESSO, ['mc'])): ?>
<div class="modal fade" id="editTenantCodeModalGlobal" tabindex="-1" aria-labelledby="editTenantCodeModalGlobalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTenantCodeModalGlobalLabel">Editar Código do Estabelecimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="alertContainerEditTenantCodeGlobal"></div>
                <form id="editTenantCodeFormGlobal">
                    <div class="mb-3">
                        <label for="editTenantCodeInputGlobal" class="form-label">Código do Estabelecimento</label>
                        <input type="text" class="form-control" id="editTenantCodeInputGlobal" name="code" required>
                        <div class="form-text">Use apenas letras, números e underscores. Espaços serão convertidos automaticamente.</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="editTenantCodeStatusGlobal" checked>
                        <label class="form-check-label" for="editTenantCodeStatusGlobal">
                            Código ativo
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarEditCodigoGlobal">Salvar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>