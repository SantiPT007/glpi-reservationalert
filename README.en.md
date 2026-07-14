# glpi-reservationalert

🇵🇹 [Versão portuguesa aqui](README.md)

GLPI 11 plugin that warns you when a reservation is about to start. It adds a bell to the
top bar of GLPI with an alert count, and if the browser allows it, it also fires native
push notifications. I built this because people kept reserving equipment and then
forgetting about it.

Works with anything reservable in GLPI (computers, projectors, and also the assets from my
other plugins: [vehicles](https://github.com/SantiPT007/glpi-vehiclereservation) and rooms).

## How it works

A cron job runs every X seconds and looks for reservations starting within the warning
window (60 min by default). When it finds one, it creates a notification for the person
who reserved and for the admins. The bell in the browser polls for new notifications
every 60s.

Each user can turn the bell off just for themselves (Tools → Reservation Alerts), and the
admin can kill everything globally in the plugin config.

UI is in English and Portuguese — the language follows the GLPI session.

## Installing

You need GLPI 11.x and PHP 8.1+. For the automatic crontab install PHP also needs
`exec`/`shell_exec` enabled, otherwise you can still install it by hand.

```bash
cd /var/www/glpi/plugins
git clone https://github.com/SantiPT007/glpi-reservationalert reservationalert
chown -R www-data:www-data reservationalert
```

Then in GLPI: Setup → Plugins → Reservation Alert → Install → Enable.

Finally set up the cron: Setup → Plugins → Reservation Alert → Configure, "System crontab"
section, "Install crontab" button. If `exec` is disabled, the page shows you the line to
add manually with `crontab -u www-data -e`.

## Configuration

On the config page (admins only):

- turn notifications on/off globally
- warning period in minutes (how long before the start it warns)
- cron interval in seconds (minimum 60, I leave it at 300)
- test buttons: send a test notification, run the cron right now, force notifications
  for every future reservation

## When something doesn't work

I've hit all of these myself, so here's what it usually is:

**Plugin doesn't show up or errors on enable** — clear the cache:

```bash
rm -rf /var/www/glpi/files/_cache/*
```

If there's OPcache, restart apache or php-fpm too.

**Cron installed but nothing arrives** — first confirm the entry exists
(`crontab -u www-data -l`) and check the log (`grep CRON /var/log/syslog` or
`journalctl -u cron`). But in practice the most common cause is the **timezone**: the cron
compares the server clock against reservation times, and if the server is on UTC while the
users are on Europe/Lisbon, reservations fall outside the window and nothing ever fires.
Looks like the cron is dead but it isn't. Check with the "Run cron now" button — if the
"Current server time" doesn't match local time, that's it. The fix is
`date.timezone = Europe/Lisbon` in php.ini, and in Docker set `TZ=Europe/Lisbon` on the
GLPI service **and** on the database service.

**Duplicate or missing notifications** — there's a diagnostic endpoint (admins) that
returns the table state as JSON:

```
https://glpi.example.com/plugins/reservationalert/front/test.php
```

**Reset from scratch** — drop the tables and reinstall:

```sql
DROP TABLE IF EXISTS glpi_plugin_reservationalert_notifications;
DROP TABLE IF EXISTS glpi_plugin_reservationalert_configs;
```

Then Setup → Plugins → Disable → Enable.

## Translations

Strings are in English in the code (gettext domain `reservationalert`) with a PT-PT
translation in `locales/`. After touching the `.po`:

```bash
msgfmt locales/pt_PT.po -o locales/pt_PT.mo
```

The bell (static JS) doesn't go through PHP gettext — it has its own dictionary and picks
the language from the page's `<html lang>`.

## License

GPL v2+
