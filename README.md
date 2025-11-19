# Sistema de Deploy e Sincronização - Blumar

Sistema completo de gerenciamento e sincronização de código entre ambientes, similar ao Git, mas otimizado para sistemas legados PHP.

## 🚀 Funcionalidades

### 1. **Push (Deploy)**
- Envio de arquivos do ambiente local para outros ambientes
- Suporte para múltiplos destinos simultâneos
- Verificação automática de conflitos
- Backup automático antes de sobrescrever

### 2. **Pull (Sincronização)**
- Download de arquivos de ambientes remotos
- Atualização do ambiente local com versões dos colegas
- Preservação automática de versões anteriores

### 3. **Compare (Comparação)**
- Comparação de arquivos entre ambientes
- Detecção de diferenças em conteúdo, data e tamanho
- Visualização de status: idêntico, diferente, novo, não encontrado

### 4. **Histórico e Versionamento**
- Registro completo de todas as operações
- Rastreamento de quem fez o quê e quando
- Estatísticas de uso
- Capacidade de restaurar versões anteriores

### 5. **Backup Automático**
- Criação de backups antes de cada operação
- Limitação automática de backups antigos
- Restauração fácil através da interface

## 📋 Pré-requisitos

- PHP 7.4 ou superior
- Extensão ZIP do PHP habilitada
- Acesso de leitura/escrita aos diretórios configurados
- Servidor web (Apache, Nginx, etc.)

## 🔧 Instalação

### 1. Clone ou extraia os arquivos

```bash
cd /seu/servidor/web
mkdir deploy-system
cd deploy-system
```

### 2. Configure o arquivo .env

Crie um arquivo `.env` na raiz do projeto:

```env
# CAMINHO LOCAL
LOCAL_PATH="C:\\laragon\\www\\blumar_legado\\blumar\\"

# CAMINHOS DE DESENVOLVIMENTO
DEV_ROGER="\\\\10.3.2.210\\webdeveloper\\desenv\\roger\\conteudo\\"
DEV_JULIO="\\\\10.3.2.210\\webdeveloper\\desenv\\julio\\conteudo\\"
DEV_JADES="\\\\10.3.2.210\\webdeveloper\\desenv\\jades\\conteudo\\"

# PRODUÇÃO
PROD_PATH="\\\\10.3.2.210\\wwwintranet\\nova_intra\\admin\\conteudo\\"

# LOG
LOG_FILE="./logs/deploy.log"
```

**Importante:** Use barras duplas (`\\`) no Windows.

### 3. Ajuste permissões

```bash
chmod 755 -R .
chmod 777 logs/
chmod 777 backups/
chmod 777 temp/
```

### 4. Acesse o sistema

Abra no navegador: `http://seu-servidor/deploy-system/`

## 📂 Estrutura de Arquivos

```
deploy-system/
├── index.html              # Interface principal
├── app.js                  # JavaScript da aplicação
├── api.php                 # API REST
├── config.php              # Configuração e carregamento .env
├── DeployManager.php       # Gerenciador principal
├── HistoryManager.php      # Gerenciador de histórico
├── FileDiffer.php          # Comparador de arquivos
├── .env                    # Configurações do ambiente
├── backups/                # Backups automáticos
├── logs/                   # Logs e histórico
│   ├── deploy.log         # Log de operações
│   └── history.json       # Histórico estruturado
└── temp/                   # Arquivos temporários
```

## 🎯 Como Usar

### Deploy (Enviar Arquivos)

1. Selecione o **ambiente de origem** (normalmente "Meu Ambiente Local")
2. Escolha a **pasta** onde estão seus arquivos
3. Selecione os **arquivos** que deseja enviar
4. Marque os **destinos** (Roger, Julio, Jades, Produção)
5. Configure as opções:
   - ✓ Criar backup (recomendado)
   - Forçar sobrescrita (use com cautela)
6. Clique em **Deploy (Enviar)**

### Sync (Baixar Arquivos)

1. Vá para a aba **Sync**
2. Selecione de qual ambiente deseja baixar
3. Escolha os arquivos
4. Clique em **Pull (Baixar)**

### Comparar Ambientes

1. Vá para a aba **Comparar**
2. Selecione dois ambientes (A e B)
3. Selecione os arquivos que deseja comparar
4. Clique em **Comparar Ambientes**
5. Veja o relatório detalhado de diferenças

### Histórico

1. Vá para a aba **Histórico**
2. Veja estatísticas e operações recentes
3. Use o botão **Restaurar** para voltar a versões anteriores

## ⚙️ Configuração Avançada

### Alterando Número Máximo de Backups

No arquivo `config.php`, altere:

```php
'max_backups' => 50,  // Padrão: 50
```

### Adicionando Novos Ambientes

No arquivo `.env`:

```env
DEV_NOVO="\\\\servidor\\pasta\\novousuario\\conteudo\\"
```

No arquivo `config.php`, adicione no array `dev_paths`:

