const CACHE_NAME = 'avaliacao-cache-v2';
const urlsToCache = [
  '/avaliacao-saas/screens/screen1.php',
  '/avaliacao-saas/screens/screen2.php',
  '/avaliacao-saas/screens/screen3.php',
  '/avaliacao-saas/screens/screen4.php',
  '/avaliacao-saas/assets/manifest.json',
  '/avaliacao-saas/assets/images/funcionarios/default.jpg',
  '/avaliacao-saas/assets/images/pwa/icon-512x512.png',
  'https://cdn.tailwindcss.com',
  'https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Cache hit - return response
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});

self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});