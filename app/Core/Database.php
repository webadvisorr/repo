<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO tabanlı veritabanı katmanı.
 * TÜM sorgular hazırlanmış ifade (prepared statement) ile çalışır -> SQL injection koruması.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $queryCount = 0;
    private static array $queryLog = [];

    public static function connect(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('db.host', '127.0.0.1');
        $port = (int) Config::get('db.port', 3306);
        $name = (string) Config::get('db.database', '');
        $user = (string) Config::get('db.username', '');
        $pass = (string) Config::get('db.password', '');
        $unixSocket = (string) Config::get('db.socket', '');

        $dsn = $unixSocket !== ''
            ? "mysql:unix_socket={$unixSocket};dbname={$name};charset=utf8mb4"
            : "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION', time_zone='+00:00'",
            ]);
        } catch (PDOException $e) {
            Logger::error('DB baglanti hatasi: ' . $e->getMessage());
            throw new PDOException('Veritabanina baglanilamadi. Ayarlari kontrol edin.', 0, $e);
        }

        return self::$pdo;
    }

    /** @param array<string|int,mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::connect();
        $start = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        self::$queryCount++;
        self::$queryLog[] = round((microtime(true) - $start) * 1000, 2) . 'ms ' . $sql;
        return $stmt;
    }

    /** @param array<string|int,mixed> $params */
    public static function select(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $place = array_map(static fn ($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO `' . self::table($table) . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $place) . ')';
        self::run($sql, $data);
        return (int) self::connect()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = "`{$c}` = :{$c}";
        }
        $sql = 'UPDATE `' . self::table($table) . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge($data, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run('DELETE FROM `' . self::table($table) . '` WHERE ' . $where, $params)->rowCount();
    }

    /** Tablo adı güvenliği: sadece harf, rakam ve alt çizgi. */
    public static function table(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Gecersiz tablo adi: ' . $name);
        }
        return $name;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connect();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function stats(): array
    {
        return ['queries' => self::$queryCount, 'log' => self::$queryLog];
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$queryCount = 0;
        self::$queryLog = [];
    }
}
