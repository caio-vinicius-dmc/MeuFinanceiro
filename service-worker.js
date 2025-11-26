// Bumped cache version to force clients to update cached service worker resources
const CACHE_NAME = 'meu-financeiro-v2';
const ASSETS = [
  '/',
  '/index.php',
  '/assets/css/style.css',
  '/assets/js/scripts.js',
  '/assets/img/favicon.png',
  '/offline.html'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        // Opcional: cachear respostas dinâmicas (comentado para evitar cache de APIs auth)
        // if (response && response.status === 200 && response.type === 'basic') {
        //   const clone = response.clone();
        //   caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        // }
        return response;
      }).catch(() => {
        // Retornar fallback offline para solicitações HTML
        if (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html')) {
          return caches.match('/offline.html');
        }
      });
    })
  );
});
