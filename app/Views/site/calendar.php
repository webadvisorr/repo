<?php
$cellStyle = 'border:1px solid var(--c-border);border-radius:10px;min-height:104px;padding:8px;background:var(--c-card);vertical-align:top;';
?>
<section class="section section--tight">
    <div class="container">
        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.7rem;margin-bottom:4px"><?= e(t('calendar.title')) ?></h1>
                <p class="muted mb-0"><?= e(t('calendar.subtitle')) ?></p>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/takvim?ay=' . $prev)) ?>">‹ <?= e(t('calendar.prev')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/takvim')) ?>"><?= e(t('calendar.today')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/takvim?ay=' . $next)) ?>"><?= e(t('calendar.next')) ?> ›</a>
            </div>
        </div>

        <div class="row row--between mb-2">
            <h2 style="font-size:1.25rem;margin:0"><?= e($monthLabel) ?></h2>
            <span class="badge badge--info"><?= e(t('calendar.count', ['count' => (int) $total])) ?></span>
        </div>

        <div class="table-wrap" style="padding:8px">
            <table style="width:100%;border-collapse:separate;border-spacing:6px">
                <thead>
                    <tr>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <th style="font-size:12.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);padding:4px"><?= e(t('calendar.dow_' . $i)) ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cell = 1;
                    $day = 1;
                    $cellsTotal = $leading + $daysInMonth;
                    $rowsTotal = (int) ceil($cellsTotal / 7);
                    for ($r = 0; $r < $rowsTotal; $r++):
                    ?>
                        <tr>
                            <?php for ($c = 0; $c < 7; $c++): ?>
                                <?php
                                $index = $r * 7 + $c;
                                if ($index < $leading || $day > $daysInMonth) {
                                    echo '<td></td>';
                                    continue;
                                }
                                $dateStr = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                                $events = $byDay[$dateStr] ?? [];
                                $isToday = $dateStr === $today;
                                ?>
                                <td style="<?= e($cellStyle . ($isToday ? 'border-color:var(--c-accent);' : '')) ?>">
                                    <div class="row row--between" style="margin-bottom:4px">
                                        <strong style="font-size:13.5px;<?= $isToday ? 'color:var(--c-accent)' : '' ?>"><?= (int) $day ?></strong>
                                        <?php if (!empty($events)): ?><span class="muted" style="font-size:11.5px"><?= count($events) ?></span><?php endif; ?>
                                    </div>
                                    <?php foreach (array_slice($events, 0, 3) as $ev): ?>
                                        <a href="<?= e(url('/panel/araclar/' . (int) $ev['vehicle_id'])) ?>"
                                           style="display:block;font-size:11.5px;line-height:1.5;text-decoration:none;color:inherit;border-inline-start:3px solid <?= e((string) ($ev['type_color'] ?: '#3B82F6')) ?>;padding:3px 6px;margin-bottom:3px;border-radius:5px;background:color-mix(in srgb, var(--c-primary) 5%, transparent)">
                                            <strong class="mono"><?= e((string) $ev['plate']) ?></strong>
                                            <span class="muted">· <?= e(label((string) $ev['type_labels'])) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if (count($events) > 3): ?>
                                        <span class="muted" style="font-size:11px">+<?= count($events) - 3 ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php $day++; ?>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <p class="muted mt-2" style="font-size:13px"><?= e(t('calendar.legend')) ?></p>
    </div>
</section>
