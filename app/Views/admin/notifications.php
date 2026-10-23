<?php
$statusLabels = [
    'queued'    => t('notifications.status_queued'),
    'sent'      => t('notifications.status_sent'),
    'failed'    => t('notifications.status_failed'),
    'skipped'   => t('notifications.status_skipped'),
    'cancelled' => t('notifications.status_cancelled'),
];
$statusClass = [
    'queued'    => 'badge--info',
    'sent'      => 'badge--ok',
    'failed'    => 'badge--danger',
    'skipped'   => 'badge--warn',
    'cancelled' => 'badge--info',
];
$queryBase = array_filter([
    'durum' => (string) $filters['status'],
    'kanal' => (string) $filters['channel'],
    'q'     => (string) $filters['q'],
    'gun'   => (string) $filters['days'],
], static fn ($v) => $v !== '');
?>

<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">✉️ <?= e(__('E-posta Günlüğü')) ?></h1>
        <p class="muted mb-0"><?= e(t('notifications.subtitle')) ?></p>
    </div>
    <div class="row">
        <form method="post" action="<?= e(url('/yonetim/bildirimler/kuyrugu-isle')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline btn-sm" type="submit"><?= e(t('notifications.dispatch_now')) ?></button>
        </form>
        <form method="post" action="<?= e(url('/yonetim/bildirimler/tara')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm" type="submit"><?= e(t('notifications.run_full')) ?></button>
        </form>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= (int) ($stats['sent'] ?? 0) ?></div><div class="stat__label"><?= e(t('notifications.status_sent')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= (int) ($stats['queued'] ?? 0) ?></div><div class="stat__label"><?= e(t('notifications.status_queued')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= (int) ($stats['failed'] ?? 0) ?></div><div class="stat__label"><?= e(t('notifications.status_failed')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= (int) ($stats['opened'] ?? 0) ?></div><div class="stat__label"><?= e(t('notifications.opened')) ?></div></div>
</div>

<div class="card mb-3">
    <div class="row row--between" style="align-items:flex-start;gap:14px;flex-wrap:wrap">
        <p class="mb-0" style="font-size:13.5px">
            <?= e(t('notifications.today', ['sent' => (int) $sentToday, 'limit' => (int) $dailyLimit])) ?>
            <?php if (empty($smtpReady)): ?>
                <span class="badge badge--danger" style="margin-inline-start:8px"><?= e(t('notifications.smtp_missing')) ?></span>
            <?php endif; ?>
        </p>
        <?php if ((int) ($stats['failed'] ?? 0) > 0): ?>
            <form method="post" action="<?= e(url('/yonetim/bildirimler/basarisizlari-dene')) ?>" data-confirm="<?= e(t('notifications.retry_all_confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-sm" type="submit"><?= e(t('notifications.retry_all')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <form method="get" action="<?= e(url('/yonetim/bildirimler')) ?>" class="row" style="gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="margin:0;min-width:170px">
            <label for="nf-q"><?= e(t('common.search')) ?></label>
            <input id="nf-q" type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="<?= e(t('notifications.search_placeholder')) ?>">
        </div>
        <div class="field" style="margin:0;min-width:150px">
            <label for="nf-durum"><?= e(t('common.status')) ?></label>
            <select id="nf-durum" name="durum">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($statusLabels[$status] ?? $status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:130px">
            <label for="nf-kanal"><?= e(t('notifications.channel')) ?></label>
            <select id="nf-kanal" name="kanal">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach ($channels as $channel): ?>
                    <option value="<?= e($channel) ?>" <?= $filters['channel'] === $channel ? 'selected' : '' ?>><?= e($channel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0;min-width:130px">
            <label for="nf-gun"><?= e(t('notifications.period')) ?></label>
            <select id="nf-gun" name="gun">
                <?php foreach ([7, 30, 90, 365, 0] as $days): ?>
                    <option value="<?= (int) $days ?>" <?= (int) $filters['days'] === $days ? 'selected' : '' ?>>
                        <?= $days === 0 ? e(t('notifications.all_time')) : e(t('notifications.last_days', ['days' => $days])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin:0">
            <label><?= e(__('Tarih aralığı')) ?></label>
            <?php $from = (string) ($filters['from'] ?? ''); $to = (string) ($filters['to'] ?? ''); require BASE_PATH . '/app/Views/partials/date_range.php'; ?>
        </div>
        <button class="btn btn-primary btn-sm" type="submit"><?= e(t('common.filter')) ?></button>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/bildirimler')) ?>"><?= e(t('notifications.clear')) ?></a>
    </form>
</div>

<div class="card">
    <p class="muted" style="font-size:13px"><?= e(t('notifications.results', ['count' => (int) $total])) ?></p>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>#</th>
                <th><?= e(t('notifications.date')) ?></th>
                <th><?= e(t('common.status')) ?></th>
                <th><?= e(t('notifications.recipient')) ?></th>
                <th><?= e(t('notifications.subject')) ?></th>
                <th><?= e(t('notifications.attempts')) ?></th>
                <th><?= e(t('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" class="muted"><?= e(t('notifications.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono" style="font-size:12.5px"><?= (int) $row['id'] ?></td>
                    <td style="font-size:13px"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?>
                        <?php if (!empty($row['opened_at'])): ?><div class="muted" style="font-size:11.5px">👁️ <?= e(format_date((string) $row['opened_at'], 'd.m.Y H:i')) ?></div><?php endif; ?>
                    </td>
                    <td><span class="badge <?= e($statusClass[(string) $row['status']] ?? 'badge--info') ?>"><?= e($statusLabels[(string) $row['status']] ?? (string) $row['status']) ?></span></td>
                    <td style="font-size:13px">
                        <?= e((string) $row['recipient']) ?>
                        <?php if (!empty($row['full_name'])): ?><div class="muted" style="font-size:11.5px"><?= e((string) $row['full_name']) ?></div><?php endif; ?>
                    </td>
                    <td style="font-size:13px">
                        <?= e((string) ($row['subject_rendered'] ?: $row['subject'] ?? '')) ?>
                        <?php if (!empty($row['plate'])): ?><div class="mono muted" style="font-size:11.5px"><?= e((string) $row['plate']) ?></div><?php endif; ?>
                    </td>
                    <td style="font-size:13px"><?= (int) $row['attempts'] ?></td>
                    <td>
                        <div class="row" style="gap:6px;flex-wrap:wrap">
                            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/bildirimler/' . (int) $row['id'])) ?>"><?= e(t('notifications.detail_short')) ?></a>
                            <?php if (in_array((string) $row['status'], ['failed', 'skipped', 'cancelled'], true)): ?>
                                <form method="post" action="<?= e(url('/yonetim/bildirimler/yeniden-dene')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="durum" value="<?= e((string) $filters['status']) ?>">
                                    <input type="hidden" name="kanal" value="<?= e((string) $filters['channel']) ?>">
                                    <input type="hidden" name="q" value="<?= e((string) $filters['q']) ?>">
                                    <input type="hidden" name="gun" value="<?= (int) $filters['days'] ?>">
                                    <button class="btn btn-outline btn-sm" type="submit"><?= e(t('notifications.retry')) ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ((string) $row['status'] === 'queued'): ?>
                                <form method="post" action="<?= e(url('/yonetim/bildirimler/iptal')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(t('common.cancel')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ((int) $pages > 1): ?>
        <div class="row mt-2" style="gap:8px;flex-wrap:wrap">
            <?php for ($p = 1; $p <= (int) $pages; $p++): ?>
                <?php if ($p === 1 || $p === (int) $pages || abs($p - (int) $page) <= 2): ?>
                    <a class="btn btn-sm <?= $p === (int) $page ? 'btn-primary' : 'btn-outline' ?>"
                       href="<?= e(url('/yonetim/bildirimler?' . http_build_query($queryBase + ['sayfa' => $p]))) ?>"><?= (int) $p ?></a>
                <?php elseif (abs($p - (int) $page) === 3): ?>
                    <span class="muted">…</span>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
