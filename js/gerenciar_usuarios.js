// Variáveis globais
let usuariosAtivos = [];
let usuariosInativos = [];

// Função para carregar usuários
function carregarUsuarios() {
    const tenantId = window.isSuperAdmin ? document.getElementById('filtroTenant')?.value || '' : '';
    
    // Carregar usuários ativos
    fetch(`api_usuarios.php?acao=listar_usuarios_ativos&id_tenants=${tenantId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                usuariosAtivos = data.usuarios;
                renderizarTabelaUsuarios('usuariosAtivosContainer', usuariosAtivos, true);
            } else {
                console.error('Erro ao carregar usuários ativos:', data.message);
                mostrarAlerta('Erro ao carregar usuários ativos: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
    
    // Carregar usuários inativos
    fetch(`api_usuarios.php?acao=listar_usuarios_inativos&id_tenants=${tenantId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                usuariosInativos = data.usuarios;
                renderizarTabelaUsuarios('usuariosInativosContainer', usuariosInativos, false);
            } else {
                console.error('Erro ao carregar usuários inativos:', data.message);
                mostrarAlerta('Erro ao carregar usuários inativos: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
}

// Função para renderizar tabela de usuários
function renderizarTabelaUsuarios(containerId, usuarios, isAtivo) {
    const container = document.getElementById(containerId);
    
    if (!usuarios || usuarios.length === 0) {
        container.innerHTML = `<p>Nenhum usuário ${isAtivo ? 'ativo' : 'inativo'} encontrado.</p>`;
        return;
    }
    
    let tableHtml = `
        <div class="table-responsive-sm">
            <table class="table table-striped table-hover table-sm">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Estabelecimento</th>
                        <th scope="col">Nível</th>
                        <th scope="col" style="width: 1%;">Ações</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    usuarios.forEach(usuario => {
        const nivelBadge = getNivelBadge(usuario.nivel);
        const acoes = isAtivo 
            ? getAcoesUsuarioAtivo(usuario)
            : getAcoesUsuarioInativo(usuario);
        
        tableHtml += `
            <tr>
                <td>
                    <button class="btn btn-link p-0 text-start" onclick="visualizarUsuario(${usuario.id})" title="Visualizar Detalhes">
                        ${escapeHtml(usuario.nome)}
                    </button>
                </td>
                <td>${escapeHtml(usuario.tenant_nome || 'N/A')}</td>
                <td>${nivelBadge}</td>
                <td>
                    <div class="btn-group" role="group">
                        ${acoes}
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Função para obter badge do nível
function getNivelBadge(nivel) {
    const badges = {
        'mc': '<span class="badge bg-info">MC</span>',
        'user': '<span class="badge bg-secondary">Usuário</span>',
        'admin': '<span class="badge bg-warning">Admin</span>',
        'super_admin': '<span class="badge bg-danger">Super Admin</span>'
    };
    return badges[nivel] || '<span class="badge bg-light text-dark">Desconhecido</span>';
}

// Função para obter ações de usuário ativo
function getAcoesUsuarioAtivo(usuario) {
    return `
        <button class="btn btn-sm btn-primary view-user-btn" onclick="visualizarUsuario(${usuario.id})" title="Visualizar Detalhes">
            <i class="bi bi-eye"></i>
        </button>
        <button class="btn btn-sm btn-warning edit-user-btn" onclick="editarUsuario(${usuario.id})" title="Editar Usuário">
            <i class="bi bi-pencil-square"></i>
        </button>
        <button class="btn btn-sm btn-info alter-level-btn" onclick="alterarNivel(${usuario.id}, '${escapeHtml(usuario.nome)}', '${usuario.nivel}')" title="Alterar Nível">
            <i class="bi bi-shield"></i>
        </button>
        <button class="btn btn-sm btn-secondary deactivate-user-btn" onclick="desativarUsuario(${usuario.id}, '${escapeHtml(usuario.nome)}')" title="Desativar Usuário">
            <i class="bi bi-box-arrow-down"></i>
        </button>
        <button class="btn btn-sm btn-danger delete-user-btn" onclick="excluirUsuario(${usuario.id}, '${escapeHtml(usuario.nome)}')" title="Excluir Permanentemente">
            <i class="bi bi-trash-fill"></i>
        </button>
    `;
}

// Função para obter ações de usuário inativo
function getAcoesUsuarioInativo(usuario) {
    return `
        <button class="btn btn-sm btn-primary view-user-btn" onclick="visualizarUsuario(${usuario.id})" title="Visualizar Detalhes">
            <i class="bi bi-eye"></i>
        </button>
        <button class="btn btn-sm btn-warning edit-user-btn" onclick="editarUsuario(${usuario.id})" title="Editar Usuário">
            <i class="bi bi-pencil-square"></i>
        </button>
        <button class="btn btn-sm btn-success reactivate-user-btn" onclick="reativarUsuario(${usuario.id}, '${escapeHtml(usuario.nome)}')" title="Reativar Usuário">
            <i class="bi bi-box-arrow-up"></i>
        </button>
        <button class="btn btn-sm btn-danger delete-user-btn" onclick="excluirUsuario(${usuario.id}, '${escapeHtml(usuario.nome)}')" title="Excluir Permanentemente">
            <i class="bi bi-trash-fill"></i>
        </button>
    `;
}

// Função para visualizar usuário
function visualizarUsuario(id) {
    fetch(`api_usuarios.php?acao=obter_usuario&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const usuario = data.usuario;
                
                document.getElementById('visualizar-nome').textContent = usuario.nome;
                document.getElementById('visualizar-telefone').textContent = usuario.telefone || 'N/A';
                document.getElementById('visualizar-cidade').textContent = usuario.cidade || 'N/A';
                document.getElementById('visualizar-uf').textContent = usuario.uf || 'N/A';
                document.getElementById('visualizar-tenant').textContent = usuario.tenant_nome || 'N/A';
                document.getElementById('visualizar-nivel').textContent = getNivelTexto(usuario.nivel);
                document.getElementById('visualizar-status').innerHTML = usuario.status == 1 
                    ? '<i class="bi bi-check-circle-fill text-success"></i> Ativo'
                    : '<i class="bi bi-x-circle-fill text-danger"></i> Inativo';
                
                new bootstrap.Modal(document.getElementById('modalVisualizarUsuario')).show();
            } else {
                mostrarAlerta('Erro ao carregar dados do usuário: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
}

// Função para editar usuário
function editarUsuario(id) {
    fetch(`api_usuarios.php?acao=obter_usuario&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const usuario = data.usuario;
                
                document.getElementById('editar-usuario-id').value = usuario.id;
                document.getElementById('editar-nome').value = usuario.nome;
                document.getElementById('editar-telefone').value = usuario.telefone || '';
                document.getElementById('editar-cidade').value = usuario.cidade || '';
                document.getElementById('editar-uf').value = usuario.uf || '';
                document.getElementById('editar-password').value = '';
                document.getElementById('editar-tenant-nome').textContent = usuario.tenant_nome || 'N/A';
                document.getElementById('editar-nivel-atual').textContent = getNivelTexto(usuario.nivel);
                
                new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
            } else {
                mostrarAlerta('Erro ao carregar dados do usuário: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
}

// Função para alterar nível
function alterarNivel(id, nome, nivelAtual) {
    document.getElementById('alterar-nivel-id').value = id;
    document.getElementById('alterar-nivel-nome').textContent = nome;
    document.getElementById('novoNivel').value = nivelAtual;
    
    new bootstrap.Modal(document.getElementById('modalAlterarNivel')).show();
}

// Função para desativar usuário
function desativarUsuario(id, nome) {
    if (confirm(`Tem certeza que deseja desativar o usuário "${nome}"?`)) {
        const formData = new FormData();
        formData.append('acao', 'desativar_usuario');
        formData.append('id', id);
        
        fetch('api_usuarios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarAlerta(data.message, 'success');
                carregarUsuarios();
            } else {
                mostrarAlerta('Erro: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
    }
}

// Função para reativar usuário
function reativarUsuario(id, nome) {
    if (confirm(`Tem certeza que deseja reativar o usuário "${nome}"?`)) {
        const formData = new FormData();
        formData.append('acao', 'reativar_usuario');
        formData.append('id', id);
        
        fetch('api_usuarios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarAlerta(data.message, 'success');
                carregarUsuarios();
            } else {
                mostrarAlerta('Erro: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
    }
}

// Função para excluir usuário
function excluirUsuario(id, nome) {
    if (confirm(`ATENÇÃO: Tem certeza que deseja EXCLUIR PERMANENTEMENTE o usuário "${nome}"?\n\nEsta ação não pode ser desfeita!`)) {
        const formData = new FormData();
        formData.append('acao', 'excluir_usuario');
        formData.append('id', id);
        
        fetch('api_usuarios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarAlerta(data.message, 'success');
                carregarUsuarios();
            } else {
                mostrarAlerta('Erro: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
    }
}

// Função para obter texto do nível
function getNivelTexto(nivel) {
    const niveis = {
        'mc': 'MC',
        'user': 'Usuário',
        'admin': 'Administrador',
        'super_admin': 'Super Administrador'
    };
    return niveis[nivel] || 'Desconhecido';
}

// Função para escapar HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}

// Função para mostrar alertas
function mostrarAlerta(mensagem, tipo) {
    // Remover alertas existentes
    const alertasExistentes = document.querySelectorAll('.alert-dismissible');
    alertasExistentes.forEach(alerta => alerta.remove());
    
    // Criar novo alerta
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} alert-dismissible fade show`;
    alerta.innerHTML = `
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Inserir no início do container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alerta, container.firstChild);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (alerta.parentNode) {
            alerta.remove();
        }
    }, 5000);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Form de editar usuário
    document.getElementById('formEditarUsuario').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('acao', 'editar_usuario');
        
        fetch('api_usuarios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarAlerta(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalEditarUsuario')).hide();
                carregarUsuarios();
            } else {
                mostrarAlerta('Erro: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
    });
    
    // Form de alterar nível
    document.getElementById('formAlterarNivel').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('acao', 'alterar_nivel');
        
        fetch('api_usuarios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarAlerta(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalAlterarNivel')).hide();
                carregarUsuarios();
            } else {
                mostrarAlerta('Erro: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarAlerta('Erro na comunicação com o servidor', 'danger');
        });
    });
});