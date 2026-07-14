<?php

namespace GlpiPlugin\Reservationalert;

use CronTask;

// cron handler do plugin, notifica reservas que se aproximam
class CronHandler
{
    public static function cronInfo(string $name): array
    {
        return match ($name) {
            'CheckReservations' => [
                'description' => __('Send alerts for reservations starting soon', 'reservationalert'),
            ],
            default => [],
        };
    }

    public static function cronCheckReservations(CronTask $task): int
    {
        global $DB;

        $config          = Config::getConfig();
        $warning_minutes = (int) ($config['warning_minutes'] ?? 60);

        $now        = new \DateTime();
        $threshold  = (clone $now)->modify("+{$warning_minutes} minutes");
        $lookback   = (clone $now)->modify("-{$warning_minutes} minutes");

        // reservas dentro da janela que ainda nao acabaram
        $iterator = $DB->request([
            'SELECT' => ['r.id AS reservations_id', 'r.users_id', 'r.begin'],
            'FROM'   => 'glpi_reservations AS r',
            'WHERE'  => [
                ['r.end'   => ['>', $now->format('Y-m-d H:i:s')]],
                ['r.begin' => ['>', $lookback->format('Y-m-d H:i:s')]],
                ['r.begin' => ['<=', $threshold->format('Y-m-d H:i:s')]],
            ],
        ]);

        if ($iterator->count() === 0) {
            $task->addVolume(0);
            return 1;
        }

        $admin_ids = self::getAdminUserIds();
        $notified  = 0;

        foreach ($iterator as $row) {
            $reservation_id = (int) $row['reservations_id'];
            $reserver_id    = (int) $row['users_id'];

            if (!Notification::exists($reservation_id, $reserver_id)) {
                Notification::create($reservation_id, $reserver_id);
                $notified++;
            }

            foreach ($admin_ids as $admin_id) {
                if ($admin_id === $reserver_id) {
                    continue;
                }
                if (!Notification::exists($reservation_id, $admin_id)) {
                    Notification::create($reservation_id, $admin_id);
                    $notified++;
                }
            }
        }

        $task->addVolume($notified);
        return 1;
    }

    // admins ativos com direito de config
    public static function getAdminUserIds(): array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT'    => ['pu.users_id'],
            'FROM'      => 'glpi_profilerights AS pr',
            'LEFT JOIN' => [
                'glpi_profiles_users AS pu' => [
                    'ON' => ['pr' => 'profiles_id', 'pu' => 'profiles_id'],
                ],
                'glpi_users AS u' => [
                    'ON' => ['pu' => 'users_id', 'u' => 'id'],
                ],
            ],
            'WHERE' => [
                'pr.name'      => 'config',
                'pr.rights'    => ['&', UPDATE],
                'u.is_active'  => 1,
                'u.is_deleted' => 0,
            ],
        ]);

        return array_values(array_unique(array_column(iterator_to_array($iterator), 'users_id')));
    }
}
