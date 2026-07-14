<?php

// dispara o cron manualmente para debug, so admin

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);
header('Content-Type: application/json');

global $DB;

$task = new CronTask();
if (!$task->getFromDBbyName('GlpiPlugin\\Reservationalert\\CronHandler', 'CheckReservations')) {
    echo json_encode([
        'ok'    => false,
        'error' => __('Cron task not found. Reinstall the plugin (disable and enable it).', 'reservationalert'),
    ]);
    exit;
}

$config          = GlpiPlugin\Reservationalert\Config::getConfig();
$warning_minutes = (int) ($config['warning_minutes'] ?? 60);
$now             = new DateTime();
$threshold       = (clone $now)->modify("+{$warning_minutes} minutes");
$lookback        = (clone $now)->modify("-{$warning_minutes} minutes");

$upcoming = iterator_to_array($DB->request([
    'SELECT' => ['r.id', 'r.begin', 'r.users_id'],
    'FROM'   => 'glpi_reservations AS r',
    'WHERE'  => [
        ['r.end'   => ['>', $now->format('Y-m-d H:i:s')]],
        ['r.begin' => ['>', $lookback->format('Y-m-d H:i:s')]],
        ['r.begin' => ['<=', $threshold->format('Y-m-d H:i:s')]],
    ],
]));

$count_before = countElementsInTable('glpi_plugin_reservationalert_notifications');

ob_start();
$result = GlpiPlugin\Reservationalert\CronHandler::cronCheckReservations($task);
$output = trim(ob_get_clean());

$count_after = countElementsInTable('glpi_plugin_reservationalert_notifications');

echo json_encode([
    'ok'               => $result === 1,
    'result'           => $result === 1 ? 'RUN_SUCCESS' : 'RUN_FAILURE',
    'notifications_created' => $count_after - $count_before,
    'upcoming_reservations' => count($upcoming),
    'window_minutes'   => $warning_minutes,
    'now'              => $now->format('Y-m-d H:i:s'),
    'threshold'        => $threshold->format('Y-m-d H:i:s'),
    'upcoming_detail'  => array_map(fn($r) => [
        'id'    => $r['id'],
        'begin' => $r['begin'],
        'user'  => $r['users_id'],
    ], $upcoming),
    'php_output'       => $output,
]);
