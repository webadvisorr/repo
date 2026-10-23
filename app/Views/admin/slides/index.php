<?php
$localeLabels = ['' => 'Tüm diller', 'tr' => 'Türkçe', 'en' => 'English', 'ru' => 'Русский', 'ar' => 'العربية', 'fa' => 'فارسی', 'el' => 'Ελληνικά'];
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">🖼️ <?= e(__('Slider')) ?></h1>
        <p class="muted mb-0"><?= e(__('Ana sayfa slaytları. Aktif slayt yoksa site varsayılan çeviri slaytlarını gösterir.')) ?></p>
    </div>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= e(__('Ana sayfada gör')) ?></a>
        <a class="btn btn-accent btn-sm" href="<?= e(url('/yonetim/slider/yeni')) ?>">+ <?= e(__('Yeni slayt')) ?></a>
    </div>
</div>

<div class="grid grid-3 mb-3">
    <div class="card stat"><div class="stat__value"><?= count($rows) ?></div><div class="stat__label"><?= e(__('Toplam slayt')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-accent)"><?= (int) $active ?></div><div class="stat__label"><?= e(__('Yayında')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= count($rows) - (int) $active ?></div><div class="stat__label"><?= e(__('Pasif')) ?></div></div>
</div>

<?php if ($rows === []): ?>
    <div class="card center">
        <h2 style="font-size:1.15rem"><?= e(__('Henüz slayt eklenmedi')) ?></h2>
        <p class="muted"><?= e(__('Şu an ana sayfada varsayılan metinli slaytlar gösteriliyor. Kendi görsel ve metinlerinizi ekleyebilirsiniz.')) ?></p>
        <a class="btn btn-accent" href="<?= e(url('/yonetim/slider/yeni')) ?>">+ <?= e(__('İlk slaytı ekle')) ?></a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(__('Görsel')) ?></th>
                    <th><?= e(__('Başlık')) ?></th>
                    <th><?= e(__('Dil')) ?></th>
                    <th><?= e(__('Sıra')) ?></th>
                    <th><?= e(__('Durum')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= (int) $row['id'] ?></td>
                    <td>
                        <?php if (!empty($row['image_path'])): ?>
                            <img src="<?= e(media_url((string) $row['image_path'])) ?>" alt="" style="width:96px;height:54px;object-fit:cover;border-radius:8px;border:1px solid var(--c-border)">
                        <?php else: ?>
                            <span class="muted" style="font-size:12px"><?= e(__('Görsel yok')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= e((string) ($row['title'] ?? '—')) ?></strong>
                        <?php if (!empty($row['subtitle'])): ?><div class="muted" style="font-size:12.5px"><?= e((string) $row['subtitle']) ?></div><?php endif; ?>
                        <?php if (!empty($row['button_text'])): ?><div class="muted" style="font-size:12px">CTA: <?= e((string) $row['button_text']) ?><?= !empty($row['button_url']) ? ' → ' . e((string) $row['button_url']) : '' ?></div><?php endif; ?>
                    </td>
                    <td><?= e($localeLabels[(string) ($row['locale'] ?? '')] ?? (string) $row['locale']) ?></td>
                    <td class="mono"><?= (int) $row['sort_order'] ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/yonetim/slider/durum')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button class="badge <?= (int) $row['is_active'] === 1 ? 'badge--ok' : 'badge--info' ?>" type="submit" style="border:0;cursor:pointer" title="<?= e(__('Yayın durumunu değiştir')) ?>">
                                <?= (int) $row['is_active'] === 1 ? e(__('Yayında')) : e(__('Pasif')) ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="row" style="gap:5px;flex-wrap:nowrap">
                            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/slider/' . (int) $row['id'])) ?>"><?= e(__('Düzenle')) ?></a>
                            <form method="post" action="<?= e(url('/yonetim/slider/sil')) ?>" style="display:inline"
                                  data-confirm="<?= e(__('Bu slayt silinecek. Emin misiniz?')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(__('Sil')) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
