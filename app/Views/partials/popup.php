<?php
use App\Core\Settings;
if ((string) Settings::get('popup.enabled', '0') !== '1') {
    return;
}
$title = (string) Settings::get('popup.title', '');
$text = (string) Settings::get('popup.text', '');
$image = (string) Settings::get('popup.image', '');
$buttonText = (string) Settings::get('popup.button_text', '');
$buttonUrl = (string) Settings::get('popup.button_url', '');
$delay = (float) Settings::get('popup.delay', '3');
$frequency = (string) Settings::get('popup.frequency', 'session');
if ($title === '' && $text === '' && $image === '') {
    return;
}
?>
<div class="modal-backdrop" data-popup-backdrop hidden></div>
<div class="modal" data-popup data-delay="<?= e((string) $delay) ?>" data-frequency="<?= e($frequency) ?>"
     role="dialog" aria-modal="true" aria-label="<?= e($title !== '' ? $title : 'Duyuru') ?>" hidden>
    <button type="button" class="modal__close" data-popup-close aria-label="<?= e(t('common.close')) ?>">&times;</button>
    <?php if ($image !== ''): ?>
        <div class="modal__media">
            <img src="<?= e(media_url($image)) ?>" alt="<?= e($title !== '' ? $title : 'Duyuru görseli') ?>" loading="lazy" decoding="async">
        </div>
    <?php endif; ?>
    <div class="modal__body">
        <?php if ($title !== ''): ?><h3><?= e($title) ?></h3><?php endif; ?>
        <?php if ($text !== ''): ?><p class="muted"><?= e($text) ?></p><?php endif; ?>
        <?php if ($buttonText !== '' && $buttonUrl !== ''): ?>
            <a class="btn btn-accent btn-block" href="<?= e(url($buttonUrl)) ?>"><?= e($buttonText) ?></a>
        <?php endif; ?>
    </div>
</div>
