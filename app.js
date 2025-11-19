/**
 * Sistema de Deploy & Sincronização - Blumar
 * JavaScript Principal
 */

// Estado global
const AppState = {
    currentEnv: 'local',
    currentFolder: '',
    files: [],
    selectedFiles: [],
    pullFiles: [],
    selectedPullFiles: [],
    history: [],
    stats: {}
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Iniciando Sistema de Deploy');
    
    loadFolders();
    loadPullFolders(); // Carregar pastas para aba Sync
    loadHistory();
    setupEventListeners();
});

// Event Listeners
function setupEventListeners() {
    document.getElementById('sourceEnv').addEventListener('change', () => {
        loadFolders();
    });

    document.getElementById('sourceFolder').addEventListener('change', () => {
        loadFiles();
    });

    document.getElementById('includeSubfolders').addEventListener('change', () => {
        loadFiles();
    });

    document.getElementById('searchFiles').addEventListener('input', (e) => {
        filterFiles(e.target.value);
    });

    document.getElementById('sortOrder').addEventListener('change', () => {
        sortFiles();
    });

    // Event listeners para aba Pull
    document.getElementById('pullSearchFiles').addEventListener('input', (e) => {
        filterPullFiles(e.target.value);
    });
}

// Carregar pastas
async function loadFolders() {
    const env = document.getElementById('sourceEnv').value;
    const folderSelect = document.getElementById('sourceFolder');
    
    folderSelect.innerHTML = '<option>Carregando...</option>';
    
    try {
        const response = await axios.get('api.php', {
            params: { action: 'listDirs', env }
        });

        // Verificar se response.data é um array
        let dirs = response.data;
        
        if (!Array.isArray(dirs)) {
            console.warn('API não retornou array, usando array vazio', dirs);
            dirs = [];
        }

        let html = '<option value="">[Pasta Raiz]</option>';
        dirs.forEach(dir => {
            if (dir !== '') {
                html += `<option value="${dir}">${dir}</option>`;
            }
        });

        folderSelect.innerHTML = html;
        loadFiles();
        
    } catch (error) {
        console.error('Erro ao carregar pastas:', error);
        if (error.response) {
            console.error('Resposta do servidor:', error.response.data);
            showToast(`Erro: ${error.response.data.message || 'Erro ao carregar pastas'}`, 'error');
        } else {
            showToast('Erro ao carregar pastas - Verifique se o servidor está rodando', 'error');
        }
        folderSelect.innerHTML = '<option>Erro ao carregar</option>';
    }
}

// Carregar arquivos
async function loadFiles() {
    const env = document.getElementById('sourceEnv').value;
    const folder = document.getElementById('sourceFolder').value;
    const includeSub = document.getElementById('includeSubfolders').value === 'true';
    
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = `
        <div class="loading">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Carregando arquivos...</p>
        </div>
    `;
    
    try {
        const response = await axios.get('api.php', {
            params: { 
                action: 'listFiles',
                env,
                folder,
                includeSub
            }
        });

        // Validar que a resposta é um array
        let files = response.data;
        
        if (!Array.isArray(files)) {
            console.warn('API não retornou array de arquivos, usando array vazio', files);
            files = [];
        }

        AppState.files = files;
        renderFiles();
        
    } catch (error) {
        console.error('Erro ao carregar arquivos:', error);
        if (error.response) {
            console.error('Resposta do servidor:', error.response.data);
            showToast(`Erro: ${error.response.data.message || 'Erro ao carregar arquivos'}`, 'error');
        } else {
            showToast('Erro ao carregar arquivos - Verifique se o servidor está rodando', 'error');
        }
        fileList.innerHTML = '<div class="alert alert-danger">Erro ao carregar arquivos. Verifique o console para mais detalhes.</div>';
        AppState.files = [];
    }
}

