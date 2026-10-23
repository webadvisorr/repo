<?php
$statusLabels = [
    'queued'    => t('notifications.status_queued'),
    'sent'      => t('notifications.status_sent'),
    'failed'    => t('notifications.status_failed'),
    'skipped'   => t('notifications.status_skipped'),
    'cancelled' => t('notifications.status_cancelled'),
];
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px"><?= e(t('notifications.detail')) ?> #<?= (int) $row['id'] ?></h1>
        <p class="muted mb-0"><?= e((string) ($statusLabels[(string) $row['status']] ?? $row['status'])) ?>
            · <?= e((string) $row['channel']) ?> · <?= e((string) $row['template_key'] === 'campaign' ? 'Kampanya e-postası' : $row['template_key']) ?></p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/bildirimler')) ?>">← <?= e(t('common.back')) ?></a>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(t('notifications.meta')) ?></h2>
        <table class="data">
            <tbody>
            <tr><th style="width:42%"><?= e(t('notifications.recipient')) ?></th><td><?= e((string) $row['recipient']) ?></td></tr>
            <tr><th><?= e(t('auth.name')) ?></th><td><?= e((string) ($row['full_name'] ?? '—')) ?></td></tr>
            <tr><th><?= e(t('notifications.organization')) ?></th><td><?= e((string) ($row['org_name'] ?? '—')) ?></td></tr>
            <tr><th><?= e(t('vehicles.plate')) ?></th><td class="mono"><?= e((string) ($row['plate'] ?? '—')) ?></td></tr>
            <tr><th><?= e(t('notifications.subject')) ?></th><td><?= e((string) ($row['subject_rendered'] ?: $row['subject'] ?? '')) ?></td></tr>
            <tr><th><?= e(t('notifications.dedupe')) ?></th><td class="mono" style="font-size:12px;word-break:break-all"><?= e((string) $row['dedupe_key']) ?></td></tr>
            <tr><th><?= e(t('notifications.attempts')) ?></th><td><?= (int) $row['attempts'] ?></td></tr>
            <tr><th><?= e(t('notifications.created')) ?></th><td><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i:s')) ?></td></tr>
            <tr><th><?= e(t('notifications.scheduled')) ?></th><td><?= e($row['scheduled_for'] ? format_date((string) $row['scheduled_for'], 'd.m.Y H:i:s') : '—') ?></td></tr>
            <tr><th><?= e(t('notifications.sent')) ?></th><td><?= e($row['sent_at'] ? format_date((string) $row['sent_at'], 'd.m.Y H:i:s') : '—') ?></td></tr>
            <tr><th><?= e(t('notifications.opened')) ?></th><td><?= e($row['opened_at'] ? format_date((string) $row['opened_at'], 'd.m.Y H:i:s') : '—') ?></td></tr>
            </tbody>
        </table>

        <?php if (!empty($row['error'])): ?>
            <div class="alert alert--error mt-2"><span>⚠️</span><div class="mono" style="font-size:12.5px"><?= e((string) $row['error']) ?></div></div>
        <?php endif; ?>

        <div class="row mt-3">
            <?php if (!in_array((string) $row['status'], ['sent', 'cancelled'], true)): ?>
                <form method="post" action="<?= e(url('/yonetim/bildirimler/yeniden-dene')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-primary btn-sm" type="submit"><?= e(t('notifications.retry')) ?></button>
                </form>
            <?php endif; ?>
            <?php if ((string) $row['status'] === 'queued'): ?>
                <form method="post" action="<?= e(url('/yonetim/bildirimler/iptal')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(t('notifications.cancel_send')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(t('notifications.body')) ?></h2>
        <?php if (empty($row['body_html'])): ?>
            <p class="muted"><?= e(t('notifications.no_body')) ?></p>
        <?php else: ?>
            <iframe sandbox="" title="<?= e(t('notifications.body')) ?>" style="width:100%;height:620px;border:1px solid var(--c-border);border-radius:12px;background:#fff"
                    srcdoc="<?= e((string) $row['body_html']) ?>"></iframe>
        <?php endif; ?>
    </div>
</div>
