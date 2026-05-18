<?php

/**
 * Admin config page: accessible via Configurar > Plugins > Reservation Alert > Config
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['warning_minutes'])) {
    GlpiPlugin\Reservationalert\Config::saveWarningMinutes((int) $_POST['warning_minutes']);
    GlpiPlugin\Reservationalert\Config::saveGlobalEnabled(isset($_POST['global_enabled']) ? 1 : 0);
    if (!empty($_POST['cron_interval'])) {
        GlpiPlugin\Reservationalert\Config::saveCronInterval((int) $_POST['cron_interval']);
    }
    Html::back();
}

// Read current cron interval from glpi_crontasks
$cron_task = new CronTask();
$cron_interval = 300; // default 5 min
if ($cron_task->getFromDBbyName('GlpiPlugin\\Reservationalert\\CronHandler', 'CheckReservations')) {
    $cron_interval = (int) $cron_task->fields['frequency'];
}

$config = GlpiPlugin\Reservationalert\Config::getConfig();

Html::header(
    'Alertas de Reserva',
    $_SERVER['PHP_SELF'],
    'setup',
    'setup'
);
?>

<div class="container-fluid">
    <div class="card mt-4" style="max-width:480px;margin:2rem auto;">
        <div class="card-header d-flex align-items-center gap-2">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <path d="M8 21h8M12 17v4"/>
                <rect x="5" y="19" width="14" height="2" rx="1"/>
            </svg>
            <strong>Alertas de Reserva: Definições</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="config.form.php">
                <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="global_enabled"
                            name="global_enabled"
                            value="1"
                            <?= !empty($config['global_enabled']) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="global_enabled">
                            Ativar notificações de reserva globalmente
                        </label>
                    </div>
                    <div class="form-text">
                        Quando desativado, o sino de notificações nao aparece para nenhum utilizador, independentemente das suas definicoes pessoais.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="warning_minutes" class="form-label">
                        Período de aviso
                        <small class="text-muted">(minutos antes do início da reserva)</small>
                    </label>
                    <input
                        type="number"
                        id="warning_minutes"
                        name="warning_minutes"
                        class="form-control"
                        min="1"
                        max="10080"
                        value="<?= (int)($config['warning_minutes'] ?? 60) ?>"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="cron_interval" class="form-label">
                        Intervalo do cron
                        <small class="text-muted">(segundos entre cada verificacao)</small>
                    </label>
                    <input
                        type="number"
                        id="cron_interval"
                        name="cron_interval"
                        class="form-control"
                        min="60"
                        max="3600"
                        value="<?= $cron_interval ?>"
                        required
                    >
                    <div class="form-text text-warning">
                        A taxa minima de atualizacao do cron e 60s. Valores baixos verificam mais frequentemente mas podem degradar o desempenho do servidor; 300s (5 min) e recomendado.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>

            <hr>

            <div class="mt-3">
                <div class="fw-semibold mb-2" style="font-size:13px;">Ferramentas de teste</div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-warning btn-sm" id="ra-test-send">
                        Enviar notificacao de teste (todos os utilizadores)
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="ra-test-clear">
                        Limpar notificacoes de teste
                    </button>
                    <button type="button" class="btn btn-info btn-sm text-white" id="ra-cron-run">
                        Correr cron agora
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" id="ra-force-notify">
                        Forcar notificacao (todas as reservas futuras)
                    </button>
                </div>
                <div id="ra-test-result" class="mt-2" style="font-size:12px;"></div>
                <pre id="ra-cron-result" style="display:none;background:#1e1e2e;color:#cdd6f4;padding:10px;
                     border-radius:6px;font-size:11px;margin-top:8px;white-space:pre-wrap;word-break:break-word;"></pre>
            </div>

            <hr>

            <div class="mt-3">
                <div class="fw-semibold mb-2" style="font-size:13px;">Crontab do sistema</div>
                <div id="ra-crontab-loading" class="text-muted" style="font-size:12px;">A verificar estado do crontab...</div>

                <div id="ra-crontab-section" style="display:none;">

                    <div id="ra-crontab-no-exec" class="alert alert-warning py-2" style="display:none;font-size:12px;">
                        <strong>exec() desativado neste servidor.</strong><br>
                        Instala o crontab manualmente via SSH com o comando abaixo.
                    </div>

                    <div id="ra-crontab-exec-ok" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px;">
                            <span>Estado:</span>
                            <span id="ra-crontab-badge"></span>
                            <span class="text-muted" id="ra-crontab-user"></span>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-success btn-sm" id="ra-crontab-install">Instalar crontab</button>
                            <button type="button" class="btn btn-danger btn-sm" id="ra-crontab-remove" style="display:none;">Remover crontab</button>
                        </div>
                    </div>

                    <div class="mt-1">
                        <div class="text-muted" style="font-size:11px;">Comando gerado (copia para instalar manualmente):</div>
                        <code id="ra-crontab-line" style="font-size:11px;word-break:break-all;display:block;
                              background:#1e1e2e;color:#cdd6f4;padding:6px 10px;border-radius:4px;margin-top:4px;"></code>
                    </div>

                    <div id="ra-crontab-result" class="mt-2" style="font-size:12px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var pluginRoot = <?= json_encode(Plugin::getWebDir('reservationalert')) ?>;
    var csrfToken = document.querySelector('meta[property="glpi:csrf_token"]');
    var token = csrfToken ? csrfToken.getAttribute('content') : '';

    function call(params, label) {
        var result = document.getElementById('ra-test-result');
        result.textContent = label + '...';

        $.post(pluginRoot + '/front/testnotify.php', Object.assign({_glpi_csrf_token: token}, params))
            .done(function (data) {
                if (params.clear) {
                    result.textContent = 'Notificacoes de teste removidas.';
                } else {
                    result.textContent = 'Inserido para ' + data.users + ' utilizador(es). O tabuleiro atualiza em ate 60s.';
                }
            })
            .fail(function (xhr) {
                result.textContent = 'Erro: ' + xhr.status + ' ' + xhr.responseText;
            });
    }

    document.getElementById('ra-test-send').addEventListener('click', function () {
        call({}, 'A enviar');
    });
    document.getElementById('ra-test-clear').addEventListener('click', function () {
        call({clear: 1}, 'A limpar');
    });

    document.getElementById('ra-force-notify').addEventListener('click', function () {
        var result = document.getElementById('ra-test-result');
        var pre    = document.getElementById('ra-cron-result');
        result.textContent = 'A forcar notificacoes...';
        pre.style.display  = 'none';

        $.post(pluginRoot + '/front/forcenotify.php', {_glpi_csrf_token: token})
            .done(function (data) {
                result.textContent = '';
                var lines = [];
                lines.push('Reservas futuras encontradas: ' + data.reservations);
                lines.push('Notificacoes criadas: ' + data.created);
                lines.push('Ja existiam (ignoradas): ' + data.skipped);
                if (data.detail && data.detail.length > 0) {
                    lines.push('');
                    data.detail.forEach(function (r) {
                        lines.push('  Reserva #' + r.reservation_id + ' | inicio: ' + r.begin + ' | user_id: ' + r.reserver);
                    });
                } else {
                    lines.push('Nenhuma reserva futura encontrada na base de dados.');
                }
                pre.textContent   = lines.join('\n');
                pre.style.display = 'block';
            })
            .fail(function (xhr) {
                result.textContent = 'Erro: ' + xhr.status;
                pre.textContent    = xhr.responseText;
                pre.style.display  = 'block';
            });
    });

    document.getElementById('ra-cron-run').addEventListener('click', function () {
        var result = document.getElementById('ra-test-result');
        var pre    = document.getElementById('ra-cron-result');
        result.textContent = 'A correr cron...';
        pre.style.display  = 'none';

        $.post(pluginRoot + '/front/runcron.php', {_glpi_csrf_token: token})
            .done(function (data) {
                result.textContent = '';
                var lines = [];
                lines.push('Resultado: ' + data.result);
                lines.push('Reservas encontradas na janela de ' + data.window_minutes + ' min: ' + data.upcoming_reservations);
                lines.push('Notificacoes criadas: ' + data.notifications_created);
                lines.push('Hora atual servidor: ' + data.now);
                lines.push('Limite: ' + data.threshold);
                if (data.upcoming_detail && data.upcoming_detail.length > 0) {
                    lines.push('');
                    lines.push('Reservas encontradas:');
                    data.upcoming_detail.forEach(function (r) {
                        lines.push('  ID ' + r.id + ' | inicio: ' + r.begin + ' | user_id: ' + r.user);
                    });
                } else {
                    lines.push('');
                    lines.push('Nenhuma reserva dentro da janela de aviso.');
                }
                if (data.php_output) {
                    lines.push('');
                    lines.push('Output PHP: ' + data.php_output);
                }
                pre.textContent   = lines.join('\n');
                pre.style.display = 'block';
            })
            .fail(function (xhr) {
                result.textContent = 'Erro: ' + xhr.status;
                pre.textContent    = xhr.responseText;
                pre.style.display  = 'block';
            });
    });
}());
</script>

<script>
(function () {
    var pluginRoot  = <?= json_encode(Plugin::getWebDir('reservationalert')) ?>;
    var csrfMeta    = document.querySelector('meta[property="glpi:csrf_token"]');
    var token       = csrfMeta ? csrfMeta.getAttribute('content') : '';

    var loading    = document.getElementById('ra-crontab-loading');
    var section    = document.getElementById('ra-crontab-section');
    var noExec     = document.getElementById('ra-crontab-no-exec');
    var execOk     = document.getElementById('ra-crontab-exec-ok');
    var badge      = document.getElementById('ra-crontab-badge');
    var userSpan   = document.getElementById('ra-crontab-user');
    var lineEl     = document.getElementById('ra-crontab-line');
    var result     = document.getElementById('ra-crontab-result');
    var btnInstall = document.getElementById('ra-crontab-install');
    var btnRemove  = document.getElementById('ra-crontab-remove');

    function setBadge(installed) {
        if (installed) {
            badge.className          = 'badge bg-success';
            badge.textContent        = 'Instalado';
            btnInstall.style.display = 'none';
            btnRemove.style.display  = '';
        } else {
            badge.className          = 'badge bg-danger';
            badge.textContent        = 'Nao instalado';
            btnInstall.style.display = '';
            btnRemove.style.display  = 'none';
        }
    }

    function loadStatus() {
        $.get(pluginRoot + '/front/crontab.php', { action: 'status' })
            .done(function (data) {
                loading.style.display = 'none';
                section.style.display = 'block';
                lineEl.textContent    = data.cron_line;

                if (!data.exec_available) {
                    noExec.style.display = 'block';
                    execOk.style.display = 'none';
                } else {
                    noExec.style.display = 'none';
                    execOk.style.display = 'block';
                    userSpan.textContent = '(utilizador: ' + data.crontab_user + ')';
                    setBadge(data.installed);
                }
            })
            .fail(function () {
                loading.textContent = 'Erro ao verificar estado do crontab.';
            });
    }

    function doAction(action, label) {
        result.textContent = label + '...';
        $.post(pluginRoot + '/front/crontab.php', { action: action, _glpi_csrf_token: token })
            .done(function (data) {
                if (data.ok) {
                    setBadge(data.installed);
                    result.textContent = action === 'install'
                        ? 'Crontab instalado com sucesso.'
                        : 'Crontab removido com sucesso.';
                } else {
                    result.textContent = 'Erro: ' + (data.error || 'falha desconhecida.');
                }
            })
            .fail(function (xhr) {
                result.textContent = 'Erro HTTP ' + xhr.status + ': ' + xhr.responseText;
            });
    }

    btnInstall.addEventListener('click', function () { doAction('install', 'A instalar'); });
    btnRemove.addEventListener('click',  function () { doAction('remove',  'A remover');  });

    loadStatus();
}());
</script>

<?php Html::footer(); ?>
