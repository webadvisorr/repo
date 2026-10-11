<?php use App\Core\Translator; ?>
<section class="hero">
    <div class="container">
        <h1><?= e(t('nav.pricing')) ?></h1>
        <p class="lead"><?= e('Araç sayınıza uygun planı seçin. Ücretsiz planla başlayın, istediğiniz an yükseltin.') ?></p>
    </div>
</section>
<section class="section--tight">
    <div class="container">
        <div class="grid grid-4">
            <?php foreach (($plans ?? []) as $index => $plan): ?>
                <?php $meta = json_decode((string) $plan['features_json'], true); $names = $meta['labels'] ?? []; ?>
                <div class="card plan <?= $index === 2 ? 'plan--featured' : '' ?>">
                    <h3 class="mb-0"><?= e((string) ($names[Translator::locale()] ?? $plan['name'])) ?></h3>
                    <div class="plan__price">
                        <?= (float) $plan['price'] > 0 ? e(number_format((float) $plan['price'], 0, ',', '.')) . ' ' . e((string) $plan['currency']) : e('Ücretsiz') ?>
                    </div>
                    <ul>
                        <li><?= e((string) $plan['max_vehicles'] . ' araç') ?></li>
                        <li><?= e((string) $plan['max_users'] . ' kullanıcı') ?></li>
                        <li><?= e('Bildirim: ' . strtoupper((string) $plan['channels'])) ?></li>
                    </ul>
                    <button class="btn btn-outline btn-block mt-2" data-open-drawer="register"><?= e('Bu planla başla') ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="center muted mt-3" style="font-size:13.5px">
            <?= e('Fiyatlar KDV hariçtir. Ödeme iyzico güvenli ödeme sayfası üzerinden alınır.') ?>
        </p>
    </div>
</section>
