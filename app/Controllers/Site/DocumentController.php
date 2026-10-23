<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Services\DocumentService;
use App\Services\MembershipService;
use App\Services\VehicleService;

/**
 * Müşteri paneli — Araç evrakları.
 * Ekleme/düzenleme/yenileme/iptal ve belge (koçan) yükleme.
 */
final class DocumentController extends Controller
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

    private function guardDocument(array $membership, string $backTo): void
    {
        if (!MembershipService::canManageDocuments($membership)) {
            $this->flashError(t('documents.permission_denied'));
            $this->redirect($backTo);
        }
    }

    /** Aracın kuruma ait olduğunu doğrular. */
    private function vehicleInOrg(int $orgId, int $vehicleId): ?array
    {
        return Database::first(
            'SELECT * FROM vehicles WHERE id = ? AND organization_id = ? AND deleted_at IS NULL LIMIT 1',
            [$vehicleId, $orgId]
        );
    }

    /** Evrağın kuruma ait olduğunu doğrular (araç üzerinden). */
    private function documentInOrg(int $orgId, int $documentId): ?array
    {
        return Database::first(
            'SELECT d.*, v.organization_id, v.plate FROM vehicle_documents d
             JOIN vehicles v ON v.id = d.vehicle_id
             WHERE d.id = ? AND v.organization_id = ? AND d.deleted_at IS NULL LIMIT 1',
            [$documentId, $orgId]
        );
    }

    /** Araca yeni evrak ekle. */
    public function store(string $vehicleId): void
    {
        $membership = $this->membershipOrRedirect();
        $orgId = (int) $membership['organization_id'];
        $vehicle = $this->vehicleInOrg($orgId, (int) $vehicleId);
        if ($vehicle === null) {
            $this->flashError(t('vehicles.not_found'));
            $this->redirect('/panel/araclar');
        }
        $backTo = '/panel/araclar/' . (int) $vehicleId;
        $this->guardDocument($membership, $backTo);

        $this->validate(
            $this->payload(),
            [
                'document_type_id' => 'required|integer',
                'end_date'         => 'required|date',
                'start_date'       => 'nullable|date',
                'cost'             => 'nullable|numeric',
                'policy_no'        => 'nullable|max:80',
                'issuer'           => 'nullable|max:160',
            ],
            ['document_type_id' => t('documents.type'), 'end_date' => t('documents.end')],
            $backTo
        );

        $result = DocumentService::create((int) $vehicleId, $this->payload(), $this->request->files('files'));
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect($backTo);
        }
        $this->flashSuccess(t('documents.created'));
        $this->redirect($backTo);
    }

    /** Evrağı güncelle. */
    public function update(string $id): void
    {
        $membership = $this->membershipOrRedirect();
        $document = $this->documentInOrg((int) $membership['organization_id'], (int) $id);
        if ($document === null) {
            $this->flashError(t('documents.not_found'));
            $this->redirect('/panel/araclar');
        }
        $backTo = '/panel/araclar/' . (int) $document['vehicle_id'];
        $this->guardDocument($membership, $backTo);

        $this->validate(
            $this->payload(),
            [
                'document_type_id' => 'required|integer',
                'end_date'         => 'required|date',
                'start_date'       => 'nullable|date',
                'cost'             => 'nullable|numeric',
                'policy_no'        => 'nullable|max:80',
                'issuer'           => 'nullable|max:160',
            ],
            ['document_type_id' => t('documents.type'), 'end_date' => t('documents.end')],
            $backTo
        );

        $result = DocumentService::update((int) $id, $this->payload(), $this->request->files('files'));
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(t('documents.updated'));
        }
        $this->redirect($backTo);
    }

    /** "Yenilendi" — eski kayıt geçmişe düşer, yeni dönem eklenir. */
    public function renew(string $id): void
    {
        $membership = $this->membershipOrRedirect();
        $document = $this->documentInOrg((int) $membership['organization_id'], (int) $id);
        if ($document === null) {
            $this->flashError(t('documents.not_found'));
            $this->redirect('/panel/araclar');
        }
        $backTo = '/panel/araclar/' . (int) $document['vehicle_id'];
        $this->guardDocument($membership, $backTo);

        $this->validate(
            $this->payload(),
            ['end_date' => 'required|date', 'start_date' => 'nullable|date', 'cost' => 'nullable|numeric'],
            ['end_date' => t('documents.end')],
            $backTo
        );

        $result = DocumentService::renew((int) $id, $this->payload(), $this->request->files('files'));
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(t('documents.renewed'));
        }
        $this->redirect($backTo);
    }

    /** Evrağı kaldır. */
    public function destroy(string $id): void
    {
        $membership = $this->membershipOrRedirect();
        $document = $this->documentInOrg((int) $membership['organization_id'], (int) $id);
        if ($document === null) {
            $this->flashError(t('documents.not_found'));
            $this->redirect('/panel/araclar');
        }
        $backTo = '/panel/araclar/' . (int) $document['vehicle_id'];
        $this->guardDocument($membership, $backTo);

        $result = DocumentService::cancel((int) $id);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(t('documents.cancelled'));
        }
        $this->redirect($backTo);
    }

    /** Evrak belgesi sil. */
    public function deleteFile(string $id, string $fileId): void
    {
        $membership = $this->membershipOrRedirect();
        $document = $this->documentInOrg((int) $membership['organization_id'], (int) $id);
        if ($document === null) {
            $this->flashError(t('documents.not_found'));
            $this->redirect('/panel/araclar');
        }
        $backTo = '/panel/araclar/' . (int) $document['vehicle_id'];
        $this->guardDocument($membership, $backTo);

        $result = DocumentService::deleteFile((int) $id, (int) $fileId);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(t('documents.file_deleted'));
        }
        $this->redirect($backTo);
    }

    /** Evrakı .ics (iCalendar) olarak indir — takvime ekleme. */
    public function ics(string $id): string
    {
        $membership = $this->membershipOrRedirect();
        $document = $this->documentInOrg((int) $membership['organization_id'], (int) $id);
        if ($document === null) {
            http_response_code(404);
            return '';
        }
        $vehicle = Database::first('SELECT * FROM vehicles WHERE id = ? LIMIT 1', [(int) $document['vehicle_id']]) ?? [];
        $ics = DocumentService::buildIcs($document, $vehicle);

        if (!headers_sent()) {
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="evrak-' . (int) $id . '.ics"');
            header('Content-Length: ' . strlen($ics));
            header('Cache-Control: no-store');
        }
        return $ics;
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'document_type_id' => $this->request->int('document_type_id'),
            'issuer'           => $this->request->string('issuer'),
            'policy_no'        => $this->request->string('policy_no'),
            'start_date'       => $this->request->string('start_date'),
            'end_date'         => $this->request->string('end_date'),
            'cost'             => $this->request->string('cost'),
            'currency'         => $this->request->string('currency'),
            'reminder_offsets' => $this->request->string('reminder_offsets'),
            'notes'            => $this->request->string('notes'),
        ];
    }
}
