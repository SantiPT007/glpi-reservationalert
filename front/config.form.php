<?php

/**
 * Admin config page: accessible via Configurar > Plugins > Reservation Alert > Config
 */

include('../../../inc/includes.php');

global $CFG_GLPI;

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
    __('Reservation Alerts', 'reservationalert'),
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
            <strong><?= __('Reservation Alerts: Settings', 'reservationalert') ?></strong>
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
                            <?= __('Enable reservation notifications globally', 'reservationalert') ?>
                        </label>
                    </div>
                    <div class="form-text">
                        <?= __('When disabled, the notification bell is hidden for every user, regardless of their personal settings.', 'reservationalert') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="warning_minutes" class="form-label">
                        <?= __('Warning period', 'reservationalert') ?>
                        <small class="text-muted"><?= __('(minutes before the reservation starts)', 'reservationalert') ?></small>
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
                        <?= __('Cron interval', 'reservationalert') ?>
                        <small class="text-muted"><?= __('(seconds between checks)', 'reservationalert') ?></small>
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
                        <?= __('The minimum cron frequency is 60s. Lower values check more often but may degrade server performance; 300s (5 min) is recommended.', 'reservationalert') ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><?= __('Save', 'reservationalert') ?></button>
            </form>

            <hr>

            <div class="mt-3">
                <div class="fw-semibold mb-2" style="font-size:13px;"><?= __('Test tools', 'reservationalert') ?></div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-warning btn-sm" id="ra-test-send">
                        <?= __('Send test notification (all users)', 'reservationalert') ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="ra-test-clear">
                        <?= __('Clear test notifications', 'reservationalert') ?>
                    </button>
                    <button type="button" class="btn btn-info btn-sm text-white" id="ra-cron-run">
                        <?= __('Run cron now', 'reservationalert') ?>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" id="ra-force-notify">
                        <?= __('Force notification (all future reservations)', 'reservationalert') ?>
                    </button>
                </div>
                <div id="ra-test-result" class="mt-2" style="font-size:12px;"></div>
                <pre id="ra-cron-result" style="display:none;background:#1e1e2e;color:#cdd6f4;padding:10px;
                     border-radius:6px;font-size:11px;margin-top:8px;white-space:pre-wrap;word-break:break-word;"></pre>
            </div>

            <hr>

            <div class="mt-3">
                <div class="fw-semibold mb-2" style="font-size:13px;"><?= __('System crontab', 'reservationalert') ?></div>
                <div id="ra-crontab-loading" class="text-muted" style="font-size:12px;"><?= __('Checking crontab status...', 'reservationalert') ?></div>

                <div id="ra-crontab-section" style="display:none;">

                    <div id="ra-crontab-no-exec" class="alert alert-warning py-2" style="display:none;font-size:12px;">
                        <strong><?= __('exec() is disabled on this server.', 'reservationalert') ?></strong><br>
                        <?= __('Install the crontab manually over SSH using the command below.', 'reservationalert') ?>
                    </div>

                    <div id="ra-crontab-exec-ok" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px;">
                            <span><?= __('Status:', 'reservationalert') ?></span>
                            <span id="ra-crontab-badge"></span>
                            <span class="text-muted" id="ra-crontab-user"></span>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-success btn-sm" id="ra-crontab-install"><?= __('Install crontab', 'reservationalert') ?></button>
                            <button type="button" class="btn btn-danger btn-sm" id="ra-crontab-remove" style="display:none;"><?= __('Remove crontab', 'reservationalert') ?></button>
                        </div>
                    </div>

                    <div class="mt-1">
                        <div class="text-muted" style="font-size:11px;"><?= __('Generated command (copy to install manually):', 'reservationalert') ?></div>
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
    var pluginRoot = <?= json_encode($CFG_GLPI['root_doc'] . '/plugins/reservationalert') ?>;
    var I18N = {
        sending:        <?= json_encode(__('Sending', 'reservationalert')) ?>,
        clearing:       <?= json_encode(__('Clearing', 'reservationalert')) ?>,
        testRemoved:    <?= json_encode(__('Test notifications removed.', 'reservationalert')) ?>,
        insertedFor:    <?= json_encode(__('Inserted for %s user(s). The tray updates within 60s.', 'reservationalert')) ?>,
        error:          <?= json_encode(__('Error:', 'reservationalert')) ?>,
        forcing:        <?= json_encode(__('Forcing notifications...', 'reservationalert')) ?>,
        futureFound:    <?= json_encode(__('Future reservations found:', 'reservationalert')) ?>,
        notifCreated:   <?= json_encode(__('Notifications created:', 'reservationalert')) ?>,
        alreadyExisted: <?= json_encode(__('Already existed (skipped):', 'reservationalert')) ?>,
        reservationNum: <?= json_encode(__('Reservation #', 'reservationalert')) ?>,
        start:          <?= json_encode(__('start:', 'reservationalert')) ?>,
        noFuture:       <?= json_encode(__('No future reservations found in the database.', 'reservationalert')) ?>,
        runningCron:    <?= json_encode(__('Running cron...', 'reservationalert')) ?>,
        result:         <?= json_encode(__('Result:', 'reservationalert')) ?>,
        windowFound:    <?= json_encode(__('Reservations found in the %s-minute window:', 'reservationalert')) ?>,
        serverTime:     <?= json_encode(__('Current server time:', 'reservationalert')) ?>,
        threshold:      <?= json_encode(__('Threshold:', 'reservationalert')) ?>,
        reservFound:    <?= json_encode(__('Reservations found:', 'reservationalert')) ?>,
        noneInWindow:   <?= json_encode(__('No reservations within the warning window.', 'reservationalert')) ?>,
        phpOutput:      <?= json_encode(__('PHP output:', 'reservationalert')) ?>
    };
    var csrfToken = document.querySelector('meta[property="glpi:csrf_token"]');
    var token = csrfToken ? csrfToken.getAttribute('content') : '';

    function call(params, label) {
        var result = document.getElementById('ra-test-result');
        result.textContent = label + '...';

        $.post(pluginRoot + '/front/testnotify.php', Object.assign({_glpi_csrf_token: token}, params))
            .done(function (data) {
                if (params.clear) {
                    result.textContent = I18N.testRemoved;
                } else {
                    result.textContent = I18N.insertedFor.replace('%s', data.users);
                }
            })
            .fail(function (xhr) {
                result.textContent = I18N.error + ' ' + xhr.status + ' ' + xhr.responseText;
            });
    }

    document.getElementById('ra-test-send').addEventListener('click', function () {
        call({}, I18N.sending);
    });
    document.getElementById('ra-test-clear').addEventListener('click', function () {
        call({clear: 1}, I18N.clearing);
    });

    document.getElementById('ra-force-notify').addEventListener('click', function () {
        var result = document.getElementById('ra-test-result');
        var pre    = document.getElementById('ra-cron-result');
        result.textContent = I18N.forcing;
        pre.style.display  = 'none';

        $.post(pluginRoot + '/front/forcenotify.php', {_glpi_csrf_token: token})
            .done(function (data) {
                result.textContent = '';
                var lines = [];
                lines.push(I18N.futureFound + ' ' + data.reservations);
                lines.push(I18N.notifCreated + ' ' + data.created);
                lines.push(I18N.alreadyExisted + ' ' + data.skipped);
                if (data.detail && data.detail.length > 0) {
                    lines.push('');
                    data.detail.forEach(function (r) {
                        lines.push('  ' + I18N.reservationNum + r.reservation_id + ' | ' + I18N.start + ' ' + r.begin + ' | user_id: ' + r.reserver);
                    });
                } else {
                    lines.push(I18N.noFuture);
                }
                pre.textContent   = lines.join('\n');
                pre.style.display = 'block';
            })
            .fail(function (xhr) {
                result.textContent = I18N.error + ' ' + xhr.status;
                pre.textContent    = xhr.responseText;
                pre.style.display  = 'block';
            });
    });

    document.getElementById('ra-cron-run').addEventListener('click', function () {
        var result = document.getElementById('ra-test-result');
        var pre    = document.getElementById('ra-cron-result');
        result.textContent = I18N.runningCron;
        pre.style.display  = 'none';

        $.post(pluginRoot + '/front/runcron.php', {_glpi_csrf_token: token})
            .done(function (data) {
                result.textContent = '';
                var lines = [];
                lines.push(I18N.result + ' ' + data.result);
                lines.push(I18N.windowFound.replace('%s', data.window_minutes) + ' ' + data.upcoming_reservations);
                lines.push(I18N.notifCreated + ' ' + data.notifications_created);
                lines.push(I18N.serverTime + ' ' + data.now);
                lines.push(I18N.threshold + ' ' + data.threshold);
                if (data.upcoming_detail && data.upcoming_detail.length > 0) {
                    lines.push('');
                    lines.push(I18N.reservFound);
                    data.upcoming_detail.forEach(function (r) {
                        lines.push('  ID ' + r.id + ' | ' + I18N.start + ' ' + r.begin + ' | user_id: ' + r.user);
                    });
                } else {
                    lines.push('');
                    lines.push(I18N.noneInWindow);
                }
                if (data.php_output) {
                    lines.push('');
                    lines.push(I18N.phpOutput + ' ' + data.php_output);
                }
                pre.textContent   = lines.join('\n');
                pre.style.display = 'block';
            })
            .fail(function (xhr) {
                result.textContent = I18N.error + ' ' + xhr.status;
                pre.textContent    = xhr.responseText;
                pre.style.display  = 'block';
            });
    });
}());
</script>

