# 🔧 Guia de Solução de Problemas

## Erro: "dirs.forEach is not a function"

### Causa
A API não está retornando um array de diretórios. Isso geralmente acontece quando:

1. O arquivo `.env` não existe ou está mal configurado
2. Os caminhos no `.env` estão incorretos
3. O PHP não consegue acessar os diretórios configurados
4. A API está retornando erro ao invés de dados

### Solução Passo a Passo

#### 1. Verificar se o .env existe

```bash
# Linux/Mac
ls -la .env

# Windows
dir .env
```

Se não existir, copie do exemplo:
```bash
cp .env.example .env
```

#### 2. Configurar o .env corretamente

Edite o arquivo `.env` e ajuste os caminhos:

**Windows (Laragon/XAMPP):**
```env
LOCAL_PATH="C:\\laragon\\www\\seu-projeto\\conteudo\\"
```

**Windows (Rede):**
```env
DEV_ROGER="\\\\10.3.2.210\\pasta\\conteudo\\"
```

**Linux:**
```env
LOCAL_PATH="/var/www/html/seu-projeto/conteudo/"
```

**IMPORTANTE:** 
- Windows: Use barras duplas `\\`
- Linux: Use barras simples `/`
- Sempre termine com barra

#### 3. Testar os caminhos manualmente

**Windows:**
```cmd
dir "C:\laragon\www\seu-projeto\conteudo\"
```

**Linux:**
```bash
ls -la /var/www/html/seu-projeto/conteudo/
```

#### 4. Verificar permissões (Linux)

```bash
# Dar permissões necessárias
chmod 755 -R .
chmod 777 backups/
chmod 777 logs/
chmod 777 temp/
```

#### 5. Usar a página de teste

Abra no navegador:
```
http://localhost/deploy-system/test.html
```

Esta página vai testar:
- ✓ Conexão com a API
- ✓ Leitura de diretórios
- ✓ Leitura de arquivos
- ✓ Histórico e estatísticas
- ✓ Configuração geral

#### 6. Verificar console do navegador

Abra o console (F12) e veja a resposta da API:

**Resposta correta:**
```json
["pasta1", "pasta2", "pasta3"]
```

**Resposta com erro:**
```json
{
  "error": true,
  "message": "Arquivo necessário não encontrado: .env"
}
```

#### 7. Verificar logs do PHP

**Apache (Linux):**
```bash
tail -f /var/log/apache2/error.log
```

**Nginx (Linux):**
```bash
tail -f /var/log/nginx/error.log
```

**Laragon (Windows):**
```
C:\laragon\etc\apache2\logs\error.log
```

## Outros Erros Comuns

### "Cannot read properties of undefined (reading 'total_operations')"

**Causa:** Histórico vazio ou não inicializado

**Solução:** Já corrigido na versão atualizada. Se ainda ocorrer:
1. Delete o arquivo `logs/history.json`
2. Recarregue a página
3. O sistema criará um novo arquivo

### "Cannot write to logs/"

**Causa:** Sem permissão de escrita

**Solução (Linux):**
```bash
chmod 777 logs/
chmod 777 backups/
chmod 777 temp/
```

**Solução (Windows):**
1. Clique direito na pasta
2. Propriedades → Segurança
3. Editar → Adicionar "Everyone" com controle total

### "ZIP extension not found"

**Causa:** Extensão PHP ZIP não instalada

**Solução (Ubuntu/Debian):**
```bash
sudo apt-get install php-zip
sudo service apache2 restart
```

**Solução (CentOS/RHEL):**
```bash
sudo yum install php-zip
sudo service httpd restart
```

**Solução (Windows/Laragon):**
1. Laragon → PHP → php.ini
2. Procure: `;extension=zip`
3. Remova o `;` do início
4. Salve e reinicie

### Arquivos não aparecem

**Verificar:**

1. **Caminho está correto?**
   ```bash
   # Teste manualmente
   cd "C:\laragon\www\blumar\conteudo"
   dir
   ```

2. **Tem arquivos na pasta?**
   ```bash
   ls -la /caminho/para/pasta
   ```

3. **Permissões corretas?**
   ```bash
   # Linux
   ls -la /caminho/para/pasta
   # Deve mostrar r-x (leitura e execução)
   ```

### API não responde

**Teste direto:**
```
http://localhost/deploy-system/api.php?action=getStats
```

**Deve retornar:**
```json
{
  "total_operations": 0,
  "by_action": {},
  "by_user": {},
  "by_day": {}
}
```

**Se retornar erro:**
1. Verifique se `config.php` existe
2. Verifique se `DeployManager.php` existe
3. Verifique se `.env` existe
4. Veja os logs do PHP

## Checklist de Instalação

Use este checklist para garantir que tudo está correto:

- [ ] Arquivos extraídos do ZIP
- [ ] Arquivo `.env` existe e está configurado
- [ ] Caminhos no `.env` estão corretos (com barras duplas no Windows)
- [ ] Caminhos são acessíveis (testado manualmente)
- [ ] Pastas `backups/`, `logs/` e `temp/` existem
- [ ] Permissões corretas (777 no Linux)
- [ ] Extensão PHP ZIP instalada
- [ ] Servidor web rodando (Apache/Nginx)
- [ ] PHP 7.4+ instalado
- [ ] Página de teste (`test.html`) passa em todos os testes

## Como Pedir Ajuda

Se ainda tiver problemas, reúna estas informações:

1. **Sistema Operacional:**
   ```bash
   # Linux
   uname -a
   
   # Windows
   ver
   ```

2. **Versão do PHP:**
   ```bash
   php -v
   ```

3. **Erro exato do console:**
   - Abra F12
   - Aba Console
   - Copie o erro completo

4. **Resposta da API de teste:**
   - Abra `test.html`
   - Copie os resultados

5. **Conteúdo do .env:**
   ```bash
   cat .env
   # (remova senhas se houver)
   ```

6. **Logs de erro:**
   ```bash
   tail -20 logs/deploy.log
   ```

## Ferramentas Úteis

### Debug no PHP

Adicione no início do `api.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Debug no JavaScript

Adicione no `app.js`:
```javascript
console.log('Response data:', response.data);
console.log('Data type:', typeof response.data);
console.log('Is array?', Array.isArray(response.data));
```

### Testar caminhos no PHP

Crie arquivo `test-paths.php`:
```php
<?php
require_once 'config.php';
$config = Config::getInstance();

echo "Local Path: " . $config->get('local_path') . "\n";
echo "Exists? " . (is_dir($config->get('local_path')) ? 'YES' : 'NO') . "\n";
echo "Readable? " . (is_readable($config->get('local_path')) ? 'YES' : 'NO') . "\n";

$dirs = $config->get('dev_paths');
foreach ($dirs as $name => $path) {
    echo "\n$name: $path\n";
    echo "Exists? " . (is_dir($path) ? 'YES' : 'NO') . "\n";
}
```

Execute:
```bash
php test-paths.php
```

## Suporte

Se nenhuma solução funcionar:

1. Abra o `test.html` e tire screenshot
2. Copie logs de erro
3. Entre em contato com a equipe

---

**Última atualização:** 19/11/2025
