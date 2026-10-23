<?php
/**
 * Müşteri paneli — tek mesaj konuşması.
 * $message, $replies
 */
$active = 'messages';
$closed = false;
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.5rem;margin:0 0 4px">✉️ <?= e((string) ($message['subject'] ?: 'Konusuz mesaj')) ?></h1>
                <p class="muted mb-0">
                    <?= e(__('Gönderim: ') . format_date((string) $message['created_at'], 'd.m.Y H:i')) ?>
                    · <?= e(__('Durum: :status', ['status' => (int) ($message['is_read'] ?? 0) === 1 ? __('Yönetim gördü') : __('Yeni')])) ?>
                </p>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/mesajlar')) ?>">← <?= e(__('Mesajlarım')) ?></a>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="card mb-3">
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
                            <strong><?= (string) $reply['sender'] === 'admin' ? e(__('Destek ekibi')) : e((string) $message['name']) ?></strong>
                            <span class="muted"><?= e(format_date((string) $reply['created_at'], 'd.m.Y H:i')) ?></span>
                        </div>
                        <div class="thread__body"><?= nl2br(e((string) $reply['body'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2 style="font-size:1.05rem"><?= e(__('Yanıt yaz')) ?></h2>
            <form method="post" action="<?= e(url('/panel/mesajlar/yanit')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
                <div class="field">
                    <label for="msg-customer-reply"><?= e(__('Mesajınız')) ?></label>
                    <textarea id="msg-customer-reply" name="body" rows="4" maxlength="5000" required
                              placeholder="<?= e(__('Yanıtınızı yazın…')) ?>"></textarea>
                </div>
                <button class="btn btn-accent" type="submit"><?= e(__('Gönder')) ?></button>
            </form>
        </div>
    </div>
</section>
