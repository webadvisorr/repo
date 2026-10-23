<?php
/**
 * Müşteri paneli — destek talepleri listesi.
 * $rows: SupportService::forUser()
 */
use App\Services\SupportService;

$active = 'support';
$pending = $pending ?? null; // yanıt bekleyen talep: yeni talep açılamaz
$statusBadge = ['open' => 'badge--warn', 'answered' => 'badge--ok', 'closed' => 'badge--info'];
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.6rem;margin:0 0 4px">🎧 <?= e(__('Destek taleplerim')) ?></h1>
                <p class="muted mb-0"><?= e(__('Sorununuzu iletin, yanıtları ve konuşmayı buradan takip edin.')) ?></p>
            </div>
            <div class="row" style="gap:8px">
                <?php if ($pending === null): ?>
                    <a class="btn btn-accent btn-sm" href="<?= e(url('/panel/destek/yeni')) ?>">+ <?= e(__('Yeni talep')) ?></a>
                <?php else: ?>
                    <span class="badge badge--warn" title="<?= e(__('Yanıt bekleyen talebiniz var')) ?>">⏳ <?= e(__('Yanıt bekleniyor')) ?></span>
                <?php endif; ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel')) ?>">← <?= e(t('nav.dashboard')) ?></a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <?php if ($pending !== null): ?>
            <div class="alert alert--info mb-3" style="align-items:flex-start">
                <span aria-hidden="true">⏳</span>
                <div>
                    <strong><?= e(__('Destek ekibi yanıtı bekleniyor')) ?></strong>
                    <p class="mb-0" style="font-size:13px"><?= e(__('Talebiniz yanıtlanana kadar yeni bir destek talebi açamaz veya talebe yeni mesaj yazamazsınız. Yanıt geldiğinde bu sayfadan devam edebilirsiniz.')) ?></p>
                    <p class="mb-0" style="font-size:13px"><a href="<?= e(url('/panel/destek/' . (int) $pending['id'])) ?>"><?= e((string) $pending['ticket_no']) ?> · <?= e(__('talebi gör')) ?></a></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (($rows ?? []) === []): ?>
            <div class="card center">
                <div class="feature-icon" style="margin-inline:auto">🎧</div>
                <h2 style="font-size:1.15rem"><?= e(__('Henüz destek talebiniz yok')) ?></h2>
                <p class="muted"><?= e(__('Evrak, ödeme veya hesap konularında talep açabilirsiniz. Yanıtlar bu sayfada görünür.')) ?></p>
                <?php if ($pending === null): ?>
                    <a class="btn btn-accent" href="<?= e(url('/panel/destek/yeni')) ?>">+ <?= e(__('Yeni talep')) ?></a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="inbox-feed">
                <?php foreach ($rows as $row): ?>
                    <a class="inbox-card" style="text-decoration:none;color:inherit" href="<?= e(url('/panel/destek/' . (int) $row['id'])) ?>">
                        <span class="inbox-card__icon" aria-hidden="true">🎧</span>
                        <div class="inbox-card__body">
                            <div class="inbox-card__top">
                                <span class="inbox-card__kind">
                                    <?= e((string) $row['ticket_no']) ?> · <?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?>
                                </span>
                                <span class="badge <?= e($statusBadge[(string) $row['status']] ?? 'badge--info') ?>">
                                    <?= e(SupportService::statusLabel((string) $row['status'])) ?>
                                </span>
                            </div>
                            <h2 class="inbox-card__title"><?= e((string) $row['subject']) ?></h2>
                            <p class="inbox-card__text">
                                <?= e(SupportService::categoryLabel((string) $row['category'])) ?>
                                · <?= e(__('Öncelik: ') . SupportService::priorityLabel((string) $row['priority'])) ?>
                                · <?= e((int) $row['message_count'] . ' mesaj') ?>
                                <?php if ((int) ($row['unread_replies'] ?? 0) > 0): ?>
                                    · <span style="color:var(--c-accent);font-weight:700"><?= e(__(':count yeni yanıt', ['count' => (int) $row['unread_replies']])) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
