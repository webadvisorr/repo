<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;

/** Sistem: veritabanı güncellemeleri (migration), cron durumu, ortam bilgisi. */
final class SystemController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $tables = [];
        try {
            $tables = Database::select(
                'SELECT table_name AS name, table_rows AS rows_count, ROUND((data_length + index_length)/1024) AS size_kb
                 FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name ASC',
                [(string) Config::get('db.database', '')]
            );
        } catch (\Throwable) {
        }

        return $this->view('admin/system', [
            'title'         => t('admin.system'),
            'pending'       => Migrator::pending(),
            'applied'       => Migrator::applied(),
            'phpVersion'    => PHP_VERSION,
            'mysqlVersion'  => (string) Database::scalar('SELECT VERSION()'),
            'tables'        => $tables,
            'cronToken'     => (string) Config::get('security.cron_token', ''),
            'lastCron'      => $this->lastCron(),
            'diskFree'      => @disk_free_space(BASE_PATH) ?: 0,
            'storageSize'   => $this->dirSize(BASE_PATH . '/storage/uploads'),
            'logSize'       => $this->dirSize(BASE_PATH . '/storage/logs'),
            'reminders'     => \App\Services\ReminderService::stats(),
            'reminderReady' => \App\Services\ReminderService::enabled(),
            'pendingQueue'  => \App\Services\ReminderService::pendingCount(),
            'sentToday'     => \App\Services\ReminderService::sentToday(),
            'dailyLimit'    => \App\Services\ReminderService::dailyLimit(),
            'templateCount' => (int) Database::scalar('SELECT COUNT(*) FROM mail_templates'),
        ]);
    }

    public function migrate(): void
    {
        $result = Migrator::migrate();

        if ($result['ok']) {
            $count = count($result['executed']);
            $this->flashSuccess($count === 0
                ? 'Veritabanı güncel — uygulanacak güncelleme yok.'
                : "{$count} güncelleme uygulandı: " . implode(', ', $result['executed']));
        } else {
            $this->flashError('Güncelleme sırasında hata: ' . implode(' | ', $result['errors']));
        }
        if ($result['skipped'] !== []) {
            $this->flashError('Atlanan (zaten var olan) değişiklikler: ' . implode(' | ', array_slice($result['skipped'], 0, 3)));
        }
        $this->redirect('/yonetim/sistem');
    }

    /** Abonelik süresi geçenleri kapatır, ödeme taleplerini kapatır ve hatırlatma motorunu tetikler (cron). */
    public function runCron(): void
    {
        $result = \App\Services\CronService::runDaily();
        \App\Core\Audit::log('system.cron_manual', 'system', null, [
            'created' => (int) $result['reminders']['created'],
            'sent'    => (int) $result['reminders']['sent'],
            'failed'  => (int) $result['reminders']['failed'],
        ]);
        $this->flashSuccess('Cron çalıştı. ' . \App\Services\CronService::summary($result));
        $this->redirect('/yonetim/sistem');
    }

    private function lastCron(): ?string
    {
        try {
            return Database::scalar("SELECT created_at FROM audit_logs WHERE action LIKE 'system.cron%' ORDER BY id DESC LIMIT 1");
        } catch (\Throwable) {
            return null;
        }
    }

    private function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += (int) $file->getSize();
        }
        return $size;
    }
}
