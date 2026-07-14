// tabuleiro de alertas e notificacoes push do browser

/* global $, CFG_GLPI */

(function ($) {
    'use strict';

    console.log('[reservationalert] script parsed, jQuery:', typeof $);

    const FETCH_INTERVAL_MS = 60_000;
    const INJECT_DELAY_MS   = 600;

    // ficheiro estatico, sem gettext do PHP: dicionario proprio, idioma vem do <html lang>
    const IS_PT = (document.documentElement.lang || '').toLowerCase().startsWith('pt');
    const T = IS_PT ? {
        title:        'Alertas de Reserva',
        markAll:      'Marcar tudo como lido',
        empty:        'Sem alertas de reserva pendentes.',
        viewLink:     'Ver reservas',
        begin:        'Início:',
        reservedBy:   'Reservado por:',
        dismiss:      'Dispensar',
        adminOff:     'Desativado pelo administrador.',
        pushTitle:    'Reserva a iniciar em breve',
        pushReserved: 'Reservado por',
    } : {
        title:        'Reservation Alerts',
        markAll:      'Mark all as read',
        empty:        'No pending reservation alerts.',
        viewLink:     'View reservations',
        begin:        'Starts:',
        reservedBy:   'Reserved by:',
        dismiss:      'Dismiss',
        adminOff:     'Disabled by the administrator.',
        pushTitle:    'Reservation starting soon',
        pushReserved: 'Reserved by',
    };

    function pluginRoot() {
        if (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) {
            return CFG_GLPI.root_doc + '/plugins/reservationalert';
        }
        return '/plugins/reservationalert';
    }

    let knownIds  = new Set();
    let pollTimer = null;

    const ICON_SVG = `
<svg width="20" height="20" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.8"
     stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true">
  <rect x="2" y="2" width="20" height="13" rx="2"/>
  <path d="M12 15v3"/>
  <rect x="4" y="18" width="16" height="4" rx="1.5"/>
  <line x1="7"  y1="20.5" x2="9"  y2="20.5"/>
  <line x1="11" y1="20.5" x2="13" y2="20.5"/>
  <line x1="15" y1="20.5" x2="17" y2="20.5"/>
</svg>`;

    function buildTray() {
        const $wrapper = $('<div id="reservationalert-wrapper"></div>');

        const $btn = $(`
            <button id="reservationalert-btn"
                    type="button"
                    class="btn btn-outline-secondary"
                    title="${T.title}"
                    aria-label="${T.title}">
                ${ICON_SVG}
                <span id="reservationalert-badge"></span>
            </button>
        `);

        const $panel = $(`
            <div id="reservationalert-panel"
                 role="dialog"
                 aria-label="${T.title}">
            </div>
        `);

        $wrapper.append($btn).append($panel);

        $btn.on('mouseenter', function () {
            this.style.setProperty('background', '#6c757d', 'important');
            this.style.setProperty('color', '#fff', 'important');
            this.style.setProperty('border-color', '#6c757d', 'important');
        }).on('mouseleave', function () {
            this.style.removeProperty('background');
            this.style.removeProperty('color');
            this.style.removeProperty('border-color');
        });

        $btn.on('click', function (e) {
            e.stopPropagation();
            $panel.toggleClass('open');
        });

        $(document).on('click.reservationalert', function (e) {
            if (!$(e.target).closest('#reservationalert-wrapper').length) {
                $panel.removeClass('open');
            }
        });

        return $wrapper;
    }

    // injeta no header do GLPI 11, ms-md-4 e o container direito do navbar antes do dropdown do utilizador
    function injectIntoNavbar($wrapper) {
        const $headerRight = $('[data-testid="main-header"] .ms-md-4');
        if ($headerRight.length) {
            $wrapper.css({ display: 'flex', alignItems: 'center', marginRight: '4px' });
            $headerRight.prepend($wrapper);
            return true;
        }

        // Fallback: right-side nav group (older GLPI layouts / plugins that restructure header)
        const $navGroup = $('.navbar .navbar-nav:last-child, .navbar .ms-auto, .user-menu').first();
        if ($navGroup.length) {
            const $li = $('<li class="nav-item" style="display:flex;align-items:center;"></li>');
            $li.append($wrapper);
            $navGroup.prepend($li);
            return true;
        }

        // Fallback: any header element
        const $header = $('header, .navbar, .main-header, #header_top').first();
        if ($header.length) {
            $header.append($wrapper);
            return true;
        }

        // Last resort: fixed overlay
        $wrapper.css({ position: 'fixed', top: '10px', right: '10px', zIndex: 9999 });
        $('body').append($wrapper);
        return true;
    }

    function renderPanel(notifications) {
        const $panel = $('#reservationalert-panel');
        const $badge = $('#reservationalert-badge');
        if (!$panel.length) return;

        $panel.empty();

        const $header = $('<div class="ra-panel-header"></div>');
        $header.append($('<span>').text(T.title));

        if (notifications.length > 0) {
            const $markAll = $('<button type="button">').text(T.markAll);
            $markAll.on('click', markAllRead);
            $header.append($markAll);
        }

        $panel.append($header);

        if (notifications.length === 0) {
            $panel.append($('<div class="ra-empty">').text(T.empty));
            $badge.removeClass('visible').text('');
            return;
        }

        const unread = notifications.filter(n => !n.is_read).length;
        if (unread > 0) {
            $badge.text(unread > 99 ? '99+' : String(unread)).addClass('visible');
        } else {
            $badge.removeClass('visible').text('');
        }

        notifications.forEach(function (n) {
            const linkHtml = n.link
                ? `<a class="ra-item-link" href="${n.link}" target="_blank">${T.viewLink}</a>`
                : '';

            const $item = $(`
                <div class="ra-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
                    <span class="ra-item-title">${escHtml(n.item_name)}</span>
                    <span class="ra-item-meta">${T.begin} ${escHtml(n.begin)}</span>
                    <span class="ra-item-meta">${T.reservedBy} ${escHtml(n.reserver)}</span>
                    ${linkHtml}
                    ${!n.is_read ? `<button class="ra-item-dismiss" data-id="${n.id}">${T.dismiss}</button>` : ''}
                </div>
            `);
            $panel.append($item);
        });

        $panel.off('click.dismiss').on('click.dismiss', '.ra-item-dismiss', function (e) {
            e.stopPropagation();
            markRead(parseInt($(this).data('id'), 10));
        });
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function poll() {
        $.ajax({
            url:       pluginRoot() + '/front/notifications.php',
            method:    'GET',
            dataType:  'json',
            xhrFields: { withCredentials: true },
            success:   function (data) {
                if (data && data.global_enabled === false) {
                    renderDisabled();
                    return;
                }

                const notifications = (data && data.notifications) ? data.notifications : [];

                notifications.filter(n => !n.is_read && !knownIds.has(n.id))
                             .forEach(function (n) {
                                 knownIds.add(n.id);
                                 firePush(n);
                             });

                notifications.forEach(n => knownIds.add(n.id));
                renderPanel(notifications);
            },
        });
    }

    function renderDisabled() {
        const $panel = $('#reservationalert-panel');
        const $badge = $('#reservationalert-badge');
        if (!$panel.length) return;
        $panel.empty();
        $panel.append($('<div class="ra-panel-header">').append($('<span>').text(T.title)));
        $panel.append($('<div class="ra-empty">').text(T.adminOff));
        $badge.removeClass('visible').text('');
    }

    function csrfToken() {
        // token CSRF do GLPI 11 esta numa meta tag
        const meta = document.querySelector('meta[property="glpi:csrf_token"]');
        if (meta) return meta.getAttribute('content');
        if (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) {
            return CFG_GLPI.csrf_token;
        }
        return '';
    }

    function markRead(id) {
        $.post(pluginRoot() + '/front/markread.php', {
            id:               id,
            _glpi_csrf_token: csrfToken(),
        }, poll);
    }

    function markAllRead() {
        $.post(pluginRoot() + '/front/markread.php', {
            all:              1,
            _glpi_csrf_token: csrfToken(),
        }, poll);
    }

    function requestPushPermission() {
        if (!('Notification' in window) || Notification.permission !== 'default') return;
        $('#reservationalert-btn').one('click', function () {
            Notification.requestPermission();
        });
    }

    function firePush(n) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        new Notification(T.pushTitle, {
            body: `${n.item_name}: ${n.begin}\n${T.pushReserved} ${n.reserver}`,
            tag:  'reservationalert-' + n.id,
        });
    }

    $(document).ready(function () {
        console.log('[reservationalert] document.ready fired');
        // so corre em paginas autenticadas
        if (!$('[data-testid="main-header"] .btn-group').length) {
            console.log('[reservationalert] auth guard failed, exiting');
            return;
        }

        // sino ativo por defeito, utilizador pode desativar nas definicoes
        if (localStorage.getItem('reservationalert_bell_enabled') === '0') {
            console.log('[reservationalert] localStorage disabled, exiting');
            return;
        }

        console.log('[reservationalert] building tray, will inject in', INJECT_DELAY_MS, 'ms');
        const $wrapper = buildTray();

        setTimeout(function () {
            console.log('[reservationalert] injecting now');
            injectIntoNavbar($wrapper);
            requestPushPermission();
            poll();
            pollTimer = setInterval(poll, FETCH_INTERVAL_MS);
        }, INJECT_DELAY_MS);
    });

}(jQuery));
