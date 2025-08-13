<?php
// Verificar se o usuário tem acesso para adicionar repertório (mc e admin)
if (check_access(NIVEL_ACESSO, ['mc', 'admin'])): ?>
<div class="modal fade" id="addRepertorioModal" tabindex="-1" aria-labelledby="addRepertorioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRepertorioModalLabel">Importar Repertório</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="alertContainerAddRepertorio"></div>
                <form id="addRepertorioForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="repertorioFile" class="form-label">Arquivo Excel (.xls ou .xlsx)</label>
                        <input type="file" class="form-control" id="repertorioFile" name="repertorio_file" accept=".xls,.xlsx" required>
                        <div class="form-text mt-3 fs-8">
                            <p><i class="fas fa-info-circle"></i> Para adicionar as músicas do seu aparelho de karaokê ao sistema, primeiro acesse sua conta 
                                <a href="https://ivideoke.com.br/minha-conta/login" target="_blank" title="Conta iVideoke para baixar o repertório">iVideoke</a>, 
                                e vá até o menu Minha Conta > Meus Aparelhos, clique em Formato na coluna Listagem e escolha XLS. 
                            </p>
                            <p>
                                O arquivo será enviado para o e-mail cadastro no iVideoke. Baixe do seu e-mail o arquivo e salve no seu PC ou Celular.
                            </p>
                            <p>
                                Depois, basta clicar em Procurar e selecionar o arquivo aqui nesse input.
                            </p>    
                        </div>
                    </div>

                    <div class="progress d-none" id="progressContainer">
                        <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnImportarRepertorio">Importar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>