<?php

// marca notificacoes como lidas, id para uma ou all para todas

include('../../../inc/includes.php');

\Session::checkLoginUser();
header('Content-Type: application/json');

$users_id = (int) \Session::getLoginUserID();

if (!empty($_POST['all'])) {
    GlpiPlugin\Reservationalert\Notification::markAllRead($users_id);
    echo json_encode(['ok' => true]);
    exit;
}

if (!empty($_POST['id'])) {
    GlpiPlugin\Reservationalert\Notification::markRead((int) $_POST['id'], $users_id);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Missing id or all parameter']);
