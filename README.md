# glpi-reservationalert

🇬🇧 [English version here](README.en.md)

Plugin para GLPI 11 que avisa quando uma reserva está prestes a começar. Mete um sino
na barra de cima do GLPI com contagem de alertas, e se o browser deixar, também dispara
notificações push do próprio sistema. Fiz isto porque as pessoas reservavam material e
depois esqueciam-se dele.

Funciona para qualquer coisa reservável no GLPI (computadores, datashows, e também os
assets dos meus outros plugins: [veículos](https://github.com/SantiPT007/glpi-vehiclereservation)
e salas).

## Como funciona

Um cron corre de X em X segundos e procura reservas que começam dentro da janela de aviso
(por defeito 60 min). Quando encontra, cria uma notificação para quem reservou e para os
admins. O sino no browser vai buscar as notificações novas a cada 60s.

Cada utilizador pode desligar o sino só para si (Ferramentas → Alertas de Reserva), e o
admin pode desligar tudo globalmente na config do plugin.

Interface em inglês e português — a língua vem da sessão do GLPI.

## Instalar

Precisa de GLPI 11.x e PHP 8.1+. Para a instalação automática do crontab o PHP também
precisa de ter `exec`/`shell_exec` ativos, senão dá para instalar à mão na mesma.

```bash
cd /var/www/glpi/plugins
git clone https://github.com/SantiPT007/glpi-reservationalert reservationalert
chown -R www-data:www-data reservationalert
```

Depois no GLPI: Configurar → Plugins → Reservation Alert → Instalar → Ativar.

Por fim configurar o cron: Configurar → Plugins → Reservation Alert → Configurar, secção
"Crontab do sistema", botão "Instalar crontab". Se o `exec` estiver desativado, a página
mostra a linha para adicionar manualmente com `crontab -u www-data -e`.

## Configuração

Na página de config (só admins):

- ligar/desligar as notificações globalmente
- período de aviso em minutos (quanto tempo antes do início é que avisa)
- intervalo do cron em segundos (mínimo 60, eu deixo 300)
- botões de teste: enviar notificação de teste, correr o cron já, forçar notificações
  para todas as reservas futuras

## Quando algo não funciona

Já me aconteceu de tudo, por isso aqui fica o que costuma ser:

**O plugin não aparece ou dá erro ao ativar** — limpar a cache:

```bash
rm -rf /var/www/glpi/files/_cache/*
```

Se houver OPcache, reiniciar o apache ou o php-fpm também.

**Cron instalado mas não chega nada** — primeiro confirmar que a entrada existe
(`crontab -u www-data -l`) e ver o log (`grep CRON /var/log/syslog` ou
`journalctl -u cron`). Mas na prática a causa mais comum é o **fuso horário**: o cron
compara a hora do servidor com a hora das reservas, e se o servidor está em UTC e os
utilizadores em Europe/Lisbon, as reservas caem fora da janela e nunca dispara nada.
Parece que o cron está morto mas não está. Confirmar com o botão "Correr cron agora" —
se a "Hora atual servidor" não bater certo com a hora local, é isto. A solução é pôr
`date.timezone = Europe/Lisbon` no php.ini, e em Docker definir `TZ=Europe/Lisbon` no
serviço do GLPI **e** no da base de dados.

**Notificações duplicadas ou em falta** — há um endpoint de diagnóstico (para admins) que
devolve o estado das tabelas em JSON:

```
https://glpi.exemplo.com/plugins/reservationalert/front/test.php
```

**Repor tudo de raiz** — apagar as tabelas e reinstalar:

```sql
DROP TABLE IF EXISTS glpi_plugin_reservationalert_notifications;
DROP TABLE IF EXISTS glpi_plugin_reservationalert_configs;
```

Depois Configurar → Plugins → Desativar → Ativar.

## Traduções

Os textos estão em inglês no código (domínio gettext `reservationalert`) com tradução
PT-PT em `locales/`. Depois de mexer no `.po`:

```bash
msgfmt locales/pt_PT.po -o locales/pt_PT.mo
```

O sino (JS estático) não passa pelo gettext do PHP — tem um dicionário próprio e escolhe
a língua pelo `<html lang>` da página.

## Licença

GPL v2+
