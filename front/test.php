<?php

// verifica tabelas e corre a query de notificacoes para diagnostico

include('../../../inc/includes.php');

\Session::checkLoginUser();
header('Content-Type: application/json');

$captured_errors = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$captured_errors) {
    $labels = [E_WARNING => 'Warning', E_NOTICE => 'Notice', E_DEPRECATED => 'Deprecated',
               E_USER_WARNING => 'UserWarning', E_USER_NOTICE => 'UserNotice'];
    $label = $labels[$errno] ?? "PHP($errno)";
    $captured_errors[] = "$label: $errstr\n  in $errfile:$errline";
    return true;
});

ob_start();

$result = ['ok' => false, 'count' => 0, 'php_output' => '', 'error' => ''];

try {
    global $DB;

    if (!$DB->tableExists('glpi_plugin_reservationalert_configs')) {
        throw new \RuntimeException(
            'Tabela glpi_plugin_reservationalert_configs nao existe.' . "\n" .
            'O plugin precisa de ser reinstalado: Configurar > Plugins > Desativar > Ativar.'
        );
    }

    if (!$DB->tableExists('glpi_plugin_reservationalert_notifications')) {
        throw new \RuntimeException(
            'Tabela glpi_plugin_reservationalert_notifications nao existe.' . "\n" .
            'O plugin precisa de ser reinstalado: Configurar > Plugins > Desativar > Ativar.'
        );
    }

    $users_id = (int) \Session::getLoginUserID();
    $is_admin = \Session::haveRight('config', READ);

    $rows  = GlpiPlugin\Reservationalert\Notification::getForUser($users_id, $is_admin);
    $count = count($rows);

    $result['ok']    = true;
    $result['count'] = $count;

} catch (\Throwable $e) {
    $result['error'] = $e->getMessage() . "\n\nStack trace:\n" . $e->getTraceAsString();
}

$buf = trim(ob_get_clean());
restore_error_handler();

$all_errors = implode("\n", $captured_errors);
$result['php_output'] = trim(($all_errors ? $all_errors . "\n" : '') . ($buf ?: ''));

echo json_encode($result);
