// Service worker - CEG ADJENGRE II (v8)
const VERSION = 'v8';
const ASSETS_CACHE = `ceg-assets-${VERSION}`;
const PAGES_CACHE = `ceg-pages-${VERSION}`;
const KEEP = [ASSETS_CACHE, PAGES_CACHE];

const SHELL_ASSETS = [
  '/manifest.json', '/icon-192.png', '/icon-512.png', '/icon-maskable-512.png',
  '/offline-queue.js',
];
// Pages à ne jamais mettre en cache
// Bootstrap, flatpickr et polices viennent d'autres sites : on les garde aussi pour l'affichage hors ligne
const CDN_HOSTS = ['cdn.jsdelivr.net', 'fonts.googleapis.com', 'fonts.gstatic.com'];

const NEVER_CACHE = ['/login', '/logout', '/api/', '/offline/'];

const OFFLINE_HTML = `<!doctype html><html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Hors ligne</title>
<style>body{font-family:sans-serif;background:#f8f9fa;color:#1a252f;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px}h1{color:#006a4e;font-size:22px}p{color:#555}</style></head>
<body><div><h1>Page non disponible hors ligne</h1><p>Ouvrez cette page une fois avec Internet, elle sera ensuite disponible sans connexion.</p></div></body></html>`;

const NOMS_PAGES = {
  '/appels': 'Appel',
  '/notes': 'Notes',
  '/notes/saisie': 'Saisie des notes',
  '/retards': 'Retards',
  '/permissions': 'Permissions',
  '/eleves': 'Registre des élèves',
};

// Page de secours : propose les pages déjà ouvertes avec Internet
async function offlinePage() {
  const cache = await caches.open(PAGES_CACHE);
  const vues = new Set();
  const liens = [];
  for (const req of await cache.keys()) {
    const u = new URL(req.url);
    const rep = await cache.match(req);
    if (!rep || rep.status !== 200 || vues.has(u.pathname)) continue;
    vues.add(u.pathname);
    const href = (u.pathname + u.search).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    const nom = (NOMS_PAGES[u.pathname] || u.pathname).replace(/</g, '&lt;');
    liens.push(`<li><a href="${href}">${nom}</a></li>`);
  }
  const suite = liens.length
    ? `<p>Pages disponibles sans connexion :</p><ul style="list-style:none;padding:0;font-size:18px;line-height:2">${liens.join('')}</ul>`
    : '';
  const html = OFFLINE_HTML.replace('</div></body>', suite + '</div></body>');
  return new Response(html, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(ASSETS_CACHE).then((cache) =>
      Promise.all(SHELL_ASSETS.map((p) =>
        fetch(new Request(p, { cache: 'reload' }))
          .then((r) => (r.ok ? cache.put(p, r) : null)).catch(() => null)))
    )
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => !KEEP.includes(k)).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

// Vider les pages mises en cache à la déconnexion (appareils partagés)
self.addEventListener('message', (event) => {
  if (event.data === 'CLEAR_PAGES') event.waitUntil(caches.delete(PAGES_CACHE));
});

function fetchWithTimeout(request, ms) {
  return new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('timeout')), ms);
    fetch(request).then(
      (r) => { clearTimeout(t); resolve(r); },
      (e) => { clearTimeout(t); reject(e); }
    );
  });
}

// Fichiers statiques (Vite : noms avec hash) : cache d'abord
async function assetStrategy(request) {
  const cache = await caches.open(ASSETS_CACHE);
  const cached = await cache.match(request);
  if (cached) return cached;
  try {
    const r = await fetch(request);
    if (r.ok || r.type === 'opaque') cache.put(request, r.clone());
    return r;
  } catch (e) {
    return Response.error();
  }
}

// Manifest et icônes : réseau d'abord
async function shellStrategy(request) {
  const cache = await caches.open(ASSETS_CACHE);
  try {
    const r = await fetch(request);
    if (r.ok) cache.put(request, r.clone());
    return r;
  } catch (e) {
    return (await cache.match(request)) || Response.error();
  }
}

// Pages : réseau d'abord, mais si la connexion est lente (4 s), on sert la copie
async function pageStrategy(request) {
  const cache = await caches.open(PAGES_CACHE);
  const cached = await cache.match(request);
  try {
    const r = await (cached ? fetchWithTimeout(request, 4000) : fetch(request));
    if (r.ok) {
      const finale = new URL(r.url);
      const interdit = NEVER_CACHE.some((p) => finale.pathname.startsWith(p));
      if (!interdit) {
        if (r.redirected) {
          // Ex. "/" renvoie vers /appels : on garde la page à son adresse finale
          // et un renvoi depuis l'adresse de départ (l'icône de l'appli ouvre "/")
          const corps = await r.clone().blob();
          const type = r.headers.get('Content-Type') || 'text/html; charset=utf-8';
          await cache.put(new Request(r.url), new Response(corps, { status: 200, headers: { 'Content-Type': type } }));
          await cache.put(request, Response.redirect(r.url, 302));
        } else {
          await cache.put(request, r.clone());
        }
      }
    }
    return r;
  } catch (e) {
    return cached
      || (await cache.match(request, { ignoreSearch: true }))
      || (await offlinePage());
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (CDN_HOSTS.includes(url.hostname)) {
    event.respondWith(assetStrategy(request));
    return;
  }
  if (url.origin !== self.location.origin) return;
  if (NEVER_CACHE.some((p) => url.pathname.startsWith(p))) return;

  if (SHELL_ASSETS.includes(url.pathname)) {
    event.respondWith(shellStrategy(request));
  } else if (
    url.pathname.startsWith('/build/') ||
    ['style', 'script', 'font', 'image'].includes(request.destination)
  ) {
    event.respondWith(assetStrategy(request));
  } else if (request.mode === 'navigate' || request.headers.get('X-Precache') === '1') {
    event.respondWith(pageStrategy(request));
  }
});
