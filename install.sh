#!/bin/bash

# ==========================================
# Script de Instalação Automática
# Sistema de Deploy & Sincronização - Blumar
# ==========================================

echo "================================================"
echo "  Sistema de Deploy & Sincronização - Blumar  "
echo "================================================"
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Função para printar com cor
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# Verificar se está rodando como root
if [ "$EUID" -ne 0 ]; then 
    print_error "Por favor, execute como root (sudo ./install.sh)"
    exit 1
fi

echo "Iniciando instalação..."
echo ""

# 1. Verificar PHP
echo "1. Verificando PHP..."
if ! command -v php &> /dev/null; then
    print_error "PHP não encontrado. Instale o PHP primeiro."
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
print_success "PHP $PHP_VERSION encontrado"

# 2. Verificar extensão ZIP
echo ""
echo "2. Verificando extensão ZIP..."
if ! php -m | grep -q zip; then
    print_info "Extensão ZIP não encontrada. Instalando..."
    
    if [ -f /etc/debian_version ]; then
        apt-get update
        apt-get install -y php-zip
    elif [ -f /etc/redhat-release ]; then
        yum install -y php-zip
    else
        print_error "Sistema não suportado. Instale php-zip manualmente."
        exit 1
    fi
    
    print_success "Extensão ZIP instalada"
else
    print_success "Extensão ZIP encontrada"
fi

# 3. Criar diretórios necessários
echo ""
echo "3. Criando estrutura de diretórios..."
mkdir -p backups logs temp
print_success "Diretórios criados"

# 4. Ajustar permissões
echo ""
echo "4. Ajustando permissões..."
chmod 755 -R .
chmod 777 backups/
chmod 777 logs/
chmod 777 temp/
print_success "Permissões configuradas"

# 5. Configurar .env
echo ""
echo "5. Configurando ambiente..."
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        print_success "Arquivo .env criado a partir do exemplo"
        print_info "IMPORTANTE: Edite o arquivo .env com seus caminhos!"
    else
        print_error "Arquivo .env.example não encontrado"
    fi
else
    print_info "Arquivo .env já existe"
fi

# 6. Testar servidor web
echo ""
echo "6. Verificando servidor web..."
if systemctl is-active --quiet apache2; then
    print_success "Apache está rodando"
    WEBSERVER="apache2"
elif systemctl is-active --quiet nginx; then
    print_success "Nginx está rodando"
    WEBSERVER="nginx"
elif systemctl is-active --quiet httpd; then
    print_success "Apache (httpd) está rodando"
    WEBSERVER="httpd"
else
    print_error "Nenhum servidor web detectado"
    print_info "Instale Apache ou Nginx e tente novamente"
    exit 1
fi

# 7. Obter caminho web
echo ""
echo "7. Detectando caminho web..."
CURRENT_DIR=$(pwd)
if [[ "$CURRENT_DIR" == *"/var/www/"* ]]; then
    WEB_PATH=${CURRENT_DIR#/var/www/html/}
    print_success "Caminho detectado: /var/www/html/$WEB_PATH"
elif [[ "$CURRENT_DIR" == *"/usr/share/nginx/"* ]]; then
    WEB_PATH=${CURRENT_DIR#/usr/share/nginx/html/}
    print_success "Caminho detectado: /usr/share/nginx/html/$WEB_PATH"
else
    print_info "Caminho web não detectado automaticamente"
    WEB_PATH=""
fi

# 8. Reiniciar servidor web
echo ""
echo "8. Reiniciando servidor web..."
if systemctl restart $WEBSERVER; then
    print_success "Servidor web reiniciado"
else
    print_error "Erro ao reiniciar servidor web"
fi

# Resumo final
echo ""
echo "================================================"
echo "  Instalação Concluída!"
echo "================================================"
echo ""
print_success "Sistema instalado com sucesso!"
echo ""
echo "Próximos passos:"
echo ""
echo "1. Edite o arquivo .env com seus caminhos:"
echo "   nano .env"
echo ""
echo "2. Acesse o sistema no navegador:"
if [ ! -z "$WEB_PATH" ]; then
    echo "   http://localhost/$WEB_PATH"
    echo "   ou"
    echo "   http://seu-servidor/$WEB_PATH"
else
    echo "   http://localhost/[caminho-para-o-sistema]"
fi
echo ""
echo "3. Leia a documentação:"
echo "   cat README.md"
echo "   cat INSTALACAO.md"
echo ""
echo "================================================"
