<section class="section">
    <div class="container" style="max-width:720px">
        <div class="error-hero center">
            <div class="error-code error-code--warn" aria-hidden="true">405</div>
            <h1><?= e(__('Bu istek yöntemi desteklenmiyor')) ?></h1>
            <p class="lead"><?= e($title ?? t('http.405')) ?></p>
            <p class="muted"><?= e(__('Sayfayı bu şekilde açamıyoruz. Ana sayfadan veya menüden devam edebilirsiniz.')) ?></p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-accent" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
            </div>
        </div>
    </div>
</section>
