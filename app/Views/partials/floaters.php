<?php
use App\Core\Settings;
$waUrl = whatsapp_url((string) Settings::get('brand.title', 'Evrako') . ' — bilgi almak istiyorum.');
?>
<div class="scroll-progress" data-scroll-progress aria-hidden="true"><span></span></div>

<?php if ($waUrl !== ''): ?>
<a class="wa-float" href="<?= e($waUrl) ?>"
   target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"
   title="WhatsApp">
    <svg viewBox="0 0 32 32" width="26" height="26" aria-hidden="true" fill="currentColor">
        <path d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.1 1.6 5.9L4 29l8.4-1.6c1.7.9 3.6 1.4 5.6 1.4 6.6 0 12-5.4 12-12S22.6 3 16 3Zm0 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-4.4.8.8-4.3-.3-.4A9.8 9.8 0 0 1 6.2 15c0-5.4 4.4-9.8 9.8-9.8s9.8 4.4 9.8 9.8-4.4 9.8-9.8 9.8Zm5.6-7.3c-.3-.2-1.8-.9-2.1-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.3 0-.5.1-.6l.5-.6c.1-.2.2-.3.3-.5s0-.4 0-.5l-.9-2.1c-.2-.5-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.4-1.1 1.1-1.1 2.6s1.1 3 1.3 3.2c.1.2 2 3.2 5 4.4 2.9 1.2 3.4 1 4 .9.6-.1 1.8-.7 2.1-1.5.3-.7.3-1.4.2-1.5-.1-.2-.3-.2-.6-.4Z"/>
    </svg>
</a>
<?php endif; ?>

<button type="button" class="back-to-top" data-back-to-top aria-label="<?= e(__('Yukarı dön')) ?>" title="<?= e(__('Yukarı dön')) ?>">
    <span aria-hidden="true">↑</span>
</button>
