<?php
declare(strict_types=1);

namespace App\Core;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        $bag = (array) Session::get('_flash', []);
        $bag[] = ['type' => $type, 'message' => $message];
        Session::put('_flash', $bag);
    }

    public static function success(string $message): void { self::set('success', $message); }
    public static function error(string $message): void   { self::set('error', $message); }
    public static function info(string $message): void    { self::set('info', $message); }

    /** @return array<int,array{type:string,message:string}> */
    public static function pull(): array
    {
        $bag = (array) Session::get('_flash', []);
        Session::forget('_flash');
        return $bag;
    }
}
