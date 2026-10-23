<section class="section">
    <div class="container" style="max-width:720px">
        <div class="error-hero center">
            <div class="error-code error-code--warn" aria-hidden="true">419</div>
            <h1><?= e(__('Oturum süresi doldu')) ?></h1>
            <p class="lead"><?= e($title ?? t('http.419')) ?></p>
            <p class="muted"><?= e(__('Güvenliğiniz için uzun süre işlem yapılmayan sayfalar kapanır. Lütfen formu yeniden gönderin.')) ?></p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-accent" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
                <a class="btn btn-outline" href="<?= e(url(current_path())) ?>"><?= e(__('Sayfayı yenile')) ?></a>
            </div>
        </div>
    </div>
</section>