```php
'dev_paths' => [
    'Roger' => $this->normalizePath(getenv('DEV_ROGER')),
    'Julio' => $this->normalizePath(getenv('DEV_JULIO')),
    'Jades' => $this->normalizePath(getenv('DEV_JADES')),
    'Novo' => $this->normalizePath(getenv('DEV_NOVO')),  // ← Adicione aqui
],
```

### Personalizando Interface

No arquivo `index.html`, você pode:

- Alterar cores no `:root` CSS
- Adicionar/remover abas
- Personalizar layout

## 🔒 Segurança

### Recomendações

1. **Autenticação:** Adicione autenticação antes de usar em produção
2. **HTTPS:** Use sempre HTTPS em produção
3. **Permissões:** Configure permissões adequadas nos diretórios
4. **Backup Produção:** Sempre crie backups antes de enviar para produção
5. **Testes:** Teste em ambientes de dev antes de produção

### Adicionando Autenticação Básica

Crie um arquivo `.htaccess`:

```apache
AuthType Basic
AuthName "Deploy System"
AuthUserFile /caminho/completo/.htpasswd
Require valid-user
```

Crie o arquivo de senhas:

```bash
htpasswd -c .htpasswd usuario
```

## 🐛 Solução de Problemas

### Erro: "Cannot create directory"

**Solução:** Verifique permissões:
```bash
chmod 777 backups/
chmod 777 logs/
chmod 777 temp/
```

### Erro: "Call to undefined function zip_open"

**Solução:** Instale a extensão ZIP:
```bash
sudo apt-get install php-zip
sudo service apache2 restart
```

### Arquivos não aparecem

**Solução:** Verifique se o caminho no `.env` está correto e acessível.

### Conflitos não são detectados

**Solução:** Certifique-se de que os relógios dos servidores estão sincronizados.

## 📊 API REST

### Endpoints GET

#### Listar Diretórios
```
GET /api.php?action=listDirs&env=local
```

#### Listar Arquivos
```
GET /api.php?action=listFiles&env=local&folder=admin&includeSub=true
```

#### Obter Histórico
```
GET /api.php?action=getHistory&limit=50
```

### Endpoints POST

#### Push (Deploy)
```javascript
POST /api.php
{
    "action": "push",
    "files": ["admin/index.php", "admin/config.php"],
    "sourceEnv": "local",
    "targetEnvs": ["Roger", "Julio"],
    "createBackup": true,
    "force": false
}
```

#### Pull (Sync)
```javascript
POST /api.php
{
    "action": "pull",
    "files": ["admin/functions.php"],
    "sourceEnv": "Roger",
    "targetEnv": "local",
    "createBackup": true
}
```

#### Comparar
```javascript
POST /api.php
{
    "action": "compare",
    "files": ["index.php"],
    "sourceEnv": "local",
    "targetEnv": "Roger"
}
```

## 🔄 Fluxo de Trabalho Recomendado

### Desenvolvimento Diário

1. **Manhã:** Pull dos ambientes dos colegas para ver mudanças
2. **Durante o dia:** Trabalhe normalmente no seu ambiente
3. **Antes de ir embora:** Push das alterações para compartilhar

### Deploy para Produção

1. **Teste local:** Verifique se tudo funciona
2. **Compare:** Use a função "Comparar" para ver diferenças
3. **Backup:** Sempre crie backup (marcado por padrão)
4. **Deploy dev:** Teste primeiro em um ambiente dev
5. **Deploy produção:** Só então envie para produção

## 🎨 Personalização

### Temas de Cores

No CSS do `index.html`:

```css
:root {
    --primary-color: #0066cc;    /* Azul principal */
    --success-color: #28a745;    /* Verde sucesso */
    --warning-color: #ffc107;    /* Amarelo aviso */
    --danger-color: #dc3545;     /* Vermelho perigo */
}
```

## 📈 Melhorias Futuras

- [ ] Diff visual de código (linha por linha)
- [ ] Integração com Git real
- [ ] Notificações por email/Slack
- [ ] Agendamento de deploys
- [ ] Rollback com um clique
- [ ] Visualização de arquivos modificados em tempo real
- [ ] Sistema de aprovação para produção
- [ ] Integração com CI/CD

## 📝 Logs

### Log de Operações (`logs/deploy.log`)

```
[2025-11-19 18:30:45] [roger] PUSH: local → Roger: admin/index.php
[2025-11-19 18:30:45] [roger] BACKUP criado: roger_backup_2025-11-19_18-30-45_Roger.zip
```

### Histórico Estruturado (`logs/history.json`)

```json
{
    "id": "65abc123",
    "timestamp": "2025-11-19 18:30:45",
    "user": "roger",
    "action": "push",
    "from": "local",
    "to": "Roger",
    "files": ["admin/index.php"],
    "file_count": 1,
    "backup_file": "roger_backup_2025-11-19_18-30-45_Roger.zip"
}
```

## 🤝 Contribuindo

Sugestões e melhorias são bem-vindas! Entre em contato com a equipe de desenvolvimento.

## 📞 Suporte

Para dúvidas ou problemas:
- Email: dev@blumar.com.br
- Slack: #deploy-system

## 📜 Licença

Uso interno - Blumar © 2025

---

**Desenvolvido com ❤️ pela equipe Blumar**
