<?php

// devolve notificacoes nao lidas em JSON, chamado pelo tabuleiro a cada 60s

include('../../../inc/includes.php');

\Session::checkLoginUser();
header('Content-Type: application/json');

try {
    $users_id       = (int) \Session::getLoginUserID();
    $is_admin       = \Session::haveRight('config', READ);
    $config         = GlpiPlugin\Reservationalert\Config::getConfig();
    $global_enabled = (bool) ($config['global_enabled'] ?? true);

    $rows = GlpiPlugin\Reservationalert\Notification::getForUser($users_id, $is_admin);

    $out = [];
    foreach ($rows as $row) {
        $is_test = ((int) $row['reservations_id']) === 0;

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
        }

        $link = '';
        if (!$is_test) {
            global $CFG_GLPI;
            $root = $CFG_GLPI['root_doc'] ?? '';
            if ($is_admin) {
                $itemtype_lower = strtolower((string) ($row['item_type'] ?? ''));
                $iid            = (int) ($row['item_items_id'] ?? 0);
                $link = $root . '/front/' . $itemtype_lower . '.form.php?id=' . $iid . '&forcetab=Reservation%241';
            } else {
                $link = $root . '/front/reservation.php';
            }
        }

        $out[] = [
            'id'        => (int) $row['id'],
            'item_name' => htmlspecialchars($item_name),
            'begin'     => $begin_str,
            'reserver'  => htmlspecialchars($reserver),
            'is_read'   => (bool) $row['is_read'],
            'link'      => $link,
        ];
    }

    echo json_encode(['notifications' => $out, 'global_enabled' => $global_enabled]);

} catch (\Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'notifications'   => [],
        'global_enabled'  => true,
        'debug_error'     => $e->getMessage(),
        'debug_trace'     => $e->getTraceAsString(),
    ]);
}
