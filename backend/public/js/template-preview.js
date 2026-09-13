(function () {
    const TEMPLATE_NAMES = {
        classic:    'template_1',
        dark:       'template_2',
        terracotta: 'template_5',
        lumiere:    'template_6',
        rose:       'template_7',
        starry:     'template_8',
    };
    const templateName = window.location.pathname.split('/').pop();
    const templateId   = TEMPLATE_NAMES[templateName] || 'template_1';

    document.body.style.paddingBottom = '72px';

    const toolbar = document.createElement('div');
    toolbar.style.cssText = `
        position:fixed;bottom:0;left:0;right:0;height:64px;background:#2d2926;
        display:flex;align-items:center;justify-content:space-between;
        padding:0 24px;z-index:1000;box-shadow:0 -2px 16px rgba(0,0,0,.2);
        font-family:'Inter',sans-serif;
    `;
    toolbar.innerHTML = `
        <a href="/" style="color:rgba(255,255,255,.6);font-size:14px;text-decoration:none;transition:color .2s;">← Шаблоны</a>
        <span style="font-size:13px;color:rgba(255,255,255,.35);letter-spacing:.3px;">Предпросмотр шаблона</span>
        <button id="_preview_edit_btn" style="
            background:linear-gradient(135deg,#c9a96e,#b8924f);color:#fff;border:none;
            border-radius:10px;padding:10px 28px;font-size:14px;font-weight:500;
            cursor:pointer;letter-spacing:.3px;transition:opacity .2s;
        ">Редактировать</button>
    `;
    document.body.appendChild(toolbar);

    document.getElementById('_preview_edit_btn').onclick = () => {
        window.location.href = '/editor?template=' + encodeURIComponent(templateId);
    };
})();
