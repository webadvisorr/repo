<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">✉️ <?= e(t('admin.mail_templates')) ?></h1>
        <p class="muted mb-0"><?= e(t('mail_templates.subtitle')) ?></p>
    </div>
</div>

<?php if (!empty($created)): ?>
    <div class="alert alert--success"><span>✅</span><div><?= e(t('mail_templates.created_defaults', ['count' => (int) $created])) ?></div></div>
<?php endif; ?>

<div class="card mb-3">
    <h2 style="font-size:1.05rem"><?= e(t('mail_templates.title')) ?></h2>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= e(t('mail_templates.template')) ?></th>
                    <?php foreach ($locales as $locale): ?>
                        <th><?= e((string) ($names[$locale] ?? $locale)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($keys as $key): ?>
                <tr>
                    <td><strong><?= e(t('mail_templates.key_' . $key)) ?></strong><div class="mono muted" style="font-size:12px"><?= e($key) ?></div></td>
                    <?php foreach ($locales as $locale): ?>
                        <?php $row = $matrix[$key][$locale] ?? null; ?>
                        <td>
                            <?php if ($row === null): ?>
                                <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/sablonlar/' . $key . '/' . $locale)) ?>"><?= e(t('mail_templates.create')) ?></a>
                            <?php else: ?>
                                <a href="<?= e(url('/yonetim/sablonlar/' . $key . '/' . $locale)) ?>">
                                    <?= (int) $row['is_active'] === 1 ? '✅' : '⏸️' ?>
                                    <?= e(t('mail_templates.edit')) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted mt-2" style="font-size:13px"><?= e(t('mail_templates.fallback_note')) ?></p>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(t('mail_templates.variables')) ?></h2>
        <p class="muted" style="font-size:13.5px"><?= e(t('mail_templates.variables_hint')) ?></p>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th><?= e(t('mail_templates.variable')) ?></th><th><?= e(t('mail_templates.meaning')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($variables as $name => $meaning): ?>
                    <tr>
                        <td class="mono" style="font-size:12.5px">{<?= e((string) $name) ?>}</td>
                        <td class="muted"><?= e((string) $meaning) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(t('mail_templates.tips_title')) ?></h2>
        <ul class="muted" style="font-size:13.5px;line-height:1.8;padding-inline-start:18px">
            <li><?= e(t('mail_templates.tip_html')) ?></li>
            <li><?= e(t('mail_templates.tip_inline')) ?></li>
            <li><?= e(t('mail_templates.tip_unsub')) ?></li>
            <li><?= e(t('mail_templates.tip_test')) ?></li>
        </ul>
        <div class="alert alert--info">
            <span>ℹ️</span>
            <div style="font-size:13.5px"><?= e(t('mail_templates.brand_note')) ?></div>
        </div>
        <p class="muted mb-0" style="font-size:13px">
            <?= e(t('mail_templates.current_brand')) ?>: <strong><?= e((string) ($layoutVars['marka_adi'] ?? '')) ?></strong>
            <?php if (($layoutVars['logo_url'] ?? '') !== ''): ?> · 🖼️ <?= e(t('mail_templates.logo_set')) ?><?php endif; ?>
        </p>
    </div>
</div>
