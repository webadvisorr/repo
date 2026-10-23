<?php
/** Yeni destek talebi formu. */
use App\Services\SupportService;

$active = 'support';
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.5rem;margin:0 0 4px">🎧 <?= e(__('Yeni destek talebi')) ?></h1>
                <p class="muted mb-0"><?= e(__('Talebinizi kısaca anlatın; ekibimiz panel üzerinden yanıtlayacak.')) ?></p>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/destek')) ?>">← <?= e(__('Destek taleplerim')) ?></a>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <form method="post" action="<?= e(url('/panel/destek')) ?>" class="card" style="max-width:760px">
            <?= csrf_field() ?>
            <div class="field">
                <label for="ticket-subject"><?= e(__('Konu')) ?> <span class="muted">*</span></label>
                <input id="ticket-subject" type="text" name="subject" maxlength="200" required
                       value="<?= e((string) old('subject')) ?>" placeholder="<?= e(__('Örn. Sigorta evrakı hatırlatması gelmiyor')) ?>">
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="ticket-category"><?= e(__('Kategori')) ?></label>
                    <select id="ticket-category" name="category">
                        <?php foreach (SupportService::CATEGORIES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= (string) old('category', 'other') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="ticket-priority"><?= e(__('Öncelik')) ?></label>
                    <select id="ticket-priority" name="priority">
                        <?php foreach (SupportService::PRIORITIES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= (string) old('priority', 'normal') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="ticket-body"><?= e(__('Mesaj')) ?> <span class="muted">*</span></label>
                <textarea id="ticket-body" name="body" rows="6" maxlength="5000" required
                          placeholder="<?= e(__('Sorunu ve varsa plaka/evrak bilgisini yazın…')) ?>"><?= e((string) old('body')) ?></textarea>
                <p class="hint"><?= e(__('En az 10 karakter. Talebiniz yönetim panelindeki Destek ekranına düşer.')) ?></p>
            </div>
            <div class="row" style="gap:10px">
                <button class="btn btn-accent" type="submit"><?= e(__('Talebi gönder')) ?></button>
                <a class="btn btn-outline" href="<?= e(url('/panel/destek')) ?>"><?= e(__('Vazgeç')) ?></a>
            </div>
        </form>
    </div>
</section>
