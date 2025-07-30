<div class="modal fade" id="confirmResetModal" tabindex="-1" aria-labelledby="confirmResetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmResetModalLabel">Confirmar Reset do Sistema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger fw-bold">ATENÇÃO: Você está prestes a resetar o sistema!</p>
                <p>Esta ação é <span class="fw-bold text-danger">irreversível</span> e irá:</p>
                <ul>
                    <li>Apagar a ordem do próximo cantor na fila</li>
                    <li>Apagar o número de rodadas já cantadas.</li>
                    <li>Apagar todas as músicas da fila da rodada.</li>
                    <li>Esta ação <strong>não apaga</strong> as mesas, cantores ou a lista de músicas dos cantores.</li>
                </ul>
                <p>Tem certeza que deseja continuar?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarResetSistema">Resetar Sistema Agora</button>
            </div>
        </div>
    </div>
</div>