# 🚀 Guia Rápido de Instalação

## Instalação em 5 Minutos

### 1️⃣ Extrair Arquivos
```bash
# Extraia o ZIP na pasta do seu servidor web
cd C:\laragon\www  (Windows)
# ou
cd /var/www/html   (Linux)

# Descompacte o arquivo
unzip deploy-system.zip
cd deploy-system
```

### 2️⃣ Configurar Ambiente

```bash
# Copie o arquivo de exemplo
copy .env.example .env    (Windows)
# ou
cp .env.example .env      (Linux)

# Edite o arquivo .env com seus caminhos
notepad .env              (Windows)
# ou
nano .env                 (Linux)
```

**Exemplo de configuração:**
```env
LOCAL_PATH="C:\\laragon\\www\\blumar\\conteudo\\"
DEV_ROGER="\\\\10.3.2.210\\webdeveloper\\desenv\\roger\\conteudo\\"
DEV_JULIO="\\\\10.3.2.210\\webdeveloper\\desenv\\julio\\conteudo\\"
PROD_PATH="\\\\10.3.2.210\\wwwintranet\\nova_intra\\admin\\conteudo\\"
```

### 3️⃣ Ajustar Permissões (Linux)

```bash
chmod 755 -R .
chmod 777 backups/
chmod 777 logs/
chmod 777 temp/
```

### 4️⃣ Verificar PHP

```bash
# Verifique se o PHP tem a extensão ZIP
php -m | grep zip

# Se não tiver, instale:
# Windows (Laragon): já vem instalado
# Linux Ubuntu/Debian:
sudo apt-get install php-zip
sudo service apache2 restart

# Linux CentOS/RHEL:
sudo yum install php-zip
sudo service httpd restart
```

### 5️⃣ Acessar o Sistema

Abra no navegador:
```
http://localhost/deploy-system/
```

## ✅ Checklist Pós-Instalação

- [ ] Arquivo `.env` configurado com os caminhos corretos
- [ ] Pastas `backups/`, `logs/` e `temp/` com permissão de escrita
- [ ] Extensão ZIP do PHP habilitada
- [ ] Acesso aos caminhos de rede configurados
- [ ] Sistema acessível via navegador

## 🔧 Personalizar para Seu Time

### Adicionar Novo Desenvolvedor

**1. No arquivo `.env`:**
```env
DEV_MARIA="\\\\10.3.2.210\\webdeveloper\\desenv\\maria\\conteudo\\"
```

**2. No arquivo `config.php` (linha ~9):**
```php
'dev_paths' => [
    'Roger' => $this->normalizePath(getenv('DEV_ROGER')),
    'Julio' => $this->normalizePath(getenv('DEV_JULIO')),
    'Jades' => $this->normalizePath(getenv('DEV_JADES')),
    'Maria' => $this->normalizePath(getenv('DEV_MARIA')),  // ← Adicione aqui
],
```

**3. No arquivo `index.html` (procure por "dest-" ~linha 175):**
```html
<div class="col-md-4">
    <div class="form-check form-check-lg mb-2">
        <input class="form-check-input" type="checkbox" value="Maria" id="dest-maria">
        <label class="form-check-label" for="dest-maria">
            <i class="bi bi-person"></i> Maria
        </label>
    </div>
</div>
```

## 🎯 Primeiro Uso

### Teste Básico

1. Acesse o sistema
2. Selecione uma pasta (ex: "admin")
3. Escolha 1 arquivo de teste
4. Marque o destino (ex: seu próprio ambiente)
5. Clique em "Deploy"
6. Verifique se o arquivo foi copiado

### Workflow Diário

**Manhã:**
```
1. Aba "Sync" → Selecione "Roger" → Baixe arquivos novos
2. Aba "Sync" → Selecione "Julio" → Baixe arquivos novos
3. Verifique mudanças no seu código
```

**Durante o dia:**
```
1. Trabalhe normalmente
2. Teste suas alterações
```

**Fim do dia:**
```
1. Aba "Deploy" → Selecione seus arquivos modificados
2. Marque destinos (ex: Roger, Julio, Jades)
3. Clique em "Deploy"
4. Verifique a aba "Histórico"
```

## 🐛 Problemas Comuns

### "Cannot write to logs/"
```bash
chmod 777 logs/
```

### "ZIP extension not found"
```bash
# Verifique:
php -m | grep zip

# Se não aparecer, instale a extensão
```

### "Path not found"
```
Verifique se os caminhos no .env estão corretos e acessíveis
Teste manualmente se consegue acessar \\10.3.2.210\webdeveloper
```

### Arquivos não aparecem
```
1. Verifique permissões de leitura
2. Teste o caminho manualmente
3. Veja o arquivo logs/deploy.log para erros
```

## 📞 Suporte

- Documentação completa: `README.md`
- Logs de erro: `logs/deploy.log`
- Histórico: `logs/history.json`

## 🎉 Pronto!

Agora você tem um sistema tipo Git para seu código legado!

**Próximos passos:**
- Leia o README.md completo
- Explore as funcionalidades de Compare
- Configure backups automáticos
- Personalize cores e layout
