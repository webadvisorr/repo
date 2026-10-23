<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Config;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Str;
use App\Core\Translator;
use App\Core\Uploader;

/**
 * Ana sayfa slider yönetimi. Slaytlar `slides` tablosunda tutulur;
 * veritabanında aktif slayt varsa ana sayfa bunları, yoksa çeviri
 * varsayılanlarını gösterir.
 */
final class SlidesController extends Controller
{
    protected string $layout = 'layouts/admin';

    private const POSITIONS = ['left', 'center', 'right'];

    public function index(): string
    {
        $rows = Database::select('SELECT * FROM slides ORDER BY sort_order ASC, id ASC');

        return $this->view('admin/slides/index', [
            'title'  => 'Slider',
            'rows'   => $rows,
            'active' => (int) Database::scalar('SELECT COUNT(*) FROM slides WHERE is_active = 1'),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/slides/form', [
            'title'   => 'Yeni slayt',
            'slide'   => null,
            'locales' => $this->locales(),
            'action'  => url('/yonetim/slider/kaydet'),
        ]);
    }

    public function edit(string $id): string
    {
        $id = (int) $id;
        $slide = Database::first('SELECT * FROM slides WHERE id = ? LIMIT 1', [$id]);
        if ($slide === null) {
            Flash::error('Slayt bulunamadı.');
            $this->redirect('/yonetim/slider');
        }

        return $this->view('admin/slides/form', [
            'title'   => 'Slayt düzenle: ' . (string) ($slide['title'] ?? '#' . $id),
            'slide'   => $slide,
            'locales' => $this->locales(),
            'action'  => url('/yonetim/slider/kaydet'),
        ]);
    }

    /** Slayt oluşturur veya günceller. */
    public function save(): void
    {
        $id = $this->request->int('id');
        $slide = $id > 0 ? Database::first('SELECT * FROM slides WHERE id = ? LIMIT 1', [$id]) : null;
        $back = $id > 0 ? '/yonetim/slider/' . $id : '/yonetim/slider/yeni';

        $title       = Str::limit($this->request->string('title'), 200, '');
        $subtitle    = Str::limit($this->request->string('subtitle'), 255, '');
        $description = Str::limit($this->request->string('description'), 2000, '');
        $buttonText  = Str::limit($this->request->string('button_text'), 80, '');
        $buttonUrl   = trim($this->request->string('button_url'));
        $target      = (string) $this->request->input('button_target', '_self');
        $position    = (string) $this->request->input('text_position', 'left');
        $locale      = trim((string) $this->request->input('locale', ''));
        $overlay     = max(0, min(100, $this->request->int('overlay_opacity', 35)));
        $sortOrder   = max(-999, min(9999, $this->request->int('sort_order', 0)));
        $isActive    = $this->request->bool('is_active');
        $removeImage = $this->request->bool('remove_image');

        if (!in_array($target, ['_self', '_blank'], true)) {
            $target = '_self';
        }
        if (!in_array($position, self::POSITIONS, true)) {
            $position = 'left';
        }
        if ($locale !== '' && !in_array($locale, Translator::SUPPORTED, true)) {
            $locale = '';
        }
        if ($buttonUrl !== '' && !$this->safeUrl($buttonUrl)) {
            $this->flashError('Buton bağlantısı / ile başlayan bir yol veya http(s) adresi olmalıdır.');
            $this->redirect($back);
        }

        $file = $this->request->file('image');
        $newImage = null;
        if ($file !== null) {
            $result = Uploader::store($file, 'slide', [
                'type'       => 'image',
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'apng'],
                'max_bytes'  => (int) Config::get('uploads.max_image_bytes', 8 * 1024 * 1024),
            ]);
            if (!$result['ok']) {
                $this->flashError((string) ($result['error'] ?? 'Görsel yüklenemedi.'));
                $this->redirect($back);
            }
            $newImage = $result['media'];
        }

