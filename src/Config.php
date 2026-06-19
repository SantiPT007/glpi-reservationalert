<?php

namespace GlpiPlugin\Reservationalert;

use CommonDBTM;
use Session;

// configuracao do plugin, tabela com uma so linha
class Config extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return 'Reservation Alert';
    }

    public static function getConfig(): array
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => 'glpi_plugin_reservationalert_configs',
            'LIMIT' => 1,
        ])->current();

        return $row ?: ['id' => 0, 'warning_minutes' => 60, 'global_enabled' => 1];
    }

    public static function saveWarningMinutes(int $minutes): void
    {
        global $DB;

        $DB->update(
            'glpi_plugin_reservationalert_configs',
            ['warning_minutes' => max(1, $minutes), 'date_mod' => date('Y-m-d H:i:s')],
            ['id' => 1]
        );
    }

    public static function saveCronInterval(int $seconds): void
    {
        global $DB;

        $seconds = max(60, min(3600, $seconds));
        $DB->update('glpi_crontasks', ['frequency' => $seconds], [
            'itemtype' => 'GlpiPlugin\\Reservationalert\\CronHandler',
            'name'     => 'CheckReservations',
        ]);
    }

    public static function saveGlobalEnabled(int $enabled): void
    {
        global $DB;

        $DB->update(
            'glpi_plugin_reservationalert_configs',
            ['global_enabled' => $enabled ? 1 : 0, 'date_mod' => date('Y-m-d H:i:s')],
            ['id' => 1]
        );
    }

    public static function getFormURL($full = true)
    {
        // Plugin::getWebDir() esta depreciado no GLPI 11; construir o caminho a mao
        global $CFG_GLPI;
        return ($full ? $CFG_GLPI['root_doc'] : '') . '/plugins/reservationalert/front/config.form.php';
    }

    public static function getMenuContent()
    {
        return [
            'title' => 'Alertas de Reserva',
            'page'  => '/plugins/reservationalert/front/settings.php',
            'icon'  => 'ti ti-bell',
            'links' => [],
        ];
    }

    public function defineTabs($options = [])
    {
        $tabs = parent::defineTabs($options);
        $this->addStandardTab(__CLASS__, $tabs, $options);
        return $tabs;
    }
}