<script>
(function () {
    var pluginRoot  = <?= json_encode($CFG_GLPI['root_doc'] . '/plugins/reservationalert') ?>;
    var I18N = {
        installed:      <?= json_encode(__('Installed', 'reservationalert')) ?>,
        notInstalled:   <?= json_encode(__('Not installed', 'reservationalert')) ?>,
        externalSched:  <?= json_encode(__('Active (external scheduler)', 'reservationalert')) ?>,
        externalNote:   <?= json_encode(__('Cron is running anyway (last run: %s) — handled by the container/system scheduler, no crontab entry needed.', 'reservationalert')) ?>,
        crontabMissing: <?= json_encode(__('The crontab command is not available on this server.', 'reservationalert')) ?>,
        userLabel:      <?= json_encode(__('(user: %s)', 'reservationalert')) ?>,
        statusFail:     <?= json_encode(__('Failed to check crontab status.', 'reservationalert')) ?>,
        installOk:      <?= json_encode(__('Crontab installed successfully.', 'reservationalert')) ?>,
        removeOk:       <?= json_encode(__('Crontab removed successfully.', 'reservationalert')) ?>,
        unknownFailure: <?= json_encode(__('unknown failure.', 'reservationalert')) ?>,
        installing:     <?= json_encode(__('Installing', 'reservationalert')) ?>,
        removing:       <?= json_encode(__('Removing', 'reservationalert')) ?>,
        error:          <?= json_encode(__('Error:', 'reservationalert')) ?>,
        httpError:      <?= json_encode(__('HTTP error', 'reservationalert')) ?>
    };
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
            badge.textContent        = I18N.installed;
            btnInstall.style.display = 'none';
            btnRemove.style.display  = '';
        } else {
            badge.className          = 'badge bg-danger';
            badge.textContent        = I18N.notInstalled;
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
                } else if (!data.crontab_available) {
                    // sem comando crontab (ex.: docker glpi/glpi) — o cron pode estar vivo
                    // por outro mecanismo; botoes mantem-se e um clique explica o porque
                    noExec.style.display = 'none';
                    execOk.style.display = 'block';
                    setBadge(data.installed);
                    userSpan.textContent = I18N.userLabel.replace('%s', data.crontab_user);
                    if (data.task_alive) {
                        badge.className   = 'badge bg-success';
                        badge.textContent = I18N.externalSched;
                        result.textContent = I18N.externalNote.replace('%s', data.task_lastrun || '?');
                    } else {
                        badge.className   = 'badge bg-warning text-dark';
                        badge.textContent = I18N.notInstalled;
                        result.textContent = I18N.crontabMissing;
                    }
                } else {
                    noExec.style.display = 'none';
                    execOk.style.display = 'block';
                    userSpan.textContent = I18N.userLabel.replace('%s', data.crontab_user);
                    setBadge(data.installed);
                }
            })
            .fail(function () {
                loading.textContent = I18N.statusFail;
            });
    }

    function doAction(action, label) {
        result.textContent = label + '...';
        $.post(pluginRoot + '/front/crontab.php', { action: action, _glpi_csrf_token: token })
            .done(function (data) {
                if (data.ok) {
                    setBadge(data.installed);
                    result.textContent = action === 'install' ? I18N.installOk : I18N.removeOk;
                } else {
                    result.textContent = I18N.error + ' ' + (data.error || I18N.unknownFailure);
                }
            })
            .fail(function (xhr) {
                result.textContent = I18N.httpError + ' ' + xhr.status + ': ' + xhr.responseText;
            });
    }

    btnInstall.addEventListener('click', function () { doAction('install', I18N.installing); });
    btnRemove.addEventListener('click',  function () { doAction('remove',  I18N.removing);  });

    loadStatus();
}());
</script>

<?php Html::footer(); ?>
