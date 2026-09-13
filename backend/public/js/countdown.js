/**
 * countdown.js — счётчик до свадьбы
 *
 * Требует в шаблоне:
 *   <span data-block-id="wedding-date" style="display:none">YYYY-MM-DD</span>
 *   <div id="days">  <div id="hours">  <div id="minutes">  <div id="seconds">
 *
 * В редакторе опционально:
 *   <input type="date" id="wedding-date-input" data-editor-only>
 */

function initCountdown() {

    const dateSpan = document.querySelector('[data-block-id="wedding-date"]');
    if (!dateSpan || !document.getElementById('days')) return;

    const dateInput = document.getElementById('wedding-date-input');

    // Синхронизация датапикера ↔ span (только в редакторе)
    if (dateInput) {
        dateInput.value = dateSpan.textContent.trim();

        // Пользователь выбрал дату → обновляем span (collectBlocks прочитает textContent)
        dateInput.addEventListener('change', () => {
            dateSpan.textContent = dateInput.value;
        });

        // Восстановление черновика: applyBlocks меняет span → синхронизируем input
        new MutationObserver(() => {
            if (dateInput.value !== dateSpan.textContent.trim()) {
                dateInput.value = dateSpan.textContent.trim();
            }
        }).observe(dateSpan, { childList: true, characterData: true, subtree: true });
    }

    function tick() {
        const iso  = dateSpan.textContent.trim();
        const diff = iso ? new Date(iso + 'T12:00:00') - Date.now() : -1;
        const set  = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };

        if (diff <= 0) {
            ['days', 'hours', 'minutes', 'seconds'].forEach(id => set(id, '00'));
            return;
        }

        set('days',    String(Math.floor(diff / 86400000)).padStart(2, '0'));
        set('hours',   String(Math.floor(diff % 86400000 / 3600000)).padStart(2, '0'));
        set('minutes', String(Math.floor(diff % 3600000 / 60000)).padStart(2, '0'));
        set('seconds', String(Math.floor(diff % 60000 / 1000)).padStart(2, '0'));
    }

    tick();
    setInterval(tick, 1000);

    // Мгновенное обновление при смене даты
    new MutationObserver(tick)
        .observe(dateSpan, { childList: true, characterData: true, subtree: true });

    initCountdownDateEditor(dateSpan, dateInput);
}

function initCountdownDateEditor(dateSpan, dateInput) {
    const isEditor = Boolean(document.getElementById('save-invitation-btn') && window.editor);
    if (!isEditor || dateSpan.dataset.countdownEditorReady === 'true') return;

    dateSpan.dataset.countdownEditorReady = 'true';

    const numbers = ['days', 'hours', 'minutes', 'seconds']
        .map(id => document.getElementById(id))
        .filter(Boolean);

    numbers.forEach(el => {
        el.dataset.countdownDateTrigger = 'true';
        el.title = 'Изменить дату свадьбы';
        el.style.cursor = 'pointer';
        el.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            openCountdownDatePicker(dateSpan, dateInput, el);
        });
    });
}

function openCountdownDatePicker(dateSpan, dateInput, anchor) {
    let picker = document.getElementById('countdown-date-editor');
    if (!picker) {
        picker = document.createElement('input');
        picker.type = 'date';
        picker.id = 'countdown-date-editor';
        picker.style.cssText = [
            'position:fixed',
            'z-index:10001',
            'padding:10px 12px',
            'border:1.5px solid #c9a96e',
            'border-radius:8px',
            'background:#fff',
            'color:#2d2926',
            'font:14px Inter, sans-serif',
            'box-shadow:0 10px 30px rgba(0,0,0,.18)',
            'outline:none'
        ].join(';');
        document.body.appendChild(picker);
    }

    const rect = anchor.getBoundingClientRect();
    picker.value = dateSpan.textContent.trim();
    picker.style.left = `${Math.min(rect.left, window.innerWidth - 180)}px`;
    picker.style.top = `${Math.max(12, rect.bottom + 8)}px`;

    const applyDate = () => {
        if (!picker.value) return;
        dateSpan.textContent = picker.value;
        if (dateInput) dateInput.value = picker.value;
        if (window.editor?.saveDraft) window.editor.saveDraft();
    };

    picker.onchange = () => {
        applyDate();
        picker.remove();
    };
    picker.onkeydown = event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyDate();
            picker.remove();
        }
        if (event.key === 'Escape') {
            picker.remove();
        }
    };
    picker.onblur = () => {
        setTimeout(() => picker.remove(), 120);
    };

    picker.focus();
    if (typeof picker.showPicker === 'function') {
        picker.showPicker();
    }
}

// Авто-запуск в шаблонах (в редакторе и при прямом открытии)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCountdown);
} else {
    initCountdown();
}
