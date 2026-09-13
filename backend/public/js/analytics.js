const Analytics = (() => {
    function track(event, meta = {}) {
        navigator.sendBeacon
            ? navigator.sendBeacon('/api/public/analytics/event', new Blob([JSON.stringify({ event, meta })], { type: 'application/json' }))
            : fetch('/api/public/analytics/event', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event, meta }),
                keepalive: true,
            }).catch(() => {});
    }

    return { track };
})();
