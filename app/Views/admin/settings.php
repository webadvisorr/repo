<?php
use App\Core\Settings;
use App\Core\Uploader;

$tabLabels = [
    'brand' => t('settings.tab_brand'), 'seo' => t('settings.tab_seo'), 'theme' => t('settings.tab_theme'),
    'preloader' => t('settings.tab_preloader'), 'site' => t('settings.tab_site'), 'mail' => t('settings.tab_mail'),
    'reminders' => t('settings.tab_reminders'),
    'channels' => t('settings.tab_channels'), 'payment' => t('settings.tab_payment'),
    'analytics' => t('settings.tab_analytics'), 'cookie' => t('settings.tab_cookie'),
    'popup' => t('settings.tab_popup'), 'slider' => t('settings.tab_slider'), 'cta' => t('settings.tab_cta'),
    'site_assistant' => t('settings.tab_site_assistant'),
];
$definition = $schema[$tab] ?? [];
$colorNames = ['primary', 'accent', 'secondary', 'warning', 'danger', 'bg', 'card', 'text', 'muted', 'border'];
?>
<div class="row row--between mb-3">
    <h1 style="font-size:1.6rem;margin:0"><?= e(t('settings.title')) ?></h1>
    <span class="muted" style="font-size:13px"><?= e(__('Tüm değişiklikler anında siteye yansır.')) ?></span>
</div>

