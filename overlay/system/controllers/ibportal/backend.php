<?php

require_once __DIR__ . '/support.php';

class backendIbportal extends cmsBackend
{
    private const LABELS = [
        'news' => 'Новости', 'sections' => 'Разделы документов', 'docs' => 'Документы',
        'tests' => 'Тесты', 'results' => 'Результаты тестов',
        'appearance' => 'Оформление', 'content' => 'Памятка и криптошлюз', 'views' => 'Статистика',
    ];

    public function actionIndex()
    {
        if (!$this->cms_user->is_admin) {
            return cmsCore::error403();
        }

        $kind = $_GET['kind'] ?? 'news';
        if (!is_string($kind) || !isset(self::LABELS[$kind])) {
            return cmsCore::error404();
        }
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $edit = isset($_GET['edit']);
        $base = href_to($this->root_url);
        $url = static fn($type, $extra = []) => $base . '?' . http_build_query(['kind' => $type] + $extra);
        $store = IbPortalSupport::store();
        if (in_array($kind, ['appearance', 'content', 'views'], true)) {
            return $this->portalOptions($store, $kind, $url);
        }
        $item = $id ? $store->find($kind, $id) : null;
        if ($id && !$item) {
            return cmsCore::error404();
        }
        $error = '';
        $form = $item ?? [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!cmsForm::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                return cmsCore::error403();
            }
            if (($_POST['operation'] ?? '') === 'delete') {
                if (!$item) {
                    return cmsCore::error404();
                }
                $store->delete($kind, $id);
                IbPortalSupport::remove($item['file_key'] ?? $item['picture'] ?? null);
                return $this->redirect($url($kind));
            }
            if ($kind === 'results' || !$edit) {
                return cmsCore::error404();
            }
            $form = array_replace($form, array_intersect_key($_POST, array_flip([
                'title', 'body', 'description', 'section_id', 'position', 'question_rows',
            ])));
            $form['published'] = isset($_POST['published']);
            $form['is_new'] = isset($_POST['is_new']);
            $form['for_crypto'] = isset($_POST['for_crypto']);
            try {
                $this->saveItem($store, $kind, $item, $form);
                cmsUser::addSessionMessage('Сохранено.', 'success');
                return $this->redirect($url($kind));
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        $sections = array_column($store->all('sections'), 'title', 'id');
        return $this->cms_template->render('backend/manage', [
            'kind' => $kind, 'labels' => self::LABELS, 'item' => $item, 'form' => $form, 'edit' => $edit,
            'items' => !$item && !$edit ? $store->all($kind) : [],
            'sections' => $sections, 'url' => $url, 'error' => $error,
            'publishedDocs' => $edit && $kind === 'news' ? $store->all('docs', true) : [],
            'token' => cmsForm::getCSRFToken(),
        ]);
    }

    private function portalOptions(IbPortalStore $store, string $kind, Closure $url)
    {
        $settings = IbPortalSettings::values($store->settings());
        $error = '';
        $token = cmsForm::getCSRFToken();
        $fields = $kind === 'views' ? [] : IbPortalSettings::fields($kind);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!cmsForm::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                return cmsCore::error403();
            }
            if ($kind === 'views') {
                return cmsCore::error404();
            }
            if ($kind === 'content' && isset($_GET['upload'])) {
                return $this->uploadContentImage();
            }

            $posted = $_POST['settings'] ?? null;
            try {
                if (!is_array($posted)) {
                    throw new InvalidArgumentException('Поля настроек не переданы.');
                }
                $settings = array_replace($settings, array_filter(array_intersect_key($posted, $fields), 'is_string'));
                $values = [];
                foreach ($fields as $key => [$label]) {
                    $rich = IbPortalSettings::isRich($key);
                    $limit = $rich ? 60000 : ($key === 'home_intro' ? 600 : 160);
                    $value = $this->text($posted[$key] ?? null, $limit, $label);
                    if ($value === '' && (str_starts_with($key, 'title_') ||
                            in_array($key, ['site_name', 'memo_title', 'crypto_docs_title'], true))) {
                        throw new InvalidArgumentException('Заполните поле «' . $label . '».');
                    }
                    $values[$key] = $rich ? IbPortalSupport::richText($value) : $value;
                }
                $store->saveSettings($values);
                cmsUser::addSessionMessage('Настройки сохранены.', 'success');
                return $this->redirect($url($kind));
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->cms_template->render('backend/manage', [
            'kind' => $kind, 'labels' => self::LABELS, 'url' => $url, 'error' => $error, 'token' => $token,
            'settings' => $settings, 'fields' => $fields,
            'pageViews' => $kind === 'views' ? $store->pageViews() : [],
            'articles' => $kind === 'views' ? array_column($store->all('news'), null, 'id') : [],
        ]);
    }

    private function uploadContentImage()
    {
        try {
            $file = IbPortalSupport::upload('image', true, true);
            if (!$file) {
                throw new InvalidArgumentException('Выберите изображение.');
            }
            return $this->cms_template->renderJSON([
                'url' => href_to('ibportal', 'content_image', [$file['key']]),
            ]);
        } catch (InvalidArgumentException $e) {
            cmsCore::getInstance()->response->setStatusCode(400);
            return $this->cms_template->renderJSON(['message' => $e->getMessage()]);
        }
    }

    private function saveItem(IbPortalStore $store, string $kind, ?array $item, array $form): void
    {
        $title = $this->text($form['title'] ?? '', $kind === 'sections' ? 180 : 200, 'Название');
        if ($title === '') {
            throw new InvalidArgumentException('Укажите название.');
        }
        $fields = ['title' => $title];
        if ($kind !== 'sections') {
            $fields['published'] = (int) !empty($form['published']);
        }
        if ($kind === 'docs' || $kind === 'tests') {
            $fields['position'] = max(-100000, min(100000, (int) ($form['position'] ?? 0)));
        }

        $upload = null;
        $oldUpload = null;
        switch ($kind) {
            case 'news':
                $fields['body'] = $this->text($form['body'] ?? '', 60000, 'Текст');
                $fields['is_new'] = (int) !empty($form['is_new']);
                $upload = IbPortalSupport::upload('picture', true);
                $oldUpload = $item['picture'] ?? null;
                $fields['picture'] = $upload['key'] ?? (isset($_POST['remove_picture']) ? null : $oldUpload);
                if (!$upload && !isset($_POST['remove_picture'])) {
                    $oldUpload = null;
                }
                break;
            case 'docs':
                $section = (int) ($form['section_id'] ?? 0);
                if ($section && !$store->find('sections', $section)) {
                    throw new InvalidArgumentException('Выберите существующий раздел.');
                }
                $fields['section_id'] = $section ?: null;
                $fields['for_crypto'] = (int) !empty($form['for_crypto']);
                $upload = IbPortalSupport::upload('file', false);
                if ($upload) {
                    $oldUpload = $item['file_key'] ?? null;
                    $fields += ['file_key' => $upload['key'], 'file_name' => $upload['name'],
                        'mime' => $upload['mime'], 'file_size' => $upload['size']];
                } elseif (!$item) {
                    throw new InvalidArgumentException('Прикрепите файл документа.');
                }
                break;
            case 'tests':
                $fields['description'] = $this->text($form['description'] ?? '', 5000, 'Описание');
                $questions = $this->questions($form['question_rows'] ?? null);
                $fields['questions'] = json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $fields['revision'] = $item && $fields['questions'] === $item['questions'] ?
                    $item['revision'] : bin2hex(random_bytes(16));
                break;
        }
        try {
            $store->save($kind, $fields, (int) ($item['id'] ?? 0));
        } catch (Throwable $e) {
            IbPortalSupport::remove($upload['key'] ?? null);
            throw $e;
        }
        IbPortalSupport::remove($oldUpload);
    }

    private function text(mixed $value, int $limit, string $label): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit) {
            throw new InvalidArgumentException("Поле «$label» должно содержать не более $limit символов.");
        }
        return trim($value);
    }

    private function questions(mixed $rows): array
    {
        if (!is_array($rows) || !count($rows) || count($rows) > 50) {
            throw new InvalidArgumentException('Добавьте от 1 до 50 вопросов.');
        }
        $questions = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_array($row['answers'] ?? null)) {
                throw new InvalidArgumentException('Проверьте варианты ответов.');
            }
            $question = $this->text($row['question'] ?? '', 500, 'Вопрос');
            $answers = array_values($row['answers']);
            if (count($answers) < 2 || count($answers) > 8) {
                throw new InvalidArgumentException('В каждом вопросе должно быть от 2 до 8 вариантов.');
            }
            $answers = array_map(fn($value) => $this->text($value, 500, 'Ответ'), $answers);
            $correct = filter_var($row['correct'] ?? null, FILTER_VALIDATE_INT);
            if ($question === '' || in_array('', $answers, true) || $correct === false || !isset($answers[$correct])) {
                throw new InvalidArgumentException('Заполните текст вопроса, все варианты и отметьте один правильный ответ.');
            }
            $questions[] = ['question' => $question, 'answers' => $answers, 'correct' => $correct];
        }
        return $questions;
    }
}
