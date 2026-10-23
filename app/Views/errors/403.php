<section class="section">
    <div class="container" style="max-width:720px">
        <div class="error-hero center">
            <div class="error-code error-code--warn" aria-hidden="true">403</div>
            <h1><?= e(__('Bu sayfaya erişim yetkiniz yok')) ?></h1>
            <p class="lead"><?= e($title ?? t('http.403')) ?></p>
            <p class="muted"><?= e(__('Bu içeriği görmek için gerekli izne sahip değilsiniz. Giriş yaptıysanız hesabınızın yetkisini kontrol edin.')) ?></p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-accent" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
                <a class="btn btn-outline" href="<?= e(url('/iletisim')) ?>"><?= e(t('nav.contact')) ?></a>
            </div>
        </div>
    </div>
</section>
