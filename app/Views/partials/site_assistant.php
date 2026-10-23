<?php
use App\Core\Security;
use App\Core\Settings;
use App\Services\SiteAssistant;

if (!SiteAssistant::configured()) {
    return;
}
$brand = (string) Settings::get('brand.title', 'Evrako');
$greeting = SiteAssistant::greeting();
?>
<div class="site-assistant" data-site-assistant>
    <section class="site-assistant__panel" id="site-assistant-panel" hidden aria-label="<?= e($brand . ' asistan') ?>">
        <header class="site-assistant__head">
            <span class="site-assistant__avatar" aria-hidden="true">✦</span>
            <div class="site-assistant__id">
                <strong><?= e($brand . ' Asistan') ?></strong>
                <span class="site-assistant__status"><i aria-hidden="true"></i> <?= e(__('Yapay zekâ destekli · çevrimiçi')) ?></span>
            </div>
            <button type="button" class="site-assistant__close" data-assistant-close aria-label="<?= e(__('Kapat')) ?>">×</button>
        </header>

        <div class="site-assistant__log" data-assistant-log aria-live="polite">
            <div class="site-assistant__msg site-assistant__msg--bot"><?= e(__($greeting)) ?></div>
        </div>

        <form class="site-assistant__form" data-assistant-form>
            <?= csrf_field() ?>
            <label class="sr-only" for="site-assistant-input"><?= e(__('Mesajınız')) ?></label>
            <textarea id="site-assistant-input" data-assistant-input rows="1" maxlength="600"
                      placeholder="<?= e(__('Örn. 2. aracım ne kadar?')) ?>" required></textarea>
            <button type="submit" class="site-assistant__send" data-assistant-send aria-label="<?= e(__('Gönder')) ?>">
                <span aria-hidden="true">➤</span>
            </button>
        </form>
        <p class="site-assistant__note"><?= e(__('Yanıtlar bilgilendirme amaçlıdır. Kesin işlemler için ekibimizle iletişime geçebilirsiniz.')) ?></p>
    </section>

    <button type="button" class="site-assistant__launcher" data-assistant-toggle
            aria-controls="site-assistant-panel" aria-expanded="false">
        <span class="site-assistant__launcher-icon" aria-hidden="true">💬</span>
        <span class="site-assistant__launcher-text"><?= e(__('Asistana sor')) ?></span>
    </button>
</div>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    var root = document.querySelector('[data-site-assistant]');
    if (!root) { return; }
    var panel = root.querySelector('#site-assistant-panel');
    var toggle = root.querySelector('[data-assistant-toggle]');
    var close = root.querySelector('[data-assistant-close]');
    var form = root.querySelector('[data-assistant-form]');
    var input = root.querySelector('[data-assistant-input]');
    var log = root.querySelector('[data-assistant-log]');
    var send = root.querySelector('[data-assistant-send]');
    var endpoint = <?= json_encode(url('/asistan'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var T = <?= json_attr([
        'unread' => __('Yanıt okunamadı.'),
        'noAnswer' => __('Şu anda yanıt veremiyorum. Lütfen biraz sonra tekrar deneyin.'),
        'offline' => __('Bağlantı kurulamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.'),
        'contactTitle' => __('Dilerseniz buradan ekibimizle iletişime geçebilirsiniz.'),
        'contactThanks' => __('Anlayışınız için teşekkür ederiz.'),
        'contactCta' => __('WhatsApp ile yazın'),
    ]) ?>;
    var whatsapp = <?= json_attr(whatsapp_url(__('Merhaba, web sitesi asistanında yanıt alamadım; yardımcı olabilir misiniz?')) ?: url('/iletisim')) ?>;
    var busy = false;

    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        setTimeout(function () { input.focus(); }, 60);
    }
    function shut() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');
        toggle.focus();
    }
    function bubble(text, who) {
        var el = document.createElement('div');
        el.className = 'site-assistant__msg site-assistant__msg--' + who;
        el.textContent = text;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }
    // Asistan yanıt veremediğinde WhatsApp ile ekibe ulaşma bağlantısı gösterilir.
    function contactBlock() {
        var last = log.lastElementChild;
        if (last && last.classList && last.classList.contains('site-assistant__contact')) {
            log.scrollTop = log.scrollHeight;
            return last;
        }
        var card = document.createElement('div');
        card.className = 'site-assistant__contact';
        var title = document.createElement('p');
        title.textContent = T.contactTitle + ' ' + T.contactThanks;
        card.appendChild(title);
        if (whatsapp) {
            var link = document.createElement('a');
            link.className = 'btn btn-accent btn-sm';
            link.href = whatsapp;
            link.target = '_blank';
            link.rel = 'noopener';
            link.innerHTML = '<span aria-hidden="true">💬</span> ' + T.contactCta;
            card.appendChild(link);
        }
        log.appendChild(card);
        log.scrollTop = log.scrollHeight;
        return card;
    }
    function typing() {
        var el = document.createElement('div');
        el.className = 'site-assistant__typing';
        el.setAttribute('data-assistant-typing', '');
        el.innerHTML = '<span></span><span></span><span></span>';
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }

    toggle.addEventListener('click', function () { panel.hidden ? open() : shut(); });
    close.addEventListener('click', shut);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { shut(); }
    });

    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(120, input.scrollHeight) + 'px';
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (text === '' || busy) { return; }
        busy = true;
        send.disabled = true;
        bubble(text, 'user');
        input.value = '';
        input.style.height = 'auto';
        var dots = typing();

        var data = new FormData(form);
        data.set('message', text);

        fetch(endpoint, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().catch(function () { return { ok: false, error: T.unread }; });
        }).then(function (json) {
            dots.remove();
            if (json && json.ok && json.reply) {
                bubble(json.reply, 'bot');
                // Asistan "yanıt veremiyorum" derse altına WhatsApp bağlantısı eklenir.
                if (String(json.reply).trim() === T.noAnswer) { contactBlock(); }
            } else {
                bubble((json && json.error) || T.noAnswer, 'error');
                contactBlock();
            }
        }).catch(function () {
            dots.remove();
            bubble(T.offline, 'error');
            contactBlock();
        }).finally(function () {
            busy = false;
            send.disabled = false;
            input.focus();
        });
    });
})();
</script>
