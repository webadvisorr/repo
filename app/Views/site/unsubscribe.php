<section class="section">
    <div class="container" style="max-width:600px">
        <div class="card center">
            <div class="feature-icon" aria-hidden="true"><?= $valid ? '📭' : '⚠️' ?></div>
            <h1 style="font-size:1.5rem"><?= e(t('unsub.title')) ?></h1>

            <?php if (empty($valid)): ?>
                <p class="lead"><?= e(t('unsub.invalid')) ?></p>
                <a class="btn btn-outline" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
            <?php else: ?>
                <?php if (!empty($unsubscribed)): ?>
                    <div class="alert alert--success"><span>✅</span><div><?= e(t('unsub.already')) ?></div></div>
                    <p class="muted"><strong><?= e((string) $email) ?></strong></p>
                    <p class="muted" style="font-size:13.5px"><?= e(t('unsub.reopen_hint')) ?></p>
                    <form method="post" action="<?= e(url('/abonelikten-cikar/yeniden-ac')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="e" value="<?= e((string) ($encoded ?? '')) ?>">
                        <input type="hidden" name="t" value="<?= e((string) ($token ?? '')) ?>">
                        <button class="btn btn-accent btn-block" type="submit"><?= e(t('unsub.reopen')) ?></button>
                    </form>
                <?php else: ?>
                    <p class="lead"><?= e(t('unsub.lead')) ?></p>
                    <p class="muted"><strong><?= e((string) $email) ?></strong></p>
                    <p class="muted" style="font-size:13.5px"><?= e(t('unsub.detail')) ?></p>
                    <form method="post" action="<?= e(url('/abonelikten-cikar')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="e" value="<?= e((string) ($encoded ?? '')) ?>">
                        <input type="hidden" name="t" value="<?= e((string) ($token ?? '')) ?>">
                        <button class="btn btn-primary btn-block" type="submit"><?= e(t('unsub.confirm')) ?></button>
                    </form>
                <?php endif; ?>

                <p class="muted mt-3" style="font-size:13px">
                    <?= e(t('unsub.keep_hint')) ?>
                    <a href="<?= e(url('/giris')) ?>"><?= e(t('auth.login')) ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
