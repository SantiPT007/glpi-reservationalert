<?php

// quando uma reserva e criada notifica ja se estiver dentro da janela
function plugin_reservationalert_item_add_reservation(Reservation $item): void
{
    $config = GlpiPlugin\Reservationalert\Config::getConfig();
    if (empty($config['global_enabled'])) {
        return;
    }

    $begin = $item->fields['begin'] ?? '';
    if (empty($begin)) {
        return;
    }

    $end = $item->fields['end'] ?? '';

    try {
        $begin_dt    = new DateTime($begin);
        $end_dt      = new DateTime($end ?: 'now');
        $now         = new DateTime();
        $warning_min = (int) ($config['warning_minutes'] ?? 60);
        $threshold   = (clone $now)->modify("+{$warning_min} minutes");
    } catch (\Exception $e) {
        return;
    }

    // ignora se ja acabou ou e demasiado longe
    if ($end_dt <= $now || $begin_dt > $threshold) {
        return;
    }

    $reservation_id = (int) $item->fields['id'];
    $reserver_id    = (int) $item->fields['users_id'];
    $admin_ids      = GlpiPlugin\Reservationalert\CronHandler::getAdminUserIds();

    $targets = array_unique(array_merge([$reserver_id], $admin_ids));
    foreach ($targets as $uid) {
        if (!GlpiPlugin\Reservationalert\Notification::exists($reservation_id, $uid)) {
            GlpiPlugin\Reservationalert\Notification::create($reservation_id, $uid);
        }
    }
}
