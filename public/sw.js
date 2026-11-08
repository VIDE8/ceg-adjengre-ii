// Service worker - CEG ADJENGRE II
// Pour forcer la mise à jour chez tous les utilisateurs : changer le numéro de version ci-dessous.
const CACHE_NAME = 'ceg-adjengre-ii-shell-v3';

const SHELL_ASSETS = [
  '/manifest.json',
  '/icon-192.png',
  '/icon-512.png',
  '/icon-maskable-512.png',
];

const OFFLINE_HTML = `<!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hors ligne</title>
<style>body{font-family:sans-serif;background:#f8f9fa;color:#1a252f;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px}
h1{color:#006a4e;font-size:22px}p{color:#555}</style></head>
<body><div><h1>Vous êtes hors ligne</h1><p>Vérifiez votre connexion Internet puis réessayez.</p></div></body></html>`;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) =>
      // cache: 'reload' = on ignore le cache HTTP pour récupérer les fichiers à jour
      Promise.all(
        SHELL_ASSETS.map((path) =>
          fetch(new Request(path, { cache: 'reload' }))
            .then((response) => (response.ok ? cache.put(path, response) : null))
            .catch(() => null)
        )
      )
    )
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  const isShellAsset =
    url.origin === self.location.origin &&
    SHELL_ASSETS.some((path) => url.pathname === path);

  // Manifest et icônes : réseau d'abord (toujours à jour), cache en secours hors ligne
  if (isShellAsset) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // Le reste : réseau d'abord. Hors ligne : cache s'il existe, sinon page "Hors ligne".
  event.respondWith(
    fetch(request).catch(() =>
      caches.match(request).then((cached) => {
        if (cached) return cached;
        if (request.mode === 'navigate') {
          return new Response(OFFLINE_HTML, {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
          });
        }
        return Response.error();
      })
    )
  );
});
