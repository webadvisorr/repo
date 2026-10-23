#!/usr/bin/env python3
"""lang/<locale>.php icindeki eksik anahtarlari Google cevirisi ile tamamlar.

Kullanim: python3 .freebuff/i18n_missing_keys.py [locale ...]
"""
import json, os, re, subprocess, sys, time, urllib.parse, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHP = os.path.join(os.environ.get('HOME', ''), 'bin/php/php')
LOCALES = sys.argv[1:] or ['ru', 'ar', 'fa', 'el']


def flat(array, prefix=''):
    out = {}
    for key, value in array.items():
        path = f'{prefix}.{key}' if prefix else str(key)
        if isinstance(value, dict):
            out.update(flat(value, path))
        else:
            out[path] = value
    return out


def load_map(locale):
    out = subprocess.check_output(
        [PHP, '-r', 'echo json_encode(require $argv[1], JSON_UNESCAPED_UNICODE);', f'lang/{locale}.php'],
        cwd=ROOT,
    ).decode()
    return json.loads(out)


def translate_batch(texts, locale):
    payload = '\n@@@\n'.join(texts)
    url = ('https://translate.googleapis.com/translate_a/single?client=gtx&sl=tr&tl=' + locale +
           '&dt=t&q=' + urllib.parse.quote(payload))
    request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (compatible; EvrakoI18n/1.0)'})
    with urllib.request.urlopen(request, timeout=30) as response:
        data = json.loads(response.read().decode('utf-8'))
    return ''.join(segment[0] or '' for segment in data[0])


def main():
    base = flat(load_map('tr'))
    for locale in LOCALES:
        current = flat(load_map(locale))
        missing = [(key, value) for key, value in base.items() if key not in current]
        print(f'[{locale}] eksik {len(missing)} anahtar')
        if not missing:
            continue
        translations = {}
        batch = 12
        for start in range(0, len(missing), batch):
            part = missing[start:start + batch]
            texts = [value for _, value in part]
            for attempt in range(3):
                try:
                    raw = translate_batch(texts, locale).split('@@@')
                    if len(raw) != len(texts):
                        raise ValueError(f'split {len(raw)}/{len(texts)}')
                    for (key, _), out in zip(part, raw):
                        out = out.strip()
                        if out:
                            translations[key] = out
                    break
                except Exception as exc:  # noqa: BLE001
                    print('  retry', attempt + 1, str(exc)[:80])
                    time.sleep(2)
            time.sleep(0.6)
        print(f'[{locale}] {len(translations)} çeviri yazılıyor')
        payload = json.dumps(translations, ensure_ascii=False)
        subprocess.run(
            [PHP, os.path.join(ROOT, '.freebuff/i18n_insert_keys.php'), locale],
            input=payload.encode(), cwd=ROOT, check=True,
        )


if __name__ == '__main__':
    main()
