<?php
/**
 * Yönetim paneli kullanım rehberi — sağ altta açılan sohbet.
 * Yalnızca yönetim paneli ekranlarında (layouts/admin) yüklenir.
 */
use App\Core\Security;
use App\Services\AdminGuide;

if (!AdminGuide::enabled()) {
    return;
}
$guideMode = AdminGuide::aiConfigured() ? 'ai' : 'guide';
?>
<div class="admin-guide" data-guide data-guide-mode="<?= e($guideMode) ?>">
    <button type="button" class="admin-guide__toggle" data-guide-toggle
            aria-controls="admin-guide-panel" aria-expanded="false"
            aria-label="<?= e('Panel kullanım rehberini aç') ?>">
        <span class="admin-guide__toggle-icon" aria-hidden="true">🧭</span>
        <span class="admin-guide__toggle-text"><?= e('Panel Rehberi') ?></span>
    </button>

    <section class="admin-guide__panel" id="admin-guide-panel" data-guide-panel hidden
             aria-label="<?= e('Panel kullanım rehberi') ?>">
        <header class="admin-guide__head">
            <div>
                <strong><?= e('Panel Rehberi') ?></strong>
                <span class="admin-guide__mode">
                    <?= $guideMode === 'ai' ? e('AI modu') : e('Yerel rehber') ?>
                </span>
            </div>
            <button type="button" class="admin-guide__close" data-guide-close
                    aria-label="<?= e('Rehberi kapat') ?>">&times;</button>
        </header>

        <div class="admin-guide__log" data-guide-log aria-live="polite">
            <div class="admin-guide__msg admin-guide__msg--bot">
                <?= e('Merhaba! Yönetim panelinde neyin nerede olduğunu sorabilirsiniz. Yalnızca bu panelin kullanımı konusunda yardımcı olurum.') ?>
            </div>
        </div>

        <div class="admin-guide__chips" data-guide-chips>
            <?php
            $chips = [
                'Kredi nasıl yüklerim?'        => '/yonetim/uyeler',
                'Plan fiyatı nasıl değişir?'   => '/yonetim/planlar',
                'Havale onayı nerede?'         => '/yonetim/odemeler',
                'Kampanya nasıl gönderirim?'   => '/yonetim/kampanya',
                'Bildirim nasıl gönderirim?'   => '/yonetim/duyurular',
                'SMTP ayarları nerede?'        => '/yonetim/ayarlar/mail',
                'Neler yapabilirsin?'          => '/yonetim/',
            ];
            foreach ($chips as $chip => $target): ?>
                <button type="button" class="admin-guide__chip" data-guide-question="<?= e($chip) ?>">
                    <?= e($chip) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <form class="admin-guide__form" data-guide-form method="post"
              action="<?= e(url('/yonetim/rehber/sor')) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="admin-guide-input"><?= e('Sorunuz') ?></label>
            <input id="admin-guide-input" type="text" name="question" maxlength="<?= AdminGuide::MAX_QUESTION ?>"
                   required autocomplete="off" placeholder="<?= e('Örn. kredi nasıl yüklerim?') ?>"
                   data-guide-input>
            <button class="btn btn-primary btn-sm" type="submit" data-guide-send>
                <?= e('Sor') ?>
            </button>
        </form>

        <p class="admin-guide__note">
            <?= e('Yalnızca yönetim paneli kullanımı. Şifre, API anahtarı veya kişisel veri paylaşmayın.') ?>
        </p>
    </section>
