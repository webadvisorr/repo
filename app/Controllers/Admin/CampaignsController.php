<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Mailer;
use App\Services\CampaignService;
use App\Services\ReminderService;

/**
 * Kampanya / reklam maili (Faz 4).
 *
 * Hedef kitle seçilir, içerik yazılır; test gönderimi yapılır ve kampanya
 * mevcut bildirim kuyruğuna alınır (yeniden deneme, günlük limit ve açılma
 * takibi hatırlatma motoruyla paylaşılır).
 */
final class CampaignsController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $campaigns = CampaignService::all();
        $rows = [];
        $totals = ['recipients' => 0, 'sent' => 0, 'opened' => 0, 'failed' => 0];
        foreach ($campaigns as $campaign) {
            $stats = CampaignService::stats((int) $campaign['id']);
            $rows[] = ['campaign' => $campaign, 'stats' => $stats];
            $totals['recipients'] += $stats['total'];
            $totals['sent'] += $stats['sent'];
            $totals['opened'] += $stats['opened'];
            $totals['failed'] += $stats['failed'];
        }

        return $this->view('admin/campaigns', [
            'title'      => t('admin.campaigns'),
            'rows'       => $rows,
            'totals'     => $totals,
            'smtpReady'  => (new Mailer())->isConfigured(),
            'queue'      => ReminderService::pendingCount(),
            'dailyLimit' => ReminderService::dailyLimit(),
            'sentToday'  => ReminderService::sentToday(),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/campaign_form', [
            'title'    => 'Yeni kampanya',
            'campaign' => null,
            'audience' => CampaignService::normalizeAudience([]),
            'options'  => $this->formOptions(),
            'preview'  => null,
        ]);
    }

    public function edit(string $id): string
    {
        $campaign = $this->findOrRedirect((int) $id);
        $audience = CampaignService::audienceOf($campaign);

        return $this->view('admin/campaign_form', [
            'title'    => 'Kampanyayı düzenle',
            'campaign' => $campaign,
            'audience' => $audience,
            'options'  => $this->formOptions(),
            'stats'    => CampaignService::stats((int) $campaign['id']),
            'preview'  => null,
        ]);
    }

    public function show(string $id): string
    {
        $campaign = $this->findOrRedirect((int) $id);

        return $this->view('admin/campaign_show', [
            'title'      => (string) $campaign['name'],
            'campaign'   => $campaign,
            'audience'   => CampaignService::audienceOf($campaign),
            'stats'      => CampaignService::stats((int) $campaign['id']),
            'recipients' => CampaignService::recipients((int) $campaign['id'], 200),
            'smtpReady'  => (new Mailer())->isConfigured(),
            'queue'      => ReminderService::pendingCount(),
        ]);
    }

    /** Kaydet (yeni veya güncelleme) → detay sayfasına döner. */
    public function save(): void
    {
        $id = (int) $this->request->input('campaign_id', 0) ?: null;
        $backTo = $id === null ? '/yonetim/kampanya/yeni' : '/yonetim/kampanya/' . $id . '/duzenle';

        $data = $this->validate(
            [
                'name'       => trim((string) $this->request->string('name', '')),
                'subject'    => trim((string) $this->request->string('subject', '')),
                'body_html'  => (string) $this->request->input('body_html', ''),
            ],
            ['name' => 'required|max:160', 'subject' => 'required|max:255', 'body_html' => 'required'],
            ['name' => 'Kampanya adı', 'subject' => 'Konu', 'body_html' => 'İçerik'],
            $backTo
        );

        $result = CampaignService::save($id, [
            'name'         => $data['name'],
            'subject'      => $data['subject'],
            'body_html'    => $data['body_html'],
            'audience'     => $this->audiencePayload(),
            'scheduled_at' => (string) $this->request->input('scheduled_at', ''),
        ]);

        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect($backTo);
        }

        $this->flashSuccess('Kampanya kaydedildi.');
        $this->redirect('/yonetim/kampanya/' . (int) $result['id']);
    }

    /** Hedef kitleyi kuyruğa al (zamanlanmış gönderim için). */
    public function queueOnly(): void
    {
        $campaign = $this->findOrRedirect((int) $this->request->input('campaign_id', 0));
        $result = CampaignService::queue((int) $campaign['id']);

        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess('Kuyruğa alındı — alıcı: ' . $result['total'] . ' · yeni kayıt: ' . $result['queued']
                . '. Gönderim kuyruğu işlendiğinde e-postalar çıkar.');
        }
        $this->redirect('/yonetim/kampanya/' . (int) $campaign['id']);
    }

    /** Kuyruğa al ve hemen gönder. */
    public function send(): void
    {
        $campaign = $this->findOrRedirect((int) $this->request->input('campaign_id', 0));
        $result = CampaignService::sendNow((int) $campaign['id']);

        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect('/yonetim/kampanya/' . (int) $campaign['id']);
        }

        $dispatch = (array) $result['dispatch'];
        if (isset($dispatch['error']) && $dispatch['error'] !== null) {
            $this->flashError((string) $dispatch['error']);
        } else {
            $this->flashSuccess('Kampanya gönderildi — alıcı: ' . $result['queued']
                . ' · gönderilen: ' . (int) $dispatch['sent']
                . ' · hata: ' . (int) $dispatch['failed']
                . ' · kuyrukta kalan: ' . (int) $dispatch['remaining']);
        }
        $this->redirect('/yonetim/kampanya/' . (int) $campaign['id']);
    }

    /** Test gönderimi (kuyruğa girmez). */
    public function test(): void
    {
        $campaign = $this->findOrRedirect((int) $this->request->input('campaign_id', 0));
        $to = trim((string) $this->request->input('test_email', ''));

        $result = CampaignService::test($campaign, $to);
        if ($result['ok']) {
            $this->flashSuccess('Test e-postası gönderildi: ' . $to);
        } else {
            $this->flashError((string) $result['error']);
        }
        $this->redirect('/yonetim/kampanya/' . (int) $campaign['id'] . '/duzenle');
    }

    /** Kaydetmeden önizleme (form üzerinden). */
    public function preview(): string
    {
        $id = (int) $this->request->input('campaign_id', 0) ?: null;
        $campaign = $id === null ? null : CampaignService::find($id);

        $rendered = CampaignService::render(
            (string) $this->request->input('subject', ''),
            (string) $this->request->input('body_html', ''),
            CampaignService::varsFor([
                'full_name' => 'Örnek Kullanıcı',
                'email'     => (string) \App\Core\Settings::get('brand.email', 'ornek@ornek.test'),
                'org_name'  => 'Örnek Kurum',
                'plan_name' => 'Profesyonel',
            ])
        );

        return $this->view('admin/campaign_preview', [
            'title'    => 'Kampanya önizlemesi',
            'rendered' => $rendered,
            'campaign' => $campaign,
        ]);
    }

    public function cancel(): void
    {
        $campaign = $this->findOrRedirect((int) $this->request->input('campaign_id', 0));
        $result = CampaignService::cancel((int) $campaign['id']);

        if ($result['ok']) {
            $this->flashSuccess('Kampanya iptal edildi — kuyruktan çıkarılan bildirim: ' . (int) ($result['cancelled'] ?? 0));
        } else {
            $this->flashError((string) $result['error']);
        }
        $this->redirect('/yonetim/kampanya/' . (int) $campaign['id']);
    }

    public function destroy(): void
    {
        $campaign = $this->findOrRedirect((int) $this->request->input('campaign_id', 0));
        $result = CampaignService::destroy((int) $campaign['id']);

        if ($result['ok']) {
            $this->flashSuccess('Kampanya silindi.');
            $this->redirect('/yonetim/kampanya');
        }
        $this->flashError((string) $result['error']);
        $this->redirect('/yonetim/kampanya/' . (int) $campaign['id']);
    }

    /** @return array{plan_key:string,org_id:int,locale:string,only_marketing_opt_in:bool} */
    private function audiencePayload(): array
    {
        return CampaignService::normalizeAudience([
            'plan_key'              => (string) $this->request->input('audience_plan_key', ''),
            'org_id'                => (int) $this->request->input('audience_org_id', 0),
            'locale'                => (string) $this->request->input('audience_locale', ''),
            'only_marketing_opt_in' => $this->request->input('audience_optin') !== null,
        ]);
    }

    /** @return array{plans:array,organizations:array,counts:array} */
    private function formOptions(): array
    {
        $payload = $this->request->method() === 'POST' ? $this->audiencePayload() : null;
        $audience = $payload ?? CampaignService::normalizeAudience([]);

        return [
            'plans'         => CampaignService::plans(),
            'organizations' => CampaignService::organizations(),
            'all'           => CampaignService::audienceCount([]),
            'current'       => CampaignService::audienceCount($audience),
            'audience'      => $audience,
        ];
    }

    private function findOrRedirect(int $id): array
    {
        $campaign = $id > 0 ? CampaignService::find($id) : null;
        if ($campaign === null) {
            $this->flashError('Kampanya bulunamadı.');
            $this->redirect('/yonetim/kampanya');
        }
        return $campaign;
    }
}
