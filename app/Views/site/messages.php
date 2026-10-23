<?php
/**
 * Müşteri paneli — iletişim mesajları listesi.
 * $rows: ContactMessageService::forUser()
 */
$active = 'messages';
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.6rem;margin:0 0 4px">📬 <?= e(__('Mesajlarım')) ?></h1>
                <p class="muted mb-0"><?= e(__('İletişim formundan gönderdiğiniz mesajları takip edin ve yanıt yazın.')) ?></p>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/iletisim')) ?>">+ <?= e(__('Yeni mesaj')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel')) ?>">← <?= e(t('nav.dashboard')) ?></a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <?php if (($rows ?? []) === []): ?>
            <div class="card center">
                <div class="feature-icon" style="margin-inline:auto">📬</div>
                <h2 style="font-size:1.15rem"><?= e(__('Henüz mesajınız yok')) ?></h2>
                <p class="muted"><?= e(__('İletişim sayfasından bize yazdığınızda mesaj burada görünür ve yanıtları buradan takip edebilirsiniz.')) ?></p>
                <a class="btn btn-accent" href="<?= e(url('/iletisim')) ?>"><?= e(__('İletişim sayfasına git')) ?></a>
            </div>
        <?php else: ?>
            <div class="inbox-feed">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $hasReply = (int) ($row['admin_reply_count'] ?? 0) > 0;
                    ?>
                    <a class="inbox-card" style="text-decoration:none;color:inherit" href="<?= e(url('/panel/mesajlar/' . (int) $row['id'])) ?>">
                        <span class="inbox-card__icon" aria-hidden="true">✉️</span>
                        <div class="inbox-card__body">
                            <div class="inbox-card__top">
                                <span class="inbox-card__kind">
                                    <?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?>
                                    <?php if ((int) ($row['reply_count'] ?? 0) > 0): ?> · <?= e(__(':count yanıt', ['count' => (int) $row['reply_count']])) ?><?php endif; ?>
                                </span>
                                <span class="badge <?= $hasReply ? 'badge--ok' : 'badge--info' ?>">
                                    <?= $hasReply ? e(__('Yanıtlandı')) : e(__('Yanıt bekliyor')) ?>
                                </span>
                            </div>
                            <h2 class="inbox-card__title"><?= e((string) ($row['subject'] ?: 'Konusuz mesaj')) ?></h2>
                            <p class="inbox-card__text"><?= e(mb_strimwidth((string) $row['message'], 0, 180, '…')) ?></p>
                            <?php if (!empty($row['last_reply'])): ?>
                                <p class="muted mb-0" style="font-size:13px">
                                    <strong><?= e(__('Son yanıt:')) ?></strong> <?= e(mb_strimwidth((string) $row['last_reply'], 0, 140, '…')) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
