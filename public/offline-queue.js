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
      var unOk = false;
      for (var k = 0; k < data.results.length; k++) {
        var r = data.results[k];
        resultats[r.uuid] = r;
        if (r.status === 'ok' || r.status === 'duplicate') {
          unOk = true;
          await tx('readwrite', function (s) { return s.delete(r.uuid); });
        } else if (r.status === 'error') { await marquerErreur(r.uuid, r.message); }
      }
      // La page ouverte vient de changer côté serveur : on renouvelle sa copie gardée pour le hors ligne
      if (unOk) { telecharger(location.pathname + location.search); }
    } catch (e) {
      // Pas de réseau : tout reste dans la file, on réessaiera
    }
    majPanneau();
    return resultats;
  }

  // ---------- Pages gardées pour le hors ligne ----------
  function lireListe(cle) {
    try { return JSON.parse(localStorage.getItem(cle) || '[]'); } catch (e) { return []; }
  }

  function registre() { return lireListe('precache-registre:' + USER_ID); }

  function enregistrerUrls(urls) {
    var liste = registre();
    var change = false;
    urls.forEach(function (u) { if (liste.indexOf(u) < 0) { liste.push(u); change = true; } });
    if (change) { try { localStorage.setItem('precache-registre:' + USER_ID, JSON.stringify(liste)); } catch (e) {} }
  }

  function horodatage(url) {
    try { return parseInt(localStorage.getItem('precache:' + USER_ID + ':' + url) || '0', 10); } catch (e) { return 0; }
  }

  function urlsMeta(nom) {
    var m = document.querySelector('meta[name="' + nom + '"]');
    return m && m.content ? m.content.split('|').filter(Boolean) : [];
  }

  function sansDoublons(liste) {
    return liste.filter(function (u, i) { return liste.indexOf(u) === i; });
  }

  // Télécharge une page pour que le service worker en garde une copie
  async function telecharger(url) {
    try {
      var r = await fetchTimeout(url, {
        credentials: 'same-origin',
        headers: { 'X-Precache': '1', 'Accept': 'text/html' }
      }, 25000);
      if (r.ok) { try { localStorage.setItem('precache:' + USER_ID + ':' + url, String(Date.now())); } catch (e) {} }
    } catch (e) { /* tant pis, on réessaiera plus tard */ }
  }

  // Télécharge des pages à l'avance (avec Internet). Chaque page n'est renouvelée qu'une fois par demi-heure,
  // sauf si on le demande. « progression » reçoit (faites, total).
  async function precharger(urls, forcer, progression) {
    enregistrerUrls(urls);
    try {
      if (!(await enLigne())) { return; }
      for (var i = 0; i < urls.length; i++) {
        if (forcer || Date.now() - horodatage(urls[i]) >= 30 * 60 * 1000) { await telecharger(urls[i]); }
        if (progression) { progression(i + 1, urls.length); }
      }
    } catch (e) {}
    majEtat();
  }

  async function majPanneau() {
    window.dispatchEvent(new CustomEvent('offline-queue-change'));
    majEtat();
    avertirIncomplet();
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
    zone.style.paddingBottom = '48px';
  }

  // ---------- Formulaires utilisables sans connexion ----------
  // Chaque formulaire reconnu est envoyé dans la file d'attente au lieu d'être posté directement.
  // Les modifications et suppressions (champ _method) restent en ligne.

  // Formulaires simples du directeur : le serveur rejoue la méthode store() du contrôleur
  var FORMULAIRES = {
    '/trimestres': { type: 'trimestre', nom: 'Trimestre' },
    '/annees-scolaires': { type: 'annee', nom: 'Année scolaire' },
    '/classes': { type: 'classe', nom: 'Classe' },
    '/matieres': { type: 'matiere', nom: 'Matière' },
    '/affectations': { type: 'affectation', nom: 'Affectation' }
  };

  // Formulaires liés à une classe ou à une absence (le numéro vient de l'adresse)
  var PATRONS = [
    { re: /^\/classes\/(\d+)\/appel$/, type: 'presence', nom: 'Appel', classe: true, brouillon: true, cle: function () { return aujourdhui(); }, dateDuJour: true },
    { re: /^\/classes\/(\d+)\/conseil$/, type: 'conseil', nom: 'Conseil de classe', classe: true, brouillon: true, cle: function (form) { return form.elements['trimestre_id'] ? form.elements['trimestre_id'].value : ''; } },
    { re: /^\/classes\/(\d+)\/comportements$/, type: 'comportement', nom: 'Comportement', classe: true, dateDuJour: true, vider: true },
    { re: /^\/comportements$/, type: 'comportement_global', nom: 'Comportement', dateDuJour: true, vider: true },
    { re: /^\/absences-heures\/(\d+)\/justifier$/, type: 'justification', nom: 'Justification', absence: true }
  ];

  function aujourdhui() {
    var d = new Date();
    var p = function (n) { return ('0' + n).slice(-2); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
  }

  function afficherMessage(form, type, texte) {
    var zone = form.parentNode.querySelector('.offline-msg');
    if (!zone) {
      zone = document.createElement('div');
      zone.className = 'offline-msg';
      form.parentNode.insertBefore(zone, form);
    }
    zone.innerHTML = '<div class="alert alert-' + type + ' small py-2">' + esc(texte) + '</div>';
  }

  // Champs du formulaire à plat : { 'decisions[5][observation]': 'texte', ... }
  function champsPlats(form) {
    var plat = {};
    new FormData(form).forEach(function (v, k) {
      if (k !== '_token' && k !== '_method' && typeof v === 'string') { plat[k] = v; }
    });
    return plat;
  }

  // 'decisions[5][observation]' -> { decisions: { 5: { observation: ... } } }
  function versImbrique(plat) {
    var racine = {};
    Object.keys(plat).forEach(function (nom) {
      var chemin = nom.replace(/\]/g, '').split('[');
      var cur = racine;
      for (var i = 0; i < chemin.length - 1; i++) {
        if (typeof cur[chemin[i]] !== 'object' || cur[chemin[i]] === null) { cur[chemin[i]] = {}; }
        cur = cur[chemin[i]];
      }
      cur[chemin[chemin.length - 1]] = plat[nom];
    });
    return racine;
  }

  // Quelle définition correspond à ce formulaire ? (null si aucune)
  function definitionPour(form) {
    if ((form.method || '').toLowerCase() !== 'post') { return null; }
    if (form.querySelector('input[name="_method"]')) { return null; }
    var chemin;
    try { chemin = new URL(form.action, location.href).pathname.replace(/\/+$/, ''); } catch (e) { return null; }

    var simple = FORMULAIRES[chemin];
    if (simple) { return { def: simple, forme: 'simple', id: null }; }

    for (var i = 0; i < PATRONS.length; i++) {
      var m = PATRONS[i].re.exec(chemin);
      if (m) { return { def: PATRONS[i], forme: 'classe', id: m[1] }; }
    }
    return null;
  }

  function construirePayload(form, cible) {
    var plat = champsPlats(form);
    if (cible.forme === 'simple') { return plat; }

    var def = cible.def;
    var p = { champs: null, plat: plat };
    if (def.classe) { p.classe_id = cible.id; }
    if (def.absence) { p.absence_id = cible.id; }
    if (def.dateDuJour) { plat.date = aujourdhui(); }    // la vraie date du jour, même si l'envoi a lieu plus tard
    if (def.cle) { p.cle = def.cle(form); }
    p.champs = versImbrique(plat);
    return p;
  }

  function libelle(form, def) {
    var titre = document.querySelector('h3, h4, h5');
    var texte = titre ? titre.textContent.replace(/\s+/g, ' ').trim() : '';
    if (def.absence) {
      var ligne = form.closest('tr');
      texte = ligne ? ligne.textContent.replace(/\s+/g, ' ').trim().slice(0, 60) : '';
    }
    return def.nom + (texte ? ' : ' + texte.slice(0, 70) : '');
  }

  async function envoyerFormulaire(form, cible) {
    var def = cible.def;
    var bouton = form.querySelector('button[type=submit], input[type=submit]');
    var texteBouton = bouton ? bouton.textContent : '';
    var verrouille = false;
    if (bouton) { bouton.disabled = true; bouton.textContent = 'Enregistrement…'; }
    try {
      var payload = construirePayload(form, cible);
      var resume = cible.forme === 'simple'
        ? Object.keys(payload).map(function (k) { return payload[k]; }).filter(Boolean).slice(0, 3).join(' · ')
        : '';
      var etiquette = cible.forme === 'simple' ? def.nom + ' : ' + resume : libelle(form, def);

      var id = await enqueue(def.type, payload, etiquette);
      var resultats = await flush();
      var r = resultats[id];

      if (r && (r.status === 'ok' || r.status === 'duplicate')) {
        afficherMessage(form, 'success', r.message || (def.nom + ' enregistré(e).'));
        if (cible.forme === 'simple' || def.vider) { form.reset(); }
        setTimeout(function () { location.reload(); }, 1200);
      } else if (r && r.status === 'error') {
        await supprimer(id);
        afficherMessage(form, 'danger', r.message);
      } else {
        afficherMessage(form, 'warning', 'Pas de connexion : c\'est gardé sur ce téléphone et sera envoyé automatiquement dès que la connexion reviendra.');
        if (cible.forme === 'simple' || def.vider) { form.reset(); }
        if (def.absence && bouton) {
          // La justification est notée : on évite de la renvoyer deux fois par erreur
          bouton.textContent = 'En attente ⏳';
          verrouille = true;
        }
      }
    } catch (e) {
      afficherMessage(form, 'danger', 'Impossible d\'enregistrer : ' + e.message);
    } finally {
      if (bouton && !verrouille) { bouton.disabled = false; bouton.textContent = texteBouton; }
    }
  }

  function brancherFormulaires() {
    document.querySelectorAll('form').forEach(function (form) {
      var cible = definitionPour(form);
      if (!cible) { return; }
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        envoyerFormulaire(form, cible);
      });
    });
    restaurerBrouillons();
  }

  // Une grille (appel, conseil de classe) saisie sans connexion et pas encore envoyée est remise à l'écran :
  // la copie de la page gardée dans le téléphone peut être plus ancienne.
  async function restaurerBrouillons() {
    var items;
    try { items = (await toutes()).filter(function (i) { return !i.error; }); } catch (e) { return; }
    if (!items.length) { return; }

    document.querySelectorAll('form').forEach(function (form) {
      var cible = definitionPour(form);
      if (!cible || !cible.def.brouillon) { return; }

      var mes = items.filter(function (i) {
        return i.type === cible.def.type &&
          String(i.payload.classe_id) === String(cible.id) &&
          String(i.payload.cle) === String(cible.def.cle(form));
      });
      if (!mes.length) { return; }

      var plat = mes[mes.length - 1].payload.plat || {};
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name || el.disabled || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') { return; }
        var present = Object.prototype.hasOwnProperty.call(plat, el.name);
        if (el.type === 'checkbox') { el.checked = present; }
        else if (el.type === 'radio') { if (present) { el.checked = plat[el.name] === el.value; } }
        else if (present) { el.value = plat[el.name]; }
      });
      afficherMessage(form, 'warning', 'Une saisie faite sans connexion attend son envoi : elle est affichée ci-dessous.');
    });
  }

  // Pages à garder pour le hors ligne, indiquées par la page elle-même selon le rôle
  function precacheDeLaPage() {
    var m = document.querySelector('meta[name="offline-precache"]');
    if (m && m.content) { precharger(m.content.split('|').filter(Boolean)); }
  }

  // ---------- Pastille d'état : connexion, pages prêtes, actions en attente ----------
  var connecte = true;
  var preparation = null;   // texte d'avancement pendant la préparation

  function ilYa(t) {
    var min = Math.round((Date.now() - t) / 60000);
    if (min < 1) { return 'à l\'instant'; }
    if (min < 60) { return 'il y a ' + min + ' min'; }
    var h = Math.round(min / 60);
    return h < 48 ? 'il y a ' + h + ' h' : 'il y a ' + Math.round(h / 24) + ' j';
  }

  function toutesLesPages() {
    return sansDoublons(urlsMeta('offline-precache').concat(registre(), urlsMeta('offline-precache-lourd')));
  }

  function etatPages() {
    var liste = toutesLesPages();
    var prets = 0;
    var plusVieux = 0;
    liste.forEach(function (u) {
      var t = horodatage(u);
      if (t) { prets++; if (!plusVieux || t < plusVieux) { plusVieux = t; } }
    });
    return { total: liste.length, prets: prets, plusVieux: plusVieux };
  }

  function creerPastille() {
    if (!USER_ID || document.getElementById('offline-etat')) { return; }
    var boite = document.createElement('div');
    boite.id = 'offline-etat';
    boite.className = 'd-print-none';
    boite.style.cssText = 'position:fixed;right:8px;bottom:8px;z-index:1075;font-size:12px;width:290px;max-width:calc(100% - 16px)';
    boite.innerHTML =
      '<div id="offline-etat-detail" class="card shadow border-0 mb-2" style="display:none"><div class="card-body p-3 small"></div></div>' +
      '<div class="text-end"><button type="button" id="offline-etat-bouton" class="btn btn-sm btn-dark rounded-pill shadow">' +
      '<span id="offline-pastille" style="display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;background:#198754"></span>' +
      '<span id="offline-etat-texte">En ligne</span></button></div>';
    document.body.appendChild(boite);
    majEtat();
  }

  async function majEtat() {
    var boite = document.getElementById('offline-etat');
    if (!boite) { return; }
    var attente = 0;
    var enAttenteListe = [];
    try { enAttenteListe = (await toutes()).filter(function (i) { return !i.error; }); attente = enAttenteListe.length; } catch (e) {}
    var p = etatPages();
    var toutPret = p.total > 0 && p.prets >= p.total;

    boite.querySelector('#offline-pastille').style.background = !connecte ? '#6c757d' : (toutPret ? '#198754' : '#fd7e14');
    boite.querySelector('#offline-etat-texte').textContent =
      (connecte ? 'En ligne' : 'Hors ligne') + (attente ? ' · ' + attente + ' en attente' : '');

    var detail = boite.querySelector('#offline-etat-detail .card-body');
    if (document.getElementById('offline-etat-detail').style.display === 'none') { return; }
    detail.innerHTML =
      '<div class="mb-1"><b>Connexion :</b> ' + (connecte ? 'en ligne' : 'hors ligne') + '</div>' +
      '<div class="mb-1"><b>Pages prêtes sans connexion :</b> ' + p.prets + ' / ' + p.total + '</div>' +
      (p.plusVieux ? '<div class="mb-1"><b>Plus ancienne copie :</b> ' + ilYa(p.plusVieux) + '</div>' : '') +
      '<div class="mb-2"><b>En attente d\'envoi :</b> ' + attente +
      (attente ? '<ul class="mb-0 ps-3">' + enAttenteListe.slice(0, 8).map(function (i) { return '<li>' + esc(i.label) + '</li>'; }).join('') +
        (attente > 8 ? '<li>… et ' + (attente - 8) + ' autre(s)</li>' : '') + '</ul>' : '') + '</div>' +
      (preparation ? '<div class="alert alert-info py-1 mb-2">' + esc(preparation) + '</div>' : '') +
      '<div class="d-grid gap-2">' +
      '<button type="button" class="btn btn-sm btn-success" data-offline-action="preparer"' + (!connecte || preparation ? ' disabled' : '') + '>Préparer le mode hors ligne</button>' +
      (attente ? '<button type="button" class="btn btn-sm btn-outline-primary" data-offline-action="envoyer"' + (!connecte ? ' disabled' : '') + '>Envoyer maintenant</button>' : '') +
      '<button type="button" class="btn btn-sm btn-outline-secondary" data-offline-action="fermer">Fermer</button></div>' +
      (!connecte ? '<div class="text-muted mt-2">La préparation demande Internet.</div>' : '');
  }

  async function preparer() {
    if (preparation) { return; }
    preparation = 'Préparation…';
    majEtat();
    await precharger(toutesLesPages(), true, function (fait, total) {
      preparation = 'Préparation : ' + fait + ' / ' + total;
      majEtat();
    });
    preparation = null;
    majEtat();
  }

  async function verifierConnexion() {
    var avant = connecte;
    connecte = await enLigne();
    if (connecte !== avant) { majEtat(); if (connecte) { flush(); } }
  }

  document.addEventListener('click', function (ev) {
    var cible = ev.target.closest ? ev.target.closest('#offline-etat-bouton, [data-offline-action]') : null;
    if (!cible) { return; }
    var detail = document.getElementById('offline-etat-detail');
    var action = cible.getAttribute('data-offline-action');
    if (cible.id === 'offline-etat-bouton') {
      detail.style.display = detail.style.display === 'none' ? '' : 'none';
      majEtat();
    } else if (action === 'fermer') {
      detail.style.display = 'none';
    } else if (action === 'preparer') {
      preparer();
    } else if (action === 'envoyer') {
      flush();
    }
  });

  // ---------- Avertissement sur les pages de résultats ----------
  var PAGES_CALCULEES = /\/(bulletins|recapitulatif|absences|statistiques|conseil|comportements)(\/|$)/;

  async function avertirIncomplet() {
    if (!PAGES_CALCULEES.test(location.pathname)) { return; }
    var items;
    try { items = (await toutes()).filter(function (i) { return !i.error; }); } catch (e) { return; }
    var ancien = document.getElementById('offline-avertissement');
    if (ancien) { ancien.parentNode.removeChild(ancien); }
    if (!items.length) { return; }
    if (!document.getElementById('offline-style-impression')) {
      var st = document.createElement('style');
      st.id = 'offline-style-impression';
      st.textContent = '@media print { .offline-avertissement { display: none !important; } }';
      document.head.appendChild(st);
    }
    var d = document.createElement('div');
    d.id = 'offline-avertissement';
    d.className = 'alert alert-warning m-2 small offline-avertissement';
    d.textContent = '⚠ ' + items.length + ' enregistrement(s) saisi(s) sans connexion ne sont pas encore envoyés : ces chiffres peuvent être incomplets. Attendez l\'envoi avant d\'imprimer.';
    document.body.insertBefore(d, document.body.firstChild);
  }

  window.addEventListener('offline', function () { connecte = false; majEtat(); });
  window.addEventListener('online', verifierConnexion);
  setInterval(function () { if (!document.hidden) { verifierConnexion(); majEtat(); } }, 20000);

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-supprimer]') : null;
    if (b) { supprimer(b.getAttribute('data-supprimer')); }
  });

  window.addEventListener('online', flush);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { flush(); } });
  window.addEventListener('load', function () {
    majPanneau();
    flush();
    brancherFormulaires();
    precacheDeLaPage();
    creerPastille();
    verifierConnexion();
    avertirIncomplet();
  });
  setInterval(flush, 30000);

  window.OfflineQueue = { enqueue: enqueue, flush: flush, enLigne: enLigne, supprimer: supprimer, toutes: toutes, precharger: precharger, preparer: preparer };
})();
