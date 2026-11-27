// Avertissement affiché sur les bulletins : des saisies faites sans connexion ne sont pas encore envoyées.
// Petit script autonome (la page des bulletins n'utilise pas le reste du mode hors ligne).
(function () {
  'use strict';
  var meta = document.querySelector('meta[name="user-id"]');
  var USER_ID = meta ? String(meta.content) : '';
  if (!USER_ID || !window.indexedDB) { return; }

  var r = indexedDB.open('ceg-offline', 1);
  r.onupgradeneeded = function () { r.result.createObjectStore('queue', { keyPath: 'uuid' }); };
  r.onerror = function () {};
  r.onsuccess = function () {
    var db = r.result;
    var req;
    try { req = db.transaction('queue', 'readonly').objectStore('queue').getAll(); } catch (e) { return; }
    req.onsuccess = function () {
      var n = (req.result || []).filter(function (i) { return String(i.user_id) === USER_ID && !i.error; }).length;
      db.close();
      if (!n) { return; }

      var st = document.createElement('style');
      st.textContent = '@media print { #offline-avertissement { display: none !important; } }';
      document.head.appendChild(st);

      var d = document.createElement('div');
      d.id = 'offline-avertissement';
      d.setAttribute('style', 'background:#fff3cd;border:1px solid #ffecb5;color:#664d03;padding:10px 14px;margin:8px;font:14px Arial,sans-serif;border-radius:6px');
      d.textContent = '⚠ ' + n + ' enregistrement(s) saisi(s) sans connexion ne sont pas encore envoyés : les notes ou absences de ce bulletin peuvent être incomplètes. Attendez l\'envoi avant d\'imprimer.';
      document.body.insertBefore(d, document.body.firstChild);
    };
  };
})();
