<?php

require_once __DIR__ . '/support.php';

class ibportal extends cmsFrontend
{
    private function page(string $view, IbPortalStore $store, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $settings = IbPortalSettings::values($store->settings());
        $title = $view === 'article' ? $item['title'] : $settings['title_' . $view];
        $articleId = $view === 'article' ? (int) $item['id'] : null;
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $store->countView($articleId === null ? $view : 'article:' . $articleId, $articleId);
            if ($articleId !== null) {
                $item['views']++;
            }
        }
        header('Content-Type: text/html; charset=UTF-8');
        $dir = dirname(__DIR__, 3) . '/templates/ibportal/views/';
        ob_start();
        require $dir . 'header.php';
        require $dir . $view . '.php';
        require $dir . 'footer.php';
        cmsCore::getInstance()->response->setContent(ob_get_clean())->sendAndExit();
    }

    public function actionIndex(): void
    {
        $store = IbPortalSupport::store();
        $this->page('home', $store, [
            'docs_count' => $store->countPublished('docs'),
            'news_count' => $store->countPublished('news'),
            'tests_count' => $store->countPublished('tests'),
            'news' => $store->all('news', true, 4),
        ]);
    }

    public function actionNews(): void
    {
        $store = IbPortalSupport::store();
        $this->page('news', $store, ['news' => $store->all('news', true)]);
    }

    public function actionArticle($id = 0): void
    {
        $store = IbPortalSupport::store();
        $item = $store->find('news', (int) $id, true);
        if (!$item) {
            cmsCore::error404();
        }
        $this->page('article', $store, ['item' => $item]);
    }

    public function actionCover($id = 0): void
    {
        $item = IbPortalSupport::store()->find('news', (int) $id, true);
        if (!$item || !$item['picture']) {
            cmsCore::error404();
        }
        $extension = pathinfo($item['picture'], PATHINFO_EXTENSION);
        $mime = match ($extension) {'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg'};
        IbPortalSupport::stream($item['picture'], 'cover.' . $extension, $mime, true);
    }

    public function actionDocuments(): void
    {
        $store = IbPortalSupport::store();
        $this->page('documents', $store, ['all_sections' => $this->documentSections($store, false)]);
    }

    public function actionCrypto(): void
    {
        $store = IbPortalSupport::store();
        $this->page('crypto', $store, ['all_sections' => $this->documentSections($store, true)]);
    }

    public function actionContentImage($key = ''): void
    {
        $mime = IbPortalSupport::imageMime($key);
        if (!$mime) {
            cmsCore::error404();
        }
        IbPortalSupport::stream($key, $key, $mime, true, true);
    }

    private function documentSections(IbPortalStore $store, bool $cryptoOnly): array
    {
        $sections = [];
        foreach ($store->all('sections') as $section) {
            $sections[$section['id']] = ['name' => $section['title'], 'items' => []];
        }
        $sections[0] = ['name' => 'Прочие документы', 'items' => []];
        foreach ($store->all('docs', true) as $doc) {
            if (!$cryptoOnly || $doc['for_crypto']) {
                $sections[$doc['section_id'] ?? 0]['items'][] = $doc;
            }
        }
        return array_filter($sections, static fn($section) => $section['items']);
    }

    public function actionFile($id = 0): void
    {
        $item = IbPortalSupport::store()->find('docs', (int) $id, true);
        if (!$item) {
            cmsCore::error404();
        }
        IbPortalSupport::stream($item['file_key'], $item['file_name'], $item['mime'], $item['mime'] === 'application/pdf');
    }

    public function actionPassword(): void
    {
        $this->page('password', IbPortalSupport::store());
    }

    public function actionPolls(): void
    {
        $testsData = [];
        $store = IbPortalSupport::store();
        foreach ($store->all('tests', true) as $test) {
            $questions = json_decode($test['questions'], true, 512, JSON_THROW_ON_ERROR);
            $public = array_map(static fn($q) => ['question' => $q['question'], 'answers' => $q['answers']], $questions);
            $testsData[] = ['id' => (int) $test['id'], 'revision' => $test['revision'], 'name' => $test['title'],
                'description' => $test['description'], 'questions' => $public];
        }
        header('Cache-Control: private, no-store');
        $this->page('polls', $store, ['testsData' => $testsData, 'token' => cmsForm::getCSRFToken()]);
    }

    public function actionSubmit(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cmsForm::validateCSRFToken($_POST['nonce'] ?? '')) {
            cmsCore::getInstance()->response->setStatusCode(403);
            $this->cms_template->renderJSON(['success' => false, 'data' => ['message' => 'Обновите страницу и попробуйте снова.']]);
            return;
        }
        try {
            $result = $this->grade();
            $this->cms_template->renderJSON(['success' => true, 'data' => $result]);
        } catch (InvalidArgumentException $e) {
            cmsCore::getInstance()->response->setStatusCode(400);
            $this->cms_template->renderJSON(['success' => false, 'data' => ['message' => $e->getMessage()]]);
        }
    }

    private function grade(): array
    {
        $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $id = filter_var($_POST['testId'] ?? null, FILTER_VALIDATE_INT);
        $revision = $_POST['revision'] ?? '';
        $attempt = $_POST['attempt'] ?? '';
        $answers = is_string($_POST['answers'] ?? null) ? json_decode($_POST['answers'], true) : null;
        if ($name === '' || mb_strlen($name) > 120 || !$id ||
            !is_string($revision) || !is_string($attempt) || !preg_match('/^[a-f0-9]{32}$/D', $attempt) ||
            !is_array($answers) || !array_is_list($answers)) {
            throw new InvalidArgumentException('Проверьте имя и ответы.');
        }
        $store = IbPortalSupport::store();
        if ($prior = $store->resultFor($attempt)) {
            return $prior;
        }
        $test = $store->find('tests', $id, true);
        if (!$test || $revision !== $test['revision']) {
            throw new InvalidArgumentException('Тест изменился или снят с публикации. Обновите страницу.');
        }
        $questions = json_decode($test['questions'], true, 512, JSON_THROW_ON_ERROR);
        if (count($answers) !== count($questions)) {
            throw new InvalidArgumentException('Ответьте на все вопросы.');
        }
        $details = [];
        $score = 0;
        foreach ($questions as $i => $q) {
            $selected = $answers[$i];
            if (!is_int($selected) || !isset($q['answers'][$selected])) {
                throw new InvalidArgumentException('Передан неизвестный вариант ответа.');
            }
            $correct = $selected === $q['correct'];
            $score += (int) $correct;
            $details[] = ['question' => $q['question'], 'selected' => $q['answers'][$selected],
                'expected' => $q['answers'][$q['correct']], 'correct' => $correct];
        }
        try {
            $resultId = $store->save('results', [
                'attempt' => $attempt, 'participant' => $name, 'test_id' => $test['id'],
                'test_title' => $test['title'], 'score' => $score, 'total' => count($questions),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '',
                'login' => mb_substr((string) ($this->cms_user->email ?? ''), 0, 180),
                'network_login' => mb_substr($_SERVER['REMOTE_USER'] ?? '', 0, 180),
                'user_agent' => mb_substr(mb_convert_encoding($_SERVER['HTTP_USER_AGENT'] ?? '', 'UTF-8', 'UTF-8'), 0, 255),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && ($prior = $store->resultFor($attempt))) {
                return $prior;
            }
            throw $e;
        }
        return ['id' => $resultId, 'score' => $score, 'total' => count($questions)];
    }
}
