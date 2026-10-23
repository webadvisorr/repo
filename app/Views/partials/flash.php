<?php
use App\Core\Flash;
$messages = Flash::pull();
foreach ($messages as $message):
    $type = match ($message['type']) {
        'success' => 'success',
        'error'   => 'error',
        default   => 'info',
    };
?>
    <div class="alert alert--<?= e($type) ?>" role="status"><?= e(__((string) $message['message'])) ?></div>
<?php endforeach; ?>
