<?php
use App\Services\SubscriptionService;
$statusLabels = ['pending' => 'Bekliyor', 'paid' => 'Ödendi', 'failed' => 'Başarısız', 'refunded' => 'İade'];
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px"><?= e('Ödemeler') ?></h1>
        <p class="muted mb-0"><?= e('Havale/EFT, nakit veya kredi kartı ile alınan ödemeleri kaydedin; üyelere kredi yükleyin.') ?></p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler')) ?>"><?= e(t('admin.members')) ?></a>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= e(number_format((float) $totals['paid'], 2, ',', '.')) ?></div><div class="stat__label"><?= e('Tahsil edilen') ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-warning)"><?= e(number_format((float) $totals['pending'], 2, ',', '.')) ?></div><div class="stat__label"><?= e('Bekleyen') ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-accent)"><?= e(number_format((float) $totals['credit'], 2, ',', '.')) ?></div><div class="stat__label"><?= e('Kullanılmayan kredi') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['count'] ?></div><div class="stat__label"><?= e('Kayıt sayısı') ?></div></div>
</div>

<div class="card mb-3">
    <h2 style="font-size:1.05rem"><?= e('Yeni ödeme / kredi kaydı') ?></h2>
    <form method="post" action="<?= e(url('/yonetim/odemeler/kaydet')) ?>">
        <?= csrf_field() ?>
        <div class="field-grid">
            <div class="field">
                <label for="pay-user"><?= e('Üye') ?></label>
                <select id="pay-user" name="user_id" required>
                    <option value=""><?= e('Üye seçin…') ?></option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= e((string) $m['full_name']) ?> — <?= e((string) $m['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="pay-mode"><?= e('İşlem türü') ?></label>
                <select id="pay-mode" name="mode">
                    <option value="credit"><?= e('Kredi yükle') ?></option>
                    <option value="subscription"><?= e('Abonelik ata (kredi yok)') ?></option>
                </select>
            </div>
            <div class="field">
                <label for="pay-amount"><?= e('Tutar') ?> (<?= e($currency) ?>)</label>
                <input id="pay-amount" type="number" name="amount" step="0.01" min="0" value="0">
            </div>
            <div class="field">
                <label for="pay-plan"><?= e('Plan (abonelik modunda)') ?></label>
                <select id="pay-plan" name="plan_id">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e((string) $p['name']) ?> — <?= e(number_format((float) $p['price'], 2, ',', '.')) ?> <?= e((string) $p['currency']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="pay-days"><?= e('Süre (gün)') ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                <input id="pay-days" type="number" name="days" min="0" max="3650" placeholder="<?= e('Plan periyodu') ?>">
            </div>
            <div class="field">
                <label for="pay-method"><?= e('Ödeme yöntemi') ?></label>
                <select id="pay-method" name="method">
                    <?php foreach (['bank_transfer' => 'Havale / EFT', 'cash' => 'Nakit', 'card' => 'Kredi kartı', 'manual' => 'Manuel'] as $k => $v): ?>
                        <option value="<?= e($k) ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="pay-note"><?= e('Not / referans') ?></label>
            <input id="pay-note" type="text" name="note" maxlength="250" placeholder="<?= e('Örn. Havale dekont no: 12345') ?>">
        </div>
        <button class="btn btn-accent" type="submit"><?= e('Kaydet') ?></button>
    </form>
</div>

<form method="get" action="<?= e(url('/yonetim/odemeler')) ?>" class="card mb-3">
    <div class="row">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e('E-posta, kurum, not veya işlem no ara…') ?>" style="flex:2;min-width:200px">
        <select name="status" style="flex:1;min-width:150px">
            <option value=""><?= e('Tüm durumlar') ?></option>
            <?php foreach ($statusLabels as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit"><?= e(t('common.search')) ?></button>
    </div>
</form>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>#</th><th><?= e('Tarih') ?></th><th><?= e('Üye / kurum') ?></th><th><?= e('Plan') ?></th>
                <th><?= e('Tutar') ?></th><th><?= e('Yöntem') ?></th><th><?= e('Durum') ?></th><th><?= e('Kaydeden') ?></th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $cls = match ((string) $row['status']) { 'paid' => 'badge--ok', 'pending' => 'badge--warn', 'refunded' => 'badge--info', default => 'badge--danger' }; ?>
            <tr>
                <td class="mono"><?= (int) $row['id'] ?></td>
                <td class="muted"><?= e(format_date((string) ($row['paid_at'] ?? $row['created_at']), 'd.m.Y H:i')) ?></td>
                <td>
                    <?php if (!empty($row['owner_id'])): ?>
                        <a href="<?= e(url('/yonetim/uyeler/' . (int) $row['owner_id'])) ?>"><?= e((string) ($row['organization_name'] ?? '—')) ?></a>
                    <?php else: ?>
                        <?= e((string) ($row['organization_name'] ?? '—')) ?>
                    <?php endif; ?>
                    <div class="muted" style="font-size:12.5px"><?= e((string) ($row['owner_email'] ?? '')) ?></div>
                </td>
                <td><?= e((string) ($row['plan_name'] ?? '—')) ?></td>
                <td class="mono"><?= e(number_format((float) $row['amount'], 2, ',', '.')) ?> <?= e((string) $row['currency']) ?></td>
                <td><?= e((string) $row['method']) ?></td>
                <td>
                    <span class="badge <?= $cls ?>"><?= e($statusLabels[(string) $row['status']] ?? (string) $row['status']) ?></span>
                    <?php if (!empty($row['notes'])): ?><div class="muted" style="font-size:12px"><?= e((string) $row['notes']) ?></div><?php endif; ?>
                </td>
                <td class="muted"><?= e((string) ($row['recorded_by_name'] ?? '—')) ?></td>
                <td>
                    <form method="post" action="<?= e(url('/yonetim/odemeler/durum')) ?>" class="row">
                        <?= csrf_field() ?>
                        <input type="hidden" name="payment_id" value="<?= (int) $row['id'] ?>">
                        <select name="status" style="width:auto" aria-label="<?= e('Durum') ?>">
                            <?php foreach ($statusLabels as $k => $v): ?>
                                <option value="<?= e($k) ?>" <?= (string) $row['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline btn-sm" type="submit"><?= e('Güncelle') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="9" class="muted"><?= e('Ödeme kaydı yok.') ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
