// Teamsync service worker (#68): lets a phone or computer install Teamsync as an app,
// and shows offline.html when there is no connection (assets/js/app.js registers it).
//
// It never keeps a copy of a page or an API answer: every board, task and report comes from the
// server, so you always see the newest state, and no team data is stored on the phone.
// It keeps only the offline page and the files that page shows.
// Change CACHE when one of OFFLINE_FILES changes, so phones fetch the new copies.

const CACHE = 'teamsync-offline-v1';
const OFFLINE_PAGE = 'offline.html';
const OFFLINE_FILES = [OFFLINE_PAGE, 'assets/css/style.css', 'favicon.ico'];

// Full addresses, so a request can be compared with them (works in a sub-folder like XAMPP's /Teamsync/ too)
const offlineUrls = new Set(OFFLINE_FILES.map((file) => new URL(file, self.location).href));

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(OFFLINE_FILES))
            .then(() => self.skipWaiting())
    );
});

// A new version: delete the copies of the old one.
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(names.filter((name) => name !== CACHE).map((name) => caches.delete(name))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;   // forms, moves, logins and other sites: the browser handles them as always
    }
    if (request.mode === 'navigate') {
        // Opening a page: always from the server. Only when that fails (no connection), the offline page.
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_PAGE)));
        return;
    }
    if (offlineUrls.has(request.url)) {
        // A file the offline page needs: the newest from the server, the kept copy only when offline.
        event.respondWith(fetch(request).catch(() => caches.match(request)));
    }
});
