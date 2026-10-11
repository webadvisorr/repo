<div class="card">
    <h2 class="mb-2"><?= e(t('auth.reset_title')) ?></h2>
    <p class="muted"><?= e('E-posta adresinizi girin; şifre sıfırlama bağlantısı gönderelim.') ?></p>
    <form method="post" action="<?= e(url('/sifremi-unuttum')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="f-email"><?= e(t('auth.email')) ?></label>
            <input id="f-email" type="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
        </div>
        <button class="btn btn-accent btn-block" type="submit"><?= e(t('auth.reset_send')) ?></button>
    </form>
    <p class="center mt-2 mb-0"><a href="<?= e(url('/giris')) ?>"><?= e(t('auth.login')) ?></a></p>
</div>
