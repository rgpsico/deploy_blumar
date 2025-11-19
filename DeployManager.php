<?php
require_once 'config.php';
require_once 'HistoryManager.php';
require_once 'FileDiffer.php';

/**
 * Sistema de Deploy com Versionamento
 */
class DeployManager {
    private $config;
    private $history;
    private $currentUser;

    public function __construct() {
        $this->config = Config::getInstance();
        $this->history = new HistoryManager($this->config->get('history_file'));
        $this->currentUser = $this->config->get('current_user');
    }

    /**
     * Push: Envia arquivos do ambiente local para outros ambientes
     */
    public function push($files, $sourceEnv, $targetEnvs, $createBackup = true, $force = false) {
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
    public function pull($files, $sourceEnv, $targetEnv = 'local', $createBackup = true, $force = false) {
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
    private function createBackup($files, $path, $env) {
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
    public function restore($backupFile, $targetEnv) {
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
    private function cleanOldBackups() {
        $maxBackups = $this->config->get('max_backups');
        $backupDir = $this->config->get('backup_dir');

        $backups = glob($backupDir . '*.zip');
        usort($backups, function($a, $b) {
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
    public function compare($files, $sourceEnv, $targetEnv) {
        $sourcePath = $this->config->getEnvironmentPath($sourceEnv);
        $targetPath = $this->config->getEnvironmentPath($targetEnv);

        return FileDiffer::compareBatch($files, $sourcePath, $targetPath, $sourceEnv, $targetEnv);
    }

    /**
     * Listar arquivos de um ambiente
     */
    public function listFiles($env, $folder = '', $includeSub = false) {
        $path = $this->config->getEnvironmentPath($env);
        if (!$path) return [];

        $fullPath = $path . $folder;
        if (!is_dir($fullPath)) return [];

        $files = [];
        
        if ($includeSub) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($path, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);
                    $files[] = [
                        'name' => $relativePath,
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime(),
                        'modified_formatted' => date('Y-m-d H:i:s', $file->getMTime())
                    ];
                }
            }
        } else {
            foreach (scandir($fullPath) as $item) {
                if ($item === '.' || $item === '..') continue;
                $itemPath = $fullPath . $item;
                
                if (is_file($itemPath)) {
                    $relativePath = $folder . $item;
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
        usort($files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });

        return $files;
    }

    /**
     * Listar diretórios de um ambiente
     */
    public function listDirs($env) {
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
    public function getHistory($limit = 50) {
        return $this->history->getHistory($limit);
    }

    /**
     * Obter estatísticas
     */
    public function getStats() {
        return $this->history->getStats();
    }

    /**
     * Log de operações
     */
    private function log($message) {
        $logFile = $this->config->get('log_file');
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [{$this->currentUser}] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
