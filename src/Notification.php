<?php

namespace GlpiPlugin\Reservationalert;

use CommonDBTM;
use Session;

// uma linha por par reserva+utilizador, serve de dedup e de store de notificacoes
class Notification extends CommonDBTM
{
    public static $rightname = 'reservation';

    public static function getTypeName($nb = 0)
    {
        return 'Reservation Notification';
    }

    public static function getForUser(int $users_id, bool $is_admin = false): array
    {
        global $DB;

        // admins ja tem linhas proprias para cada reserva, nao precisamos de mostrar as dos outros utilizadores
        $where = ['n.is_read' => 0, 'n.users_id' => $users_id];

        $iterator = $DB->request([
            'SELECT'     => [
                'n.id',
                'n.reservations_id',
                'n.users_id',
                'n.is_read',
                'n.date_creation',
                'r.begin as reservation_begin',
                'r.end as reservation_end',
                'ri.id as reservationitems_id',
                'ri.itemtype as item_type',
                'ri.items_id as item_items_id',
                'u.name as reserver_name',
                'u.firstname as reserver_firstname',
            ],
            'FROM'       => 'glpi_plugin_reservationalert_notifications AS n',
            'LEFT JOIN'  => [
                'glpi_reservations AS r' => [
                    'ON' => ['n' => 'reservations_id', 'r' => 'id'],
                ],
                'glpi_reservationitems AS ri' => [
                    'ON' => ['r' => 'reservationitems_id', 'ri' => 'id'],
                ],
                'glpi_users AS u' => [
                    'ON' => ['r' => 'users_id', 'u' => 'id'],
                ],
            ],
            'WHERE'      => $where,
            'ORDER'      => 'r.begin ASC',
        ]);

        return iterator_to_array($iterator);
    }

    public static function markRead(int $notification_id, int $users_id): void
    {
        global $DB;

        $DB->update(
            'glpi_plugin_reservationalert_notifications',
            ['is_read' => 1],
            ['id' => $notification_id, 'users_id' => $users_id]
        );
    }

    public static function markAllRead(int $users_id): void
    {
        global $DB;

        $DB->update(
            'glpi_plugin_reservationalert_notifications',
            ['is_read' => 1],
            ['users_id' => $users_id, 'is_read' => 0]
        );
    }

    // evita duplicados
    public static function exists(int $reservations_id, int $users_id): bool
    {
        global $DB;

        return countElementsInTable(
            'glpi_plugin_reservationalert_notifications',
            ['reservations_id' => $reservations_id, 'users_id' => $users_id]
        ) > 0;
    }

    public static function create(int $reservations_id, int $users_id): void
    {
        global $DB;

        $DB->insert('glpi_plugin_reservationalert_notifications', [
            'reservations_id' => $reservations_id,
            'users_id'        => $users_id,
            'is_read'         => 0,
            'date_creation'   => date('Y-m-d H:i:s'),
        ]);
    }
}