        $hasImage = $newImage !== null || ($slide !== null && !empty($slide['image_path']) && !$removeImage);
        if ($title === '' && !$hasImage) {
            $this->flashError('Bir başlık veya slayt görseli gerekli.');
            $this->redirect($back);
        }

        $data = [
            'title'           => $title !== '' ? $title : null,
            'subtitle'        => $subtitle !== '' ? $subtitle : null,
            'description'     => $description !== '' ? $description : null,
            'button_text'     => $buttonText !== '' ? $buttonText : null,
            'button_url'      => $buttonUrl !== '' ? $buttonUrl : null,
            'button_target'   => $target,
            'text_position'   => $position,
            'overlay_opacity' => $overlay,
            'locale'          => $locale !== '' ? $locale : null,
            'is_active'       => $isActive ? 1 : 0,
            'sort_order'      => $sortOrder,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($newImage !== null) {
            $data['media_id']  = (int) $newImage['id'];
            $data['image_path'] = (string) $newImage['path'];
        } elseif ($removeImage) {
            $data['media_id']  = null;
            $data['image_path'] = null;
        }

        if ($slide !== null) {
            $oldMediaId = (int) ($slide['media_id'] ?? 0);
            Database::update('slides', $data, 'id = :slide_id', ['slide_id' => $id]);
            // Görsel değiştiyse veya kaldırıldıysa eski dosyayı temizle (yetim dosya bırakma).
            if ($oldMediaId > 0 && ($newImage !== null || $removeImage)) {
                Uploader::delete($oldMediaId);
            }
            Audit::log('slide.update', 'slide', $id, ['title' => $title, 'active' => $data['is_active']]);
            $this->flashSuccess('Slayt güncellendi.');
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = Database::insert('slides', $data);
            Audit::log('slide.create', 'slide', $id, ['title' => $title, 'active' => $data['is_active']]);
            $this->flashSuccess('Slayt eklendi.');
        }

        $this->redirect('/yonetim/slider');
    }

    public function toggle(): void
    {
        $id = $this->request->int('id');
        $slide = Database::first('SELECT id, title, is_active FROM slides WHERE id = ? LIMIT 1', [$id]);
        if ($slide === null) {
            $this->flashError('Slayt bulunamadı.');
            $this->redirect('/yonetim/slider');
        }

        $newState = (int) $slide['is_active'] === 1 ? 0 : 1;
        Database::run('UPDATE slides SET is_active = ?, updated_at = ? WHERE id = ?', [$newState, date('Y-m-d H:i:s'), $id]);
        Audit::log('slide.toggle', 'slide', $id, ['active' => $newState]);
        $this->flashSuccess($newState === 1 ? 'Slayt yayına alındı.' : 'Slayt yayından kaldırıldı.');
        $this->redirect('/yonetim/slider');
    }

    public function destroy(): void
    {
        $id = $this->request->int('id');
        $slide = Database::first('SELECT id, title, media_id FROM slides WHERE id = ? LIMIT 1', [$id]);
        if ($slide === null) {
            $this->flashError('Slayt bulunamadı.');
            $this->redirect('/yonetim/slider');
        }

        Database::run('DELETE FROM slides WHERE id = ?', [$id]);
        $mediaId = (int) ($slide['media_id'] ?? 0);
        if ($mediaId > 0) {
            Uploader::delete($mediaId);
        }
        Audit::log('slide.delete', 'slide', $id, ['title' => (string) ($slide['title'] ?? '')]);
        $this->flashSuccess('Slayt silindi.');
        $this->redirect('/yonetim/slider');
    }

    /** @return array<string,string> */
    private function locales(): array
    {
        $out = ['' => 'Tüm diller'];
        foreach (Translator::options() as $code => $name) {
            $out[$code] = $name;
        }
        return $out;
    }

    private function safeUrl(string $url): bool
    {
        if (str_starts_with($url, '//')) {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return true;
        }
        return preg_match('~^https?://~i', $url) === 1;
    }
}
