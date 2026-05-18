<?php

// devolve notificacoes nao lidas em JSON, chamado pelo tabuleiro a cada 60s

include('../../../inc/includes.php');

\Session::checkLoginUser();
header('Content-Type: application/json');

$users_id      = (int) \Session::getLoginUserID();
$is_admin      = \Session::haveRight('config', READ);
$config        = GlpiPlugin\Reservationalert\Config::getConfig();
$global_enabled = (bool) ($config['global_enabled'] ?? true);

$rows = GlpiPlugin\Reservationalert\Notification::getForUser($users_id, $is_admin);

$out = [];
foreach ($rows as $row) {
    // Test notifications have reservations_id=0 and no joined data
    $is_test = ((int) $row['reservations_id']) === 0;

    $item_url        = '';
    $reservation_url = '';

    if ($is_test) {
        $item_name = '[TESTE] Notificacao de teste';
        $begin_str = 'Agora';
        $reserver  = 'Admin';
    } else {
        $begin_str = '';
        if (!empty($row['reservation_begin'])) {
            try {
                $begin_str = (new DateTime($row['reservation_begin']))->format('d M Y, H:i');
            } catch (\Exception $e) {
                $begin_str = $row['reservation_begin'];
            }
        }

        $item_name = '';
        $itemtype  = $row['item_type'] ?? '';
        $items_id  = (int) ($row['item_items_id'] ?? 0);
        if ($itemtype && $items_id && class_exists($itemtype)) {
            $item = new $itemtype();
            if ($item->getFromDB($items_id)) {
                $item_name = $item->getName();
            }
        }
        if (empty($item_name)) {
            $item_name = $itemtype ? $itemtype . ' #' . $items_id : 'Item desconhecido';
        }

        $reserver = trim(($row['reserver_firstname'] ?? '') . ' ' . ($row['reserver_name'] ?? ''));

        $glpi_root       = CFG_GLPI['root_doc'] ?? '';
        $item_url        = $glpi_root . '/front/' . strtolower($itemtype) . '.form.php?id=' . $items_id . '&forcetab=Reservation$1';
        $reservation_url = $glpi_root . '/front/reservation.php';
    }

    $out[] = [
        'id'              => (int) $row['id'],
        'item_name'       => htmlspecialchars($item_name),
        'begin'           => $begin_str,
        'reserver'        => htmlspecialchars($reserver),
        'is_read'         => (bool) $row['is_read'],
        'item_url'        => $is_admin && !$is_test ? $item_url : '',
        'reservation_url' => !$is_test ? $reservation_url : '',
    ];
}

echo json_encode(['notifications' => $out, 'global_enabled' => $global_enabled, 'is_admin' => $is_admin]);
