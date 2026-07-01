if('serviceWorker' in navigator) {
  navigator.serviceWorker.register('./sw.js');
}

var cacheName = 'LPA-v1';
var contentToCache = [
  './build/index.html',
  './build/scripts/app.js',
  './build/styles/style.css',
  './build/fonts/DMSans-Bold.woff',
  './build/fonts/DMSans-BoldItalic.woff',
  './build/fonts/DMSans-Medium.woff',
  './build/fonts/DMSans-MediumItalic.woff',
  './build/fonts/DMSans-Regular.woff',
  './build/fonts/DMSans-RegularItalic.woff',
  './favicon/apple-touch-icon.png.ico',
  './build/images/splash.jpg',
];

// add to cache
self.addEventListener('install', (e) => {
  console.log('[Service Worker] Install');
  e.waitUntil(
    caches.open(cacheName).then((cache) => {
      console.log('[Service Worker] Caching all: app shell and content');
      return cache.addAll(contentToCache);
    })
  );
});

// show from cache or download
self.addEventListener('fetch', (e) => {
  e.respondWith(
    caches.match(e.request).then((r) => {
      console.log('[Service Worker] Fetching resource: '+e.request.url);
      return r || fetch(e.request).then((response) => {
        return caches.open(cacheName).then((cache) => {
          console.log('[Service Worker] Caching new resource: '+e.request.url);
          cache.put(e.request, response.clone());
          return response;
        });
      });
    })
  );
});

// clear
// self.addEventListener('activate', (e) => {
//   e.waitUntil(
//     caches.keys().then((keyList) => {
//       return Promise.all(keyList.map((key) => {
//         if(key !== cacheName) {
//           return caches.delete(key);
//         }
//       }));
//     })
//   );
// });
