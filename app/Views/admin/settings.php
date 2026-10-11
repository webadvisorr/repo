<?php
use App\Core\Settings;
use App\Core\Uploader;

$tabLabels = [
    'brand' => t('settings.tab_brand'), 'seo' => t('settings.tab_seo'), 'theme' => t('settings.tab_theme'),
    'preloader' => t('settings.tab_preloader'), 'site' => t('settings.tab_site'), 'mail' => t('settings.tab_mail'),
    'channels' => t('settings.tab_channels'), 'payment' => t('settings.tab_payment'),
];
$definition = $schema[$tab] ?? [];
$colorNames = ['primary', 'accent', 'secondary', 'warning', 'danger', 'bg', 'card', 'text', 'muted', 'border'];
?>
<div class="row row--between mb-3">
    <h1 style="font-size:1.6rem;margin:0"><?= e(t('settings.title')) ?></h1>
    <span class="muted" style="font-size:13px"><?= e('Tüm değişiklikler anında siteye yansır.') ?></span>
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
                        <span class="muted"><?= e('Yüklenmedi — varsayılan kullanılıyor.') ?></span>
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

<form method="post" action="<?= e(url('/yonetim/ayarlar/kaydet')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e((string) $tab) ?>">

    <?php if (!empty($definition['text']) || !empty($definition['select']) || !empty($definition['number']) || !empty($definition['password']) || !empty($definition['color'])): ?>
        <div class="card mb-3">
            <h2 style="font-size:1.05rem"><?= e($tabLabels[$tab] ?? $tab) ?></h2>
            <div class="field-grid">
                <?php foreach (($definition['text'] ?? []) as $key => $meta): ?>
                    <div class="field">
                        <label for="f-<?= e(md5($key)) ?>"><?= e(t('settings.' . $meta['label'])) ?></label>
                        <?php if (!empty($meta['textarea'])): ?>
                            <textarea id="f-<?= e(md5($key)) ?>" name="<?= e(str_replace('.', '_', (string) $key)) ?>" maxlength="<?= (int) ($meta['max'] ?? 255) ?>"><?= e((string) Settings::get($key, '')) ?></textarea>
                        <?php else: ?>
                            <input id="f-<?= e(md5($key)) ?>" type="text" name="<?= e(str_replace('.', '_', (string) $key)) ?>" maxlength="<?= (int) ($meta['max'] ?? 255) ?>" value="<?= e((string) Settings::get($key, '')) ?>">
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
            <p class="hint mt-2"><?= e('Bu palet sitenin tamamında (butonlar, durum renkleri, arka plan) kullanılır.') ?></p>
        </div>
    <?php endif; ?>

    <button class="btn btn-accent" type="submit"><?= e(t('common.save')) ?></button>
</form>

<?php if (!empty($definition['test'])): ?>
    <div class="card mt-3">
        <h2 style="font-size:1.05rem"><?= e(t('settings.smtp_test')) ?></h2>
        <p class="muted"><?= e('Ayarları kaydettikten sonra bir test maili gönderin.') ?></p>
        <form method="post" action="<?= e(url('/yonetim/ayarlar/test-mail')) ?>" class="row">
            <?= csrf_field() ?>
            <input type="email" name="test_email" required placeholder="ornek@alanadi.com" style="flex:1;min-width:220px">
            <button class="btn btn-primary" type="submit"><?= e(t('settings.smtp_test')) ?></button>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($media)): ?>
    <div class="card mt-3">
        <h2 style="font-size:1.05rem"><?= e('Son yüklenen dosyalar') ?></h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th><?= e('Dosya') ?></th><th><?= e('Tür') ?></th><th><?= e('Boyut') ?></th><th><?= e('Tarih') ?></th></tr></thead>
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