// Renderizar lista de arquivos
function renderFiles() {
    const fileList = document.getElementById('fileList');
    
    // Garantir que files é um array
    let files = AppState.files;
    if (!Array.isArray(files)) {
        console.warn('AppState.files não é array, usando array vazio');
        files = [];
        AppState.files = [];
    }

    if (files.length === 0) {
        fileList.innerHTML = '<div class="text-center text-muted p-4">Nenhum arquivo encontrado</div>';
        document.getElementById('fileCount').textContent = '0';
        return;
    }

    let html = '';
    files.forEach((file, index) => {
        const isSelected = AppState.selectedFiles.includes(file.name);
        html += `
            <div class="file-item ${isSelected ? 'selected' : ''}" data-filename="${file.name}">
                <div class="file-info">
                    <label style="cursor: pointer; flex: 1; margin: 0;">
                        <input type="checkbox" 
                               class="file-check" 
                               value="${file.name}"
                               ${isSelected ? 'checked' : ''}
                               onchange="toggleFile('${file.name}')">
                        <span class="file-name">
                            <i class="bi bi-file-earmark-code"></i> ${file.name}
                        </span>
                    </label>
                    <span class="file-meta">
                        <i class="bi bi-clock"></i> ${file.modified_formatted}
                        <i class="bi bi-hdd ms-2"></i> ${formatBytes(file.size)}
                    </span>
                </div>
            </div>
        `;
    });

    fileList.innerHTML = html;
    document.getElementById('fileCount').textContent = files.length;
}

// Toggle seleção de arquivo
function toggleFile(filename) {
    const index = AppState.selectedFiles.indexOf(filename);
    if (index > -1) {
        AppState.selectedFiles.splice(index, 1);
    } else {
        AppState.selectedFiles.push(filename);
    }
    renderFiles();
}

// Selecionar todos
function selectAll() {
    AppState.selectedFiles = AppState.files.map(f => f.name);
    renderFiles();
    showToast(`${AppState.selectedFiles.length} arquivos selecionados`, 'success');
}

// Desselecionar todos
function deselectAll() {
    AppState.selectedFiles = [];
    renderFiles();
    showToast('Seleção limpa', 'info');
}

// Filtrar arquivos
function filterFiles(term) {
    const items = document.querySelectorAll('#fileList .file-item');
    const lowerTerm = term.toLowerCase();

    items.forEach(item => {
        const filename = item.dataset.filename.toLowerCase();
        item.style.display = filename.includes(lowerTerm) ? '' : 'none';
    });
}

// Filtrar arquivos do Pull
function filterPullFiles(term) {
    const items = document.querySelectorAll('#pullFileList .file-item');
    const lowerTerm = term.toLowerCase();

    items.forEach(item => {
        const filename = item.dataset.filename.toLowerCase();
        item.style.display = filename.includes(lowerTerm) ? '' : 'none';
    });
}

// Ordenar arquivos
function sortFiles() {
    const order = document.getElementById('sortOrder').value;
    
    if (order === 'name') {
        AppState.files.sort((a, b) => a.name.localeCompare(b.name));
    } else {
        AppState.files.sort((a, b) => b.modified - a.modified);
    }
    
    renderFiles();
}

// Verificar conflitos
async function checkConflicts() {
    if (AppState.selectedFiles.length === 0) {
        showToast('Selecione ao menos um arquivo', 'warning');
        return;
    }

    const destinos = getSelectedDestinations();
    if (destinos.length === 0) {
        showToast('Selecione ao menos um destino', 'warning');
        return;
    }

    showLoading('Verificando conflitos...');

    try {
        const sourceEnv = document.getElementById('sourceEnv').value;
        
        for (const dest of destinos) {
            const response = await axios.post('api.php', {
                action: 'checkConflicts',
                files: AppState.selectedFiles,
                sourceEnv,
                targetEnv: dest
            });

            if (response.data.conflicts && response.data.conflicts.length > 0) {
                showConflicts(response.data.conflicts, dest);
            }
        }

        hideLoading();
        
    } catch (error) {
        console.error('Erro ao verificar conflitos:', error);
        showToast('Erro ao verificar conflitos', 'error');
        hideLoading();
    }
}

// Mostrar conflitos
function showConflicts(conflicts, env) {
    const panel = document.getElementById('conflictPanel');
    
    let html = `
        <div class="conflict-warning">
            <h5><i class="bi bi-exclamation-triangle"></i> Conflitos Encontrados em ${env}</h5>
            <p>Os seguintes arquivos têm versões mais recentes no destino:</p>
            <ul>
    `;

    conflicts.forEach(conflict => {
        html += `
            <li>
                <strong>${conflict.file}</strong><br>
                <small class="text-muted">
                    ${conflict.message}
                    ${conflict.source_time ? `<br>Origem: ${conflict.source_time} | Destino: ${conflict.target_time}` : ''}
                </small>
            </li>
        `;
    });

    html += `
            </ul>
            <div class="alert alert-warning mt-3">
                <i class="bi bi-lightbulb"></i> 
                <strong>Dica:</strong> Marque "Forçar sobrescrita" para ignorar estes conflitos.
            </div>
        </div>
    `;

    panel.innerHTML = html;
    panel.classList.add('show');
}