<div class="admin-tabs">
    <?php foreach ($tabs as $key): ?>
        <a href="<?= e(url('/yonetim/ayarlar/' . $key)) ?>" class="<?= $key === $tab ? 'is-active' : '' ?>">
            <?= e($tabLabels[$key] ?? $key) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($definition['media'])): ?>
    <div class="grid grid-2 mb-3">
        <?php foreach ($definition['media'] as $settingKey => $meta): ?>
            <?php $currentPath = (string) Settings::get($settingKey, ''); ?>
            <div class="card">
                <h3 style="font-size:1rem"><?= e(t('settings.' . $meta['label'])) ?></h3>
                <div class="media-preview mb-2">
                    <?php if ($currentPath !== ''): ?>
                        <img src="<?= e(media_url($currentPath)) ?>" alt="<?= e(t('settings.' . $meta['label'])) ?>">
                        <div class="mono muted" style="font-size:12px;word-break:break-all"><?= e($currentPath) ?></div>
                    <?php else: ?>
                        <span class="muted"><?= e(__('Yüklenmedi — varsayılan kullanılıyor.')) ?></span>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= e(url('/yonetim/ayarlar/medya-yukle')) ?>" enctype="multipart/form-data" class="row">
                    <?= csrf_field() ?>
                    <input type="hidden" name="setting_key" value="<?= e((string) $settingKey) ?>">
                    <input type="hidden" name="tab" value="<?= e((string) $tab) ?>">
                    <input type="file" name="file" required accept=".svg,.png,.jpg,.jpeg,.gif,.webp,.apng,.avif,.ico"
                           style="flex:1;min-width:170px">
                    <button class="btn btn-primary btn-sm" type="submit"><?= e($currentPath !== '' ? t('common.upload_new') : t('common.upload')) ?></button>
                </form>
                <?php if ($currentPath !== ''): ?>
                    <form method="post" action="<?= e(url('/yonetim/ayarlar/medya-kaldir')) ?>" class="mt-2"
                          data-confirm="<?= e(t('common.confirm_delete')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_key" value="<?= e((string) $settingKey) ?>">
                        <input type="hidden" name="tab" value="<?= e((string) $tab) ?>">
                        <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(t('common.remove_current')) ?></button>
                    </form>
                <?php endif; ?>
                <p class="hint"><small class="muted"><?= e('İzin verilen: ' . implode(', ', Uploader::EXTENSIONS['image'])) ?></small></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($tab === 'slider'): ?>
    <div class="card mb-3">
        <div class="row row--between" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <h2 style="font-size:1.05rem">🖼️ <?= e(__('Slayt içerikleri')) ?></h2>
                <p class="muted mb-0" style="font-size:13.5px">
                    <?= e(__('Başlık, alt başlık, açıklama, buton yazısı, buton bağlantısı ve görsel Slider Yönetimi ekranında düzenlenir.')) ?>
                </p>
            </div>
            <div class="row" style="gap:6px;flex-wrap:wrap">
                <a class="btn btn-primary btn-sm" href="<?= e(url('/yonetim/slider/yeni')) ?>">+ <?= e(__('Yeni slayt')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/slider')) ?>"><?= e(__('Slider Yönetimi')) ?></a>
            </div>
        </div>

        <?php if (empty($slides)): ?>
            <div class="alert alert--info mt-2">
                <span>ℹ️</span>
                <div><?= e(__('Henüz slayt eklenmedi. “Yeni slayt” ile görsel ve metinleri ekleyin; aktif slayt yoksa ana sayfa varsayılan slaytları gösterir.')) ?></div>
            </div>
        <?php else: ?>
            <div class="table-wrap mt-2">
                <table class="data">
                    <thead><tr><th><?= e(__('Görsel')) ?></th><th><?= e(__('Başlık')) ?></th><th><?= e(__('Buton')) ?></th><th><?= e(__('Durum')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($slides as $slide): ?>
                        <tr>
                            <td>
                                <?php if (!empty($slide['image_path'])): ?>
                                    <img src="<?= e(media_url((string) $slide['image_path'])) ?>" alt="" style="width:76px;height:44px;object-fit:cover;border-radius:7px;border:1px solid var(--c-border)">
                                <?php else: ?>
                                    <span class="muted" style="font-size:12px"><?= e(__('Görsel yok')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e((string) ($slide['title'] ?? '—')) ?></strong>
                                <?php if (!empty($slide['subtitle'])): ?><div class="muted" style="font-size:12.5px"><?= e((string) $slide['subtitle']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($slide['button_text'])): ?>
                                    <?= e((string) $slide['button_text']) ?>
                                    <div class="muted mono" style="font-size:11.5px;word-break:break-all"><?= e((string) ($slide['button_url'] ?? '')) ?></div>
                                <?php else: ?><span class="muted">—</span><?php endif; ?>
                            </td>
                            <td><span class="badge <?= (int) $slide['is_active'] === 1 ? 'badge--ok' : 'badge--info' ?>"><?= (int) $slide['is_active'] === 1 ? e(__('Yayında')) : e(__('Pasif')) ?></span></td>
                            <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/slider/' . (int) $slide['id'])) ?>"><?= e(__('Düzenle')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($tab === 'site_assistant'): ?>
    <?php $siteAiReady = \App\Services\SiteAssistant::configured(); ?>
    <div class="card mb-3">
        <div class="row row--between" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <h2 style="font-size:1.05rem">✦ <?= e(__('Site asistanı (Gemini)')) ?></h2>
                <p class="muted mb-0" style="font-size:13.5px">
                    <?= e(__('Sitenin sol alt köşesindeki “Asistana sor” düğmesi ziyaretçilerle ilgilenir: siteyi tanıtır, paketleri ve araç indirimlerini anlatır, kayıt ve kullanım adımlarında yol gösterir.')) ?>
                </p>
            </div>
            <span class="badge <?= $siteAiReady ? 'badge--ok' : 'badge--info' ?>" style="white-space:nowrap">
                <?= $siteAiReady ? e(__('Yayında')) : e(__('Anahtar bekleniyor')) ?>
            </span>
        </div>
        <ul class="muted" style="font-size:13px;line-height:1.7;margin:12px 0 0;padding-inline-start:20px">
            <li><?= e(__('Google AI Studio üzerinden bir Gemini API anahtarı oluşturun ve aşağıdaki alana yapıştırın. Anahtar yalnızca sunucuda saklanır.')) ?></li>
            <li><?= e(__('Önerilen model: gemini-3.6-flash. Daha ekonomik/hızlı yanıt için gemini-2.5-flash veya gemini-3.1-flash-lite da kullanılabilir.')) ?></li>
            <li><?= e(__('Asistan, sohbet yanıtlarında “düşünme” adımını kapatır (thinkingBudget=0); bu yüzden yanıtlar genellikle 1-2 saniyede döner. Yanıt hiç gelmezse model adını kontrol edin.')) ?></li>
            <li><?= e(__('Asistan fiyat ve indirim uydurmaz; paket bilgileri ve evrakların ücretsiz olduğu sistem rolünde tanımlıdır.')) ?></li>
            <li><?= e(__('Oturum başına 10 dakikada en fazla 15 mesaj gönderilebilir; mesaj geçmişi yalnızca oturumda tutulur.')) ?></li>
        </ul>
        <p class="hint mt-2 mb-0">
            <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer"><?= e(__('Gemini API anahtarı al →')) ?></a>
        </p>
    </div>
<?php endif; ?>



<form method="post" action="<?= e(url('/yonetim/ayarlar/kaydet')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e((string) $tab) ?>">

    <?php if (!empty($definition['text']) || !empty($definition['select']) || !empty($definition['number']) || !empty($definition['password']) || !empty($definition['color'])): ?>
        <div class="card mb-3">
            <h2 style="font-size:1.05rem"><?= e($tabLabels[$tab] ?? $tab) ?></h2>
            <div class="field-grid">
                <?php foreach (($definition['text'] ?? []) as $key => $meta): ?>
                    <?php $textValue = Settings::get($key, ''); if (is_array($textValue)) { $textValue = implode(', ', array_map('strval', $textValue)); } ?>
                    <div class="field">
                        <label for="f-<?= e(md5($key)) ?>"><?= e(t('settings.' . $meta['label'])) ?></label>
                        <?php if (!empty($meta['textarea'])): ?>
                            <textarea id="f-<?= e(md5($key)) ?>" name="<?= e(str_replace('.', '_', (string) $key)) ?>" maxlength="<?= (int) ($meta['max'] ?? 255) ?>"><?= e((string) $textValue) ?></textarea>
                        <?php else: ?>
                            <input id="f-<?= e(md5($key)) ?>" type="text" name="<?= e(str_replace('.', '_', (string) $key)) ?>" maxlength="<?= (int) ($meta['max'] ?? 255) ?>" value="<?= e((string) $textValue) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php foreach (($definition['password'] ?? []) as $key => $meta): ?>
                    <div class="field">
                        <label for="f-<?= e(md5($key)) ?>"><?= e(t('settings.' . $meta['label'])) ?></label>
                        <input id="f-<?= e(md5($key)) ?>" type="password" name="<?= e(str_replace('.', '_', (string) $key)) ?>" autocomplete="new-password" placeholder="••••••••">
                        <p class="hint"><?= e(t('settings.security_note')) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php foreach (($definition['number'] ?? []) as $key => $meta): ?>
                    <div class="field">
                        <label for="f-<?= e(md5($key)) ?>"><?= e(t('settings.' . $meta['label'])) ?></label>
                        <input id="f-<?= e(md5($key)) ?>" type="number" name="<?= e(str_replace('.', '_', (string) $key)) ?>"
                               min="<?= e((string) ($meta['min'] ?? 0)) ?>" max="<?= e((string) ($meta['max'] ?? 100)) ?>"
                               step="<?= e((string) ($meta['step'] ?? '1')) ?>" value="<?= e((string) Settings::get($key, '0')) ?>">
                    </div>
                <?php endforeach; ?>

                <?php if ($tab === 'payment'): ?>
                    <p class="hint"><small class="muted"><?= e(t('settings.rates_hint')) ?></small></p>
                <?php endif; ?>

                <?php foreach (($definition['select'] ?? []) as $key => $meta): ?>
                    <div class="field">
                        <label for="f-<?= e(md5($key)) ?>"><?= e(t('settings.' . $meta['label'])) ?></label>
                        <select id="f-<?= e(md5($key)) ?>" name="<?= e(str_replace('.', '_', (string) $key)) ?>">
                            <?php foreach ($meta['options'] as $option): ?>
                                <option value="<?= e((string) $option) ?>" <?= (string) Settings::get($key, '') === (string) $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>

                <?php foreach (($definition['color'] ?? []) as $key => $meta): ?>
                    <?php $value = (string) Settings::get($key, '#000000'); ?>
                    <div class="field">
                        <label for="f-<?= e(md5($key)) ?>"><?= e(t('settings.' . $meta['label'])) ?></label>
                        <div class="color-input">
                            <input type="color" value="<?= e($value) ?>" oninput="this.nextElementSibling.value=this.value.toUpperCase()">
                            <input id="f-<?= e(md5($key)) ?>" type="text" name="<?= e(str_replace('.', '_', (string) $key)) ?>" pattern="^#[0-9A-Fa-f]{6}$" value="<?= e($value) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php foreach (($definition['bool'] ?? []) as $key => $meta): ?>
                <div class="field check mt-2">
                    <input id="f-<?= e(md5($key)) ?>" type="checkbox" name="<?= e(str_replace('.', '_', (string) $key)) ?>" value="1" <?= (string) Settings::get($key, '0') === '1' ? 'checked' : '' ?>>
                    <label for="f-<?= e(md5($key)) ?>"><?= e(t('settings.' . $meta['label'])) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($definition['colors'])): ?>
        <div class="card mb-3">
            <h2 style="font-size:1.05rem"><?= e(t('settings.colors_light')) ?></h2>
            <div class="field-grid">
                <?php foreach ($colorNames as $name): ?>
                    <?php $value = (string) ($colors['light'][$name] ?? '#000000'); ?>
                    <div class="field">
                        <label><?= e(ucfirst($name)) ?></label>
                        <div class="color-input">
                            <input type="color" value="<?= e($value) ?>" oninput="this.nextElementSibling.value=this.value.toUpperCase()">
                            <input type="text" name="colors_light[<?= e($name) ?>]" pattern="^#[0-9A-Fa-f]{6}$" value="<?= e($value) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <h2 class="mt-3" style="font-size:1.05rem"><?= e(t('settings.colors_dark')) ?></h2>
            <div class="field-grid">
                <?php foreach ($colorNames as $name): ?>
                    <?php $value = (string) ($colors['dark'][$name] ?? '#000000'); ?>
                    <div class="field">
                        <label><?= e(ucfirst($name)) ?></label>
                        <div class="color-input">
                            <input type="color" value="<?= e($value) ?>" oninput="this.nextElementSibling.value=this.value.toUpperCase()">
                            <input type="text" name="colors_dark[<?= e($name) ?>]" pattern="^#[0-9A-Fa-f]{6}$" value="<?= e($value) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="hint mt-2"><?= e(__('Bu palet sitenin tamamında (butonlar, durum renkleri, arka plan) kullanılır.')) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($definition['bank_accounts'])): ?>
        <?php
        $bankRows = (array) Settings::get('payment.bank_accounts', []);
        $bankRows = array_values(array_filter($bankRows, 'is_array'));
        while (count($bankRows) < 3) { $bankRows[] = ['bank' => '', 'owner' => '', 'iban' => '', 'account' => '', 'branch' => '', 'note' => '']; }
        ?>
        <div class="card mb-3">
            <h2 style="font-size:1.05rem"><?= e(t('settings.bank_accounts')) ?></h2>
            <p class="hint"><?= e(t('settings.bank_accounts_hint')) ?></p>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr>
                        <th><?= e(t('settings.bank_name')) ?></th><th><?= e(t('settings.bank_owner_label')) ?></th>
                        <th>IBAN</th><th><?= e(t('settings.bank_account_no')) ?></th>
                        <th><?= e(t('settings.bank_branch')) ?></th><th><?= e(t('settings.bank_note_col')) ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($bankRows as $row): ?>
                        <tr>
                            <td><input type="text" name="ba_bank[]" maxlength="80" value="<?= e((string) ($row['bank'] ?? '')) ?>"></td>
                            <td><input type="text" name="ba_owner[]" maxlength="120" value="<?= e((string) ($row['owner'] ?? '')) ?>"></td>
                            <td><input type="text" name="ba_iban[]" maxlength="40" class="mono" placeholder="TR00 0000 0000 0000 0000 0000 00" value="<?= e((string) ($row['iban'] ?? '')) ?>"></td>
                            <td><input type="text" name="ba_account[]" maxlength="40" class="mono" value="<?= e((string) ($row['account'] ?? '')) ?>"></td>
                            <td><input type="text" name="ba_branch[]" maxlength="80" value="<?= e((string) ($row['branch'] ?? '')) ?>"></td>
                            <td><input type="text" name="ba_note[]" maxlength="160" value="<?= e((string) ($row['note'] ?? '')) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hint mt-2"><?= e(t('settings.bank_accounts_remove_hint')) ?></p>
        </div>
    <?php endif; ?>

    <button class="btn btn-accent" type="submit"><?= e(t('common.save')) ?></button>
</form>

<?php if (!empty($definition['test'])): ?>
    <div class="card mt-3">
        <h2 style="font-size:1.05rem"><?= e(t('settings.smtp_test')) ?></h2>
        <p class="muted"><?= e(__('Ayarları kaydettikten sonra bir test maili gönderin.')) ?></p>
        <form method="post" action="<?= e(url('/yonetim/ayarlar/test-mail')) ?>" class="row">
            <?= csrf_field() ?>
            <input type="email" name="test_email" required placeholder="ornek@alanadi.com" style="flex:1;min-width:220px">
            <button class="btn btn-primary" type="submit"><?= e(t('settings.smtp_test')) ?></button>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($media)): ?>
    <div class="card mt-3">
        <h2 style="font-size:1.05rem"><?= e(__('Son yüklenen dosyalar')) ?></h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th><?= e(__('Dosya')) ?></th><th><?= e(__('Tür')) ?></th><th><?= e(__('Boyut')) ?></th><th><?= e(__('Tarih')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($media as $item): ?>
                    <tr>
                        <td><a href="<?= e(media_url((string) $item['disk_path'])) ?>" target="_blank" rel="noopener"><?= e((string) ($item['original_name'] ?? $item['disk_path'])) ?></a></td>
                        <td class="mono" style="font-size:12.5px"><?= e((string) ($item['extension'] ?? '')) ?></td>
                        <td><?= e(format_bytes((int) $item['size_bytes'])) ?></td>
                        <td class="muted"><?= e(format_date((string) $item['created_at'], 'd.m.Y H:i')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
