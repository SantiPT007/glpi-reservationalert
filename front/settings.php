<?php

/**
 * User settings page: per-user bell toggle + diagnostic output.
 * Accessible to all logged-in users (not admin-only).
 */

include('../../../inc/includes.php');

global $CFG_GLPI;

Session::checkLoginUser();

$config         = GlpiPlugin\Reservationalert\Config::getConfig();
$global_enabled = (bool) ($config['global_enabled'] ?? true);
// Plugin::getWebDir() esta depreciado no GLPI 11; construir o caminho a mao
$test_url       = $CFG_GLPI['root_doc'] . '/plugins/reservationalert/front/test.php';

Html::header(__('Reservation Alerts', 'reservationalert'), $_SERVER['PHP_SELF'], 'tools', 'reservationalert');
?>

<div class="container-fluid">
    <div class="card mt-4" style="max-width:600px;margin:2rem auto;">
        <div class="card-header d-flex align-items-center gap-2">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="2" width="20" height="13" rx="2"/>
                <path d="M12 15v3"/>
                <rect x="4" y="18" width="16" height="4" rx="1.5"/>
                <line x1="7"  y1="20.5" x2="9"  y2="20.5"/>
                <line x1="11" y1="20.5" x2="13" y2="20.5"/>
                <line x1="15" y1="20.5" x2="17" y2="20.5"/>
            </svg>
            <strong><?= __('Reservation Alerts: Settings', 'reservationalert') ?></strong>
        </div>

        <div class="card-body">
            <?php if (!$global_enabled): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <?= __('The administrator has disabled reservation notifications globally. Contact an administrator to re-enable them.', 'reservationalert') ?>
            </div>
            <?php endif; ?>

            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-semibold"><?= __('Notification bell in the header', 'reservationalert') ?></div>
                        <div class="text-muted" style="font-size:12px;">
                            <?= __('Shows an icon with the pending alert count in the navigation bar.', 'reservationalert') ?>
                        </div>
                    </div>
                    <div class="form-check form-switch ms-3">
                        <input class="form-check-input" type="checkbox" id="ra-bell-toggle"
                               style="width:2.5rem;height:1.25rem;"
                               <?= !$global_enabled ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="ra-bell-toggle" id="ra-toggle-label">
                            <?= __('Disabled', 'reservationalert') ?>
                        </label>
                    </div>
                </div>
            </div>

            <div id="ra-diag-wrapper" style="display:none;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold" style="font-size:13px;"><?= __('Diagnostic result', 'reservationalert') ?></span>
                    <span id="ra-diag-status" class="badge"></span>
                </div>
                <pre id="ra-diag-pre"
                     style="background:#1e1e2e;color:#cdd6f4;padding:12px 16px;border-radius:6px;
                            font-size:12px;max-height:320px;overflow-y:auto;white-space:pre-wrap;
                            word-break:break-word;margin:0;"></pre>
            </div>

            <div id="ra-diag-running" style="display:none;" class="text-muted" style="font-size:13px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" style="animation:spin 1s linear infinite;display:inline-block;">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83
                             M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
                <?= __('Checking...', 'reservationalert') ?>
            </div>
        </div>

        <div class="card-footer text-muted" style="font-size:12px;">
            <?= __('This setting is stored in this browser only. Other devices need to enable it separately.', 'reservationalert') ?>
            <?php if (Session::haveRight('config', UPDATE)): ?>
            <span class="ms-2">
                <a href="<?= $CFG_GLPI['root_doc'] ?>/plugins/reservationalert/front/config.form.php">
                    <?= __('Global settings (admin)', 'reservationalert') ?>
                </a>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<script>
