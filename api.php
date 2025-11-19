<?php
/**
 * API REST - Sistema de Deploy & Sincronização
 * Versão corrigida com melhor tratamento de erros
 */

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratar OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // Verificar se os arquivos necessários existem
    $requiredFiles = ['config.php', 'DeployManager.php'];
    foreach ($requiredFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            throw new Exception("Arquivo necessário não encontrado: $file");
        }
    }

    require_once 'DeployManager.php';
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        handleGetRequest();
    } elseif ($method === 'POST') {
        handlePostRequest();
    } else {
        throw new Exception('Método HTTP não permitido: ' . $method);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
    exit;
}

function handleGetRequest() {
    $action = $_GET['action'] ?? '';
    
    try {
        $deploy = new DeployManager();
        
        switch ($action) {
            case 'listDirs':
                $env = $_GET['env'] ?? 'local';
                $dirs = $deploy->listDirs($env);
                
                // Garantir que retorna array
                if (!is_array($dirs)) {
                    $dirs = [];
                }
                
                echo json_encode($dirs);
                break;
                
            case 'listFiles':
                $env = $_GET['env'] ?? 'local';
                $folder = $_GET['folder'] ?? '';
                $includeSub = ($_GET['includeSub'] ?? 'false') === 'true';
                
                $files = $deploy->listFiles($env, $folder, $includeSub);
                
                // Garantir que retorna array
                if (!is_array($files)) {
                    $files = [];
                }
                
                echo json_encode($files);
                break;
                
            case 'getHistory':
                $limit = (int)($_GET['limit'] ?? 50);
                $history = $deploy->getHistory($limit);
                $stats = $deploy->getStats();
                
                // Garantir estrutura correta
                if (!is_array($history)) {
                    $history = [];
                }
                
                if (!is_array($stats)) {
                    $stats = [
                        'total_operations' => 0,
                        'by_action' => [],
                        'by_user' => [],
                        'by_day' => []
                    ];
                }
                
                echo json_encode([
                    'history' => $history,
                    'stats' => $stats
                ]);
                break;
                
            case 'getStats':
                $stats = $deploy->getStats();
                
                if (!is_array($stats)) {
                    $stats = [
                        'total_operations' => 0,
                        'by_action' => [],
                        'by_user' => [],
                        'by_day' => []
                    ];
                }
                
                echo json_encode($stats);
                break;
                
            default:
                throw new Exception('Ação não reconhecida: ' . $action);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'action' => $action
        ]);
    }
}

function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => true,
            'message' => 'JSON inválido: ' . json_last_error_msg()
        ]);
        return;
    }
    
    $action = $input['action'] ?? '';
    
    try {
        $deploy = new DeployManager();
        
        switch ($action) {
            case 'push':
                $result = $deploy->push(
                    $input['files'] ?? [],
                    $input['sourceEnv'] ?? 'local',
                    $input['targetEnvs'] ?? [],
                    $input['createBackup'] ?? true,
                    $input['force'] ?? false
                );
                echo json_encode($result);
                break;
                
            case 'pull':
                $result = $deploy->pull(
                    $input['files'] ?? [],
                    $input['sourceEnv'] ?? 'local',
                    $input['targetEnv'] ?? 'local',
                    $input['createBackup'] ?? true,
                    $input['force'] ?? false
                );
                echo json_encode($result);
                break;
                
            case 'compare':
                $result = $deploy->compare(
                    $input['files'] ?? [],
                    $input['sourceEnv'] ?? 'local',
                    $input['targetEnv'] ?? 'local'
                );
                echo json_encode($result);
                break;
                
            case 'checkConflicts':
                $config = Config::getInstance();
                $sourcePath = $config->getEnvironmentPath($input['sourceEnv'] ?? 'local');
                $targetPath = $config->getEnvironmentPath($input['targetEnv'] ?? 'local');
                
                if (!$sourcePath || !$targetPath) {
                    throw new Exception('Ambiente inválido');
                }
                
                $conflicts = FileDiffer::checkConflicts(
                    $input['files'] ?? [],
                    $sourcePath,
                    $targetPath
                );
                
                echo json_encode(['conflicts' => $conflicts]);
                break;
                
            case 'restore':
                $result = $deploy->restore(
                    $input['backupFile'] ?? '',
                    $input['targetEnv'] ?? 'local'
                );
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Ação não reconhecida: ' . $action);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'action' => $action
        ]);
    }
}
