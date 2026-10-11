<div class="card">
    <h2 class="mb-2"><?= e(t('auth.register')) ?></h2>
    <?php if (empty($registrationOpen)): ?>
        <div class="alert alert--info"><?= e('Yeni kayıtlar şu anda kapalı. Lütfen daha sonra tekrar deneyin.') ?></div>
    <?php else: ?>
    <form method="post" action="<?= e(url('/kayit')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="r-name"><?= e(t('auth.name')) ?></label>
            <input id="r-name" type="text" name="full_name" required minlength="3" autocomplete="name" value="<?= e((string) old('full_name')) ?>">
        </div>
        <div class="field">
            <label for="r-email"><?= e(t('auth.email')) ?></label>
            <input id="r-email" type="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
        </div>
        <div class="field">
            <label for="r-phone"><?= e(t('auth.phone')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
            <input id="r-phone" type="tel" name="phone" autocomplete="tel">
        </div>
        <div class="field">
            <label for="r-pass"><?= e(t('auth.password')) ?></label>
            <input id="r-pass" type="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="r-pass2"><?= e(t('auth.password_confirm')) ?></label>
            <input id="r-pass2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field check">
            <input id="r-terms" type="checkbox" name="terms" value="1" required>
            <label for="r-terms"><?= e(t('auth.terms')) ?></label>
        </div>
        <div class="field check">
            <input id="r-marketing" type="checkbox" name="marketing_opt_in" value="1" checked>
            <label for="r-marketing"><?= e(t('auth.marketing_opt_in')) ?></label>
        </div>
        <button class="btn btn-accent btn-block" type="submit"><?= e(t('auth.register')) ?></button>
    </form>
    <?php endif; ?>
    <p class="center mt-2 mb-0"><a href="<?= e(url('/giris')) ?>"><?= e(t('auth.have_account')) ?></a></p>
</div>
