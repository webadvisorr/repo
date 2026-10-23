<?php
/**
 * Müşteri paneli — destek talebi konuşması.
 * $ticket, $messages
 */
use App\Core\Auth;
use App\Services\SupportService;

$active = 'support';
$status = (string) $ticket['status'];
// Bu talep kapalı olsa bile başka bir talep yanıt bekliyor olabilir; o durumda yeni talep açılamaz.
$otherPending = SupportService::pendingForUser((int) (Auth::id() ?? 0));
$closed = $status === 'closed';
$waiting = $status === 'open'; // müşteri yazdı, destek ekibi yanıtı bekleniyor
$badge = ['open' => 'badge--warn', 'answered' => 'badge--ok', 'closed' => 'badge--info'][$status] ?? 'badge--info';
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.45rem;margin:0 0 4px">🎧 <?= e((string) $ticket['subject']) ?></h1>
                <p class="muted mb-0">
                    <?= e((string) $ticket['ticket_no']) ?>
                    · <?= e(SupportService::categoryLabel((string) $ticket['category'])) ?>
                    · <?= e(__('Öncelik: ') . SupportService::priorityLabel((string) $ticket['priority'])) ?>
                    <span class="badge <?= e($badge) ?>" style="margin-inline-start:6px"><?= e(SupportService::statusLabel($status)) ?></span>
                </p>
            </div>
            <div class="row" style="gap:8px">
                <?php if (!$closed): ?>
                    <form method="post" action="<?= e(url('/panel/destek/kapat')) ?>"
                          data-confirm="<?= e(__('Talep kapatılacak. Kapatılmış talebe yeni mesaj yazılamaz. Emin misiniz?')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit"><?= e(__('Talebi kapat')) ?></button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/destek')) ?>">← <?= e(__('Destek taleplerim')) ?></a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="card mb-3">
            <div class="thread">
                <?php foreach ($messages as $message): ?>
                    <div class="thread__item thread__item--<?= (string) $message['sender'] === 'admin' ? 'admin' : 'customer' ?>">
                        <div class="thread__meta">
                            <strong><?= (string) $message['sender'] === 'admin' ? e(__('Destek ekibi')) : e(__('Siz')) ?></strong>
                            <span class="muted"><?= e(format_date((string) $message['created_at'], 'd.m.Y H:i')) ?></span>
                        </div>
                        <div class="thread__body"><?= nl2br(e((string) $message['body'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($closed): ?>
            <div class="card">
                <p class="muted mb-0"><?= e(__('Bu talep kapatıldı. Yeni bir konu için yeni talep açabilirsiniz.')) ?></p>
                <?php if ($otherPending === null): ?>
                    <p class="mt-2 mb-0"><a class="btn btn-accent btn-sm" href="<?= e(url('/panel/destek/yeni')) ?>">+ <?= e(__('Yeni talep')) ?></a></p>
                <?php else: ?>
                    <p class="mt-2 mb-0" style="font-size:13px">
                        <?= e(__('Yanıt bekleyen başka bir talebiniz var. Destek ekibimiz yanıtladıktan sonra yeni talep açabilirsiniz.')) ?>
                        <a href="<?= e(url('/panel/destek/' . (int) $otherPending['id'])) ?>"><?= e((string) $otherPending['ticket_no']) ?> · <?= e(__('talebi gör')) ?></a>
                    </p>
                <?php endif; ?>
            </div>
        <?php elseif ($waiting): ?>
            <div class="card">
                <h2 style="font-size:1.05rem">⏳ <?= e(__('Destek ekibi yanıtı bekleniyor')) ?></h2>
                <p class="muted mb-0"><?= e(__('Talebiniz ekibimize iletildi. Yanıt geldiğinde bu sayfadan yanıtlayabilir, talebi kapatabilir veya yeni bir talep açabilirsiniz.')) ?></p>
                <?php $supportWhatsapp = whatsapp_url(__('Destek talebim hakkında yardım istiyorum. Talep no: ') . (string) $ticket['ticket_no']); ?>
                <?php if ($supportWhatsapp !== ''): ?>
                    <p class="mt-2 mb-0"><a class="btn btn-outline btn-sm" href="<?= e($supportWhatsapp) ?>" target="_blank" rel="noopener"><?= e(__('WhatsApp ile yaz')) ?></a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <h2 style="font-size:1.05rem"><?= e(__('Yanıt yaz')) ?></h2>
                <form method="post" action="<?= e(url('/panel/destek/yanit')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <div class="field">
                        <label for="ticket-reply"><?= e(__('Mesajınız')) ?></label>
                        <textarea id="ticket-reply" name="body" rows="4" maxlength="5000" required
                                  placeholder="<?= e(__('Ek bilgi veya yanıtınızı yazın…')) ?>"></textarea>
                    </div>
                    <button class="btn btn-accent" type="submit"><?= e(__('Gönder')) ?></button>
                </form>
                <p class="hint mb-0 mt-2"><?= e(__('Yanıtınızı gönderdikten sonra destek ekibimiz yanıtlayana kadar yeni mesaj yazılamaz. Bu sırada talebi kapatabilir veya yeni bir talep açabilirsiniz.')) ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
