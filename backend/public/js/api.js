const API_BASE = '';

const api = {
    _authState: null,
    _csrfToken: null,

    async _fetch(url, options = {}) {
        return api._request(url, options, true);
    },

    async _request(url, options = {}, retryOnCsrfError = false) {
        const isFormData = options.body instanceof FormData;
        const headers = { ...(isFormData ? {} : { 'Content-Type': 'application/json' }), ...options.headers };
        const method = (options.method || 'GET').toUpperCase();

        if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method) && !api._isCsrfExempt(url)) {
            headers['X-CSRF-Token'] = await api._getCsrfToken();
        }

        const res = await fetch(API_BASE + url, { ...options, headers, credentials: 'same-origin' });
        const data = await res.json().catch(() => ({}));
        if (res.status === 403 && data?.error?.includes('CSRF')) {
            api._csrfToken = null;
            if (retryOnCsrfError) {
                return api._request(url, options, false);
            }
        }
        if (res.status === 401) {
            api._authState = false;
            throw { status: 401, data };
        }
        if (!res.ok) throw { status: res.status, data };
        return data;
    },

    _isCsrfExempt(url) {
        return url === '/api/csrf-token'
            || url === '/api/auth/login'
            || url === '/api/auth/register'
            || url === '/api/auth/forgot-password'
            || url === '/api/auth/reset-password'
            || url === '/api/public/waitlist'
            || url === '/api/support'
            || url.startsWith('/api/public/rsvp/');
    },

    _readCookie(name) {
        const cookie = document.cookie
            .split('; ')
            .find(row => row.startsWith(`${name}=`));

        if (!cookie) return null;

        return decodeURIComponent(cookie.split('=').slice(1).join('='));
    },

    async _getCsrfToken() {
        const existingToken = api._csrfToken || api._readCookie('XSRF-TOKEN');
        if (existingToken) {
            api._csrfToken = existingToken;
            return existingToken;
        }

        const data = await api._request('/api/csrf-token', {}, false);
        api._csrfToken = data.csrf_token || api._readCookie('XSRF-TOKEN');
        if (!api._csrfToken) {
            throw { status: 403, data: { error: 'CSRF-токен не получен' } };
        }

        return api._csrfToken;
    },

    get:    (url)       => api._fetch(url),
    post:   (url, body) => api._fetch(url, { method: 'POST',  body: JSON.stringify(body) }),
    patch:  (url, body) => api._fetch(url, { method: 'PATCH', body: JSON.stringify(body) }),
    delete: (url)       => api._fetch(url, { method: 'DELETE' }),

    auth: {
        register:       (data) => api.post('/api/auth/register',        data),
        login:          (data) => api.post('/api/auth/login',           data),
        logout:         ()     => api._fetch('/api/auth/logout', { method: 'POST' }),
        forgotPassword: (data) => api.post('/api/auth/forgot-password', data),
        resetPassword:  (data) => api.post('/api/auth/reset-password',  data),
    },

    user: {
        me:             ()     => api.get('/api/user/me'),
        updateName:     (data) => api.patch('/api/user/profile',  data),
        updateEmail:    (data) => api.patch('/api/user/email',    data),
        updatePassword: (data) => api.patch('/api/user/password', data),
    },

    invitations: {
        create: (templateId, blocks) => api.post('/api/invitations', { template_id: templateId, blocks }),
        update: (id, blocks)         => api.patch(`/api/invitations/${id}`, { blocks }),
        get:    (id)                 => api.get(`/api/invitations/${id}`),
        list:   ()                   => api.get('/api/invitations'),
        delete: (id)                 => api.delete(`/api/invitations/${id}`),
        rsvp:   (id)                 => api.get(`/api/invitations/${id}/rsvp`),
        uploadImage: (formData)      => api._fetch('/api/invitations/upload-image', {
            method: 'POST',
            body: formData,
        }),
    },

    async isLoggedIn() {
        if (api._authState !== null) return api._authState;

        try {
            await api.user.me();
            api._authState = true;
            return true;
        } catch {
            api._authState = false;
            return false;
        }
    },

    async logout() {
        try {
            await api.auth.logout();
        } catch {}
        api._authState = false;
        window.location.href = '/login';
    },
};
