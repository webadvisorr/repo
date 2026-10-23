#!/usr/bin/env python3
"""Arayuz sozlugunu (lang/ui/<locale>.php) Google Translate ile tamamlar.

Kaynak: .freebuff/i18n-source.json  (Turkce metinler)
Kullanim: python3 .freebuff/i18n_google.py <locale> [locale ...]
Her calistirmada eksik kalanlardan en fazla --limit kadarini cevirir (varsayilan 400).
"""
import json, os, re, subprocess, sys, tempfile, time
import urllib.parse, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHP = os.path.join(os.environ.get('HOME', ''), 'bin/php/php')
SOURCE = os.path.join(ROOT, '.freebuff/i18n-source.json')
PH = re.compile(r'(:[a-zA-Z_][a-zA-Z0-9_]*|\{[a-zA-Z_][a-zA-Z0-9_]*\}|__[A-Z_]+__)')
BATCH = 20
LIMIT = int(os.environ.get('I18N_LIMIT', '400'))


def load_phrases():
    with open(SOURCE, encoding='utf-8') as handle:
        return json.load(handle)


def existing(locale):
    path = os.path.join(ROOT, f'lang/ui/{locale}.php')
    if not os.path.isfile(path):
        return {}
    raw = subprocess.check_output(
        [PHP, '-r', 'echo json_encode(require $argv[1], JSON_UNESCAPED_UNICODE);', path], cwd=ROOT
    ).decode()
    return json.loads(raw)


def translate(text, locale):
    url = ('https://translate.googleapis.com/translate_a/single?client=gtx&sl=tr&tl=' + locale +
           '&dt=t&q=' + urllib.parse.quote(text))
    request = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (compatible; EvrakoI18n/1.0)'})
    with urllib.request.urlopen(request, timeout=25) as response:
        data = json.loads(response.read().decode('utf-8'))
    return ''.join(segment[0] or '' for segment in data[0])


def save(locale, chunk):
    payload = json.dumps(chunk, ensure_ascii=False)
    handle, path = tempfile.mkstemp(suffix='.json')
    with os.fdopen(handle, 'w', encoding='utf-8') as out:
        out.write(payload)
    subprocess.run([PHP, os.path.join(ROOT, '.freebuff/i18n_apply_map.php'), locale, path], cwd=ROOT, check=True)
    os.unlink(path)


def translate_batch(items, locale):
    tokens = []

    def protect(match):
        tokens.append(match.group(0))
        return f'[[[PH{len(tokens) - 1}]]]'

    protected = [PH.sub(protect, item) for item in items]
    payload = '\n@@@\n'.join(protected)
    out = None
    for attempt in range(3):
        try:
            out = translate(payload, locale)
            break
        except Exception as exc:  # noqa: BLE001
            print('    retry', attempt + 1, str(exc)[:70], flush=True)
            time.sleep(1.5 + attempt)
    if out is None:
        return {}
    parts = [part.strip() for part in out.split('@@@')]
    if len(parts) != len(items):
        return translate_single(protected, items, tokens, locale)
    return restore(parts, items, tokens)


def translate_single(protected, items, tokens, locale):
    results = {}
    for source, shielded in zip(items, protected):
        try:
            out = translate(shielded, locale).strip()
        except Exception:  # noqa: BLE001
            continue
        if out:
            results[source] = _restore_text(out, tokens)
        time.sleep(0.4)
    return results


def _restore_text(text, tokens):
    for index, token in enumerate(tokens):
        text = text.replace(f'[[[PH{index}]]]', token)
    return text


def restore(parts, items, tokens):
    results = {}
    for source, out in zip(items, parts):
        if not out:
            continue
        text = _restore_text(out, tokens)
        if re.search(r'\[\[\[PH\d+\]\]\]', text):
            continue
        results[source] = text
    return results


def run(locale, phrases):
    current = existing(locale)
    missing = [phrase for phrase in phrases if phrase not in current or not str(current.get(phrase, '')).strip()]
    print(f'[{locale}] eksik {len(missing)} / {len(phrases)}', flush=True)
    if not missing:
        return 0
    missing = missing[:LIMIT]
    added = 0
    started = time.time()
    for start in range(0, len(missing), BATCH):
        if time.time() - started > 235:
            print(f'[{locale}] sure doldu, kalan {len(missing) - start}', flush=True)
            break
        chunk = translate_batch(missing[start:start + BATCH], locale)
        if chunk:
            save(locale, chunk)
            added += len(chunk)
        print(f'  +{len(chunk)} / {added} (batch {start // BATCH + 1})', flush=True)
        time.sleep(0.5)
    print(f'[{locale}] eklendi {added}', flush=True)
    return added


def main():
    locales = sys.argv[1:] or ['en', 'ru', 'ar', 'fa', 'el']
    phrases = load_phrases()
    for locale in locales:
        run(locale, phrases)


if __name__ == '__main__':
    main()
