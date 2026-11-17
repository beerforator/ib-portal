document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    const docLink = document.getElementById('ib-doc-link');
    docLink?.addEventListener('change', () => {
        const option = docLink.selectedOptions[0];
        if (!option?.dataset.href) return;
        const editor = window.tinymce?.get('ib-news-body');
        if (editor) {
            const anchor = document.createElement('a');
            anchor.setAttribute('href', option.dataset.href);
            anchor.textContent = option.textContent;
            editor.focus();
            editor.insertContent(anchor.outerHTML);
        } else {
            const field = document.getElementById('ib-news-body');
            field?.setRangeText('[doc:' + option.value + ']', field.selectionStart, field.selectionEnd, 'end');
        }
        docLink.value = '';
    });

    document.querySelectorAll('.ib-inline-image').forEach(control => {
        const button = control.querySelector('[data-image-insert]');
        const input = control.querySelector('[data-image-file]');
        const alt = control.querySelector('[data-image-alt]');
        const status = control.querySelector('[data-image-status]');
        const form = control.closest('form');
        button.addEventListener('click', async () => {
            const file = input.files[0];
            if (!file) {
                status.textContent = 'Сначала выберите изображение.';
                input.focus();
                return;
            }
            const data = new FormData();
            data.append('csrf_token', form.elements.csrf_token.value);
            data.append('image', file);
            button.disabled = true;
            status.textContent = 'Загрузка…';
            try {
                const response = await fetch(control.dataset.upload, { method: 'POST', body: data, credentials: 'same-origin' });
                const result = await response.json();
                if (!response.ok || !result.url) throw new Error(result.message || 'Не удалось загрузить изображение.');
                const image = document.createElement('img');
                image.setAttribute('src', result.url);
                image.alt = alt.value.trim();
                const editor = window.tinymce?.get(control.dataset.editor);
                if (editor) {
                    editor.focus();
                    editor.insertContent(image.outerHTML);
                } else {
                    const textarea = document.getElementById(control.dataset.editor);
                    textarea.setRangeText(image.outerHTML, textarea.selectionStart, textarea.selectionEnd, 'end');
                }
                input.value = '';
                alt.value = '';
                status.textContent = 'Изображение вставлено. Сохраните страницу.';
            } catch (error) {
                status.textContent = error.message || 'Не удалось загрузить изображение.';
            } finally {
                button.disabled = false;
            }
        });
    });

    const list = document.getElementById('ib-questions');
    if (!list) return;
    const add = document.getElementById('ib-add-question');
    const questions = JSON.parse(document.getElementById('ib-question-data').textContent);
    let nextId = 0;

    function button(text, action, className = 'btn-outline-secondary') {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'btn btn-sm ' + className;
        el.textContent = text;
        el.addEventListener('click', action);
        return el;
    }

    function questionField(data = {}) {
        if (list.children.length >= 50) return;
        const id = nextId++;
        const field = document.createElement('fieldset');
        const legend = document.createElement('legend');
        legend.textContent = 'Вопрос';
        const question = document.createElement('input');
        question.className = 'form-control';
        question.name = 'question_rows[' + id + '][question]';
        question.placeholder = 'Текст вопроса';
        question.setAttribute('aria-label', 'Текст вопроса');
        question.maxLength = 500;
        question.required = true;
        question.value = data.question || '';

        const answers = document.createElement('div');
        const addAnswer = button('Добавить вариант', () => answerField());
        function renumber() {
            [...answers.children].forEach((row, index) => {
                row.querySelector('input[type=radio]').value = index;
                row.querySelector('input[type=radio]').setAttribute('aria-label', 'Вариант ' + (index + 1) + ' правильный');
                row.querySelector('input[type=text]').setAttribute('aria-label', 'Текст варианта ' + (index + 1));
                row.querySelector('button').disabled = answers.children.length <= 2;
            });
            addAnswer.disabled = answers.children.length >= 8;
        }

        function answerField(value = '', isCorrect = false) {
            if (answers.children.length >= 8) return;
            const row = document.createElement('div');
            row.className = 'ib-answer';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'question_rows[' + id + '][correct]';
            radio.required = true;
            radio.checked = isCorrect;
            radio.title = 'Правильный ответ';
            const text = document.createElement('input');
            text.type = 'text';
            text.className = 'form-control';
            text.name = 'question_rows[' + id + '][answers][]';
            text.placeholder = 'Вариант ответа';
            text.required = true;
            text.maxLength = 500;
            text.value = value;
            row.append(radio, text, button('Убрать', () => {
                row.remove();
                renumber();
            }));
            answers.append(row);
            renumber();
        }

        const remove = button('Удалить вопрос', () => {
            field.remove();
            add.disabled = false;
        }, 'btn-outline-danger');
        field.append(legend, question, answers, addAnswer, document.createTextNode(' '), remove);
        list.append(field);
        (Array.isArray(data.answers) && data.answers.length ? data.answers : ['', ''])
            .forEach((value, index) => answerField(value, index === Number(data.correct)));
        add.disabled = list.children.length >= 50;
    }

    add.addEventListener('click', () => questionField());
    (questions.length ? questions : [{}]).forEach(question => questionField(question || {}));
});
