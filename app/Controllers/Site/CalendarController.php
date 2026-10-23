<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Translator;
use App\Services\MembershipService;
use App\Services\VehicleService;

/**
 * Müşteri paneli — aylık evrak takvimi.
 * Hangi araçta hangi evrak hangi gün bitiyor, tek bakışta görünür.
 */
final class CalendarController extends Controller
{
    public function index(): string
    {
        $membership = MembershipService::forUser((int) Auth::id());
        if ($membership === null) {
            $this->flashError(t('vehicles.no_org'));
            $this->redirect('/panel');
        }
        $orgId = (int) $membership['organization_id'];

        $monthParam = (string) $this->request->input('ay', '');
        $month = preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1 ? $monthParam : date('Y-m');
        $firstTs = strtotime($month . '-01');
        $year = (int) date('Y', $firstTs);
        $monthNum = (int) date('n', $firstTs);

        $first = date('Y-m-01', $firstTs);
        $last = date('Y-m-t', $firstTs);

        $rows = Database::select(
            "SELECT d.id, d.end_date, d.start_date, d.status, d.issuer, d.policy_no,
                    v.id AS vehicle_id, v.plate, dt.labels_json AS type_labels, dt.color AS type_color
             FROM vehicle_documents d
             JOIN vehicles v ON v.id = d.vehicle_id
             JOIN document_types dt ON dt.id = d.document_type_id
             WHERE v.organization_id = ? AND v.deleted_at IS NULL AND d.deleted_at IS NULL
               AND d.status = 'active' AND d.end_date BETWEEN ? AND ?
             ORDER BY d.end_date ASC, v.plate ASC",
            [$orgId, $first, $last]
        );

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['end_date']][] = $row;
        }

        // Ay başının haftanın günü (1=Pazartesi ... 7=Pazar)
        $leading = (int) date('N', $firstTs) - 1;
        $daysInMonth = (int) date('t', $firstTs);

        $prev = date('Y-m', strtotime($month . '-01 -1 month'));
        $next = date('Y-m', strtotime($month . '-01 +1 month'));

        return $this->view('site/calendar', [
            'title'        => t('calendar.title'),
            'membership'   => $membership,
            'month'        => $month,
            'monthLabel'   => $this->monthLabel($year, $monthNum),
            'leading'      => $leading,
            'daysInMonth'  => $daysInMonth,
            'byDay'        => $byDay,
            'today'        => date('Y-m-d'),
            'currentMonth' => date('Y-m'),
            'prev'         => $prev,
            'next'         => $next,
            'total'        => count($rows),
        ]);
    }

    private function monthLabel(int $year, int $month): string
    {
        // Ay adları dile göre: çeviri anahtarı yoksa sayısal gösterime düş.
        $key = 'calendar.month_' . $month;
        $label = t($key);
        return $label === $key ? ($month . '/' . $year) : ($label . ' ' . $year);
    }
}
