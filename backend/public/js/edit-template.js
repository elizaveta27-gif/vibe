/**
 * Универсальный редактор для свадебных шаблонов
 * Подключается к любому HTML и активирует редактирование блоков с классами .js-edit-*
 */

class WeddingEditor {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || '/api/update-block';
        this.debug = options.debug ?? false;
        this.activeInput = null;
        this.init();
    }

    log(...args) {
        if (this.debug) console.log('[Editor]', ...args);
    }

    init() {
        this.log('Инициализация редактора');

        // Находим все редактируемые блоки
        this.findEditableBlocks();
        this.validateRequiredBlocks();

        // Вешаем обработчики
        this.attachHandlers();

        // Инициализируем управление таймлайном
        this.initTimelineControls();
    }

    findEditableBlocks() {
        // Ищем все элементы с классами, начинающимися на js-edit-
        const allElements = document.querySelectorAll('[class*="js-edit-"]');
        this.editableBlocks = [];

        allElements.forEach(el => {
            // Получаем все классы элемента
            const classes = el.className.split(' ');

            // Фильтруем классы, начинающиеся с js-edit-
            const editClasses = classes.filter(c => c.startsWith('js-edit-'));

            if (editClasses.length > 0) {
                this.editableBlocks.push({
                    element: el,
                    editClasses: editClasses
                });

                // Добавляем атрибут для идентификации
                el.setAttribute('data-editable', 'true');

                // Сохраняем оригинальный текст
                if (!el.hasAttribute('data-original-text')) {
                    el.setAttribute('data-original-text', el.textContent.trim());
                }

                this.log('Найден блок:', el, 'классы:', editClasses);
            }
        });

        this.log('Всего найдено блоков:', this.editableBlocks.length);
    }

    attachElementHandlers(element) {
        element.addEventListener('mouseenter', () => this.showOutline(element));
        element.addEventListener('mouseleave', () => this.hideOutline(element));
        element.addEventListener('click', (e) => {
            if (this.activeInput) return;
            e.preventDefault();
            e.stopPropagation();
            this.activateEdit(element);
        });
    }

    attachHandlers() {
        this.editableBlocks.forEach(({ element }) => {
            this.attachElementHandlers(element);
        });

        // Клик вне блока - сохраняем и закрываем редактирование
        document.addEventListener('click', (e) => {
            if (!this.activeInput) return;
            if (this.activeContainer && this.activeContainer.contains(e.target)) return;
            this.saveEdit(this.activeInput);
        });
    }

    /**
     * Инициализирует кнопки добавления/удаления событий таймлайна
     */
    initTimelineControls() {
        const timeline = document.querySelector('.timeline');
        if (!timeline) return;

        // Добавляем кнопку удаления к каждому существующему элементу
        timeline.querySelectorAll('.timeline-item').forEach(item => {
            this.addDeleteButton(item);
        });

        // Кнопка добавления нового события
        const addBtn = document.createElement('button');
        addBtn.className = 'timeline-add-btn';
        addBtn.textContent = '+ Добавить событие';
        addBtn.addEventListener('click', () => this.addTimelineItem(timeline));
        timeline.insertAdjacentElement('afterend', addBtn);
    }

    /**
     * Добавляет кнопку удаления к элементу таймлайна
     */
    addDeleteButton(item) {
        const btn = document.createElement('button');
        btn.className = 'timeline-delete-btn';
        btn.innerHTML = '&times;';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            item.remove();
        });
        item.appendChild(btn);
    }

    /**
     * Добавляет новый элемент в таймлайн
     */
    addTimelineItem(timeline) {
        const index = timeline.querySelectorAll('.timeline-item').length;
        const templateItem = timeline.querySelector('.timeline-item:last-of-type')
            || timeline.querySelector('.timeline-item');
        const item = templateItem ? templateItem.cloneNode(true) : this.createFallbackTimelineItem();

        item.dataset.timelineIndex = index;
        item.classList.remove('removing');
        item.querySelectorAll('.timeline-delete-btn').forEach(btn => btn.remove());
        this.reindexTimelineItem(item, index);
        timeline.appendChild(item);
        this.addDeleteButton(item);

        // Регистрируем новые редактируемые поля
        item.querySelectorAll('[class*="js-edit-"]').forEach(el => {
            el.setAttribute('data-editable', 'true');
            if (!el.hasAttribute('data-original-text')) {
                el.setAttribute('data-original-text', el.textContent.trim());
            }
            this.editableBlocks.push({ element: el, editClasses: [] });
            this.attachElementHandlers(el);
        });
    }

    createFallbackTimelineItem() {
        const item = document.createElement('div');
        item.className = 'timeline-item';

        const timeEl = document.createElement('div');
        timeEl.className = 'timeline-time js-edit-time';

        const contentEl = document.createElement('div');
        contentEl.className = 'timeline-content';

        const titleEl = document.createElement('h3');
        titleEl.className = 'timeline-title js-edit-title';

        const descEl = document.createElement('p');
        descEl.className = 'timeline-desc js-edit-textarea';

        contentEl.appendChild(titleEl);
        contentEl.appendChild(descEl);
        item.appendChild(timeEl);
        item.appendChild(contentEl);

        return item;
    }

    reindexTimelineItem(item, index) {
        const defaults = {
            time: '00:00',
            title: 'Название',
            desc: 'Описание события',
        };

        Object.entries(defaults).forEach(([field, value]) => {
            const el = item.querySelector(`[data-block-id$="-${field}"]`)
                || item.querySelector(`.timeline-${field}`);
            if (!el) return;

            el.dataset.blockId = `timeline-${index}-${field}`;
            el.textContent = value;
            el.setAttribute('data-original-text', value);
        });
    }

    /**
     * Деактивирует режим редактирования без сохранения
     * @param {boolean} [restoreOriginal=true] - восстанавливать ли оригинальный текст
     */
    deactivateEdit(restoreOriginal = true) {
        this.log('Деактивация редактирования');

        // Если ничего не активно - выходим
        if (!this.activeInput || !this.activeContainer || !this.originalElement) {
            this.log('Нет активного редактирования');
            this.resetEditingState();
            return;
        }

        try {
            if (restoreOriginal) {
                // Восстанавливаем оригинальный элемент без изменений
                this.activeContainer.parentNode.replaceChild(
                    this.originalElement,
                    this.activeContainer
                );

                // Убираем классы редактирования
                this.originalElement.classList.remove('editing', 'editor-outline');

                this.log('Восстановлен оригинальный текст');
            } else {
                // Если нужно сохранить изменения, но что-то пошло не так
                // Просто убираем инпут, оставляем элемент как есть
                this.activeContainer.parentNode.replaceChild(
                    this.originalElement,
                    this.activeContainer
                );

                this.originalElement.classList.remove('editing', 'editor-outline');
            }

            // Очищаем состояние
            this.resetEditingState();

        } catch (error) {
            console.error('Ошибка при деактивации:', error);
        }
    }


    /**
     * Сброс состояния редактирования
     */
    resetEditingState() {
        this.activeInput = null;
        this.activeContainer = null;
        this.originalElement = null;
    }

    showOutline(element) {
        // Добавляем класс для подсветки
        element.classList.add('editor-outline');
    }

    hideOutline(element) {
        // Убираем подсветку, если не редактируется
        if (!element.classList.contains('editing')) {
            element.classList.remove('editor-outline');
        }
    }

    activateEdit(element) {
        this.log('Активация редактирования для:', element);

        // Сохраняем текущий элемент
        this.activeElement = element;
        element.classList.add('editing');

        // Определяем тип редактирования
        const editType = this.getEditType(element);
        this.log('Тип редактирования:', editType);

        // Создаем инпут в зависимости от типа
        const input = this.createInput(element, editType);

        // image-тип обрабатывается отдельно (createImageUploader), input === null
        if (input === null) return;

        // Заменяем содержимое на инпут
        this.replaceWithInput(element, input);

        // Фокусируемся на инпуте
        setTimeout(() => input.focus(), 100);
    }

    getEditType(element) {
        // Определяем по классу, что редактировать
        const classes = element.className.split(' ');

        if (classes.includes('js-edit-image')) return 'image';
        if (classes.includes('js-edit-date')) return 'date';
        if (classes.includes('js-edit-textarea')) return 'textarea';
        if (classes.includes('js-edit-number')) return 'number';
        if (classes.includes('js-edit-email')) return 'email';

        // По умолчанию - обычный текст
        return 'text';
    }

    createInput(element, type) {
        let input;

        switch(type) {
            case 'image':
                this.createImageUploader(element);
                return null; // Возвращаем null, чтобы прервать создание обычного инпута
            case 'textarea':
                input = document.createElement('textarea');
                input.rows = 3;
                input.addEventListener('keydown', (event) => {
                    // Ctrl+Enter — вставляем перенос строки вручную
                    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                        event.preventDefault();
                        const start = input.selectionStart;
                        const end = input.selectionEnd;
                        input.value = input.value.slice(0, start) + '\n' + input.value.slice(end);
                        input.selectionStart = input.selectionEnd = start + 1;
                        return;
                    }
                    // Enter — сохраняем
                    if (event.key === 'Enter' && !event.ctrlKey && !event.metaKey) {
                        event.preventDefault();
                        this.saveEdit(input);
                        return;
                    }
                    // Escape — отмена
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        this.cancelEdit(input);
                    }
                });
                break;
            case 'date': {
                input = document.createElement('input');
                input.type = 'date';
                const dateText = element.textContent.trim();
                const parsedDate = this.parseDate(dateText);
                if (parsedDate) {
                    input.value = parsedDate;
                }
                break;
            }
            case 'number':
                input = document.createElement('input');
                input.type = 'number';
                break;
            default:
                input = document.createElement('input');
                input.type = 'text';
        }

        // Общие атрибуты
        input.className = 'editor-input';
        input.value = element.textContent.trim();
        input.setAttribute('data-original-element', element.id || '');

        // Обработчики для не-textarea инпутов
        if (type !== 'textarea') {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.saveEdit(input);
                }
                if (e.key === 'Escape') {
                    this.cancelEdit(input);
                }
            });
        }

        return input;
    }



    /**
     * Создает загрузчик изображений для блока
     */
    createImageUploader(element) {
        // Создаем скрытый input для загрузки
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.style.display = 'none';

        // Добавляем в DOM
        document.body.appendChild(fileInput);

        // Обработчик выбора файла
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            try {
                if (!await api.isLoggedIn()) {
                    const loggedIn = await authModal.show();
                    if (!loggedIn) return;
                }

                if (!['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'].includes(file.type)) {
                    this.showMessage('Поддерживаются только JPG, PNG, WebP, GIF и AVIF', 'error');
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    this.showMessage('Максимальный размер изображения - 5 МБ', 'error');
                    return;
                }

                this.showMessage('Загрузка...', 'info');

                const formData = new FormData();
                formData.append('image', file);
                formData.append('block_id', element.dataset.blockId || element.id || '');
                formData.append('template_id', document.body.dataset.templateId || 'default');

                const data = await api.invitations.uploadImage(formData);
                this.applyImageUrl(element, data.url);
                this.saveDraft();

                this.showMessage('Изображение загружено!', 'success');

            } catch (error) {
                console.error('Ошибка:', error);
                const message = error.status === 422 && error.data?.errors?.image
                    ? error.data.errors.image
                    : error.status === 401
                    ? 'Войдите, чтобы загрузить изображение'
                    : 'Ошибка при загрузке';
                this.showMessage(message, 'error');
            } finally {
                fileInput.remove();
            }
        });

        // Запускаем выбор файла
        fileInput.click();
    }

    applyImageUrl(element, url) {
        if (element.tagName === 'IMG') {
            element.src = url;
            return;
        }

        element.style.backgroundImage = `url('${url.replace(/'/g, '%27')}')`;
    }

    parseDate(dateText) {
        // Пытаемся распарсить русскую дату "15 июня 2025" в YYYY-MM-DD
        const months = {
            'января': '01', 'февраля': '02', 'марта': '03', 'апреля': '04',
            'мая': '05', 'июня': '06', 'июля': '07', 'августа': '08',
            'сентября': '09', 'октября': '10', 'ноября': '11', 'декабря': '12'
        };

        const match = dateText.match(/(\d+)\s+([а-я]+)\s+(\d{4})/);
        if (match) {
            const day = match[1].padStart(2, '0');
            const month = months[match[2].toLowerCase()];
            const year = match[3];
            if (month) {
                return `${year}-${month}-${day}`;
            }
        }
        return null;
    }

    replaceWithInput(element, input) {
        const rect = element.getBoundingClientRect();
        const container = document.createElement('div');
        container.className = 'editor-input-container';
        container.style.minWidth = rect.width + 'px';
        container.style.minHeight = rect.height + 'px';

        container.appendChild(input);

        // Заменяем элемент на контейнер
        element.parentNode.replaceChild(container, element);

        // Сохраняем ссылки
        this.activeInput = input;
        this.activeContainer = container;
        this.originalElement = element;
    }

    async saveEdit(input) {
        if (!input) return;

        const newValue = input.value.trim();
        const container = this.activeContainer;
        const originalElement = this.originalElement;

        this.log('Сохранение изменений:', newValue);

        // Обновляем текст в оригинальном элементе
        if (input.type === 'date') {
            // Форматируем дату обратно в красивый вид
            const date = new Date(newValue + 'T12:00:00');
            const formattedDate = this.formatDate(date);
            originalElement.textContent = formattedDate;
        } else {
            originalElement.textContent = newValue;
        }

        // Возвращаем оригинальный элемент на место
        container.parentNode.replaceChild(originalElement, container);

        // Убираем классы редактирования
        originalElement.classList.remove('editing', 'editor-outline');

        // Сохраняем черновик в localStorage
        this.saveDraft();

        // Очищаем ссылки
        this.activeInput = null;
        this.activeContainer = null;
        this.originalElement = null;
    }

    formatDate(date) {
        if (isNaN(date.getTime())) return 'Дата не указана';

        const months = [
            'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
        ];

        const day = date.getDate();
        const month = months[date.getMonth()];
        const year = date.getFullYear();

        return `${day} ${month} ${year}`;
    }

    cancelEdit() {
        const container = this.activeContainer;
        const originalElement = this.originalElement;

        // Просто возвращаем оригинал без изменений
        container.parentNode.replaceChild(originalElement, container);
        originalElement.classList.remove('editing', 'editor-outline');

        this.activeInput = null;
        this.activeContainer = null;
        this.originalElement = null;
    }

    showMessage(text, type = 'info') {
        // Показываем уведомление в углу
        let messageDiv = document.getElementById('editor-message');

        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'editor-message';
            messageDiv.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 10px 20px;
                background: #333;
                color: white;
                border-radius: 4px;
                z-index: 10000;
                transition: opacity 0.3s;
            `;
            document.body.appendChild(messageDiv);
        }

        messageDiv.textContent = text;
        messageDiv.style.backgroundColor = type === 'success' ? '#4CAF50' : '#f44336';
        messageDiv.style.opacity = '1';

        setTimeout(() => {
            messageDiv.style.opacity = '0';
        }, 3000);
    }

    validateRequiredBlocks() {
        const missing = WeddingEditor.REQUIRED_BLOCK_IDS
            .filter(blockId => !document.querySelector(`[data-block-id="${blockId}"]`));

        if (!missing.length) return;

        console.warn('В шаблоне отсутствуют обязательные data-block-id:', missing);
        this.showMessage('В шаблоне не хватает некоторых редактируемых блоков', 'error');
    }

    /**
     * Собирает текущее состояние всех блоков шаблона по data-block-id.
     * Возвращает плоский объект { "hero-names": "...", "timeline-0-time": "...", ... }
     */
    collectBlocks() {
        const blocks = {};

        document.querySelectorAll('[data-block-id]').forEach(el => {
            const blockId = el.dataset.blockId;

            if (blockId === 'hero-image') {
                if (el.tagName === 'IMG') {
                    blocks[blockId] = el.getAttribute('src') || '';
                    return;
                }

                const bg = el.style.backgroundImage;
                const match = bg.match(/url\(["']?(.+?)["']?\)/);
                if (match) blocks[blockId] = match[1];
                return;
            }

            if (blockId === 'timeline') return; // контейнер, не блок

            blocks[blockId] = el.textContent.trim();
        });

        return blocks;
    }

    /**
     * Сохраняет текущее состояние редактора в localStorage как черновик.
     */
    saveDraft() {
        const templateId = document.body.dataset.templateId || 'template_1';
        const draft = {
            templateId,
            blocks: this.collectBlocks(),
            savedAt: new Date().toISOString(),
        };
        const serializedDraft = JSON.stringify(draft);
        const draftSize = new Blob([serializedDraft]).size;

        if (draftSize > WeddingEditor.DRAFT_MAX_BYTES) {
            this.showMessage('Черновик слишком большой. Сохраните приглашение на сервере.', 'error');
            return;
        }

        try {
            localStorage.setItem(`vibe_draft_${templateId}`, serializedDraft);
            this.log('Черновик сохранён в localStorage');
        } catch (error) {
            const isQuotaError = error instanceof DOMException
                && (error.name === 'QuotaExceededError' || error.name === 'NS_ERROR_DOM_QUOTA_REACHED');
            this.showMessage(
                isQuotaError
                    ? 'В браузере закончилось место для черновиков. Сохраните приглашение на сервере.'
                    : 'Не удалось сохранить черновик.',
                'error'
            );
        }
    }

    /**
     * Восстанавливает черновик из localStorage (если есть).
     */
    restoreDraft(templateId) {
        const key = `vibe_draft_${templateId}`;
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch {
            localStorage.removeItem(key);
            return null;
        }
    }
}

WeddingEditor.DRAFT_MAX_BYTES = 512 * 1024;
WeddingEditor.REQUIRED_BLOCK_IDS = [
    'hero-names',
    'hero-date',
    'wedding-date',
    'venue-name',
    'venue-address',
    'venue-lat',
    'venue-lng',
];

// Инициализация после загрузки страницы
document.addEventListener('DOMContentLoaded', () => {
    window.editor = new WeddingEditor({
        apiEndpoint: '/api/update-block',
        debug: false,
    });
});
