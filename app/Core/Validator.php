<?php
declare(strict_types=1);

namespace App\Core;

/** Sunucu tarafi dogrulama. Hatali veri asla veritabanina yazilmaz. */
final class Validator
{
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules  'field' => 'required|email|max:190'
     * @param array<string,string> $labels 'field' => 'E-posta'
     */
    public function validate(array $data, array $rules, array $labels = []): bool
    {
        $this->errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $label = $labels[$field] ?? $field;
            foreach (explode('|', $ruleString) as $rule) {
                if ($rule === '') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($name, $param, $field, $label, $value, $data);
            }
        }
        return $this->errors === [];
    }

    private function applyRule(string $rule, ?string $param, string $field, string $label, mixed $value, array $data): void
    {
        $isEmpty = $value === null || (is_string($value) && trim($value) === '') || $value === [];

        switch ($rule) {
            case 'required':
                if ($isEmpty) {
                    $this->add($field, t('validation.required', ['field' => $label]));
                }
                return;
            case 'nullable':
                return;
            case 'email':
                if (!$isEmpty && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->add($field, t('validation.email', ['field' => $label]));
                }
                return;
            case 'url':
                if (!$isEmpty && filter_var((string) $value, FILTER_VALIDATE_URL) === false) {
                    $this->add($field, t('validation.url', ['field' => $label]));
                }
                return;
            case 'numeric':
                if (!$isEmpty && !is_numeric($value)) {
                    $this->add($field, t('validation.numeric', ['field' => $label]));
                }
                return;
            case 'integer':
                if (!$isEmpty && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->add($field, t('validation.integer', ['field' => $label]));
                }
                return;
            case 'min':
                if (!$isEmpty && mb_strlen((string) $value) < (int) $param) {
                    $this->add($field, t('validation.min', ['field' => $label, 'min' => $param]));
                }
                return;
            case 'max':
                if (!$isEmpty && mb_strlen((string) $value) > (int) $param) {
                    $this->add($field, t('validation.max', ['field' => $label, 'max' => $param]));
                }
                return;
            case 'date':
                if (!$isEmpty && strtotime((string) $value) === false) {
                    $this->add($field, t('validation.date', ['field' => $label]));
                }
                return;
            case 'password':
                if (!$isEmpty && mb_strlen((string) $value) < 8) {
                    $this->add($field, t('validation.password', ['field' => $label]));
                }
                return;
            case 'confirmed':
                if (($data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->add($field, t('validation.confirmed', ['field' => $label]));
                }
                return;
            case 'in':
                $allowed = explode(',', (string) $param);
                if (!$isEmpty && !in_array((string) $value, $allowed, true)) {
                    $this->add($field, t('validation.in', ['field' => $label]));
                }
                return;
            case 'phone':
                if (!$isEmpty && !preg_match('/^[0-9+()\s\-]{7,25}$/', (string) $value)) {
                    $this->add($field, t('validation.phone', ['field' => $label]));
                }
                return;
            case 'plate':
                // KKTC/TC plaka benzeri: harf, rakam, bosluk, tire
                if (!$isEmpty && !preg_match('/^[A-Z0-9\s\-]{2,16}$/u', mb_strtoupper((string) $value))) {
                    $this->add($field, t('validation.plate', ['field' => $label]));
                }
                return;
            case 'accepted':
                if (empty($value)) {
                    $this->add($field, t('validation.accepted', ['field' => $label]));
                }
                return;
            case 'unique':
                // unique:tablo,kolon[,yoksayilacakId]
                $parts = explode(',', (string) $param);
                if (count($parts) >= 2 && !$isEmpty) {
                    $table = Database::table(trim($parts[0]));
                    $column = preg_match('/^[a-zA-Z0-9_]+$/', trim($parts[1])) ? trim($parts[1]) : null;
                    if ($column === null) {
                        return;
                    }
                    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
                    $params = [$value];
                    if (isset($parts[2]) && is_numeric($parts[2])) {
                        $sql .= ' AND id <> ?';
                        $params[] = (int) $parts[2];
                    }
                    if ((int) Database::scalar($sql, $params) > 0) {
                        $this->add($field, t('validation.unique', ['field' => $label]));
                    }
                }
                return;
            case 'exists':
                $parts = explode(',', (string) $param);
                if (count($parts) >= 2 && !$isEmpty) {
                    $table = Database::table(trim($parts[0]));
                    $column = preg_match('/^[a-zA-Z0-9_]+$/', trim($parts[1])) ? trim($parts[1]) : null;
                    if ($column !== null && (int) Database::scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?", [$value]) === 0) {
                        $this->add($field, t('validation.exists', ['field' => $label]));
                    }
                }
                return;
            default:
                Logger::warning('Bilinmeyen dogrulama kurali: ' . $rule);
        }
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return (string) $messages[0];
            }
        }
        return null;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }
}