// Realizar deploy
async function performDeploy() {
    if (AppState.selectedFiles.length === 0) {
        showToast('Selecione ao menos um arquivo para enviar', 'warning');
        return;
    }

    const destinos = getSelectedDestinations();
    if (destinos.length === 0) {
        showToast('Selecione ao menos um destino', 'warning');
        return;
    }

    const sourceEnv = document.getElementById('sourceEnv').value;
    const createBackup = document.getElementById('createBackup').checked;
    const force = document.getElementById('forceOverwrite').checked;

    if (!confirm(`Confirma enviar ${AppState.selectedFiles.length} arquivo(s) para ${destinos.join(', ')}?`)) {
        return;
    }

    showLoading('Realizando deploy...');

    try {
        const response = await axios.post('api.php', {
            action: 'push',
            files: AppState.selectedFiles,
            sourceEnv,
            targetEnvs: destinos,
            createBackup,
            force
        });

        hideLoading();

        if (response.data.success) {
            showDeployResults(response.data);
            loadHistory();
            AppState.selectedFiles = [];
            renderFiles();
        } else {
            showToast('Erro no deploy', 'error');
        }

    } catch (error) {
        console.error('Erro no deploy:', error);
        showToast('Erro ao realizar deploy', 'error');
        hideLoading();
    }
}

// Mostrar resultados do deploy
function showDeployResults(results) {
    let message = '<h5>Resultado do Deploy</h5>';
    
    if (results.success && Object.keys(results.success).length > 0) {
        message += '<div class="alert alert-success">';
        message += '<strong>✓ Enviado com sucesso:</strong><ul>';
        for (const [env, files] of Object.entries(results.success)) {
            message += `<li>${env}: ${files.length} arquivo(s)</li>`;
        }
        message += '</ul></div>';
    }

    if (results.errors && Object.keys(results.errors).length > 0) {
        message += '<div class="alert alert-danger">';
        message += '<strong>✗ Erros:</strong><ul>';
        for (const [env, errors] of Object.entries(results.errors)) {
            message += `<li>${env}: ${errors.join(', ')}</li>`;
        }
        message += '</ul></div>';
    }

    if (results.conflicts && Object.keys(results.conflicts).length > 0) {
        message += '<div class="alert alert-warning">';
        message += '<strong>⚠ Conflitos (não enviado):</strong><ul>';
        for (const [env, conflicts] of Object.entries(results.conflicts)) {
            message += `<li>${env}: ${conflicts.length} conflito(s)</li>`;
        }
        message += '</ul></div>';
    }

    showModal('Resultado do Deploy', message);
}

// Realizar pull
async function performPull() {
    if (AppState.selectedPullFiles.length === 0) {
        showToast('Selecione ao menos um arquivo para baixar', 'warning');
        return;
    }

    const sourceEnv = document.getElementById('pullSourceEnv').value;
    const targetEnv = 'local';
    const createBackup = document.getElementById('pullCreateBackup').checked;
    const force = document.getElementById('pullForceOverwrite').checked;

    if (!confirm(`Confirma baixar ${AppState.selectedPullFiles.length} arquivo(s) de ${sourceEnv}?`)) {
        return;
    }

    showLoading('Baixando arquivos...');

    try {
        const response = await axios.post('api.php', {
            action: 'pull',
            files: AppState.selectedPullFiles,
            sourceEnv,
            targetEnv,
            createBackup,
            force
        });

        hideLoading();

        if (response.data.success && response.data.success.length > 0) {
            showToast(`${response.data.success.length} arquivo(s) baixado(s) com sucesso!`, 'success');
            loadHistory();
            AppState.selectedPullFiles = [];
            renderPullFiles();
        } else if (response.data.error) {
            showToast(`Erro: ${response.data.error}`, 'error');
        } else {
            showToast('Nenhum arquivo foi baixado', 'warning');
        }

    } catch (error) {
        console.error('Erro no pull:', error);
        if (error.response) {
            showToast(`Erro: ${error.response.data.message || 'Erro ao baixar arquivos'}`, 'error');
        } else {
            showToast('Erro ao baixar arquivos', 'error');
        }
        hideLoading();
    }
}

