# glpi-reservationalert

Plugin para GLPI 11 que envia alertas no tabuleiro (tray) do browser quando uma reserva está prestes a começar.

## Requisitos

- GLPI 11.x
- PHP 8.1+
- Extensão `exec` e `shell_exec` ativas (para instalação automática do crontab)

## Instalação

### 1. Clonar o repositório

```bash
cd /var/www/glpi/plugins
git clone https://github.com/SantiPT007/glpi-reservationalert reservationalert
```

### 2. Corrigir permissões

```bash
chown -R www-data:www-data /var/www/glpi/plugins/reservationalert
```

### 3. Ativar o plugin

No GLPI: **Configurar → Plugins → Reservation Alert → Instalar → Ativar**

### 4. Configurar o cron

No GLPI: **Configurar → Plugins → Reservation Alert → Configurar**

Na secção **Crontab do Sistema**, clicar em **Instalar crontab**. O plugin adiciona automaticamente a entrada ao crontab do utilizador do servidor web (`www-data`).

Se `exec` estiver desativado no PHP, o plugin mostra o comando para adicionar manualmente:

```bash
crontab -u www-data -e
```

E adicionar a linha apresentada no ecrã.

---

## Resolução de problemas

### Plugin não aparece ou dá erro ao ativar

Limpar a cache do GLPI e do PHP:

```bash
rm -rf /var/www/glpi/files/_cache/*
```

Se o servidor usar OPcache:

```bash
# Apache
service apache2 restart

# Nginx + PHP-FPM
service php8.4-fpm restart
```

### Erro "not allowed to run as root" ao instalar dependências

Se correr comandos PHP como root:

```bash
COMPOSER_ALLOW_SUPERUSER=1 composer install
```

Ou adicionar a flag diretamente:

```bash
php -d allow_url_fopen=1 /usr/bin/composer install
```

### Cron instalado mas notificações não chegam

Verificar se o crontab está presente:

```bash
crontab -u www-data -l
```

Verificar o log do cron:

```bash
# Dependendo da distro
grep CRON /var/log/syslog | tail -20
journalctl -u cron --since "10 minutes ago"
```

Testar o cron manualmente no GLPI: **Configurar → Plugins → Reservation Alert → Configurar → Correr cron agora**

**Fuso horário (causa mais comum).** O cron compara a hora do servidor (`new DateTime()` em
PHP) com os horários das reservas. Se o fuso do servidor não coincidir com o local (ex.:
container em **UTC** mas utilizadores em **Europe/Lisbon**), as reservas caem fora da janela
de aviso e nada dispara — parecendo que o cron está parado. Garantir que o PHP e a base de
dados correm no fuso local:

- PHP: `date.timezone = Europe/Lisbon` no `php.ini` (ou um `.ini` em `conf.d/`).
- Em Docker: definir `TZ=Europe/Lisbon` nos serviços do GLPI **e** da base de dados.

Confirmar com **Correr cron agora**: a "Hora atual servidor" tem de bater certo com a hora
local; se estiver desfasada, é fuso horário.

### Notificações duplicadas ou não aparecem

Aceder ao endpoint de diagnóstico (admin):

```
https://glpi.exemplo.com/plugins/reservationalert/front/test.php
```

Retorna JSON com o estado das tabelas e contagem de notificações.

### Remover o plugin completamente

```bash
rm -rf /var/www/glpi/plugins/reservationalert
```

Depois no GLPI desativar o plugin antes de apagar a pasta, ou limpar a cache após apagar:

```bash
rm -rf /var/www/glpi/files/_cache/*
```

### Repor o plugin de raiz

```bash
# No MySQL/MariaDB
DROP TABLE IF EXISTS glpi_plugin_reservationalert_notifications;
DROP TABLE IF EXISTS glpi_plugin_reservationalert_configs;
```

Depois, no GLPI: **Configurar → Plugins → Desativar → Ativar**
