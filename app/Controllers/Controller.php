<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

abstract class Controller
{
    protected Request $request;

    /** Panel görünümü varsayılan olarak admin layout'u kullanır. */
    protected string $layout = 'layouts/site';

    public function __construct()
    {
        $this->request = new Request();
    }

    protected function view(string $template, array $data = [], ?string $layout = null): string
    {
        return View::render($template, $data, $layout ?? $this->layout);
    }

    protected function redirect(string $to): never
    {
        \App\Core\Response::redirect($to);
    }

    /** Form doğrulama: hata varsa eski değerlerle geri döner. */
    protected function validate(array $data, array $rules, array $labels = [], string $backTo = '/'): array
    {
        $validator = new Validator();
        if (!$validator->validate($data, $rules, $labels)) {
            Session::put('_errors', $validator->errors());
            Session::put('_old', $data);
            Flash::error($validator->firstError() ?? t('common.error'));
            $this->redirect($backTo);
        }
        Session::forget('_errors');
        Session::forget('_old');
        return $data;
    }

    protected function flashSuccess(string $message): void
    {
        Flash::success($message);
    }

    protected function flashError(string $message): void
    {
        Flash::error($message);
    }

    protected function csrfToken(): string
    {
        return Csrf::token();
    }
}
