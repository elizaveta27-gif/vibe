/**
 * Логика кнопки "Сохранить" в редакторе шаблона.
 * Зависит от: api.js, edit-template.js (window.editor)
 *
 * Читает из URL параметры:
 *   ?template=template_1           — новое приглашение
 *   ?template=template_1&id=42     — редактирование существующего
 */

document.addEventListener('DOMContentLoaded', () => {
    const params      = new URLSearchParams(window.location.search);
    const templateId  = params.get('template') || document.body.dataset.templateId || 'template_1';
    const invitationId = params.get('id') ? parseInt(params.get('id'), 10) : null;

    // Прокидываем templateId в body для collectBlocks()
    document.body.dataset.templateId = templateId;

    // ── Восстановление черновика ────────────────────────────────────────────
    // (после инициализации редактора)
    setTimeout(() => {
        if (!window.editor) return;
        const draft = window.editor.restoreDraft(templateId);
        if (draft && draft.blocks && Object.keys(draft.blocks).length > 0) {
            applyBlocks(draft.blocks);
            showToast('Восстановлен черновик', 'info');
        }
    }, 200);

    // ── Кнопка сохранения ──────────────────────────────────────────────────
    const saveBtn = document.getElementById('save-invitation-btn');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        if (!await api.isLoggedIn()) {
            const loggedIn = await authModal.show();
            if (!loggedIn) return; // пользователь закрыл модалку
        }

        const blocks = window.editor?.collectBlocks() ?? {};

        if (Object.keys(blocks).length === 0) {
            showToast('Нет данных для сохранения', 'error');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Сохранение...';

        try {
            let result;

            if (invitationId) {
                // Обновляем существующее
                result = await api.invitations.update(invitationId, blocks);
            } else {
                // Создаём новое
                result = await api.invitations.create(templateId, blocks);
            }

            try {
                localStorage.removeItem(`vibe_draft_${templateId}`);
            } catch {}

            showSuccessModal(result.short_code);

        } catch (err) {
            if (err.status === 422 && err.data?.errors) {
                showToast(Object.values(err.data.errors).join(', '), 'error');
            } else {
                const msg = err.status === 503
                    ? (err.data?.error || 'Создание приглашений временно недоступно')
                    : err.status === 429
                    ? 'Слишком много запросов, подождите немного'
                    : 'Ошибка сохранения, попробуйте ещё раз';
                showToast(msg, 'error');
            }
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';
        }
    });

    // ── Применяет blocks к DOM (восстановление черновика/загрузка с сервера) ─
    function applyBlocks(blocks) {
        // Сначала создаём недостающие timeline-события.
        // blocks могут содержать timeline-3-*, timeline-4-* и т.д.,
        // которых нет в шаблоне по умолчанию.
        const timeline = document.getElementById('timeline');
        if (timeline && window.editor) {
            const indices = Object.keys(blocks)
                .map(k => k.match(/^timeline-(\d+)-/))
                .filter(Boolean)
                .map(m => parseInt(m[1], 10));

            if (indices.length) {
                const maxIndex    = Math.max(...indices);
                const existingCount = timeline.querySelectorAll('.timeline-item').length;
                for (let i = existingCount; i <= maxIndex; i++) {
                    window.editor.addTimelineItem(timeline);
                }
            }
        }

        // Применяем все значения
        Object.entries(blocks).forEach(([blockId, value]) => {
            const el = document.querySelector(`[data-block-id="${blockId}"]`);
            if (!el) return;

            if (blockId === 'hero-image') {
                if (el.tagName === 'IMG') {
                    el.src = value;
                } else if (/^(https?:\/\/|\/)/i.test(value)) {
                    el.style.backgroundImage = `url('${value.replace(/'/g, '%27')}')`;
                }
            } else {
                el.textContent = value;
                if (el.hasAttribute('data-original-text')) {
                    el.setAttribute('data-original-text', value);
                }
            }
        });

        // Восстанавливаем маркер на карте если есть координаты
        const lat = parseFloat(blocks['venue-lat']);
        const lng = parseFloat(blocks['venue-lng']);
        if (lat && lng) {
            document.dispatchEvent(new CustomEvent('venue:restore', { detail: { lat, lng } }));
        }
    }

    // ── Модальное окно с короткой ссылкой ─────────────────────────────────
    function showSuccessModal(shortCode) {
        const baseUrl = window.location.origin;
        const link    = `${baseUrl}/${shortCode}`;

        let modal = document.getElementById('save-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'save-modal';
            modal.style.cssText = `
                position: fixed; inset: 0; background: rgba(0,0,0,.5);
                display: flex; align-items: center; justify-content: center;
                z-index: 9999; font-family: 'Inter', sans-serif;
            `;
            modal.innerHTML = `
                <div style="background:#fff; border-radius:16px; padding:40px; max-width:440px; width:90%; text-align:center;">
                    <div style="font-size:40px; margin-bottom:16px;">🎉</div>
                    <h2 style="font-family:'Cormorant Garamond',serif; font-size:26px; margin-bottom:8px; color:#2d2926;">
                        Приглашение сохранено!
                    </h2>
                    <p style="color:#9e9289; font-size:14px; margin-bottom:24px;">
                        Отправьте эту ссылку гостям
                    </p>
                    <div style="background:#f5f2ee; border-radius:10px; padding:12px 16px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                        <span id="modal-link" style="flex:1; font-size:14px; color:#2d2926; word-break:break-all; text-align:left;"></span>
                        <button id="modal-copy-btn" style="
                            background:#2d2926; color:#f5ede3; border:none; border-radius:8px;
                            padding:8px 14px; font-size:13px; cursor:pointer; white-space:nowrap;
                        ">Копировать</button>
                    </div>
                    <button id="modal-close-btn" style="
                        background:transparent; border:1.5px solid #e5ddd5; border-radius:10px;
                        padding:10px 24px; font-size:14px; cursor:pointer; color:#6b5e54;
                    ">Закрыть</button>
                </div>
            `;
            document.body.appendChild(modal);
        }

        document.getElementById('modal-link').textContent = link;

        document.getElementById('modal-copy-btn').onclick = () => {
            navigator.clipboard.writeText(link).then(() => {
                document.getElementById('modal-copy-btn').textContent = 'Скопировано!';
                setTimeout(() => {
                    document.getElementById('modal-copy-btn').textContent = 'Копировать';
                }, 2000);
            });
        };

        document.getElementById('modal-close-btn').onclick = () => {
            modal.remove();
        };

        modal.onclick = (e) => { if (e.target === modal) modal.remove(); };

        modal.style.display = 'flex';
    }

    // ── Тост-уведомление ──────────────────────────────────────────────────
    function showToast(text, type = 'info') {
        const colors = { info: '#4a3f35', error: '#c0392b', success: '#27ae60' };
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: ${colors[type] ?? colors.info}; color: #fff;
            padding: 12px 24px; border-radius: 10px; font-size: 14px;
            z-index: 9999; opacity: 1; transition: opacity .3s;
            font-family: 'Inter', sans-serif;
        `;
        toast.textContent = text;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
    }
});