// Carregar pastas para Pull
async function loadPullFolders() {
    const env = document.getElementById('pullSourceEnv').value;
    const folderSelect = document.getElementById('pullSourceFolder');
    
    folderSelect.innerHTML = '<option>Carregando...</option>';
    
    try {
        const response = await axios.get('api.php', {
            params: { action: 'listDirs', env }
        });

        let dirs = response.data;
        
        if (!Array.isArray(dirs)) {
            console.warn('API não retornou array, usando array vazio', dirs);
            dirs = [];
        }

        let html = '<option value="">[Pasta Raiz]</option>';
        dirs.forEach(dir => {
            if (dir !== '') {
                html += `<option value="${dir}">${dir}</option>`;
            }
        });

        folderSelect.innerHTML = html;
        loadPullFiles();
        
    } catch (error) {
        console.error('Erro ao carregar pastas:', error);
        showToast('Erro ao carregar pastas', 'error');
        folderSelect.innerHTML = '<option>Erro ao carregar</option>';
    }
}

// Carregar arquivos para Pull
async function loadPullFiles() {
    const env = document.getElementById('pullSourceEnv').value;
    const folder = document.getElementById('pullSourceFolder').value;
    const includeSub = document.getElementById('pullIncludeSubfolders').value === 'true';
    
    const fileList = document.getElementById('pullFileList');
    fileList.innerHTML = `
        <div class="loading">
            <div class="spinner-border text-success" role="status"></div>
            <p class="mt-2">Carregando arquivos...</p>
        </div>
    `;
    
    try {
        const response = await axios.get('api.php', {
            params: { 
                action: 'listFiles',
                env,
                folder,
                includeSub
            }
        });

        let files = response.data;
        
        if (!Array.isArray(files)) {
            console.warn('API não retornou array de arquivos, usando array vazio', files);
            files = [];
        }

        AppState.pullFiles = files;
        AppState.selectedPullFiles = [];
        renderPullFiles();
        
    } catch (error) {
        console.error('Erro ao carregar arquivos:', error);
        fileList.innerHTML = '<div class="alert alert-danger">Erro ao carregar arquivos</div>';
        showToast('Erro ao carregar arquivos', 'error');
        AppState.pullFiles = [];
    }
}

// Renderizar arquivos para Pull
function renderPullFiles() {
    const fileList = document.getElementById('pullFileList');
    
    let files = AppState.pullFiles;
    if (!Array.isArray(files)) {
        console.warn('AppState.pullFiles não é array, usando array vazio');
        files = [];
        AppState.pullFiles = [];
    }

    if (files.length === 0) {
        fileList.innerHTML = '<div class="text-center text-muted p-4">Nenhum arquivo encontrado</div>';
        document.getElementById('pullFileCount').textContent = '0';
        return;
    }

    let html = '';
    files.forEach((file) => {
        const isSelected = AppState.selectedPullFiles.includes(file.name);
        html += `
            <div class="file-item ${isSelected ? 'selected' : ''}" data-filename="${file.name}">
                <div class="file-info">
                    <label style="cursor: pointer; flex: 1; margin: 0;">
                        <input type="checkbox" 
                               class="file-check" 
                               value="${file.name}"
                               ${isSelected ? 'checked' : ''}
                               onchange="togglePullFile('${file.name}')">
                        <span class="file-name">
                            <i class="bi bi-file-earmark-code"></i> ${file.name}
                        </span>
                    </label>
                    <span class="file-meta">
                        <i class="bi bi-clock"></i> ${file.modified_formatted}
                        <i class="bi bi-hdd ms-2"></i> ${formatBytes(file.size)}
                    </span>
                </div>
            </div>
        `;
    });

    fileList.innerHTML = html;
    document.getElementById('pullFileCount').textContent = files.length;
}

// Toggle arquivo para Pull
function togglePullFile(filename) {
    const index = AppState.selectedPullFiles.indexOf(filename);
    if (index > -1) {
        AppState.selectedPullFiles.splice(index, 1);
    } else {
        AppState.selectedPullFiles.push(filename);
    }
    renderPullFiles();
}

// Selecionar todos para Pull
function selectAllPull() {
    AppState.selectedPullFiles = AppState.pullFiles.map(f => f.name);
    renderPullFiles();
    showToast(`${AppState.selectedPullFiles.length} arquivos selecionados`, 'success');
}

// Desselecionar todos para Pull
function deselectAllPull() {
    AppState.selectedPullFiles = [];
    renderPullFiles();
    showToast('Seleção limpa', 'info');
}

