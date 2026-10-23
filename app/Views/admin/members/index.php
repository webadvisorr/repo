<?php use App\Core\Settings; ?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px"><?= e(t('admin.members')) ?></h1>
        <p class="muted mb-0"><?= e(__('Kredi yükleyin, abonelik atayın, hesap durumunu yönetin.')) ?></p>
    </div>
    <a class="btn btn-primary btn-sm" href="<?= e(url('/yonetim/odemeler')) ?>"><?= e(__('Kredi / ödeme kaydı')) ?></a>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['users'] ?></div><div class="stat__label"><?= e(__('Toplam kullanıcı')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['customers'] ?></div><div class="stat__label"><?= e(__('Müşteri')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-accent)"><?= e(number_format((float) $totals['credit'], 2, ',', '.')) ?></div><div class="stat__label"><?= e(__('Yüklü kredi toplamı')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['active'] ?></div><div class="stat__label"><?= e(__('Aktif abonelik')) ?></div></div>
</div>

<form method="get" action="<?= e(url('/yonetim/uyeler')) ?>" class="card mb-3">
    <div class="row">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('Ad, e-posta, telefon, plaka veya kurum ara…')) ?>" style="flex:2;min-width:220px">
        <select name="status" style="flex:1;min-width:150px">
            <option value=""><?= e(__('Tüm durumlar')) ?></option>
            <?php foreach (['active', 'passive', 'suspended'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="plan" style="flex:1;min-width:150px">
            <option value=""><?= e(__('Tüm planlar')) ?></option>
            <?php foreach ($plans as $p): ?>
                <option value="<?= e((string) $p['key']) ?>" <?= $plan === (string) $p['key'] ? 'selected' : '' ?>><?= e((string) $p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span style="display:inline-flex;align-items:center;gap:6px;min-height:38px">
            <?php $from = (string) ($from ?? ''); $to = (string) ($to ?? ''); require BASE_PATH . '/app/Views/partials/date_range.php'; ?>
        </span>
        <button class="btn btn-primary" type="submit"><?= e(t('common.search')) ?></button>
        <?php if ($search !== '' || $status !== '' || $plan !== '' || ($from ?? '') !== '' || ($to ?? '') !== ''): ?>
            <a class="btn btn-outline" href="<?= e(url('/yonetim/uyeler')) ?>"><?= e(__('Temizle')) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th><?= e(t('auth.name')) ?></th>
                <th><?= e(t('auth.email')) ?></th>
                <th><?= e(__('Plan')) ?></th>
                <th><?= e(__('Kredi')) ?></th>
                <th><?= e(__('Araç')) ?></th>
                <th><?= e(__('Durum')) ?></th>
                <th><?= e(__('Son giriş')) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $days = days_left($row['current_period_end'] !== null ? (string) $row['current_period_end'] : null); ?>
            <tr>
                <td class="mono"><?= (int) $row['id'] ?></td>
                <td>
                    <strong><?= e((string) $row['full_name']) ?></strong>
                    <?php if ((string) $row['role'] !== 'customer'): ?>
                        <span class="badge badge--info"><?= e((string) $row['role']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['organization_name'])): ?>
                        <div class="muted" style="font-size:12.5px"><?= e((string) $row['organization_name']) ?></div>
                    <?php endif; ?>
                </td>
                <td><a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a></td>
                <td>
                    <?php if (!empty($row['plan_name'])): ?>
                        <?= e((string) $row['plan_name']) ?>
                        <?php if ($days !== null): ?>
                            <div class="muted" style="font-size:12px"><?= $days < 0 ? e(abs($days) . ' gün önce bitti') : e($days . ' gün kaldı') ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="mono"><?= e(number_format((float) ($row['credit_balance'] ?? 0), 2, ',', '.')) ?></td>
                <td><?= (int) ($row['vehicle_count'] ?? 0) ?></td>
                <td>
                    <?php $cls = match ((string) $row['status']) { 'active' => 'badge--ok', 'passive' => 'badge--warn', default => 'badge--danger' }; ?>
                    <span class="badge <?= $cls ?>"><?= e((string) $row['status']) ?></span>
                </td>
                <td class="muted"><?= e($row['last_login_at'] ? format_date((string) $row['last_login_at'], 'd.m.Y H:i') : '—') ?></td>
                <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler/' . (int) $row['id'])) ?>"><?= e(__('Detay')) ?></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="9" class="muted"><?= e(__('Kayıt bulunamadı.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="row mt-3">
    <?php if ($page > 1): ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler?' . http_build_query(array_filter(['page' => $page - 1, 'q' => $search, 'status' => $status, 'plan' => $plan, 'baslangic' => $from ?? '', 'bitis' => $to ?? ''], static fn ($v) => $v !== '' && $v !== null)))) ?>">← <?= e(__('Önceki')) ?></a>
    <?php endif; ?>
    <span class="muted"><?= e('Sayfa ' . $page) ?></span>
    <?php if (!empty($hasNext)): ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler?' . http_build_query(array_filter(['page' => $page + 1, 'q' => $search, 'status' => $status, 'plan' => $plan, 'baslangic' => $from ?? '', 'bitis' => $to ?? ''], static fn ($v) => $v !== '' && $v !== null)))) ?>"><?= e(__('Sonraki')) ?> →</a>
    <?php endif; ?>
</div>
