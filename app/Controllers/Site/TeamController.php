<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\Settings;
use App\Core\Str;
use App\Services\MembershipService;
use App\Services\SubscriptionService;

/**
 * Müşteri paneli — Filo / Ekip yönetimi.
 * Alt kullanıcı daveti (auth_tokens.invite), yetki matrisi, üye düzenleme/kaldırma.
 * Plan limiti: plans.max_users.
 */
final class TeamController extends Controller
{
    private function membershipOrRedirect(): array
    {
        $membership = MembershipService::forUser((int) Auth::id());
        if ($membership === null) {
            $this->flashError(t('vehicles.no_org'));
            $this->redirect('/panel');
        }
        return $membership;
    }

    private function guardOwner(array $membership): void
    {
        if ((string) $membership['role'] !== 'owner') {
            $this->flashError(t('team.owner_only'));
            $this->redirect('/panel/ekip');
        }
    }

    /** Üye listesi + davet formu + yetki matrisi. */
    public function index(): string
    {
        $membership = $this->membershipOrRedirect();
        $orgId = (int) $membership['organization_id'];
        $members = MembershipService::members($orgId);

        $used = 0;
        foreach ($members as $m) {
            if ((string) $m['status'] !== 'removed') {
                $used++;
            }
        }
        $limits = SubscriptionService::limits($orgId);

        return $this->view('site/team', [
            'title'      => t('team.title'),
            'membership' => $membership,
            'members'    => $members,
            'isOwner'    => (string) $membership['role'] === 'owner',
            'used'       => $used,
            'maxUsers'   => (int) $limits['max_users'],
            'plan'       => (string) $limits['plan'],
        ]);
    }

