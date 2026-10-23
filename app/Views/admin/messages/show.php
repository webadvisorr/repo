<?php
$unread = (int) $message['is_read'] === 0;
$replies = $replies ?? [];
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px"><?= e((string) ($message['subject'] ?: 'Konusuz mesaj')) ?></h1>
        <p class="muted mb-0">
            <?= e((string) $message['name']) ?>
            · <a href="mailto:<?= e((string) $message['email']) ?>"><?= e((string) $message['email']) ?></a>
            <?php if (!empty($message['phone'])): ?> · <a href="tel:<?= e((string) $message['phone']) ?>"><?= e((string) $message['phone']) ?></a><?php endif; ?>
        </p>
    </div>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/mesajlar')) ?>">← <?= e(__('Mesajlar')) ?></a>
        <?php if (!empty($message['user_id'])): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler/' . (int) $message['user_id'])) ?>"><?= e(__('Üye kaydı')) ?></a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="mailto:<?= e((string) $message['email']) ?>?subject=<?= e(rawurlencode('Yanıt: ' . (string) ($message['subject'] ?? ''))) ?>"><?= e(__('E-posta ile yanıtla')) ?></a>
    </div>
</div>

<div class="card mb-3">
    <div class="row row--between mb-2">
        <span class="muted" style="font-size:13px"><?= e(format_date((string) $message['created_at'], 'd.m.Y H:i')) ?></span>
        <span class="badge <?= $unread ? 'badge--ok' : 'badge--info' ?>"><?= $unread ? e(__('Okunmamış')) : e(__('Okundu')) ?></span>
    </div>

    <div class="thread">
        <div class="thread__item thread__item--customer">
            <div class="thread__meta">
                <strong><?= e((string) $message['name']) ?></strong>
                <span class="muted"><?= e(format_date((string) $message['created_at'], 'd.m.Y H:i')) ?></span>
            </div>
            <div class="thread__body"><?= nl2br(e((string) $message['message'])) ?></div>
        </div>

        <?php foreach ($replies as $reply): ?>
            <div class="thread__item thread__item--<?= (string) $reply['sender'] === 'admin' ? 'admin' : 'customer' ?>">
                <div class="thread__meta">
                    <strong><?= (string) $reply['sender'] === 'admin' ? e('Yönetim · ' . (string) ($reply['full_name'] ?: 'Destek')) : e((string) $message['name']) ?></strong>
                    <span class="muted"><?= e(format_date((string) $reply['created_at'], 'd.m.Y H:i')) ?></span>
                </div>
                <div class="thread__body"><?= nl2br(e((string) $reply['body'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($message['ip'])): ?>
        <p class="muted mt-2 mb-0" style="font-size:12px">IP: <span class="mono"><?= e((string) $message['ip']) ?></span></p>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <h2 style="font-size:1.05rem"><?= e(__('Panelden yanıtla')) ?></h2>
    <form method="post" action="<?= e(url('/yonetim/mesajlar/yanit')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
        <div class="field">
            <label for="msg-reply"><?= e(__('Yanıt')) ?></label>
            <textarea id="msg-reply" name="body" rows="4" maxlength="5000" required
                      placeholder="<?= e(__('Müşteriye iletilecek yanıtı yazın…')) ?>"></textarea>
            <p class="hint"><?= e(__('Yanıt, müşterinin panelindeki Mesajlar bölümünde görünür. E-posta göndermez.')) ?></p>
        </div>
        <button class="btn btn-accent" type="submit"><?= e(__('Yanıtı gönder')) ?></button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1.05rem"><?= e(__('İşlemler')) ?></h2>
    <div class="row" style="flex-wrap:wrap">
        <form method="post" action="<?= e(url('/yonetim/mesajlar/durum')) ?>" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
            <input type="hidden" name="read" value="<?= $unread ? '1' : '0' ?>">
            <button class="btn btn-outline" type="submit"><?= $unread ? e(__('Okundu olarak işaretle')) : e(__('Okunmadı olarak işaretle')) ?></button>
        </form>
        <form method="post" action="<?= e(url('/yonetim/mesajlar/sil')) ?>" style="display:inline"
              data-confirm="<?= e(__('Bu mesaj ve tüm yanıtları kalıcı olarak silinecek. Emin misiniz?')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
            <button class="btn btn-outline" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(__('Mesajı sil')) ?></button>
        </form>
    </div>
</div>
