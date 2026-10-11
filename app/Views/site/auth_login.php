<div class="card">
    <h2 class="mb-2"><?= e(t('auth.login')) ?></h2>
    <form method="post" action="<?= e(url('/giris')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="l-email"><?= e(t('auth.email')) ?></label>
            <input id="l-email" type="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
        </div>
        <div class="field">
            <label for="l-pass"><?= e(t('auth.password')) ?></label>
            <input id="l-pass" type="password" name="password" required minlength="8" autocomplete="current-password">
        </div>
        <div class="field check">
            <input id="l-remember" type="checkbox" name="remember" value="1">
            <label for="l-remember"><?= e(t('auth.remember')) ?></label>
        </div>
        <button class="btn btn-accent btn-block" type="submit"><?= e(t('auth.login')) ?></button>
    </form>
    <p class="center mt-2 mb-0">
        <a href="<?= e(url('/sifremi-unuttum')) ?>"><?= e(t('auth.forgot')) ?></a>
        &nbsp;·&nbsp;
        <a href="<?= e(url('/kayit')) ?>"><?= e(t('auth.register')) ?></a>
    </p>
</div>
