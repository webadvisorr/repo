<?php
/**
 * Müşteri paneli — hesap bilgilerim ve profil güncelleme talebi.
 * $profileUser users satırı, $pending bekleyen talep, $pendingRows eski/yeni değerler.
 */
use App\Services\ProfileChangeService;

$active = 'profile';
$u = $profileUser ?? [];
$pending = $pending ?? null;
$statusLabels = [
    'pending'   => __('Onay bekliyor'),
    'approved'  => __('Onaylandı'),
    'rejected'  => __('Reddedildi'),
    'cancelled' => __('Geri çekildi'),
];
$statusBadge = [
    'pending'   => 'badge--warn',
    'approved'  => 'badge--ok',
    'rejected'  => 'badge--danger',
    'cancelled' => 'badge--info',
];
$values = [
    'full_name' => (string) ($u['full_name'] ?? ''),
    'email'     => (string) ($u['email'] ?? ''),
    'phone'     => (string) ($u['phone'] ?? ''),
    'whatsapp'  => (string) ($u['whatsapp'] ?? ''),
    'locale'    => (string) ($u['locale'] ?? 'tr'),
];
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.6rem;margin:0 0 4px">👤 <?= e(__('Hesap bilgilerim')) ?></h1>
                <p class="muted mb-0"><?= e(__('Bilgilerinizi görüntüleyin; değişiklikler güvenlik kontrolü ve yönetici onayıyla uygulanır.')) ?></p>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/panel')) ?>">← <?= e(t('nav.dashboard')) ?></a>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="grid grid-2 mb-3">
            <div class="card">
                <h2 style="font-size:1.05rem">📋 <?= e(__('Kayıtlı bilgiler')) ?></h2>
                <table class="data" style="width:100%">
                    <tbody>
                        <tr><th style="width:38%"><?= e(t('auth.name')) ?></th><td><?= e($values['full_name']) ?></td></tr>
                        <tr>
                            <th><?= e(t('auth.email')) ?></th>
                            <td>
                                <?= e($values['email']) ?>
                                <?php if (!empty($u['email_verified_at'])): ?>
                                    <span class="badge badge--ok"><?= e(__('Doğrulandı')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge--warn"><?= e(__('Doğrulanmadı')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><th><?= e(t('auth.phone')) ?></th><td><?= e($values['phone'] !== '' ? $values['phone'] : '—') ?></td></tr>
                        <tr><th>WhatsApp</th><td><?= e($values['whatsapp'] !== '' ? $values['whatsapp'] : '—') ?></td></tr>
                        <tr><th><?= e(t('common.language')) ?></th><td><?= e($locales[$values['locale']] ?? $values['locale']) ?></td></tr>
                    </tbody>
                </table>
                <p class="hint mb-0 mt-2"><?= e(__('Şifrenizi değiştirmek için çıkış yapıp “Şifremi unuttum” bağlantısını kullanabilirsiniz.')) ?></p>
            </div>

            <div class="card">
                <h2 style="font-size:1.05rem">🔒 <?= e(__('Güvenlik ve onay')) ?></h2>
                <ul class="muted" style="font-size:13.5px;line-height:1.8;margin:0;padding-inline-start:18px">
                    <li><?= e(__('Her talep için')) ?> <strong><?= e(__('mevcut şifreniz')) ?></strong> <?= e(__('sorulur.')) ?></li>
                    <li><?= e(__('Bilgileriniz talep anında değişmez; önce yönetici onayı gerekir.')) ?></li>
                    <li><?= e(__('E-posta adresiniz değişirse yeni adresinize doğrulama kodu gönderilir.')) ?></li>
                    <li><?= e(__('Onaylanan değişiklikler için panelden ve e-postayla bilgilendirilirsiniz.')) ?></li>
                </ul>

                <?php if ($pending !== null): ?>
                    <div class="alert alert--info mt-3" style="align-items:flex-start">
                        <span aria-hidden="true">⏳</span>
                        <div style="flex:1">
                            <strong><?= e(__('Onay bekleyen talebiniz var.')) ?></strong>
                            <div class="muted" style="font-size:13px"><?= e(__(':date tarihinde gönderildi.', ['date' => format_date((string) $pending['created_at'], 'd.m.Y H:i')])) ?></div>
                            <ul class="mb-2" style="font-size:13px;margin-top:8px">
                                <?php foreach (($pendingRows ?? []) as $row): ?>
                                    <li><?= e(__((string) $row['label'])) ?>: <span class="muted"><?= e($row['old']) ?></span> → <strong><?= e($row['new']) ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                            <form method="post" action="<?= e(url('/panel/profil/iptal')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $pending['id'] ?>">
                                <button class="btn btn-outline btn-sm" type="submit"><?= e(__('Talebi geri çek')) ?></button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <h2 style="font-size:1.1rem">✏️ <?= e(__('Bilgi güncelleme talebi')) ?></h2>
            <p class="muted" style="font-size:13.5px;margin-top:0"><?= e(__('Yalnızca değiştirmek istediğiniz alanları düzenleyin. Talep, yönetici onayından sonra uygulanır.')) ?></p>
            <form method="post" action="<?= e(url('/panel/profil')) ?>" data-form-validate>
                <?= csrf_field() ?>
                <div class="field-grid">
                    <div class="field">
                        <label for="profile-name"><?= e(t('auth.name')) ?></label>
                        <input id="profile-name" type="text" name="full_name" required minlength="3" maxlength="160"
                               value="<?= e((string) old('full_name', $values['full_name'])) ?>">
                    </div>
                    <div class="field">
                        <label for="profile-email"><?= e(t('auth.email')) ?></label>
                        <input id="profile-email" type="email" name="email" required maxlength="190"
                               value="<?= e((string) old('email', $values['email'])) ?>">
                        <p class="hint"><?= e(__('E-posta değişirse yeni adresinizi doğrulamanız gerekir.')) ?></p>
                    </div>
                    <div class="field">
                        <label for="profile-phone"><?= e(t('auth.phone')) ?></label>
                        <input id="profile-phone" type="tel" name="phone" maxlength="32"
                               value="<?= e((string) old('phone', $values['phone'])) ?>">
                    </div>
                    <div class="field">
                        <label for="profile-whatsapp">WhatsApp</label>
                        <input id="profile-whatsapp" type="tel" name="whatsapp" maxlength="32"
                               value="<?= e((string) old('whatsapp', $values['whatsapp'])) ?>">
                    </div>
                    <div class="field">
                        <label for="profile-locale"><?= e(t('common.language')) ?></label>
                        <select id="profile-locale" name="locale">
                            <?php foreach (($locales ?? []) as $code => $name): ?>
                                <option value="<?= e((string) $code) ?>" <?= (string) old('locale', $values['locale']) === (string) $code ? 'selected' : '' ?>><?= e((string) $name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="profile-password"><?= e(__('Mevcut şifreniz')) ?> <span class="muted">*</span></label>
                        <input id="profile-password" type="password" name="current_password" required minlength="8"
                               autocomplete="current-password" aria-describedby="profile-password-hint">
                        <p class="hint" id="profile-password-hint"><?= e(__('Güvenlik için talebi göndermek üzere şifrenizi doğrulayın.')) ?></p>
                    </div>
                </div>
                <button class="btn btn-accent mt-2" type="submit"><?= e(__('Onaya gönder')) ?></button>
            </form>
        </div>

        <?php if (($history ?? []) !== []): ?>
            <div class="card">
                <h2 style="font-size:1.05rem">🕘 <?= e(__('Talep geçmişim')) ?></h2>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th><?= e(__('Tarih')) ?></th><th><?= e(__('Değişiklikler')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(__('Açıklama')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($history as $row): ?>
                            <?php $rows = ProfileChangeService::describe($row, $u); ?>
                            <tr>
                                <td class="mono"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?></td>
                                <td style="font-size:13px">
                                    <?php foreach ($rows as $change): ?>
                                        <div><?= e(__((string) $change['label'])) ?>: <strong><?= e($change['new']) ?></strong></div>
                                    <?php endforeach; ?>
                                </td>
                                <td><span class="badge <?= e($statusBadge[(string) $row['status']] ?? 'badge--info') ?>"><?= e($statusLabels[(string) $row['status']] ?? (string) $row['status']) ?></span></td>
                                <td class="muted" style="font-size:13px"><?= e((string) ($row['review_note'] ?? '') ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
