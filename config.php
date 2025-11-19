<?php
/**
 * Sistema de Deploy e Sincronização - Blumar
 * Configuração Principal
 */

class Config {
    private static $instance = null;
    private $config = [];

    private function __construct() {
        $this->loadEnv();
        $this->setupConfig();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnv() {
        $envFile = __DIR__ . '/.env';
        if (!file_exists($envFile)) {
            throw new Exception("Arquivo .env não encontrado!");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    private function setupConfig() {
        $this->config = [
            // Caminhos
            'local_path' => $this->normalizePath(getenv('LOCAL_PATH')),
            'dev_paths' => [
                'Roger' => $this->normalizePath(getenv('DEV_ROGER')),
                'Julio' => $this->normalizePath(getenv('DEV_JULIO')),
                'Jades' => $this->normalizePath(getenv('DEV_JADES')),
            ],
            'prod_path' => $this->normalizePath(getenv('PROD_PATH')),
            
            // Diretórios do sistema
            'backup_dir' => __DIR__ . '/backups/',
            'log_file' => __DIR__ . '/logs/deploy.log',
            'history_file' => __DIR__ . '/logs/history.json',
            'temp_dir' => __DIR__ . '/temp/',
            
            // Configurações
            'max_backups' => 50,
            'enable_compression' => true,
            'enable_versioning' => true,
            
            // Usuário atual (pode vir de autenticação)
            'current_user' => getenv('USERNAME') ?: 'roger',
        ];

        // Criar diretórios necessários
        $this->ensureDirectories();
    }

    private function normalizePath($path) {
        if (empty($path)) {
            return '';
        }
        
        // Remove aspas extras
        $path = trim($path, '"\'');
        
        // Detectar sistema operacional pelo caminho
        $isWindows = (strpos($path, '\\') !== false || preg_match('/^[A-Z]:/i', $path));
        
        if ($isWindows) {
            // Windows: manter barras invertidas
            // Normalizar barras duplas para simples (exceto no início para UNC)
            if (strpos($path, '\\\\') === 0) {
                // UNC path (\\servidor\pasta)
                $path = '\\\\' . str_replace('\\\\', '\\', substr($path, 2));
            } else {
                // Path local (C:\pasta)
                $path = str_replace('/', '\\', $path);
            }
            
            // Garantir barra no final
            $path = rtrim($path, '\\') . '\\';
        } else {
            // Linux/Unix: usar barras normais
            $path = str_replace('\\', '/', $path);
            
            // Garantir barra no final
            $path = rtrim($path, '/') . '/';
        }
        
        return $path;
    }

    private function ensureDirectories() {
        $dirs = ['backup_dir', 'temp_dir'];
        foreach ($dirs as $dir) {
            $path = $this->config[$dir];
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Garantir que o diretório de logs existe
        $logDir = dirname($this->config['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function get($key) {
        return $this->config[$key] ?? null;
    }

    public function getAll() {
        return $this->config;
    }

    public function getEnvironmentPath($env) {
        if ($env === 'local') {
            return $this->config['local_path'];
        }
        if ($env === 'producao') {
            return $this->config['prod_path'];
        }
        return $this->config['dev_paths'][$env] ?? null;
    }

    public function getAllEnvironments() {
        $envs = ['local' => 'Meu Ambiente Local'];
        foreach ($this->config['dev_paths'] as $name => $path) {
            $envs[$name] = $name;
        }
        $envs['producao'] = 'Produção';
        return $envs;
    }
}

// Retorna instância para compatibilidade
return Config::getInstance()->getAll();
