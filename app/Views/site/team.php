<?php
$active = 'team';
?>
<section class="section section--tight">
    <div class="container">
        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.7rem;margin-bottom:4px"><?= e(t('team.title')) ?></h1>
                <p class="muted mb-0"><?= e(t('team.plan_usage', ['used' => (int) $used, 'max' => (int) $maxUsers, 'plan' => (string) $plan])) ?></p>
            </div>
        </div>

        <?php if (!$isOwner): ?>
            <div class="alert alert--info"><?= e(t('team.owner_only')) ?></div>
        <?php else: ?>
            <div class="card mb-3">
                <h2 style="font-size:1.15rem">+ <?= e(t('team.invite')) ?></h2>
                <form method="post" action="<?= e(url('/panel/ekip/davet')) ?>">
                    <?= csrf_field() ?>
                    <div class="grid grid-2">
                        <div class="field">
                            <label for="inv-email"><?= e(t('auth.email')) ?> *</label>
                            <input id="inv-email" type="email" name="email" required maxlength="190" placeholder="ornek@firma.com">
                        </div>
                        <div class="field">
                            <label for="inv-role"><?= e(t('team.role')) ?></label>
                            <select id="inv-role" name="role">
                                <option value="viewer"><?= e(t('team.role_viewer')) ?></option>
                                <option value="manager"><?= e(t('team.role_manager')) ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2" style="gap:18px">
                        <label class="check"><input type="checkbox" name="can_manage_vehicles" value="1"> <?= e(t('team.can_vehicles')) ?></label>
                        <label class="check"><input type="checkbox" name="can_manage_documents" value="1"> <?= e(t('team.can_documents')) ?></label>
                        <label class="check"><input type="checkbox" name="can_manage_billing" value="1"> <?= e(t('team.can_billing')) ?></label>
                    </div>
                    <button class="btn btn-accent" type="submit"><?= e(t('team.send_invite')) ?></button>
                </form>
            </div>
        <?php endif; ?>

        <h2 style="font-size:1.2rem"><?= e(t('team.members')) ?></h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th><?= e(t('team.member')) ?></th>
                        <th><?= e(t('team.role')) ?></th>
                        <th><?= e(t('team.can_vehicles')) ?></th>
                        <th><?= e(t('team.can_documents')) ?></th>
                        <th><?= e(t('team.can_billing')) ?></th>
                        <th><?= e(t('common.status')) ?></th>
                        <?php if ($isOwner): ?><th><?= e(t('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <?php
                        $isOwnerRow = (string) $m['role'] === 'owner';
                        $name = (string) ($m['full_name'] ?? '');
                        $mail = (string) ($m['user_email'] ?? $m['invited_email'] ?? '');
                        $statusKey = match ((string) $m['status']) {
                            'active'  => 'team.status_active',
                            'invited' => 'team.status_invited',
                            default   => 'team.status_removed',
                        };
                        $badge = match ((string) $m['status']) {
                            'active'  => 'badge--ok',
                            'invited' => 'badge--warn',
                            default   => 'badge--danger',
                        };
                        ?>
                        <tr style="<?= (string) $m['status'] === 'removed' ? 'opacity:.55' : '' ?>">
                            <td>
                                <strong><?= e($name !== '' ? $name : $mail) ?></strong><br>
                                <span class="muted" style="font-size:12.5px"><?= e($mail) ?></span>
                            </td>
                            <td><?= e($isOwnerRow ? t('team.role_owner') : ((string) $m['role'] === 'manager' ? t('team.role_manager') : t('team.role_viewer'))) ?></td>
                            <td><?= (int) $m['can_manage_vehicles'] === 1 ? '✓' : '—' ?></td>
                            <td><?= (int) $m['can_manage_documents'] === 1 ? '✓' : '—' ?></td>
                            <td><?= (int) $m['can_manage_billing'] === 1 ? '✓' : '—' ?></td>
                            <td><span class="badge <?= e($badge) ?>"><?= e(t($statusKey)) ?></span></td>
                            <?php if ($isOwner): ?>
                                <td>
                                    <?php if ($isOwnerRow): ?>
                                        <span class="muted" style="font-size:12.5px"><?= e(t('team.owner_locked')) ?></span>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('/panel/ekip/' . (int) $m['id'])) ?>" class="row" style="gap:6px;align-items:center">
                                            <?= csrf_field() ?>
                                            <select name="role" style="width:auto;padding:6px 10px">
                                                <option value="viewer" <?= (string) $m['role'] === 'viewer' ? 'selected' : '' ?>><?= e(t('team.role_viewer')) ?></option>
                                                <option value="manager" <?= (string) $m['role'] === 'manager' ? 'selected' : '' ?>><?= e(t('team.role_manager')) ?></option>
                                            </select>
                                            <label class="check" title="<?= e(t('team.can_vehicles')) ?>"><input type="checkbox" name="can_manage_vehicles" value="1" <?= (int) $m['can_manage_vehicles'] === 1 ? 'checked' : '' ?>> 🚗</label>
                                            <label class="check" title="<?= e(t('team.can_documents')) ?>"><input type="checkbox" name="can_manage_documents" value="1" <?= (int) $m['can_manage_documents'] === 1 ? 'checked' : '' ?>> 📄</label>
                                            <label class="check" title="<?= e(t('team.can_billing')) ?>"><input type="checkbox" name="can_manage_billing" value="1" <?= (int) $m['can_manage_billing'] === 1 ? 'checked' : '' ?>> 💳</label>
                                            <button class="btn btn-primary btn-sm" type="submit"><?= e(t('common.save')) ?></button>
                                        </form>
                                        <form method="post" action="<?= e(url('/panel/ekip/' . (int) $m['id'] . '/kaldir')) ?>" class="mt-2" onsubmit="return confirm('<?= e(t('team.remove_confirm')) ?>');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-danger btn-sm" type="submit"><?= e(t('team.remove')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
