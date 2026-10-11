<div class="card">
    <h2 class="mb-2"><?= e(t('admin.login_title')) ?></h2>
    <form method="post" action="<?= e(url('/yonetim/giris')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="a-email"><?= e(t('auth.email')) ?></label>
            <input id="a-email" type="email" name="email" required autocomplete="username" value="<?= e((string) old('email')) ?>">
        </div>
        <div class="field">
            <label for="a-pass"><?= e(t('auth.password')) ?></label>
            <input id="a-pass" type="password" name="password" required minlength="8" autocomplete="current-password">
        </div>
        <div class="field check">
            <input id="a-remember" type="checkbox" name="remember" value="1">
            <label for="a-remember"><?= e(t('auth.remember')) ?></label>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.login')) ?></button>
    </form>
</div>
