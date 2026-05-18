<?php

// insere notificacao de teste para todos os utilizadores ativos, POST limpa

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);
header('Content-Type: application/json');

global $DB;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['clear'])) {
    $DB->delete('glpi_plugin_reservationalert_notifications', ['reservations_id' => 0]);
    echo json_encode(['ok' => true, 'action' => 'cleared']);
    exit;
}

$users = $DB->request([
    'SELECT' => ['id'],
    'FROM'   => 'glpi_users',
    'WHERE'  => ['is_deleted' => 0, 'is_active' => 1],
]);

$inserted = 0;
foreach ($users as $user) {
    $uid = (int) $user['id'];
    $exists = countElementsInTable(
        'glpi_plugin_reservationalert_notifications',
        ['reservations_id' => 0, 'users_id' => $uid]
    ) > 0;

    if (!$exists) {
        $DB->insert('glpi_plugin_reservationalert_notifications', [
            'reservations_id' => 0,
            'users_id'        => $uid,
            'is_read'         => 0,
            'date_creation'   => date('Y-m-d H:i:s'),
        ]);
        $inserted++;
    }
}

echo json_encode(['ok' => true, 'action' => 'inserted', 'users' => $inserted]);
