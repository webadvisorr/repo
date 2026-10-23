<?php
/**
 * Tarih aralığı filtresi (liste ekranları).
 * Beklenen değişkenler: $from, $to (Y-m-d) ve isteğe bağlı $fromLabel.
 */
$from = (string) ($from ?? '');
$to = (string) ($to ?? '');
?>
<span class="date-range" role="group" aria-label="<?= e(__('Tarih aralığı')) ?>">
    <label class="date-range__field">
        <span class="sr-only"><?= e(__('Başlangıç tarihi')) ?></span>
        <input type="date" name="baslangic" value="<?= e($from) ?>" aria-label="<?= e(__('Başlangıç tarihi')) ?>">
    </label>
    <span class="date-range__sep" aria-hidden="true">–</span>
    <label class="date-range__field">
        <span class="sr-only"><?= e(__('Bitiş tarihi')) ?></span>
        <input type="date" name="bitis" value="<?= e($to) ?>" aria-label="<?= e(__('Bitiş tarihi')) ?>">
    </label>
</span>