</div>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    'use strict';

    var guide = document.querySelector('[data-guide]');
    if (!guide) { return; }

    var panel = guide.querySelector('[data-guide-panel]');
    var log = guide.querySelector('[data-guide-log]');
    var form = guide.querySelector('[data-guide-form]');
    var input = guide.querySelector('[data-guide-input]');
    var toggles = document.querySelectorAll('[data-guide-toggle]');
    var busy = false;

    function syncToggles(open) {
        toggles.forEach(function (btn) { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    }
    function openGuide() {
        if (!panel) { return; }
        panel.hidden = false;
        syncToggles(true);
        if (input) { setTimeout(function () { input.focus(); }, 40); }
    }
    function closeGuide() {
        if (!panel) { return; }
        panel.hidden = true;
        syncToggles(false);
    }
    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (panel && panel.hidden) { openGuide(); } else { closeGuide(); }
        });
    });
    guide.querySelectorAll('[data-guide-close]').forEach(function (btn) {
        btn.addEventListener('click', closeGuide);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel && !panel.hidden) { closeGuide(); }
    });

    function scrollLog() {
        if (log) { log.scrollTop = log.scrollHeight; }
    }
    function addMessage(role, text, extraClass) {
        var bubble = document.createElement('div');
        bubble.className = 'admin-guide__msg admin-guide__msg--' + role + (extraClass ? ' ' + extraClass : '');
        bubble.textContent = text;
        log.appendChild(bubble);
        scrollLog();
        return bubble;
    }
    function safeLink(raw) {
        try {
            var parsed = new URL(raw, window.location.origin);
            if (parsed.origin !== window.location.origin) { return null; }
            if (parsed.pathname.indexOf('/yonetim') !== 0) { return null; }
            return parsed.href;
        } catch (e) {
            return null;
        }
    }
    function addAnswer(data) {
        var bubble = document.createElement('div');
        bubble.className = 'admin-guide__msg admin-guide__msg--bot';

        if (data.title) {
            var title = document.createElement('strong');
            title.className = 'admin-guide__title';
            title.textContent = data.title;
            bubble.appendChild(title);
        }
        bubble.appendChild(document.createTextNode(String(data.answer || '')));

        if (Array.isArray(data.steps) && data.steps.length > 0) {
            var list = document.createElement('ol');
            data.steps.forEach(function (step) {
                var li = document.createElement('li');
                li.textContent = String(step);
                list.appendChild(li);
            });
            bubble.appendChild(list);
        }

        var links = [];
        if (data.link && data.link.url) { links.push(data.link); }
        if (Array.isArray(data.related)) { links = links.concat(data.related); }
        var seen = {};
        var linkRow = document.createElement('div');
        linkRow.className = 'admin-guide__links';
        links.forEach(function (link) {
            var href = safeLink(String(link.url || ''));
            if (!href || seen[href]) { return; }
            seen[href] = true;
            var a = document.createElement('a');
            a.href = href;
            a.textContent = String(link.label || 'Sayfayı aç');
            linkRow.appendChild(a);
        });
        if (linkRow.childElementCount > 0) { bubble.appendChild(linkRow); }

        if (data.mode) {
            var badge = document.createElement('span');
            badge.className = 'admin-guide__badge';
            badge.textContent = data.mode === 'ai' ? 'AI yanıtı' : 'Yerel rehber';
            bubble.appendChild(badge);
        }

        log.appendChild(bubble);
        scrollLog();
    }

    var typing = null;
    function setBusy(value) {
        busy = value;
        var send = guide.querySelector('[data-guide-send]');
        if (send) { send.disabled = value; }
        if (value) {
            typing = document.createElement('div');
            typing.className = 'admin-guide__typing';
            typing.textContent = 'Rehber yanıtlıyor…';
            log.appendChild(typing);
            scrollLog();
        } else if (typing) {
            typing.remove();
            typing = null;
        }
    }

    function askGuide(question) {
        question = String(question || '').trim();
        if (question === '' || busy || !form) { return; }
        addMessage('user', question);
        if (input) { input.value = ''; }
        setBusy(true);

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () { return null; }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        }).then(function (result) {
            setBusy(false);
            var data = result.data;
            if (!data || data.ok !== true) {
                addMessage('bot', (data && data.error) ? String(data.error) : 'Yanıt alınamadı. Lütfen tekrar deneyin.', 'admin-guide__msg--error');
                return;
            }
            addAnswer(data);
        }).catch(function () {
            setBusy(false);
            addMessage('bot', 'Bağlantı kurulamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.', 'admin-guide__msg--error');
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var value = input ? input.value : '';
            if (value.trim() === '') { return; }
            askGuide(value);
        });
    }
    guide.querySelectorAll('[data-guide-question]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            openGuide();
            askGuide(chip.getAttribute('data-guide-question'));
        });
    });
})();
</script>