// Verificar conflitos para Pull
async function checkPullConflicts() {
    if (AppState.selectedPullFiles.length === 0) {
        showToast('Selecione ao menos um arquivo', 'warning');
        return;
    }

    const sourceEnv = document.getElementById('pullSourceEnv').value;
    const targetEnv = 'local';

    showLoading('Verificando conflitos...');

    try {
        const response = await axios.post('api.php', {
            action: 'checkConflicts',
            files: AppState.selectedPullFiles,
            sourceEnv,
            targetEnv
        });

        hideLoading();

        if (response.data.conflicts && response.data.conflicts.length > 0) {
            showPullConflicts(response.data.conflicts);
        } else {
            showToast('Nenhum conflito encontrado!', 'success');
        }
        
    } catch (error) {
        console.error('Erro ao verificar conflitos:', error);
        showToast('Erro ao verificar conflitos', 'error');
        hideLoading();
    }
}

// Mostrar conflitos do Pull
function showPullConflicts(conflicts) {
    const panel = document.getElementById('pullConflictPanel');
    
    let html = `
        <div class="conflict-warning">
            <h5><i class="bi bi-exclamation-triangle"></i> Conflitos Encontrados</h5>
            <p>Os seguintes arquivos têm versões mais recentes no seu ambiente local:</p>
            <ul>
    `;

    conflicts.forEach(conflict => {
        html += `
            <li>
                <strong>${conflict.file}</strong><br>
                <small class="text-muted">
                    ${conflict.message}
                    ${conflict.source_time ? `<br>Remoto: ${conflict.source_time} | Local: ${conflict.target_time}` : ''}
                </small>
            </li>
        `;
    });

    html += `
            </ul>
            <div class="alert alert-warning mt-3">
                <i class="bi bi-lightbulb"></i> 
                <strong>Dica:</strong> Marque "Forçar sobrescrita" para ignorar estes conflitos.
            </div>
        </div>
    `;

    panel.innerHTML = html;
    panel.classList.add('show');
}

// Comparar ambientes
async function performCompare() {
    if (AppState.selectedFiles.length === 0) {
        showToast('Selecione arquivos para comparar', 'warning');
        return;
    }

    const envA = document.getElementById('compareEnvA').value;
    const envB = document.getElementById('compareEnvB').value;

    showLoading('Comparando...');

    try {
        const response = await axios.post('api.php', {
            action: 'compare',
            files: AppState.selectedFiles,
            sourceEnv: envA,
            targetEnv: envB
        });

        hideLoading();
        showCompareResults(response.data);

    } catch (error) {
        console.error('Erro ao comparar:', error);
        showToast('Erro na comparação', 'error');
        hideLoading();
    }
}

