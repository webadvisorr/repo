<?php
declare(strict_types=1);

namespace App\Core;

/** Kim, ne zaman, neyi degistirdi? Guvenlik ve destek icin degistirilemez kayit. */
final class Audit
{
    public static function log(string $action, ?string $entity = null, string|int|null $entityId = null, array $meta = []): void
    {
        try {
            $request = new Request();
            Database::insert('audit_logs', [
                'user_id'    => Auth::id(),
                'action'     => Str::limit($action, 60, ''),
                'entity'     => $entity,
                'entity_id'  => $entityId === null ? null : (string) $entityId,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta_json'  => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Audit kaydi yazilamadi: ' . $e->getMessage());
        }
    }
}
