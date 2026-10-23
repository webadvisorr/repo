<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px"><?= e(t('mail_templates.key_' . $key)) ?></h1>
        <p class="muted mb-0 mono" style="font-size:13px"><?= e($key) ?> · <?= e($locale) ?></p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/sablonlar')) ?>">← <?= e(t('common.back')) ?></a>
</div>

<div class="admin-tabs">
    <?php foreach ($locales as $code): ?>
        <a href="<?= e(url('/yonetim/sablonlar/' . $key . '/' . $code)) ?>" class="<?= $code === $locale ? 'is-active' : '' ?>">
            <?= e((string) ($names[$code] ?? $code)) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($isDefault)): ?>
    <div class="alert alert--info"><span>ℹ️</span><div><?= e(t('mail_templates.using_default')) ?></div></div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <form method="post" action="<?= e(url('/yonetim/sablonlar/kaydet')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="key" value="<?= e($key) ?>">
            <input type="hidden" name="locale" value="<?= e($locale) ?>">

            <div class="field">
                <label for="mt-name"><?= e(t('mail_templates.name')) ?></label>
                <input id="mt-name" type="text" name="name" maxlength="120" value="<?= e((string) ($template['name'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="mt-subject"><?= e(t('mail_templates.subject')) ?> *</label>
                <input id="mt-subject" type="text" name="subject" maxlength="255" required value="<?= e((string) ($template['subject'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="mt-body"><?= e(t('mail_templates.body_html')) ?> *</label>
                <textarea id="mt-body" name="body_html" rows="16" required class="mono" style="font-size:12.5px"><?= e((string) ($template['body_html'] ?? '')) ?></textarea>
                <p class="hint"><?= e(t('mail_templates.body_html_hint')) ?></p>
            </div>
            <div class="field">
                <label for="mt-text"><?= e(t('mail_templates.body_text')) ?></label>
                <textarea id="mt-text" name="body_text" rows="6" class="mono" style="font-size:12.5px"><?= e((string) ($template['body_text'] ?? '')) ?></textarea>
                <p class="hint"><?= e(t('mail_templates.body_text_hint')) ?></p>
            </div>
            <div class="field">
                <label class="row" style="gap:8px;align-items:center">
                    <input type="checkbox" name="is_active" value="1" <?= (int) ($template['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span><?= e(t('mail_templates.active')) ?></span>
                </label>
            </div>
            <div class="row">
                <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
            </div>
        </form>

        <hr style="border:none;border-top:1px solid var(--c-border);margin:18px 0">
        <form method="post" action="<?= e(url('/yonetim/sablonlar/sifirla')) ?>" data-confirm="<?= e(t('mail_templates.reset_confirm')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="key" value="<?= e($key) ?>">
            <input type="hidden" name="locale" value="<?= e($locale) ?>">
            <button class="btn btn-outline btn-sm" type="submit"><?= e(t('mail_templates.reset')) ?></button>
        </form>
    </div>

    <div>
        <div class="card mb-3">
            <h2 style="font-size:1.05rem">👁️ <?= e(t('mail_templates.preview')) ?></h2>
            <p class="muted" style="font-size:13.5px"><?= e(t('mail_templates.preview_hint')) ?></p>
            <form method="post" action="<?= e(url('/yonetim/sablonlar/onizleme')) ?>" target="_blank">
                <?= csrf_field() ?>
                <input type="hidden" name="key" value="<?= e($key) ?>">
                <input type="hidden" name="locale" value="<?= e($locale) ?>">
                <button class="btn btn-outline" type="submit"><?= e(t('mail_templates.preview')) ?></button>
            </form>
        </div>

        <div class="card mb-3">
            <h2 style="font-size:1.05rem">📤 <?= e(t('mail_templates.test')) ?></h2>
            <?php if (empty($smtpReady)): ?>
                <div class="alert alert--error"><span>⚠️</span><div><?= e(t('mail_templates.smtp_missing')) ?></div></div>
            <?php else: ?>
                <form method="post" action="<?= e(url('/yonetim/sablonlar/test')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="key" value="<?= e($key) ?>">
                    <input type="hidden" name="locale" value="<?= e($locale) ?>">
                    <div class="field">
                        <label for="mt-to"><?= e(t('settings.smtp_test_to')) ?></label>
                        <input id="mt-to" type="email" name="test_email" required placeholder="ornek@site.com">
                    </div>
                    <button class="btn btn-accent" type="submit"><?= e(t('settings.smtp_test')) ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="font-size:1.05rem"><?= e(t('mail_templates.variables')) ?></h2>
            <p class="muted" style="font-size:13px"><?= e(t('mail_templates.variables_hint')) ?></p>
            <div class="table-wrap" style="max-height:320px;overflow-y:auto">
                <table class="data">
                    <tbody>
                    <?php foreach ($variables as $name => $meaning): ?>
                        <tr>
                            <td class="mono" style="font-size:12.5px;white-space:nowrap">{<?= e((string) $name) ?>}</td>
                            <td class="muted" style="font-size:12.5px"><?= e((string) $meaning) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
