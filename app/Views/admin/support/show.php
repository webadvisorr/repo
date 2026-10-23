<?php
/**
 * Yönetim — destek talebi detayı: müşteri bilgisi, konuşma, yanıt ve durum.
 * $ticket, $messages
 */
use App\Services\SupportService;

$status = (string) $ticket['status'];
$badge = ['open' => 'badge--warn', 'answered' => 'badge--ok', 'closed' => 'badge--info'][$status] ?? 'badge--info';
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.45rem;margin:0 0 4px">🎧 <?= e((string) $ticket['subject']) ?></h1>
        <p class="muted mb-0">
            <?= e((string) $ticket['ticket_no']) ?>
            · <?= e(SupportService::categoryLabel((string) $ticket['category'])) ?>
            · <?= e('Öncelik: ' . SupportService::priorityLabel((string) $ticket['priority'])) ?>
            <span class="badge <?= e($badge) ?>" style="margin-inline-start:6px"><?= e(SupportService::statusLabel($status)) ?></span>
        </p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/destek')) ?>">← <?= e(__('Destek talepleri')) ?></a>
</div>

<div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:16px;align-items:start">
    <div>
        <div class="card mb-3">
            <div class="thread">
                <?php foreach ($messages as $message): ?>
                    <div class="thread__item thread__item--<?= (string) $message['sender'] === 'admin' ? 'admin' : 'customer' ?>">
                        <div class="thread__meta">
                            <strong>
                                <?= (string) $message['sender'] === 'admin'
                                    ? e('Yönetim · ' . (string) ($message['full_name'] ?: 'Destek'))
                                    : e((string) $ticket['full_name']) ?>
                            </strong>
                            <span class="muted"><?= e(format_date((string) $message['created_at'], 'd.m.Y H:i')) ?></span>
                        </div>
                        <div class="thread__body"><?= nl2br(e((string) $message['body'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2 style="font-size:1.05rem"><?= e(__('Yanıtla')) ?></h2>
            <?php if ($status === 'closed'): ?>
                <p class="muted mb-0"><?= e(__('Talep kapalı. Yanıt yazmak için durumu “Açık” yapın.')) ?></p>
            <?php else: ?>
                <form method="post" action="<?= e(url('/yonetim/destek/yanit')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <div class="field">
                        <label for="support-reply"><?= e(__('Yanıt')) ?></label>
                        <textarea id="support-reply" name="body" rows="5" maxlength="5000" required
                                  placeholder="<?= e(__('Müşteriye iletilecek yanıtı yazın…')) ?>"></textarea>
                        <p class="hint"><?= e(__('Yanıt gönderildiğinde talep “Yanıtlandı” olur ve müşteri panelinde görünür.')) ?></p>
                    </div>
                    <button class="btn btn-accent" type="submit"><?= e(__('Yanıtı gönder')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <aside>
        <div class="card mb-3">
            <h2 style="font-size:1rem"><?= e(__('Müşteri')) ?></h2>
            <p class="mb-1">
                <strong><?= e((string) $ticket['full_name']) ?></strong>
                <span class="muted">(#<?= (int) $ticket['user_id'] ?>)</span>
            </p>
            <p class="muted mb-1" style="font-size:13px"><?= e((string) $ticket['email']) ?></p>
            <?php if (!empty($ticket['phone'])): ?><p class="muted mb-1" style="font-size:13px"><?= e((string) $ticket['phone']) ?></p><?php endif; ?>
            <p class="mb-0" style="font-size:13px">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler/' . (int) $ticket['user_id'])) ?>"><?= e(__('Üye kaydını aç')) ?></a>
            </p>
        </div>

        <div class="card">
            <h2 style="font-size:1rem"><?= e(__('Durum')) ?></h2>
            <div class="row" style="gap:6px;flex-wrap:wrap">
                <?php foreach (SupportService::STATUSES as $key => $label): ?>
                    <?php if ($key === $status) { continue; } ?>
                    <form method="post" action="<?= e(url('/yonetim/destek/durum')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                        <input type="hidden" name="status" value="<?= e($key) ?>">
                        <button class="btn btn-outline btn-sm" type="submit"><?= e($label) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
            <p class="muted mt-2 mb-0" style="font-size:12px">
                <?= e('Açılış: ' . format_date((string) $ticket['created_at'], 'd.m.Y H:i')) ?>
                <?php if (!empty($ticket['last_reply_at'])): ?><br><?= e('Son hareket: ' . format_date((string) $ticket['last_reply_at'], 'd.m.Y H:i')) ?><?php endif; ?>
            </p>
        </div>
    </aside>
</div>
