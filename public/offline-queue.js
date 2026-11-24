// File d'attente hors ligne - CEG ADJENGRE II
// Les actions faites sans connexion sont gardées dans le téléphone (IndexedDB),
// puis envoyées au serveur dès que la connexion revient.
(function () {
  'use strict';

  var DB_NAME = 'ceg-offline';
  var STORE = 'queue';
  var meta = document.querySelector('meta[name="user-id"]');
  var USER_ID = meta ? String(meta.content) : '';
  var etat = { session: true };

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 3) | 8).toString(16);
    });
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function ouvrir() {
    return new Promise(function (resolve, reject) {
      var r = indexedDB.open(DB_NAME, 1);
      r.onupgradeneeded = function () { r.result.createObjectStore(STORE, { keyPath: 'uuid' }); };
      r.onsuccess = function () { resolve(r.result); };
      r.onerror = function () { reject(r.error); };
    });
  }

  function tx(mode, fn) {
    return ouvrir().then(function (db) {
      return new Promise(function (resolve, reject) {
        var t = db.transaction(STORE, mode);
        var req = fn(t.objectStore(STORE));
        t.oncomplete = function () { db.close(); resolve(req ? req.result : undefined); };
        t.onerror = t.onabort = function () { db.close(); reject(t.error); };
      });
    });
  }

  // Seules les actions de l'utilisateur connecté sont envoyées avec son compte
  function toutes() {
    return tx('readonly', function (s) { return s.getAll(); }).then(function (a) {
      return (a || [])
        .filter(function (i) { return String(i.user_id) === USER_ID; })
        .sort(function (x, y) { return x.created_at < y.created_at ? -1 : 1; });
    });
  }

  function supprimer(id) {
    return tx('readwrite', function (s) { return s.delete(id); }).then(majPanneau);
  }

  function marquerErreur(id, message) {
    return tx('readwrite', function (s) {
      var req = s.get(id);
      req.onsuccess = function () {
        if (req.result) { req.result.error = message || 'Refusé par le serveur.'; s.put(req.result); }
      };
      return req;
    });
  }

  function fetchTimeout(url, options, ms) {
    var ctrl = new AbortController();
    var t = setTimeout(function () { ctrl.abort(); }, ms);
    options = options || {};
    options.signal = ctrl.signal;
    return fetch(url, options).then(
      function (r) { clearTimeout(t); return r; },
      function (e) { clearTimeout(t); throw e; }
    );
  }

  // Vraie connexion au serveur (navigator.onLine ment quand le réseau est présent mais sans données)
  function enLigne() {
    return fetchTimeout('/offline/ping', { cache: 'no-store' }, 3000)
      .then(function (r) { return r.ok; })
      .catch(function () { return false; });
  }

  function enqueue(type, payload, label) {
    var item = {
      uuid: uuid(),
      user_id: USER_ID,
      type: type,
      payload: payload,
      label: label || type,
      created_at: new Date().toISOString()
    };
    return tx('readwrite', function (s) { return s.put(item); }).then(function () {
      majPanneau();
      return item.uuid;
    });
  }

  // Envoie la file d'attente. Renvoie les résultats par uuid. Les envois se font l'un après l'autre.
  var chaine = Promise.resolve();
  function flush() {
    var p = chaine.then(lancer);
    chaine = p.catch(function () {});
    return p;
  }

  async function lancer() {
    var resultats = {};
    try {
      var items = (await toutes()).filter(function (i) { return !i.error; });
      if (!items.length) { return resultats; }

      var tk = await fetchTimeout('/offline/token', {
        credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' }
      }, 10000);
      if (tk.status === 401 || tk.status === 419 || tk.redirected) { etat.session = false; return resultats; }
      if (!tk.ok) { return resultats; }
      var info = await tk.json();
      if (String(info.user_id) !== USER_ID) { return resultats; }

      var res = await fetchTimeout('/offline/sync', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': info.token
        },
        body: JSON.stringify({
          actions: items.map(function (i) { return { uuid: i.uuid, type: i.type, payload: i.payload }; })
        })
      }, 30000);
      if (res.status === 401 || res.status === 419) { etat.session = false; return resultats; }
      if (!res.ok) { return resultats; }

      var data = await res.json();
      etat.session = true;
      for (var k = 0; k < data.results.length; k++) {
        var r = data.results[k];
        resultats[r.uuid] = r;
        if (r.status === 'ok' || r.status === 'duplicate') { await tx('readwrite', function (s) { return s.delete(r.uuid); }); }
        else if (r.status === 'error') { await marquerErreur(r.uuid, r.message); }
      }
    } catch (e) {
      // Pas de réseau : tout reste dans la file, on réessaiera
    }
    majPanneau();
    return resultats;
  }

  async function majPanneau() {
    var zone = document.getElementById('offline-panel');
    if (!zone) { return; }
    var items;
    try { items = await toutes(); } catch (e) { return; }
    var attente = items.filter(function (i) { return !i.error; });
    var erreurs = items.filter(function (i) { return i.error; });
    var html = '';

    if (attente.length) {
      html += '<div class="alert alert-warning py-2 small shadow mb-2" style="pointer-events:auto">⏳ '
        + attente.length + ' action(s) en attente d\'envoi'
        + (etat.session === false ? ' : reconnectez-vous pour les envoyer.' : ' : envoi dès le retour de la connexion.')
        + '</div>';
    }
    erreurs.forEach(function (i) {
      html += '<div class="alert alert-danger py-2 small shadow mb-2 d-flex justify-content-between align-items-start gap-2" style="pointer-events:auto">'
        + '<span><b>Refusé :</b> ' + esc(i.label) + '<br>' + esc(i.error) + '</span>'
        + '<button type="button" class="btn btn-sm btn-outline-danger" data-supprimer="' + esc(i.uuid) + '">OK</button></div>';
    });
    zone.innerHTML = html;
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-supprimer]') : null;
    if (b) { supprimer(b.getAttribute('data-supprimer')); }
  });

  window.addEventListener('online', flush);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { flush(); } });
  window.addEventListener('load', function () { majPanneau(); flush(); });
  setInterval(flush, 30000);

  window.OfflineQueue = { enqueue: enqueue, flush: flush, enLigne: enLigne, supprimer: supprimer, toutes: toutes };
})();
