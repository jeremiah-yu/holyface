const CACHE = 'hf-amgs-v2';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) =>
      cache.addAll([
        './index.php',
        './login.php',
        './assets/css/site.css',
        './assets/js/site.js',
        './assets/img/hf-logo.png',
        './manifest.webmanifest',
      ]).catch(() => undefined)
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes('/api/')) return;

  event.respondWith(
    fetch(req)
      .then((res) => {
        if (
          res.ok &&
          (url.pathname.includes('/assets/') ||
            url.pathname.includes('/teacher-app/') ||
            url.pathname.endsWith('.webmanifest'))
        ) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(req, copy));
        }
        return res;
      })
      .catch(() =>
        caches.match(req).then((hit) => hit || (req.mode === 'navigate' ? caches.match('./index.php') : Promise.reject()))
      )
  );
});
