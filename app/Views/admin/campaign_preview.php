<?php
/** Kampanya önizlemesi. $rendered (subject, html), $campaign (?array) */
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.4rem;margin:0 0 4px"><?= e(__('Kampanya önizlemesi')) ?></h1>
        <p class="muted mb-0"><?= e(__('Değişkenler örnek bir alıcıya göre dolduruldu. Gönderim anında her alıcı için ayrı doldurulur.')) ?></p>
    </div>
    <div class="row">
        <?php if ($campaign !== null): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/kampanya/' . (int) $campaign['id'] . '/duzenle')) ?>"><?= e(__('Düzenlemeye dön')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <p class="mb-0"><strong><?= e(__('Konu:')) ?></strong> <?= e((string) $rendered['subject']) ?></p>
</div>

<div class="card">
    <h2 style="font-size:1.05rem"><?= e(__('E-posta gövdesi')) ?></h2>
    <div style="background:#F7F9FC;border:1px solid var(--c-border);border-radius:10px;padding:16px;overflow:auto">
        <?= $rendered['html'] ?>
    </div>
</div>
