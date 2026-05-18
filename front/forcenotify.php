<?php

// forca notificacoes para todas as reservas futuras ignorando a janela de aviso

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);
header('Content-Type: application/json');

global $DB;

$now = new DateTime();

$iterator = $DB->request([
    'SELECT' => ['r.id AS reservations_id', 'r.users_id', 'r.begin'],
    'FROM'   => 'glpi_reservations AS r',
    'WHERE'  => [
        'r.begin' => ['>', $now->format('Y-m-d H:i:s')],
    ],
]);

$reservations = iterator_to_array($iterator);
$admin_ids    = GlpiPlugin\Reservationalert\CronHandler::getAdminUserIds();
$created      = 0;
$skipped      = 0;
$detail       = [];

foreach ($reservations as $row) {
    $reservation_id = (int) $row['reservations_id'];
    $reserver_id    = (int) $row['users_id'];

    $targets = array_unique(array_merge([$reserver_id], $admin_ids));
    foreach ($targets as $uid) {
        if (!GlpiPlugin\Reservationalert\Notification::exists($reservation_id, $uid)) {
            GlpiPlugin\Reservationalert\Notification::create($reservation_id, $uid);
            $created++;
        } else {
            $skipped++;
        }
    }

    $detail[] = ['reservation_id' => $reservation_id, 'begin' => $row['begin'], 'reserver' => $reserver_id];
}

echo json_encode([
    'ok'           => true,
    'reservations' => count($reservations),
    'created'      => $created,
    'skipped'      => $skipped,
    'detail'       => $detail,
]);