(function () {
    'use strict';

    var TEST_URL   = <?= json_encode($test_url) ?>;
    var I18N = {
        enabled:        <?= json_encode(__('Enabled', 'reservationalert')) ?>,
        disabled:       <?= json_encode(__('Disabled', 'reservationalert')) ?>,
        invalidResp:    <?= json_encode(__('Invalid server response:', 'reservationalert')) ?>,
        phpErrors:      <?= json_encode(__('=== PHP warnings/errors ===', 'reservationalert')) ?>,
        errorHdr:       <?= json_encode(__('=== Error ===', 'reservationalert')) ?>,
        statusOk:       <?= json_encode(__('=== Status: OK ===', 'reservationalert')) ?>,
        tablesOk:       <?= json_encode(__('Tables: OK', 'reservationalert')) ?>,
        notifFound:     <?= json_encode(__('Notifications found:', 'reservationalert')) ?>,
        bellActive:     <?= json_encode(__('The bell is active. If it does not appear in the navigation bar, reload the page.', 'reservationalert')) ?>,
        statusError:    <?= json_encode(__('=== Status: error ===', 'reservationalert')) ?>,
        bellMayFail:    <?= json_encode(__('The bell may not work correctly. See the errors above.', 'reservationalert')) ?>,
        error:          <?= json_encode(__('Error', 'reservationalert')) ?>,
        networkFail:    <?= json_encode(__('Network failure: could not reach the server.', 'reservationalert')) ?>
    };
    var toggle     = document.getElementById('ra-bell-toggle');
    var label      = document.getElementById('ra-toggle-label');
    var wrapper    = document.getElementById('ra-diag-wrapper');
    var pre        = document.getElementById('ra-diag-pre');
    var running    = document.getElementById('ra-diag-running');
    var statusBadge = document.getElementById('ra-diag-status');

    function setLabel(enabled) {
        label.textContent = enabled ? I18N.enabled : I18N.disabled;
    }

    // ON by default — only explicitly disabled when key === '0'
    var saved = localStorage.getItem('reservationalert_bell_enabled') !== '0';
    toggle.checked = saved;
    setLabel(saved);
    if (saved) {
        runDiagnostic(false);
    }

    toggle.addEventListener('change', function () {
        if (this.checked) {
            localStorage.removeItem('reservationalert_bell_enabled'); // remove '0' → back to default ON
            runDiagnostic(false);
        } else {
            localStorage.setItem('reservationalert_bell_enabled', '0');
            setLabel(false);
            wrapper.style.display = 'none';
        }
    });

    function runDiagnostic(applyOnSuccess) {
        running.style.display = 'block';
        wrapper.style.display = 'none';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', TEST_URL);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            running.style.display = 'none';
            wrapper.style.display = 'block';

            var data;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                data = {ok: false, error: I18N.invalidResp + '\n' + xhr.responseText, php_output: '', count: 0};
            }

            var lines = [];

            if (data.php_output) {
                lines.push(I18N.phpErrors);
                lines.push(data.php_output);
                lines.push('');
            }

            if (data.error) {
                lines.push(I18N.errorHdr);
                lines.push(data.error);
                lines.push('');
            }

            if (data.ok) {
                lines.push(I18N.statusOk);
                lines.push(I18N.tablesOk);
                lines.push(I18N.notifFound + ' ' + data.count);
                lines.push('');
                lines.push(I18N.bellActive);

                statusBadge.className   = 'badge bg-success ms-1';
                statusBadge.textContent = 'OK';
                setLabel(true);
            } else {
                lines.push(I18N.statusError);
                lines.push(I18N.bellMayFail);

                statusBadge.className   = 'badge bg-danger ms-1';
                statusBadge.textContent = I18N.error;
            }

            pre.textContent = lines.join('\n');
        };

        xhr.onerror = function () {
            running.style.display = 'none';
            wrapper.style.display = 'block';
            pre.textContent = I18N.networkFail;
            statusBadge.className   = 'badge bg-danger ms-1';
            statusBadge.textContent = I18N.error;
            if (applyOnSuccess) {
                toggle.checked = false;
                setLabel(false);
            }
        };

        xhr.send();
    }
}());
</script>

<?php Html::footer(); ?>
