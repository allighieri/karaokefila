// Gerenciamento do modal de importação de repertório

// Função para exibir alertas no modal
function showAlertAddRepertorio(message, type = 'danger') {
    const alertContainer = document.getElementById('alertContainerAddRepertorio');
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    alertContainer.innerHTML = alertHtml;
}

// Função para limpar alertas
function clearAlertsAddRepertorio() {
    document.getElementById('alertContainerAddRepertorio').innerHTML = '';
}

// Função para mostrar/esconder progress bar
function toggleProgressBar(show, progress = 0) {
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = progressContainer.querySelector('.progress-bar');
    
    if (show) {
        progressContainer.classList.remove('d-none');
        progressBar.style.width = progress + '%';
        progressBar.setAttribute('aria-valuenow', progress);
        progressBar.textContent = progress + '%';
    } else {
        progressContainer.classList.add('d-none');
    }
}

// Função para resetar o formulário
function resetFormAddRepertorio() {
    document.getElementById('addRepertorioForm').reset();
    clearAlertsAddRepertorio();
    toggleProgressBar(false);
}

// Função para validar arquivo
function validateFile(file) {
    const allowedTypes = [
        'application/vnd.ms-excel', // .xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // .xlsx
    ];
    
    const allowedExtensions = ['.xls', '.xlsx'];
    const fileName = file.name.toLowerCase();
    const hasValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
    
    if (!hasValidExtension || !allowedTypes.includes(file.type)) {
        return false;
    }
    
    // Verificar tamanho do arquivo (máximo 10MB)
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        showAlertAddRepertorio('O arquivo é muito grande. Tamanho máximo permitido: 10MB');
        return false;
    }
    
    return true;
}

// Função para processar o arquivo Excel
function processExcelFile(file, substituirDuplicados) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                
                // Pegar a primeira planilha
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                
                // Converter para JSON
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                
                if (jsonData.length < 2) {
                    reject('O arquivo deve conter pelo menos uma linha de cabeçalho e uma linha de dados.');
                    return;
                }
                
                // Assumir que a primeira linha são os cabeçalhos
                const headers = jsonData[0].map(h => h ? h.toString().toLowerCase().trim() : '');
                const rows = jsonData.slice(1);
                
                // Mapear colunas necessárias
                const columnMap = {
                    interprete: findColumnIndex(headers, ['interprete', 'artista', 'cantor']),
                    codigo: findColumnIndex(headers, ['codigo', 'código', 'code', 'id']),
                    nome: findColumnIndex(headers, ['nome', 'titulo', 'título', 'title', 'musica', 'música']),
                    trecho: findColumnIndex(headers, ['trecho', 'letra', 'lyrics']),
                    idioma: findColumnIndex(headers, ['idioma', 'language', 'lang'])
                };
                
                // Verificar se as colunas obrigatórias foram encontradas
                const requiredColumns = ['codigo', 'nome'];
                const missingColumns = requiredColumns.filter(col => columnMap[col] === -1);
                
                if (missingColumns.length > 0) {
                    reject(`Colunas obrigatórias não encontradas: ${missingColumns.join(', ')}`);
                    return;
                }
                
                // Processar dados
                const processedData = [];
                
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    
                    // Pular linhas vazias
                    if (!row || row.every(cell => !cell)) continue;
                    
                    const musica = {
                        codigo: row[columnMap.codigo] ? String(row[columnMap.codigo]).trim() : '',
                        titulo: row[columnMap.nome] ? String(row[columnMap.nome]).trim() : '',
                        artista: columnMap.interprete !== -1 && row[columnMap.interprete] ? String(row[columnMap.interprete]).trim() : '',
                        trecho: columnMap.trecho !== -1 && row[columnMap.trecho] ? String(row[columnMap.trecho]).trim() : '',
                        idioma: columnMap.idioma !== -1 && row[columnMap.idioma] ? String(row[columnMap.idioma]).trim() : 'Português'
                    };
                    
                    // Validar dados obrigatórios
                    if (!musica.codigo || !musica.titulo) {
                        console.warn(`Linha ${i + 2} ignorada: código ou título em branco`);
                        continue;
                    }
                    
                    processedData.push(musica);
                }
                
                if (processedData.length === 0) {
                    reject('Nenhum dado válido encontrado no arquivo.');
                    return;
                }
                
                resolve(processedData);
                
            } catch (error) {
                reject('Erro ao processar o arquivo: ' + error.message);
            }
        };
        
        reader.onerror = function() {
            reject('Erro ao ler o arquivo.');
        };
        
        reader.readAsArrayBuffer(file);
    });
}

// Função auxiliar para encontrar índice da coluna
function findColumnIndex(headers, possibleNames) {
    for (let name of possibleNames) {
        const index = headers.findIndex(h => h.includes(name));
        if (index !== -1) return index;
    }
    return -1;
}

// Função para enviar dados para o servidor
function sendDataToServer(musicasData) {
    return fetch('api_add_repertorio.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'import_repertorio',
            musicas: musicasData
        })
    })
    .then(response => response.json());
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('addRepertorioModal');
    const btnImportar = document.getElementById('btnImportarRepertorio');
    const fileInput = document.getElementById('repertorioFile');
    
    // Resetar formulário quando modal é fechado
    modal.addEventListener('hidden.bs.modal', function() {
        resetFormAddRepertorio();
    });
    
    // Validar arquivo quando selecionado
    fileInput.addEventListener('change', function() {
        clearAlertsAddRepertorio();
        
        if (this.files.length > 0) {
            const file = this.files[0];
            if (!validateFile(file)) {
                this.value = '';
                showAlertAddRepertorio('Arquivo inválido. Selecione um arquivo Excel (.xls ou .xlsx).');
            }
        }
    });
    
    // Processar importação
    btnImportar.addEventListener('click', async function() {
        clearAlertsAddRepertorio();
        
        const fileInput = document.getElementById('repertorioFile');
        
        if (!fileInput.files.length) {
            showAlertAddRepertorio('Selecione um arquivo para importar.');
            return;
        }
        
        const file = fileInput.files[0];
        
        if (!validateFile(file)) {
            return;
        }
        
        // Desabilitar botão e mostrar progresso
        btnImportar.disabled = true;
        btnImportar.textContent = 'Processando...';
        toggleProgressBar(true, 10);
        
        try {
            // Processar arquivo Excel
            toggleProgressBar(true, 30);
            const musicasData = await processExcelFile(file);
            
            toggleProgressBar(true, 60);
            showAlertAddRepertorio(`${musicasData.length} músicas encontradas. Enviando para o servidor...`, 'info');
            
            // Enviar para servidor
            toggleProgressBar(true, 80);
            const response = await sendDataToServer(musicasData);
            
            toggleProgressBar(true, 100);
            
            if (response.success) {
                showAlertAddRepertorio(response.message, 'success');
                
                // Fechar modal após 2 segundos
                setTimeout(() => {
                    bootstrap.Modal.getInstance(modal).hide();
                    // Recarregar página se estivermos na página de músicas
                    if (window.location.pathname.includes('musicas') || window.location.pathname.includes('cantores')) {
                        window.location.reload();
                    }
                }, 2000);
            } else {
                showAlertAddRepertorio(response.message || 'Erro ao importar repertório.');
            }
            
        } catch (error) {
            showAlertAddRepertorio(error);
        } finally {
            // Reabilitar botão
            btnImportar.disabled = false;
            btnImportar.textContent = 'Importar';
            toggleProgressBar(false);
        }
    });
});