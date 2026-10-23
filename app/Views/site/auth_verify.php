<?php
use App\Core\Security;
$remaining = (int) ($status['remaining'] ?? 0);
$resendIn  = (int) ($status['resend_in'] ?? 0);
$expiresAt = time() + $remaining;
$resendAt  = time() + $resendIn;
$timerText = $remaining > 0
    ? t('auth.verify_timer', ['time' => sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60)])
    : t('auth.verify_expired');
$resendText = $resendIn > 0
    ? t('auth.verify_resend_wait', ['seconds' => $resendIn])
    : t('auth.verify_resend');
?>
<div class="card">
    <div class="center mb-2">
        <div aria-hidden="true" style="font-size:42px;line-height:1;margin-bottom:10px">✉️</div>
        <h2 style="margin-bottom:6px"><?= e(t('auth.verify_title')) ?></h2>
        <p class="muted mb-0"><?= e(t('auth.verify_sent', ['email' => (string) $email])) ?></p>
    </div>

    <?php if (empty($smtpReady)): ?>
        <div class="alert alert--error">
            <span>⚠️</span>
            <div><?= e(t('auth.verify_smtp_missing')) ?></div>
        </div>
    <?php endif; ?>

    <?php if (empty($status['exists'])): ?>
        <div class="alert alert--info">
            <span>ℹ️</span>
            <div><?= e(t('auth.verify_no_code')) ?></div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/dogrula')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="verify-code"><?= e(t('auth.verify_code')) ?></label>
            <input id="verify-code" type="text" name="code" required inputmode="numeric"
                   pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus
                   style="font-family:'Courier New',monospace;font-size:26px;letter-spacing:8px;text-align:center"
                   aria-describedby="verify-help">
            <p class="hint" id="verify-help"><?= e(t('auth.verify_code_hint')) ?></p>
        </div>
        <button class="btn btn-accent btn-block" type="submit"><?= e(t('auth.verify_submit')) ?></button>
    </form>

    <div class="row row--between mt-3" style="align-items:center;gap:10px;flex-wrap:wrap">
        <span class="muted" style="font-size:13px" data-verify-timer
              data-expires-at="<?= (int) $expiresAt ?>" data-resend-at="<?= (int) $resendAt ?>"
              aria-live="polite"><?= e($timerText) ?></span>
        <form method="post" action="<?= e(url('/dogrula/yeniden-gonder')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline btn-sm" type="submit" data-verify-resend
                    <?= $resendIn > 0 ? 'disabled' : '' ?>><?= e($resendText) ?></button>
        </form>
    </div>

    <p class="center muted mt-3 mb-0" style="font-size:12.5px"><?= e(t('auth.verify_help')) ?></p>
    <p class="center mt-2 mb-0"><a href="<?= e(url('/cikis')) ?>"><?= e(t('auth.logout')) ?></a></p>
</div>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    var timer = document.querySelector('[data-verify-timer]');
    if (!timer) { return; }
    var expiresAt = parseInt(timer.getAttribute('data-expires-at'), 10) || 0;
    var resendAt = parseInt(timer.getAttribute('data-resend-at'), 10) || 0;
    var resend = document.querySelector('[data-verify-resend]');
    var expiredText = <?= json_encode(t('auth.verify_expired'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var timerTemplate = <?= json_encode(t('auth.verify_timer', ['time' => '__TIME__']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var resendText = <?= json_encode(t('auth.verify_resend'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var resendWaitTemplate = <?= json_encode(t('auth.verify_resend_wait', ['seconds' => '__SECONDS__']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var pad = function (n) { return n < 10 ? '0' + n : String(n); };

    setInterval(function () {
        var now = Math.floor(Date.now() / 1000);
        var left = Math.max(0, expiresAt - now);
        timer.textContent = left > 0
            ? timerTemplate.replace('__TIME__', pad(Math.floor(left / 60)) + ':' + pad(left % 60))
            : expiredText;

        if (resend) {
            var wait = Math.max(0, resendAt - now);
            resend.disabled = wait > 0;
            resend.textContent = wait > 0
                ? resendWaitTemplate.replace('__SECONDS__', String(wait))
                : resendText;
        }
    }, 1000);
})();
</script>
