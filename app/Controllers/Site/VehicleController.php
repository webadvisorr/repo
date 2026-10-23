<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Settings;
use App\Services\CreditService;
use App\Services\DocumentService;
use App\Services\MembershipService;
use App\Services\SubscriptionService;
use App\Services\VehiclePricing;
use App\Services\VehicleService;

/**
 * Müşteri paneli — Araç yönetimi.
 * Plan limiti (max_vehicles) ve üyelik yetkileri (can_manage_vehicles) burada korunur.
 */
final class VehicleController extends Controller
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

    private function guardManage(array $membership, string $backTo): void
    {
        if (!MembershipService::canManageVehicles($membership)) {
            $this->flashError(t('vehicles.permission_denied'));
            $this->redirect($backTo);
        }
    }

    /** Araç listesi + filtreler + plan limiti. */
    public function index(): string
    {
        $membership = $this->membershipOrRedirect();
        $orgId = (int) $membership['organization_id'];

        $filters = [
            'type_id' => $this->request->int('tur'),
            'status'  => $this->request->string('durum'),
            'q'       => $this->request->string('q'),
        ];

        $vehicles = VehicleService::forOrganization($orgId, array_filter($filters, static fn ($v): bool => $v !== '' && $v !== 0));
        $limits = SubscriptionService::limits($orgId);

        return $this->view('site/vehicles', [
            'title'        => t('vehicles.title'),
            'membership'   => $membership,
            'vehicles'     => $vehicles,
            'types'        => VehicleService::types(),
            'filters'      => $filters,
            'limits'       => $limits,
            'used'         => VehicleService::count($orgId),
            'canManage'    => MembershipService::canManageVehicles($membership),
            'canAddResult' => SubscriptionService::canAddVehicle($orgId),
        ]);
    }

    /** Yeni araç formu. */
    public function create(): string
    {
        $membership = $this->membershipOrRedirect();
        $this->guardManage($membership, '/panel/araclar');
        $orgId = (int) $membership['organization_id'];
        $canAdd = SubscriptionService::canAddVehicle($orgId);
        if (!$canAdd['ok']) {
            $this->flashError((string) $canAdd['error']);
            $this->redirect('/panel/araclar');
        }

        return $this->view('site/vehicle_form', [
            'title'      => t('vehicles.add'),
            'membership' => $membership,
            'vehicle'    => null,
            'types'      => VehicleService::types(),
            'formAction' => url('/panel/araclar'),
            'vehicleFee' => SubscriptionService::nextVehicleFee($orgId),
        ]);
    }

    /** Araç kaydet. */
    public function store(): void
    {
        $membership = $this->membershipOrRedirect();
        $this->guardManage($membership, '/panel/araclar');
        $orgId = (int) $membership['organization_id'];

        $canAdd = SubscriptionService::canAddVehicle($orgId);
        if (!$canAdd['ok']) {
            $this->flashError((string) $canAdd['error']);
            $this->redirect('/panel/araclar');
        }

        $data = $this->validate(
            $this->payload(),
            [
                'plate'           => 'required|plate|max:24',
                'vehicle_type_id' => 'required|integer',
                'brand'           => 'nullable|max:80',
                'model'           => 'nullable|max:80',
                'vin'             => 'nullable|max:40',
                'engine_no'       => 'nullable|max:40',
                'color'           => 'nullable|max:40',
                'driver_name'     => 'nullable|max:120',
            ],
            ['plate' => t('vehicles.plate'), 'vehicle_type_id' => t('vehicles.type')],
            '/panel/araclar/yeni'
        );

        // Standart pakette ilk araçtan sonraki her araç için kademeli ücret kredi
        // bakiyesinden düşülür. Ücretsiz deneme paketinde ek ücret yoktur.
        $fee = SubscriptionService::nextVehicleFee($orgId);
        $charged = null;
        if ($fee !== null) {
            if (!$fee['enough']) {
                $this->flashError('Bu araç için ' . VehiclePricing::money((float) $fee['price'], (string) $fee['currency'])
                    . ' gerekiyor. Kredi bakiyeniz yetersiz; ödeme merkezinden kredi yükleyebilirsiniz.');
                $this->redirect('/panel/odeme');
            }
            $charged = CreditService::deduct(
                $orgId,
                (float) $fee['price'],
                'subscription',
                $fee['no'] . '. araç kaydı (' . ($fee['discount'] > 0 ? '%' . $fee['discount'] . ' indirimli' : 'paket fiyatı') . ')',
                'vehicle-slot',
                (int) Auth::id()
            );
            if (!$charged['ok']) {
                $this->flashError((string) $charged['error']);
                $this->redirect('/panel/odeme');
            }
        }

        $result = VehicleService::save($orgId, null, $data);
        if (!$result['ok']) {
            if ($charged !== null && !empty($charged['ok'])) {
                CreditService::add($orgId, (float) $fee['price'], 'refund', 'Araç kaydı tamamlanamadı', 'vehicle-slot-refund', (int) Auth::id());
            }
            $this->flashError((string) $result['error']);
            $this->redirect('/panel/araclar/yeni');
        }
        $this->flashSuccess(!empty($result['revived']) ? t('vehicles.revived') : t('vehicles.created'));
        $this->redirect('/panel/araclar/' . (int) $result['id']);
    }

    /** Araç detayı: evraklar + trafik ışığı. */
    public function show(string $id): string
    {
        $membership = $this->membershipOrRedirect();
        $orgId = (int) $membership['organization_id'];
        $vehicle = VehicleService::find($orgId, (int) $id);
        if ($vehicle === null) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => t('http.404')]);
        }

        $documents = DocumentService::forVehicle((int) $vehicle['id']);
        $limits = SubscriptionService::limits($orgId);
        $docCount = 0;
        foreach ($documents as $doc) {
            if ((string) $doc['status'] !== 'cancelled') {
                $docCount++;
            }
        }

        return $this->view('site/vehicle_show', [
            'title'      => (string) $vehicle['plate'],
            'membership' => $membership,
            'vehicle'    => $vehicle,
            'documents'  => $documents,
            'docTypes'   => DocumentService::types((int) $vehicle['vehicle_type_id']),
            'currency'   => (string) Settings::get('payment.currency', 'TRY'),
            'maxDocs'    => (int) $limits['max_documents_per_vehicle'],
            'docCount'   => $docCount,
            'canManageVehicles'  => MembershipService::canManageVehicles($membership),
            'canManageDocuments' => MembershipService::canManageDocuments($membership),
        ]);
    }

    /** Düzenleme formu. */
    public function edit(string $id): string
    {
        $membership = $this->membershipOrRedirect();
        $this->guardManage($membership, '/panel/araclar');
        $vehicle = VehicleService::find((int) $membership['organization_id'], (int) $id);
        if ($vehicle === null) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => t('http.404')]);
        }

        return $this->view('site/vehicle_form', [
            'title'      => t('vehicles.edit'),
            'membership' => $membership,
            'vehicle'    => $vehicle,
            'types'      => VehicleService::types(),
            'formAction' => url('/panel/araclar/' . (int) $vehicle['id']),
        ]);
    }

    /** Araç güncelle. */
    public function update(string $id): void
    {
        $membership = $this->membershipOrRedirect();
        $this->guardManage($membership, '/panel/araclar');
        $orgId = (int) $membership['organization_id'];
        $vehicle = VehicleService::find($orgId, (int) $id);
        if ($vehicle === null) {
            $this->flashError(t('vehicles.not_found'));
            $this->redirect('/panel/araclar');
        }

        $data = $this->validate(
            $this->payload(),
            [
                'plate'           => 'required|plate|max:24',
                'vehicle_type_id' => 'required|integer',
                'brand'           => 'nullable|max:80',
                'model'           => 'nullable|max:80',
                'vin'             => 'nullable|max:40',
                'engine_no'       => 'nullable|max:40',
                'color'           => 'nullable|max:40',
                'driver_name'     => 'nullable|max:120',
            ],
            ['plate' => t('vehicles.plate'), 'vehicle_type_id' => t('vehicles.type')],
            '/panel/araclar/' . (int) $id . '/duzenle'
        );

        $result = VehicleService::save($orgId, (int) $id, $data);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect('/panel/araclar/' . (int) $id . '/duzenle');
        }
        $this->flashSuccess(t('vehicles.updated'));
        $this->redirect('/panel/araclar/' . (int) $id);
    }

    /** Yazdırılabilir evrak dökümü raporu (tarayıcıdan PDF olarak kaydedilebilir). */
    public function report(string $id): string
    {
        $membership = $this->membershipOrRedirect();
        $orgId = (int) $membership['organization_id'];
        $vehicle = VehicleService::find($orgId, (int) $id);
        if ($vehicle === null) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => t('http.404')]);
        }

        return $this->view('site/report_vehicle', [
            'title'      => t('report.title') . ' — ' . (string) $vehicle['plate'],
            'membership' => $membership,
            'vehicle'    => $vehicle,
            'documents'  => DocumentService::forVehicle((int) $vehicle['id']),
            'generated'  => date('d.m.Y H:i'),
        ], null);
    }

    /** Aracı arşivle. */
    public function archive(string $id): void
    {
        $membership = $this->membershipOrRedirect();
        $this->guardManage($membership, '/panel/araclar');
        $result = VehicleService::archive((int) $membership['organization_id'], (int) $id);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(t('vehicles.archived'));
        }
        $this->redirect('/panel/araclar');
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'plate'           => $this->request->string('plate'),
            'vehicle_type_id' => $this->request->int('vehicle_type_id'),
            'brand'           => $this->request->string('brand'),
            'model'           => $this->request->string('model'),
            'model_year'      => $this->request->input('model_year'),
            'vin'             => $this->request->string('vin'),
            'engine_no'       => $this->request->string('engine_no'),
            'color'           => $this->request->string('color'),
            'odometer'        => $this->request->input('odometer'),
            'driver_name'     => $this->request->string('driver_name'),
            'notes'           => $this->request->string('notes'),
            'status'          => $this->request->string('status', 'active'),
        ];
    }
}
