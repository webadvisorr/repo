<?php
$active = '';
?>
<section class="section">
    <div class="container" style="max-width:520px">
        <div class="card">
            <h1 style="font-size:1.5rem"><?= e(t('team.invite_title')) ?></h1>
            <?php if (empty($valid)): ?>
                <div class="alert alert--error"><?= e(t('team.invite_invalid')) ?></div>
                <a class="btn btn-outline" href="<?= e(url('/giris')) ?>"><?= e(t('auth.login')) ?></a>
            <?php else: ?>
                <p class="muted"><?= e(t('team.invite_help')) ?></p>
                <?php if (!empty($email)): ?>
                    <p class="muted"><strong><?= e((string) $email) ?></strong></p>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/davet')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e((string) ($token ?? '')) ?>">
                    <div class="field">
                        <label for="acc-name"><?= e(t('auth.name')) ?> *</label>
                        <input id="acc-name" type="text" name="full_name" required minlength="3" maxlength="160" value="<?= e((string) old('full_name')) ?>">
                    </div>
                    <div class="field">
                        <label for="acc-pass"><?= e(t('auth.password')) ?> *</label>
                        <input id="acc-pass" type="password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="acc-pass2"><?= e(t('auth.password_confirm')) ?> *</label>
                        <input id="acc-pass2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </div>
                    <button class="btn btn-accent btn-block" type="submit"><?= e(t('team.accept_join')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
