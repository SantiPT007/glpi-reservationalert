<?php

// unico ficheiro carregado pelo GLPI 11 para descoberta e init do plugin

if (!defined('PLUGIN_RESERVATIONALERT_VERSION')) {
    define('PLUGIN_RESERVATIONALERT_VERSION', '1.0.0');
}
if (!defined('PLUGIN_RESERVATIONALERT_MIN_GLPI')) {
    define('PLUGIN_RESERVATIONALERT_MIN_GLPI', '11.0.0');
}
if (!defined('PLUGIN_RESERVATIONALERT_MAX_GLPI')) {
    define('PLUGIN_RESERVATIONALERT_MAX_GLPI', '11.0.99');
}

function plugin_version_reservationalert(): array
{
    return [
        'name'         => 'Reservation Alert',
        'version'      => PLUGIN_RESERVATIONALERT_VERSION,
        'author'       => 'Santiago Almendra',
        'license'      => 'GPL v2+',
        'homepage'     => 'https://github.com/SantiPT007/glpi-reservationalert',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_RESERVATIONALERT_MIN_GLPI,
                'max' => PLUGIN_RESERVATIONALERT_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_reservationalert_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_RESERVATIONALERT_MIN_GLPI, 'lt')) {
        echo 'Reservation Alert requires GLPI >= ' . PLUGIN_RESERVATIONALERT_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_reservationalert_check_config(bool $verbose = false): bool
{
    return true;
}

// init, corre em cada pedido autenticado
function plugin_init_reservationalert(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['reservationalert'] = true;

    $plugin = new Plugin();
    if (!$plugin->isActivated('reservationalert')) {
        return;
    }

    // GLPI 11.0.7+: registar SEM o prefixo `public/` (o core ja procura em public/ no disco).
    // usar `public/...` aciona uma depreciacao que corrompe o JS servido (red banner + "Unexpected token '<'").
    $PLUGIN_HOOKS['add_css']['reservationalert']        = ['css/reservationalert.css'];
    $PLUGIN_HOOKS['add_javascript']['reservationalert'] = ['js/reservationalert.js'];

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['reservationalert'] = 'front/config.form.php';
    }

    $PLUGIN_HOOKS['menu_toadd']['reservationalert'] = ['tools' => 'GlpiPlugin\\Reservationalert\\Config'];

    // hook para notificar imediatamente quando uma reserva e criada
    $PLUGIN_HOOKS['item_add']['reservationalert'] = [
        'Reservation' => 'plugin_reservationalert_item_add_reservation',
    ];
}

function plugin_reservationalert_install(): bool
{
    global $DB;

    $schema = Plugin::getPhpDir('reservationalert') . '/sql/install.sql';
    if (file_exists($schema)) {
        $DB->runFile($schema);
    }

    CronTask::register(
        'GlpiPlugin\\Reservationalert\\CronHandler',
        'CheckReservations',
        300,
        [
            'comment' => 'Send alerts for reservations starting soon',
            'mode'    => CronTask::MODE_EXTERNAL,
        ]
    );

    return true;
}

function plugin_reservationalert_uninstall(): bool
{
    global $DB;

    foreach ([
        'glpi_plugin_reservationalert_notifications',
        'glpi_plugin_reservationalert_configs',
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->dropTable($table);
        }
    }

    CronTask::unregister('reservationalert');

    return true;
}