// Mostrar resultados da comparação
function showCompareResults(data) {
    const panel = document.getElementById('compareResults');
    
    let html = `
        <h5>Resultado da Comparação</h5>
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="text-center p-3 bg-success text-white rounded">
                    <h3>${data.identical}</h3>
                    <small>Idênticos</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-warning text-dark rounded">
                    <h3>${data.different}</h3>
                    <small>Diferentes</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-info text-white rounded">
                    <h3>${data.new}</h3>
                    <small>Novos</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-danger text-white rounded">
                    <h3>${data.not_found}</h3>
                    <small>Não Encontrados</small>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Status</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
    `;

    data.files.forEach(file => {
        let statusBadge = '';
        let details = '';

        switch(file.status) {
            case 'identical':
                statusBadge = '<span class="status-badge status-identical">Idêntico</span>';
                details = 'Arquivos iguais';
                break;
            case 'different':
                statusBadge = '<span class="status-badge status-different">Diferente</span>';
                details = `Origem: ${file.source.modified} | Destino: ${file.target.modified}`;
                break;
            case 'new_file':
                statusBadge = '<span class="status-badge status-new">Novo</span>';
                details = 'Não existe no destino';
                break;
            default:
                statusBadge = '<span class="badge bg-secondary">Não encontrado</span>';
                details = file.message;
        }

        html += `
            <tr>
                <td>${file.file}</td>
                <td>${statusBadge}</td>
                <td><small>${details}</small></td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    panel.innerHTML = html;
    panel.classList.add('show');
}

// Carregar histórico
async function loadHistory() {
    try {
        const response = await axios.get('api.php', {
            params: { action: 'getHistory', limit: 50 }
        });

        // Validar estrutura da resposta
        if (response.data && typeof response.data === 'object') {
            AppState.history = Array.isArray(response.data.history) ? response.data.history : [];
            AppState.stats = response.data.stats || {
                total_operations: 0,
                by_action: {},
                by_user: {},
                by_day: {}
            };
        } else {
            console.warn('Resposta inválida do histórico:', response.data);
            AppState.history = [];
            AppState.stats = {
                total_operations: 0,
                by_action: {},
                by_user: {},
                by_day: {}
            };
        }
        
        renderHistory();
        renderStats();

    } catch (error) {
        console.error('Erro ao carregar histórico:', error);
        if (error.response) {
            console.error('Resposta do servidor:', error.response.data);
        }
        
        // Definir valores padrão em caso de erro
        AppState.history = [];
        AppState.stats = {
            total_operations: 0,
            by_action: {},
            by_user: {},
            by_day: {}
        };
        
        renderHistory();
        renderStats();
    }
}

// Renderizar histórico
function renderHistory() {
    const historyList = document.getElementById('historyList');
    const history = AppState.history;

    if (!history || history.length === 0) {
        historyList.innerHTML = '<div class="text-center text-muted p-4">Nenhum registro encontrado</div>';
        return;
    }

    let html = '';
    history.forEach(entry => {
        const icon = entry.action === 'push' ? 'upload' : 
                     entry.action === 'pull' ? 'download' : 'clock-history';
        
        html += `
            <div class="history-item ${entry.action}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">
                            <i class="bi bi-${icon}"></i> 
                            ${entry.action.toUpperCase()} 
                            <span class="badge bg-secondary">${entry.file_count} arquivo(s)</span>
                        </h6>
                        <p class="mb-1 text-muted">
                            <i class="bi bi-arrow-right"></i> ${entry.from} → ${entry.to}
                        </p>
                        <small class="text-muted">
                            <i class="bi bi-person"></i> ${entry.user} | 
                            <i class="bi bi-clock"></i> ${entry.timestamp}
                        </small>
                        ${entry.notes ? `<p class="mt-2 mb-0"><small>${entry.notes}</small></p>` : ''}
                    </div>
                    ${entry.backup_file ? `
                        <button class="btn btn-sm btn-outline-warning" onclick="restoreBackup('${entry.backup_file}', '${entry.from}')">
                            <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    });

    historyList.innerHTML = html;
}

// Renderizar estatísticas
function renderStats() {
    const stats = AppState.stats || {
        total_operations: 0,
        by_action: {},
        by_user: {},
        by_day: {}
    };

    document.getElementById('statsTotal').textContent = stats.total_operations || 0;
    document.getElementById('statsPush').textContent = (stats.by_action && stats.by_action.push) || 0;
    document.getElementById('statsPull').textContent = (stats.by_action && stats.by_action.pull) || 0;
    document.getElementById('statsRestore').textContent = (stats.by_action && stats.by_action.restore) || 0;
}

// Restaurar backup
async function restoreBackup(backupFile, targetEnv) {
    if (!confirm(`Confirma restaurar o backup ${backupFile}?`)) {
        return;
    }

    showLoading('Restaurando backup...');

    try {
        const response = await axios.post('api.php', {
            action: 'restore',
            backupFile,
            targetEnv
        });

        hideLoading();

        if (response.data.success) {
            showToast(`Backup restaurado: ${response.data.success.length} arquivo(s)`, 'success');
            loadHistory();
        } else {
            showToast('Erro ao restaurar backup', 'error');
        }

    } catch (error) {
        console.error('Erro ao restaurar:', error);
        showToast('Erro ao restaurar backup', 'error');
        hideLoading();
    }
}

// Obter destinos selecionados
function getSelectedDestinations() {
    const destinos = [];
    document.querySelectorAll('input[id^="dest-"]:checked').forEach(checkbox => {
        destinos.push(checkbox.value);
    });
    return destinos;
}

// Utilidades
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show`;
    toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}

function showLoading(message) {
    const loading = document.createElement('div');
    loading.id = 'globalLoading';
    loading.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7); z-index: 9998;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 20px;
    `;
    loading.innerHTML = `
        <div class="text-center">
            <div class="spinner-border mb-3" style="width: 3rem; height: 3rem;"></div>
            <div>${message}</div>
        </div>
    `;
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('globalLoading');
    if (loading) loading.remove();
}

function showModal(title, content) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${title}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">${content}</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', () => {
        modal.remove();
    });
}
