<?php

// gere a entrada do crontab do sistema para o plugin

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);
header('Content-Type: application/json');

$marker_begin = '# BEGIN glpi-reservationalert';
$marker_end   = '# END glpi-reservationalert';
$php_bin      = ra_find_php();
$cron_php     = GLPI_ROOT . '/front/cron.php';
$cron_line    = "* * * * * {$php_bin} {$cron_php} > /dev/null 2>&1";
$block        = "{$marker_begin}\n{$cron_line}\n{$marker_end}";

// PHP_BINARY e vazio em contexto FPM, procuramos o binario pelo caminho
// preferimos caminhos sem versao para nao partir quando o PHP for atualizado
function ra_find_php(): string
{
    $unversioned = ['/usr/bin/php', '/usr/local/bin/php'];
    foreach ($unversioned as $bin) {
        if (is_executable($bin)) {
            return $bin;
        }
    }

    $which = trim((string) shell_exec('which php 2>/dev/null'));
    if ($which && is_executable($which)) {
        return $which;
    }

    foreach ([
        '/usr/bin/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        '/usr/bin/php' . PHP_MAJOR_VERSION,
    ] as $bin) {
        if (is_executable($bin)) {
            return $bin;
        }
    }

    return 'php';
}

function ra_exec_available(): bool
{
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return function_exists('exec')
        && function_exists('shell_exec')
        && !in_array('exec', $disabled, true)
        && !in_array('shell_exec', $disabled, true);
}

function ra_read_crontab(): string
{
    return (string) shell_exec('crontab -l 2>/dev/null');
}

function ra_write_crontab(string $content): bool
{
    $tmp = tempnam(sys_get_temp_dir(), 'glpi_ra_crontab_');
    file_put_contents($tmp, $content);
    exec('crontab ' . escapeshellarg($tmp), $out, $code);
    unlink($tmp);
    return $code === 0;
}

function ra_remove_block(string $crontab, string $begin, string $end): string
{
    return preg_replace(
        '/\n?' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '\n?/s',
        '',
        $crontab
    ) ?? $crontab;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

if ($action === 'status') {
    $exec_ok = ra_exec_available();
    $crontab = $exec_ok ? ra_read_crontab() : '';
    $user    = $exec_ok ? trim((string) shell_exec('whoami')) : 'N/A';

    echo json_encode([
        'exec_available' => $exec_ok,
        'installed'      => $exec_ok && str_contains($crontab, $marker_begin),
        'cron_line'      => $cron_line,
        'crontab_user'   => $user,
    ]);
    exit;
}

if (!ra_exec_available()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => __('exec() is disabled on this server.', 'reservationalert')]);
    exit;
}

if ($action === 'install') {
    $crontab = ra_remove_block(ra_read_crontab(), $marker_begin, $marker_end);
    $crontab = rtrim($crontab) . "\n" . $block . "\n";
    $ok      = ra_write_crontab($crontab);
    echo json_encode(['ok' => $ok, 'installed' => $ok]);
    exit;
}

if ($action === 'remove') {
    $crontab = ra_remove_block(ra_read_crontab(), $marker_begin, $marker_end);
    $ok      = ra_write_crontab(rtrim($crontab) . "\n");
    echo json_encode(['ok' => $ok, 'installed' => false]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => __('Unknown action.', 'reservationalert')]);
