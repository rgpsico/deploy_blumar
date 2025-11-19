<?php
/**
 * Diagnóstico do Sistema - Debug
 * Use este arquivo para verificar se tudo está configurado corretamente
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Diagnóstico do Sistema</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
    h1, h2 { color: #4ec9b0; }
    .success { color: #4ec9b0; }
    .error { color: #f48771; }
    .warning { color: #dcdcaa; }
    pre { background: #252526; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .section { background: #252526; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #3e3e42; }
</style></head><body>";

echo "<h1>🔍 Diagnóstico do Sistema de Deploy</h1>";

// 1. Verificar PHP
echo "<div class='section'>";
echo "<h2>1. Informações do PHP</h2>";
echo "Versão: <span class='success'>" . phpversion() . "</span><br>";
echo "Sistema: <span class='success'>" . PHP_OS . "</span><br>";

// Verificar extensão ZIP
if (extension_loaded('zip')) {
    echo "Extensão ZIP: <span class='success'>✓ Instalada</span><br>";
} else {
    echo "Extensão ZIP: <span class='error'>✗ NÃO Instalada</span><br>";
}
echo "</div>";

// 2. Verificar arquivos necessários
echo "<div class='section'>";
echo "<h2>2. Arquivos Necessários</h2>";

$requiredFiles = [
    '.env' => 'Arquivo de configuração',
    'config.php' => 'Configuração principal',
    'DeployManager.php' => 'Gerenciador de deploy',
    'HistoryManager.php' => 'Gerenciador de histórico',
    'FileDiffer.php' => 'Comparador de arquivos',
    'api.php' => 'API REST',
    'index.html' => 'Interface principal'
];

foreach ($requiredFiles as $file => $desc) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<span class='success'>✓</span> $file - $desc<br>";
    } else {
        echo "<span class='error'>✗</span> $file - $desc <strong>NÃO ENCONTRADO</strong><br>";
    }
}
echo "</div>";

// 3. Verificar diretórios
echo "<div class='section'>";
echo "<h2>3. Diretórios do Sistema</h2>";

$requiredDirs = ['backups', 'logs', 'temp'];

foreach ($requiredDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $writable = is_writable($path);
        echo "<span class='success'>✓</span> /$dir/ - ";
        echo $writable ? "<span class='success'>Gravável</span>" : "<span class='error'>NÃO Gravável</span>";
        echo "<br>";
    } else {
        echo "<span class='error'>✗</span> /$dir/ - <strong>NÃO EXISTE</strong><br>";
    }
}
echo "</div>";

// 4. Testar .env e configuração
echo "<div class='section'>";
echo "<h2>4. Teste de Configuração (.env)</h2>";

if (file_exists(__DIR__ . '/.env')) {
    echo "<span class='success'>✓</span> Arquivo .env encontrado<br><br>";
    
    try {
        require_once 'config.php';
        $config = Config::getInstance();
        
        echo "<h3>Configurações Carregadas:</h3>";
        
        // Local path
        echo "<strong>Caminho Local:</strong><br>";
        $localPath = $config->get('local_path');
        echo "<pre>$localPath</pre>";
        
        if (is_dir($localPath)) {
            echo "<span class='success'>✓ Diretório existe</span><br>";
            if (is_readable($localPath)) {
                echo "<span class='success'>✓ Diretório pode ser lido</span><br>";
                
                // Testar escrita
                if (is_writable($localPath)) {
                    echo "<span class='success'>✓ Diretório pode ser escrito</span><br>";
                    
                    // Testar criação de arquivo
                    $testFile = $localPath . '.test_write_' . time() . '.tmp';
                    if (@file_put_contents($testFile, 'test')) {
                        echo "<span class='success'>✓ Teste de escrita bem sucedido</span><br>";
                        @unlink($testFile);
                    } else {
                        echo "<span class='warning'>⚠ Falha no teste de escrita</span><br>";
                    }
                } else {
                    echo "<span class='error'>✗ Diretório NÃO pode ser escrito</span><br>";
                    echo "<span class='warning'>⚠ Você só poderá BAIXAR arquivos, não enviar</span><br>";
                }
                
                // Listar alguns arquivos
                $files = @scandir($localPath);
                if ($files && count($files) > 2) {
                    echo "<span class='success'>✓ " . (count($files) - 2) . " itens encontrados</span><br>";
                    echo "<details><summary>Ver primeiros 10 itens</summary><pre>";
                    $count = 0;
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        echo $file . "\n";
                        if (++$count >= 10) break;
                    }
                    echo "</pre></details>";
                } else {
                    echo "<span class='warning'>⚠ Diretório vazio</span><br>";
                }
            } else {
                echo "<span class='error'>✗ Diretório NÃO pode ser lido</span><br>";
            }
        } else {
            echo "<span class='error'>✗ Diretório NÃO existe</span><br>";
            echo "<span class='warning'>⚠ Verifique se o caminho está correto no .env</span><br>";
            echo "<span class='warning'>⚠ Windows: use barras duplas (C:\\\\pasta\\\\)</span><br>";
            echo "<span class='warning'>⚠ Linux: use barras simples (/pasta/)</span><br>";
        }
        
        // Dev paths
        echo "<br><strong>Ambientes de Desenvolvimento:</strong><br>";
        $devPaths = $config->get('dev_paths');
        foreach ($devPaths as $name => $path) {
            echo "<br><strong>$name:</strong><br>";
            echo "<pre>$path</pre>";
            if (is_dir($path)) {
                echo "<span class='success'>✓ Acessível</span><br>";
            } else {
                echo "<span class='error'>✗ NÃO Acessível</span><br>";
            }
        }
        
        // Prod path
        echo "<br><strong>Produção:</strong><br>";
        $prodPath = $config->get('prod_path');
        echo "<pre>$prodPath</pre>";
        if (is_dir($prodPath)) {
            echo "<span class='success'>✓ Acessível</span><br>";
        } else {
            echo "<span class='error'>✗ NÃO Acessível</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>✗ Erro ao carregar configuração:</span><br>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
    
} else {
    echo "<span class='error'>✗ Arquivo .env NÃO encontrado</span><br>";
    echo "<span class='warning'>Copie o .env.example para .env e configure os caminhos</span><br>";
}
echo "</div>";

// 5. Testar API
echo "<div class='section'>";
echo "<h2>5. Teste da API</h2>";

try {
    // Simular requisição listDirs
    $_GET['action'] = 'listDirs';
    $_GET['env'] = 'local';
    
    ob_start();
    require_once 'DeployManager.php';
    $deploy = new DeployManager();
    $dirs = $deploy->listDirs('local');
    ob_end_clean();
    
    if (is_array($dirs)) {
        echo "<span class='success'>✓ listDirs retorna array</span><br>";
        echo "Diretórios encontrados: " . count($dirs) . "<br>";
        if (count($dirs) > 0) {
            echo "<details><summary>Ver diretórios</summary><pre>";
            print_r($dirs);
            echo "</pre></details>";
        }
    } else {
        echo "<span class='error'>✗ listDirs NÃO retorna array</span><br>";
        echo "<pre>Tipo retornado: " . gettype($dirs) . "</pre>";
        echo "<pre>";
        var_dump($dirs);
        echo "</pre>";
    }
    
    // Simular requisição listFiles
    $files = $deploy->listFiles('local', '', false);
    
    if (is_array($files)) {
        echo "<span class='success'>✓ listFiles retorna array</span><br>";
        echo "Arquivos encontrados: " . count($files) . "<br>";
        if (count($files) > 0) {
            echo "<details><summary>Ver primeiros 5 arquivos</summary><pre>";
            print_r(array_slice($files, 0, 5));
            echo "</pre></details>";
        }
    } else {
        echo "<span class='error'>✗ listFiles NÃO retorna array</span><br>";
        echo "<pre>Tipo retornado: " . gettype($files) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>✗ Erro ao testar API:</span><br>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>";

// 6. Recomendações
echo "<div class='section'>";
echo "<h2>6. Próximos Passos</h2>";
echo "<ol>";
echo "<li>Se todos os testes passaram ✓, o sistema está pronto para usar</li>";
echo "<li>Se há erros ✗, corrija-os seguindo as mensagens acima</li>";
echo "<li>Verifique especialmente os caminhos no arquivo .env</li>";
echo "<li>Teste a interface em: <a href='index.html'>index.html</a></li>";
echo "<li>Use a página de teste: <a href='test.html'>test.html</a></li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
