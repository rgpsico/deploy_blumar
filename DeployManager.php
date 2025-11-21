<?php
require_once 'config.php';
require_once 'HistoryManager.php';
require_once 'FileDiffer.php';

/**
 * Sistema de Deploy com Versionamento
 */
class DeployManager
{
    private $config;
    private $history;
    private $currentUser;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->history = new HistoryManager($this->config->get('history_file'));
        $this->currentUser = $this->config->get('current_user');
    }

    /**
     * Push: Envia arquivos do ambiente local para outros ambientes
     */
    public function push($files, $sourceEnv, $targetEnvs, $createBackup = true, $force = false)
    {
        $results = [
            'success' => [],
            'errors' => [],
            'conflicts' => [],
            'backups' => []
        ];

        // Validar ambiente de origem
        $sourcePath = $this->config->getEnvironmentPath($sourceEnv);
        if (!$sourcePath) {
            $results['errors'][] = "Ambiente de origem inválido: $sourceEnv";
            return $results;
        }

        if (!is_dir($sourcePath)) {
            $results['errors'][] = "Caminho de origem não existe ou não é acessível: $sourcePath";
            return $results;
        }

        foreach ($targetEnvs as $targetEnv) {
            $targetPath = $this->config->getEnvironmentPath($targetEnv);

            if (!$targetPath) {
                $results['errors'][] = "Ambiente $targetEnv inválido";
                continue;
            }

            // Validar se o caminho de destino existe
            if (!is_dir($targetPath)) {
                $results['errors'][] = "Caminho de destino não existe: $targetPath (ambiente: $targetEnv)";
                continue;
            }

            // Verificar se tem permissão de escrita
            if (!is_writable($targetPath)) {
                $results['errors'][] = "Sem permissão de escrita em: $targetPath (ambiente: $targetEnv)";
                continue;
            }

            // Verificar conflitos
            if (!$force) {
                $conflicts = FileDiffer::checkConflicts($files, $sourcePath, $targetPath);
                if (!empty($conflicts)) {
                    $results['conflicts'][$targetEnv] = $conflicts;
                    continue;
                }
            }

            // Criar backup antes de sobrescrever
            $backupFile = null;
            if ($createBackup) {
                try {
                    $backupFile = $this->createBackup($files, $targetPath, $targetEnv);
                    $results['backups'][$targetEnv] = $backupFile;
                } catch (Exception $e) {
                    $results['errors'][] = "Erro ao criar backup para $targetEnv: " . $e->getMessage();
                    continue;
                }
            }

            // Copiar arquivos
            $copied = [];
            $errors = [];

            foreach ($files as $file) {
                $sourceFile = $sourcePath . $file;
                $targetFile = $targetPath . $file;

                if (!file_exists($sourceFile)) {
                    $errors[] = "Arquivo $file não encontrado na origem";
                    continue;
                }

                // Criar diretórios necessários
                $targetDir = dirname($targetFile);

                // Validar caminho antes de criar diretório
                if (empty($targetDir) || $targetDir === '.' || $targetDir === '..') {
                    $errors[] = "Caminho de destino inválido para: $file";
                    continue;
                }

                if (!is_dir($targetDir)) {
                    try {
                        if (!@mkdir($targetDir, 0755, true)) {
                            $error = error_get_last();
                            $errors[] = "Erro ao criar diretório para $file: " . ($error['message'] ?? 'Erro desconhecido');
                            continue;
                        }
                    } catch (Exception $e) {
                        $errors[] = "Erro ao criar diretório para $file: " . $e->getMessage();
                        continue;
                    }
                }

                if (@copy($sourceFile, $targetFile)) {
                    $copied[] = $file;
                    $this->log("PUSH: $sourceEnv → $targetEnv: $file");
                } else {
                    $error = error_get_last();
                    $errors[] = "Erro ao copiar $file: " . ($error['message'] ?? 'Erro desconhecido');
                }
            }

            // Registrar no histórico
            if (!empty($copied)) {
                try {
                    $historyId = $this->history->addEntry(
                        'push',
                        $copied,
                        $sourceEnv,
                        $targetEnv,
                        $this->currentUser,
                        count($copied) . ' arquivo(s) enviado(s)'
                    );

                    if ($backupFile) {
                        $this->history->setBackupFile($historyId, $backupFile);
                    }

                    $results['success'][$targetEnv] = $copied;
                } catch (Exception $e) {
                    $results['errors'][] = "Erro ao registrar histórico: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                $results['errors'][$targetEnv] = $errors;
            }
        }

        return $results;
    }

    /**
     * Pull: Baixa arquivos de outros ambientes para o local
     */
    public function pull($files, $sourceEnv, $targetEnv = 'local', $createBackup = true, $force = false)
    {
        $sourcePath = $this->config->getEnvironmentPath($sourceEnv);
        $targetPath = $this->config->getEnvironmentPath($targetEnv);

        if (!$sourcePath || !$targetPath) {
            return ['error' => 'Ambiente inválido'];
        }

        $results = [
            'success' => [],
            'errors' => [],
            'conflicts' => [],
            'backup' => null
        ];

        // Verificar conflitos
        if (!$force) {
            $conflicts = FileDiffer::checkConflicts($files, $sourcePath, $targetPath);
            if (!empty($conflicts)) {
                $results['conflicts'] = $conflicts;
                return $results;
            }
        }

        // Criar backup
        if ($createBackup) {
            $results['backup'] = $this->createBackup($files, $targetPath, $targetEnv);
        }

        // Baixar arquivos
        foreach ($files as $file) {
            $sourceFile = $sourcePath . $file;
            $targetFile = $targetPath . $file;

            if (!file_exists($sourceFile)) {
                $results['errors'][] = "Arquivo $file não encontrado em $sourceEnv";
                continue;
            }

            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if (copy($sourceFile, $targetFile)) {
                $results['success'][] = $file;
                $this->log("PULL: $sourceEnv → $targetEnv: $file");
            } else {
                $results['errors'][] = "Erro ao baixar $file";
            }
        }

        // Registrar no histórico
        if (!empty($results['success'])) {
            $historyId = $this->history->addEntry(
                'pull',
                $results['success'],
                $sourceEnv,
                $targetEnv,
                $this->currentUser,
                count($results['success']) . ' arquivo(s) baixado(s)'
            );

            if ($results['backup']) {
                $this->history->setBackupFile($historyId, $results['backup']);
            }
        }

        return $results;
    }

    /**
     * Criar backup de arquivos
     */
    private function createBackup($files, $path, $env)
    {
        $backupDir = $this->config->get('backup_dir');
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . "{$this->currentUser}_backup_{$timestamp}_{$env}.zip";

        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            return null;
        }

        foreach ($files as $file) {
            $fullPath = $path . $file;
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $file);
            }
        }

        $zip->close();

        $this->log("BACKUP criado: $backupFile");
        $this->cleanOldBackups();

        return basename($backupFile);
    }

    /**
     * Restaurar de um backup
     */
    public function restore($backupFile, $targetEnv)
    {
        $backupPath = $this->config->get('backup_dir') . $backupFile;

        if (!file_exists($backupPath)) {
            return ['error' => 'Backup não encontrado'];
        }

        $targetPath = $this->config->getEnvironmentPath($targetEnv);
        if (!$targetPath) {
            return ['error' => 'Ambiente inválido'];
        }

        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== TRUE) {
            return ['error' => 'Erro ao abrir backup'];
        }

        $extracted = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $targetFile = $targetPath . $filename;

            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if ($zip->extractTo($targetPath, $filename)) {
                $extracted[] = $filename;
            }
        }

        $zip->close();

        // Registrar restauração
        $this->history->addEntry(
            'restore',
            $extracted,
            'backup',
            $targetEnv,
            $this->currentUser,
            "Restaurado de $backupFile"
        );

        $this->log("RESTORE: $backupFile → $targetEnv");

        return ['success' => $extracted];
    }

    /**
     * Limpar backups antigos
     */
    private function cleanOldBackups()
    {
        $maxBackups = $this->config->get('max_backups');
        $backupDir = $this->config->get('backup_dir');

        $backups = glob($backupDir . '*.zip');
        usort($backups, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $toDelete = array_slice($backups, $maxBackups);
        foreach ($toDelete as $file) {
            unlink($file);
            $this->log("CLEANUP: Backup removido - " . basename($file));
        }
    }

    /**
     * Comparar arquivos entre ambientes
     */
    public function compare($files, $sourceEnv, $targetEnv)
    {
        $sourcePath = $this->config->getEnvironmentPath($sourceEnv);
        $targetPath = $this->config->getEnvironmentPath($targetEnv);

        return FileDiffer::compareBatch($files, $sourcePath, $targetPath, $sourceEnv, $targetEnv);
    }

    /**
     * Listar arquivos de um ambiente
     */
    public function listFiles($env, $folder = '', $includeSub = false)
    {
        $path = $this->config->getEnvironmentPath($env);
        if (!$path) return [];

        // Garantir separador de diretório correto
        if (!empty($folder)) {
            // Remover barras do início e fim
            $folder = trim($folder, '/\\');
            // Adicionar barra no final
            $folder = $folder . DIRECTORY_SEPARATOR;
        }

        $fullPath = $path . $folder;
        if (!is_dir($fullPath)) {
            // Debug: log do caminho que está tentando acessar
            error_log("DeployManager::listFiles - Caminho não encontrado: $fullPath");
            return [];
        }

        $files = [];

        if ($includeSub) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($path, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);
                    // Remover barra inicial se houver
                    $relativePath = ltrim($relativePath, '/');
                    $files[] = [
                        'name' => $relativePath,
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime(),
                        'modified_formatted' => date('Y-m-d H:i:s', $file->getMTime())
                    ];
                }
            }
        } else {
            $items = @scandir($fullPath);
            if (!$items) {
                error_log("DeployManager::listFiles - Não foi possível ler: $fullPath");
                return [];
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $itemPath = $fullPath . $item;

                if (is_file($itemPath)) {
                    // Montar caminho relativo correto
                    $relativePath = !empty($folder) ? $folder . $item : $item;
                    // Normalizar barras
                    $relativePath = str_replace('\\', '/', $relativePath);

                    $files[] = [
                        'name' => $relativePath,
                        'size' => filesize($itemPath),
                        'modified' => filemtime($itemPath),
                        'modified_formatted' => date('Y-m-d H:i:s', filemtime($itemPath))
                    ];
                }
            }
        }

        // Ordenar por data (mais recentes primeiro)
        usort($files, function ($a, $b) {
            return $b['modified'] - $a['modified'];
        });

        return $files;
    }

    /**
     * Listar diretórios de um ambiente
     */
    public function listDirs($env)
    {
        $path = $this->config->getEnvironmentPath($env);
        if (!$path || !is_dir($path)) return [];

        $dirs = [''];
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir($path . $item)) {
                $dirs[] = $item;
            }
        }

        return $dirs;
    }

    /**
     * Obter histórico
     */
    public function getHistory($limit = 50)
    {
        return $this->history->getHistory($limit);
    }

    /**
     * Obter estatísticas
     */
    public function getStats()
    {
        return $this->history->getStats();
    }

    /**
     * Listar todos os projetos customizados do .env
     */
    public function listProjects()
    {
        $projects = [];

        // Buscar todos os projetos customizados do .env
        // Formato: PROJECT_NOME="caminho"
        foreach ($_ENV as $key => $value) {
            if (strpos($key, 'PROJECT_') === 0) {
                $projectName = str_replace('PROJECT_', '', $key);
                $projectName = str_replace('_', ' ', $projectName);
                $projectName = ucwords(strtolower($projectName));

                $path = $this->config->normalizePathPublic($value);

                if (is_dir($path)) {
                    $projects[] = [
                        'name' => $projectName,
                        'path' => $path,
                        'env' => 'custom',
                        'file_count' => $this->countFiles($path),
                        'last_modified' => $this->getLastModified($path),
                        'exists' => true
                    ];
                } else {
                    // Mostrar mesmo se não existir (com aviso)
                    $projects[] = [
                        'name' => $projectName . ' ⚠️',
                        'path' => $path,
                        'env' => 'custom',
                        'file_count' => 0,
                        'last_modified' => null,
                        'exists' => false
                    ];
                }
            }
        }

        // Adicionar também os caminhos de deploy como projetos
        // Projeto local
        $localPath = $this->config->get('local_path');
        if ($localPath && is_dir($localPath)) {
            $projects[] = [
                'name' => 'Deploy Local',
                'path' => $localPath,
                'env' => 'local',
                'file_count' => $this->countFiles($localPath),
                'last_modified' => $this->getLastModified($localPath),
                'exists' => true
            ];
        }

        // Ambientes de desenvolvimento
        $devPaths = $this->config->get('dev_paths');
        if ($devPaths && is_array($devPaths)) {
            foreach ($devPaths as $envName => $envPath) {
                if (is_dir($envPath)) {
                    $projects[] = [
                        'name' => 'Deploy ' . $envName,
                        'path' => $envPath,
                        'env' => strtolower($envName),
                        'file_count' => $this->countFiles($envPath),
                        'last_modified' => $this->getLastModified($envPath),
                        'exists' => true
                    ];
                }
            }
        }

        // Produção
        $prodPath = $this->config->get('prod_path');
        if ($prodPath && is_dir($prodPath)) {
            $projects[] = [
                'name' => 'Deploy Produção',
                'path' => $prodPath,
                'env' => 'producao',
                'file_count' => $this->countFiles($prodPath),
                'last_modified' => $this->getLastModified($prodPath),
                'exists' => true
            ];
        }

        return $projects;
    }

    /**
     * Contar arquivos em um diretório (não recursivo)
     */
    private function countFiles($path)
    {
        if (!is_dir($path)) return 0;

        $count = 0;
        $items = @scandir($path);

        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                if (is_file($path . $item)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Obter data da última modificação
     */
    private function getLastModified($path)
    {
        if (!is_dir($path)) return null;

        $latest = 0;
        $items = @scandir($path);

        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $itemPath = $path . $item;
                if (is_file($itemPath)) {
                    $mtime = filemtime($itemPath);
                    if ($mtime > $latest) {
                        $latest = $mtime;
                    }
                }
            }
        }

        return $latest > 0 ? date('d/m/Y H:i', $latest) : null;
    }

    /**
     * Obter subdiretórios de primeiro nível
     */
    private function getSubdirectories($path, $maxDepth = 1)
    {
        if (!is_dir($path)) return [];

        $subdirs = [];
        $items = @scandir($path);

        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $path . $item;
                if (is_dir($itemPath)) {
                    $subdirs[] = [
                        'name' => $item,
                        'path' => $itemPath . DIRECTORY_SEPARATOR,
                        'file_count' => $this->countFiles($itemPath . DIRECTORY_SEPARATOR),
                        'last_modified' => $this->getLastModified($itemPath . DIRECTORY_SEPARATOR)
                    ];
                }
            }
        }

        return $subdirs;
    }

    /**
     * Log de operações
     */
    private function log($message)
    {
        $logFile = $this->config->get('log_file');
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [{$this->currentUser}] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
