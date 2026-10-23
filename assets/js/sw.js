/* evrako — cihaz bildirimleri service worker'ı (kök kapsam: /sw.js) */
'use strict';

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

function safeUrl(raw) {
    try {
        var url = new URL(String(raw || '/panel/bildirimler'), self.location.origin);
        if (!/^https?:$/.test(url.protocol)) {
            return self.location.origin + '/panel/bildirimler';
        }
        return url.href;
    } catch (e) {
        return self.location.origin + '/panel/bildirimler';
    }
}

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'evrako', body: event.data ? event.data.text() : '' };
    }

    var title = String(data.title || 'evrako');
    var options = {
        body: String(data.body || ''),
        icon: data.icon ? safeUrl(data.icon) : undefined,
        badge: data.icon ? safeUrl(data.icon) : undefined,
        image: data.image ? safeUrl(data.image) : undefined,
        tag: data.tag ? String(data.tag) : undefined,
        renotify: false,
        data: { url: safeUrl(data.url) }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var target = safeUrl(event.notification.data && event.notification.data.url);

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url === target && 'focus' in client) {
                    return client.focus();
                }
            }
            for (var j = 0; j < clientList.length; j++) {
                var openClient = clientList[j];
                if ('navigate' in openClient && 'focus' in openClient) {
                    return openClient.navigate(target).then(function (c) { return c ? c.focus() : null; });
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }
            return null;
        })
    );
});