    /** Yeni üye davet et. */
    public function invite(): void
    {
        $membership = $this->membershipOrRedirect();
        $this->guardOwner($membership);
        $orgId = (int) $membership['organization_id'];
        $backTo = '/panel/ekip';

        $data = $this->validate(
            [
                'email' => mb_strtolower($this->request->string('email')),
                'role'  => $this->request->string('role', 'viewer'),
            ],
            ['email' => 'required|email|max:190', 'role' => 'required|in:manager,viewer'],
            ['email' => t('auth.email'), 'role' => t('team.role')],
            $backTo
        );

        $limits = SubscriptionService::limits($orgId);
        $used = (int) Database::scalar("SELECT COUNT(*) FROM organization_members WHERE organization_id = ? AND status <> 'removed'", [$orgId]);
        if ($used >= (int) $limits['max_users']) {
            $this->flashError(t('team.limit', ['max' => (int) $limits['max_users']]));
            $this->redirect($backTo);
        }

        $permissions = [
            'can_manage_vehicles'  => $this->request->bool('can_manage_vehicles') ? 1 : 0,
            'can_manage_documents' => $this->request->bool('can_manage_documents') ? 1 : 0,
            'can_manage_billing'   => $this->request->bool('can_manage_billing') ? 1 : 0,
        ];
        $now = date('Y-m-d H:i:s');

        $user = Database::first('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [(string) $data['email']]);
        $newUser = false;
        if ($user === null) {
            $userId = Database::insert('users', [
                'full_name'     => Str::limit((string) $data['email'], 60, ''),
                'email'         => (string) $data['email'],
                'password_hash' => password_hash(Str::random(32), PASSWORD_DEFAULT),
                'role'          => 'customer',
                'status'        => 'passive',
                'locale'        => 'tr',
                'created_at'    => $now,
            ]);
            $newUser = true;
        } else {
            $userId = (int) $user['id'];
        }

        $existing = Database::first(
            'SELECT * FROM organization_members WHERE organization_id = ? AND (user_id = ? OR invited_email = ?) LIMIT 1',
            [$orgId, $userId, (string) $data['email']]
        );
        if ($existing !== null && (string) $existing['status'] !== 'removed') {
            $this->flashError(t('team.already_member'));
            $this->redirect($backTo);
        }

        $memberData = [
            'organization_id'      => $orgId,
            'user_id'              => $userId,
            'invited_email'        => (string) $data['email'],
            'role'                 => (string) $data['role'],
            'can_manage_vehicles'  => $permissions['can_manage_vehicles'],
            'can_manage_documents' => $permissions['can_manage_documents'],
            'can_manage_billing'   => $permissions['can_manage_billing'],
            'status'               => 'invited',
            'created_at'           => $now,
        ];
        if ($existing !== null) {
            Database::update('organization_members', $memberData, 'id = :id', ['id' => (int) $existing['id']]);
            $memberId = (int) $existing['id'];
        } else {
            $memberId = Database::insert('organization_members', $memberData);
        }

        // Kullanıcı hesabı aktifse (mevcut müşteri) davet doğrudan kabul sayılır.
        if (!$newUser && (string) ($user['status'] ?? '') === 'active') {
            Database::update('organization_members', ['status' => 'active', 'invited_email' => null], 'id = :id', ['id' => $memberId]);
            $this->notify((string) $data['email'], $membership, false);
            $this->flashSuccess(t('team.added'));
            $this->redirect($backTo);
        }

        $token = Auth::createToken($userId, 'invite', 86400 * 7);
        $this->notify((string) $data['email'], $membership, true, $token);

        $message = t('team.invited');
        // SMTP tanımlı değilse davet bağlantısı gönderilemez; sahibine gösterip kopyalamasını sağla.
        if (!(new Mailer())->isConfigured()) {
            $message .= ' ' . t('team.invite_link_manual', ['link' => url('/davet?token=' . urlencode($token))]);
        }
        $this->flashSuccess($message);
        $this->redirect($backTo);
    }

    /** Davet bağlantısı: hesabı tamamla. */
    public function accept(): string
    {
        $token = (string) $this->request->input('token', '');
        $row = $this->peekInvite($token);
        if ($row === null) {
            return $this->view('site/invite_accept', [
                'title' => t('team.invite_title'),
                'valid' => false,
                'token' => null,
            ]);
        }
        $user = Database::first('SELECT * FROM users WHERE id = ? LIMIT 1', [(int) $row['user_id']]);

        // Mevcut ve aktif kullanıcı: davet zaten kabul edilmiş sayılır, giriş yapması yeterli.
        if ($user !== null && (string) $user['status'] === 'active' && (string) $user['password_hash'] !== '') {
            Auth::consumeToken($token, 'invite');
            $this->activateMembership((int) $row['user_id']);
            $this->flashSuccess(t('team.accept_signin'));
            $this->redirect('/giris');
        }

        return $this->view('site/invite_accept', [
            'title' => t('team.invite_title'),
            'valid' => true,
            'token' => $token,
            'email' => $user === null ? '' : (string) $user['email'],
        ]);
    }

    /** Davet kabulü: ad + şifre belirle, kuruma katıl. */
    public function acceptSubmit(): void
    {
        $token = (string) $this->request->input('token', '');
        $data = $this->validate(
            [
                'full_name' => $this->request->string('full_name'),
                'password'  => (string) $this->request->input('password', ''),
                'password_confirmation' => (string) $this->request->input('password_confirmation', ''),
            ],
            [
                'full_name' => 'required|min:3|max:160',
                'password'  => 'required|password|confirmed|max:100',
            ],
            ['full_name' => t('auth.name'), 'password' => t('auth.password')],
            '/davet?token=' . urlencode($token)
        );

        $user = Auth::consumeToken($token, 'invite');
        if ($user === null) {
            $this->flashError(t('team.invite_invalid'));
            $this->redirect('/giris');
        }
        $userId = (int) $user['id'];
        Database::update('users', [
            'full_name'         => (string) $data['full_name'],
            'password_hash'     => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $userId]);

        $this->activateMembership($userId);
        Auth::login($userId);

        $this->flashSuccess(t('team.welcome'));
        $this->redirect('/panel');
    }

    /** Üye rolü/izinlerini güncelle. */
    public function update(string $memberId): void
    {
        $membership = $this->membershipOrRedirect();
        $this->guardOwner($membership);
        $orgId = (int) $membership['organization_id'];
        $member = Database::first('SELECT * FROM organization_members WHERE id = ? AND organization_id = ? LIMIT 1', [(int) $memberId, $orgId]);
        if ($member === null) {
            $this->flashError(t('team.not_found'));
            $this->redirect('/panel/ekip');
        }
        if ((string) $member['role'] === 'owner') {
            $this->flashError(t('team.owner_locked'));
            $this->redirect('/panel/ekip');
        }

        $role = $this->request->string('role', 'viewer');
        if (!in_array($role, ['manager', 'viewer'], true)) {
            $role = 'viewer';
        }
        Database::update('organization_members', [
            'role'                 => $role,
            'can_manage_vehicles'  => $this->request->bool('can_manage_vehicles') ? 1 : 0,
            'can_manage_documents' => $this->request->bool('can_manage_documents') ? 1 : 0,
            'can_manage_billing'   => $this->request->bool('can_manage_billing') ? 1 : 0,
        ], 'id = :id', ['id' => (int) $memberId]);

        \App\Core\Audit::log('team.update', 'organization_member', (int) $memberId, ['role' => $role]);
        $this->flashSuccess(t('team.updated'));
        $this->redirect('/panel/ekip');
    }

    /** Üyeyi kurumdan kaldır. */
    public function remove(string $memberId): void
    {
        $membership = $this->membershipOrRedirect();
        $this->guardOwner($membership);
        $orgId = (int) $membership['organization_id'];
        $member = Database::first('SELECT * FROM organization_members WHERE id = ? AND organization_id = ? LIMIT 1', [(int) $memberId, $orgId]);
        if ($member === null) {
            $this->flashError(t('team.not_found'));
            $this->redirect('/panel/ekip');
        }
        if ((string) $member['role'] === 'owner') {
            $this->flashError(t('team.owner_locked'));
            $this->redirect('/panel/ekip');
        }
        Database::update('organization_members', ['status' => 'removed'], 'id = :id', ['id' => (int) $memberId]);
        \App\Core\Audit::log('team.remove', 'organization_member', (int) $memberId);
        $this->flashSuccess(t('team.removed'));
        $this->redirect('/panel/ekip');
    }

    private function activateMembership(int $userId): void
    {
        $row = Database::first(
            "SELECT * FROM organization_members WHERE user_id = ? AND status IN ('invited','active') ORDER BY id ASC LIMIT 1",
            [$userId]
        );
        if ($row !== null) {
            Database::update('organization_members', ['status' => 'active', 'invited_email' => null], 'id = :id', ['id' => (int) $row['id']]);
        }
    }

    /** @return array|null Geçerli, kullanılmamış, süresi geçmemiş davet tokenı. */
    private function peekInvite(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return Database::first(
            "SELECT * FROM auth_tokens WHERE token_hash = ? AND type = 'invite' AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
            [hash('sha256', $token)]
        );
    }

    private function notify(string $email, array $membership, bool $needsSetup, string $token = ''): void
    {
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $subject = $needsSetup ? t('team.mail_invite_subject') : t('team.mail_added_subject');
        $body = '<p>' . e($email) . ' ' . e(t('team.mail_body', ['org' => (string) $membership['org_name']])) . '</p>';
        if ($needsSetup) {
            $link = url('/davet?token=' . urlencode($token));
            $body .= '<p><a href="' . e($link) . '">' . e(t('team.mail_cta')) . '</a></p>';
        }
        $body .= '<p>' . e($brand) . '</p>';

        $mailer = new Mailer();
        if ($mailer->isConfigured()) {
            $mailer->send($email, $subject . ' — ' . $brand, $body);
        }
    }
}
