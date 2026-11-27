// Retards et permissions du surveillant, utilisables sans connexion.
// - L'enregistrement part dans la file d'attente du téléphone, puis au serveur dès que possible.
// - Sans connexion, le billet s'imprime depuis les données saisies (numéro officiel attribué à l'envoi).
(function () {
  'use strict';

  var OQ = window.OfflineQueue;
  var ECOLE = window.ECOLE || {};
  if (!OQ) { return; }

  var TIRET = '\u2013';

  // ---------- Petits outils ----------
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function pad2(n) { return ('0' + n).slice(-2); }

  function aujourdhui() {
    var d = new Date();
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  function maintenant() {
    var d = new Date();
    return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
  }

  function dateFr(iso) {
    var p = String(iso || '').split('-');
    return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : (iso || '');
  }

  function formulairePost(finAction) {
    var formes = document.querySelectorAll('form');
    for (var i = 0; i < formes.length; i++) {
      var f = formes[i];
      if ((f.method || '').toLowerCase() === 'post' && f.getAttribute('action') && f.getAttribute('action').slice(-finAction.length) === finAction) { return f; }
    }
    return null;
  }

  // Le texte d'une option est « NOM Prénom – 6ème A (matricule) » : on en tire le nom et la classe
  function eleveDepuisOption(form) {
    var liste = form.elements['eleve_id'];
    var option = liste && liste.options && liste.selectedIndex >= 0 ? liste.options[liste.selectedIndex] : null;
    if (!option) { return { nom: '', classe: '', matricule: '' }; }
    var texte = option.text;
    var i = texte.lastIndexOf(' (');
    var matricule = i > 0 ? texte.slice(i + 2).replace(/\)$/, '') : '';
    var reste = i > 0 ? texte.slice(0, i) : texte;
    var j = reste.indexOf(' ' + TIRET + ' ');
    var nom = j >= 0 ? reste.slice(0, j) : reste;
    var classe = j >= 0 ? reste.slice(j + 3) : '';
    if (classe === 'sans classe') { classe = '—'; }
    return { nom: nom, classe: classe, matricule: matricule };
  }

  function lireFormulaire(form) {
    var d = {};
    new FormData(form).forEach(function (v, k) {
      if (k !== '_token' && k !== '_method' && typeof v === 'string') { d[k] = v; }
    });
    return d;
  }

  function message(form, type, texte, html) {
    var zone = document.getElementById('offline-msg-surveillant');
    if (!zone) {
      zone = document.createElement('div');
      zone.id = 'offline-msg-surveillant';
      form.parentNode.insertBefore(zone, form);
    }
    zone.innerHTML = '<div class="alert alert-' + type + ' small py-2">' + esc(texte) + (html || '') + '</div>';
    zone.scrollIntoView({ block: 'nearest' });
  }

  // ---------- Billet imprimé sans connexion ----------
  var CSS = '*{box-sizing:border-box}body{margin:0;background:#e9ecef;font-family:"Times New Roman",Times,serif;color:#000}' +
    '.barre-outils{background:#212529;color:#fff;padding:10px 20px;display:flex;gap:16px;align-items:center;font-family:Arial,sans-serif;font-size:14px}' +
    '.barre-outils button{background:#ffc107;border:0;padding:7px 16px;font-weight:bold;border-radius:4px;cursor:pointer;font-size:14px}' +
    '.feuille{width:148mm;min-height:210mm;margin:14px auto;background:#fff;padding:8mm;box-shadow:0 2px 10px rgba(0,0,0,.2)}' +
    '.billet{height:93mm;border:1.5px solid #000;padding:4mm 6mm;font-size:10.5pt;position:relative}' +
    '.coupure{text-align:center;font-size:8pt;letter-spacing:2px;margin:4mm 0;border-top:1.5px dashed #555;padding-top:1mm;color:#555}' +
    '.entete{display:flex;align-items:center;gap:4mm;border-bottom:1px solid #000;padding-bottom:2mm}.entete img{height:15mm}' +
    '.entete .ecole{flex:1;text-align:center;font-size:8pt;line-height:1.25}.entete .ecole b{font-size:10pt}' +
    '.titre{text-align:center;font-weight:bold;font-size:14pt;margin:3mm 0 2mm;letter-spacing:1px}' +
    '.numero{text-align:center;font-size:9pt;margin-bottom:3mm}.ligne{margin:1.6mm 0}' +
    '.ligne b{display:inline-block;min-width:32mm}.autorisation{margin-top:3mm;font-weight:bold;text-align:center}' +
    '.signature{position:absolute;right:6mm;bottom:3mm;text-align:center;font-size:9pt}' +
    '.parent{position:absolute;left:6mm;bottom:9mm;font-size:9pt}' +
    '.exemplaire{position:absolute;left:6mm;bottom:3mm;font-size:8pt;font-style:italic}' +
    '@page{size:A5 portrait;margin:0}@media print{body{background:#fff}.no-print{display:none!important}.feuille{margin:0;box-shadow:none}}';

  function pageBillet(def) {
    var logo = ECOLE.logo ? '<img src="' + esc(ECOLE.logo) + '" alt="">' : '';
    var billets = def.exemplaires.map(function (ex, i) {
      return '<div class="billet"><div class="entete">' + logo +
        '<div class="ecole">' + esc(ECOLE.republique) + ' ' + TIRET + ' ' + esc(ECOLE.devise_nationale) + '<br>' +
        esc(ECOLE.ministere) + '<br><b>' + esc(ECOLE.nom) + '</b></div></div>' +
        '<div class="titre">' + esc(def.titre) + '</div>' +
        '<div class="numero">' + esc(def.numero) + '</div>' +
        def.lignes.map(function (l) { return '<div class="ligne"><b>' + esc(l[0]) + '</b> ' + esc(l[1]) + '</div>'; }).join('') +
        '<div class="autorisation">' + esc(def.autorisation) + '</div>' +
        (def.parent ? '<div class="parent">Signature du parent / tuteur :</div>' : '') +
        '<div class="exemplaire">' + esc(ex) + '</div>' +
        '<div class="signature">Le surveillant<br><br>' + esc(ECOLE.utilisateur || '') + '</div></div>' +
        (i === 0 ? '<div class="coupure">&#9986; &nbsp; - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>' : '');
    }).join('');

    return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>' + esc(def.titre) + '</title><style>' + CSS + '</style></head><body>' +
      '<div class="barre-outils no-print"><button type="button" onclick="window.print()">Imprimer</button>' +
      '<span>Établi hors connexion : le numéro officiel est attribué à l\'envoi.</span></div>' +
      '<div class="feuille">' + billets + '</div>' +
      '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},400)})<\/script></body></html>';
  }

  function billetRetard(p) {
    var e = p._affichage || {};
    return pageBillet({
      titre: 'BILLET D\'ENTRÉE',
      numero: 'N° provisoire (numéro officiel attribué à l\'envoi)',
      lignes: [
        ['Nom et prénom(s) :', e.nom || ''],
        ['Classe :', e.classe || '—'],
        ['Date :', dateFr(p.date) + '     Heure d\'arrivée : ' + p.heure],
        ['Motif du retard :', p.motif || '........................................']
      ],
      autorisation: 'L\'élève est autorisé(e) à entrer en classe.',
      exemplaires: ['Exemplaire de l\'élève (à remettre au professeur)', 'Souche du surveillant']
    });
  }

  function billetPermission(p) {
    var e = p._affichage || {};
    var sortie = p.type === 'sortie';
    var lignes = [['Nom et prénom(s) :', e.nom || ''], ['Classe :', e.classe || '—']];
    if (sortie) {
      lignes.push(['Date :', dateFr(p.date_debut) + '     Heure de sortie : ' + (p.heure_sortie || '')]);
      lignes.push(['Retour prévu :', p.heure_retour_prevue || '—']);
      lignes.push(['Accompagné(e) par :', p.accompagnateur || '........................................']);
    } else {
      var jours = Math.round((new Date(p.date_fin + 'T00:00:00') - new Date(p.date_debut + 'T00:00:00')) / 86400000) + 1;
      lignes.push(['Période :', 'du ' + dateFr(p.date_debut) + ' au ' + dateFr(p.date_fin) + ' (' + jours + ' jour' + (jours > 1 ? 's' : '') + ')']);
    }
    lignes.push(['Motif :', p.motif || '']);
    return pageBillet({
      titre: sortie ? 'PERMISSION DE SORTIE' : 'PERMISSION D\'ABSENCE',
      numero: 'N° provisoire (numéro officiel attribué à l\'envoi)',
      lignes: lignes,
      autorisation: sortie ? 'L\'élève est autorisé(e) à quitter l\'établissement.' : 'L\'élève est autorisé(e) à s\'absenter pour la période indiquée.',
      parent: sortie,
      exemplaires: ['Exemplaire de l\'élève et de sa famille', 'Souche du surveillant']
    });
  }

  function ecrireDans(fenetre, html) {
    fenetre.document.open();
    fenetre.document.write(html);
    fenetre.document.close();
  }

  function ouvrirBillet(html) {
    var f = window.open('', '_blank');
    if (f) { ecrireDans(f, html); }
    return !!f;
  }

  // ---------- Date et heure du jour dans les formulaires ----------
  function garderAJour(form, champs) {
    champs.forEach(function (c) {
      var el = form.elements[c[0]];
      if (!el) { return; }
      el.addEventListener('input', function () { el.dataset.modifie = '1'; });
    });
    function maj() {
      champs.forEach(function (c) {
        var el = form.elements[c[0]];
        if (el && !el.dataset.modifie) { el.value = c[1](); }
      });
    }
    maj();
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { maj(); } });
    return maj;
  }

  // ---------- Enregistrement d'un retard ou d'une permission ----------
  function brancher(form, def) {
    var majChamps = garderAJour(form, def.champsDuJour);

    form.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      // La fenêtre du billet est ouverte tout de suite : sinon le téléphone la bloque
      var fenetre = window.open('', '_blank');
      var bouton = form.querySelector('button[type=submit]');
      var texteBouton = bouton ? bouton.textContent : '';
      if (bouton) { bouton.disabled = true; bouton.textContent = 'Enregistrement…'; }

      try {
        var payload = lireFormulaire(form);
        payload._affichage = eleveDepuisOption(form);
        var id = await OQ.enqueue(def.type, payload, def.label(payload));
        var resultats = await OQ.flush();
        var r = resultats[id];

        if (r && (r.status === 'ok' || r.status === 'duplicate')) {
          if (fenetre && r.data && r.data.billet_url) { fenetre.location.href = r.data.billet_url + '?imprimer=1'; }
          else if (fenetre) { fenetre.close(); }
          message(form, 'success', r.message || 'Enregistré.');
          form.reset();
          setTimeout(function () { location.reload(); }, 2500);
        } else if (r && r.status === 'error') {
          if (fenetre) { fenetre.close(); }
          await OQ.supprimer(id);
          message(form, 'danger', r.message);
        } else {
          // Pas de connexion : le billet est imprimé depuis les données saisies
          var html = def.billet(payload);
          var ouvert = false;
          if (fenetre) { ecrireDans(fenetre, html); ouvert = true; }
          message(form, 'warning', 'Pas de connexion : c\'est gardé sur ce téléphone et sera envoyé dès que la connexion reviendra.' + (ouvert ? '' : ' '));
          if (!ouvert) {
            var zone = document.getElementById('offline-msg-surveillant');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm btn-primary';
            b.textContent = 'Imprimer le billet';
            b.addEventListener('click', function () { ouvrirBillet(html); });
            zone.firstChild.appendChild(b);
          }
          form.reset();
          for (var k = 0; k < def.champsDuJour.length; k++) {
            var el = form.elements[def.champsDuJour[k][0]];
            if (el) { delete el.dataset.modifie; }
          }
          majChamps();
        }
      } catch (e) {
        if (fenetre) { fenetre.close(); }
        message(form, 'danger', 'Impossible d\'enregistrer : ' + e.message);
      } finally {
        if (bouton) { bouton.disabled = false; bouton.textContent = texteBouton; }
      }
    });
  }

  // ---------- Lignes « en attente d'envoi » dans le tableau ----------
  function datePage() {
    var champ = document.querySelector('form[method="GET"] input[name="date"], form[method="get"] input[name="date"]');
    return champ && champ.value ? champ.value : aujourdhui();
  }

  function retirerLignesEnAttente() {
    document.querySelectorAll('tr.offline-ligne').forEach(function (tr) { tr.parentNode.removeChild(tr); });
  }

  function corpsTableau() {
    var corps = document.querySelector('table tbody');
    if (!corps) { return null; }
    var vide = corps.querySelector('td[colspan]');
    if (vide && corps.rows.length === 1 && !vide.closest('tr').classList.contains('offline-ligne')) { vide.closest('tr').classList.add('offline-vide'); }
    return corps;
  }

  var enAttenteAvant = 0;

  async function afficherEnAttente(items) {
    retirerLignesEnAttente();
    var corps = corpsTableau();
    if (!corps) { return; }
    var jour = datePage();
    var lignes = [];

    items.forEach(function (i) {
      var p = i.payload || {};
      var e = p._affichage || {};
      if (i.type === 'retard' && p.date === jour) {
        lignes.push('<tr class="table-warning offline-ligne"><td class="fw-bold">' + esc(p.heure) + '</td>' +
          '<td><span class="fw-bold">' + esc(e.nom) + '</span><div class="small text-muted">⏳ En attente d\'envoi</div></td>' +
          '<td>' + esc(e.classe || '—') + '</td><td class="small">' + esc(p.motif || '—') + '</td>' +
          '<td class="text-center">—</td>' +
          '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-reimprimer="' + esc(i.uuid) + '">Imprimer</button></td></tr>');
      } else if (i.type === 'permission') {
        var fin = p.type === 'sortie' ? p.date_debut : (p.date_fin || p.date_debut);
        if (p.date_debut <= jour && jour <= fin) {
          var sortie = p.type === 'sortie';
          lignes.push('<tr class="table-warning offline-ligne"><td><span class="fw-bold">' + esc(e.nom) + '</span><div class="small text-muted">' + esc(e.classe || '—') + '</div></td>' +
            '<td><span class="badge ' + (sortie ? 'bg-info' : 'bg-warning') + ' text-dark">' + (sortie ? 'Sortie' : 'Absence') + '</span></td>' +
            '<td class="small">' + (sortie ? 'Sortie à ' + esc(p.heure_sortie) + (p.heure_retour_prevue ? ' · retour prévu ' + esc(p.heure_retour_prevue) : '') + (p.accompagnateur ? '<div>Avec ' + esc(p.accompagnateur) + '</div>' : '')
              : 'Du ' + esc(dateFr(p.date_debut)) + ' au ' + esc(dateFr(p.date_fin))) + '<div class="text-muted">' + esc(p.motif) + '</div></td>' +
            '<td class="small">⏳ En attente d\'envoi</td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-reimprimer="' + esc(i.uuid) + '">Imprimer</button></td></tr>');
        }
      }
    });

    var vide = corps.querySelector('tr.offline-vide');
    if (vide) { vide.style.display = lignes.length ? 'none' : ''; }
    if (lignes.length) { corps.insertAdjacentHTML('afterbegin', lignes.join('')); }
  }

  // ---------- Retour d'un élève sorti ----------
  function idPermission(form) {
    var m = /\/permissions\/(\d+)\/retour$/.exec(form.getAttribute('action') || '');
    return m ? m[1] : null;
  }

  function marquerRetourEnAttente(form, heure) {
    var span = document.createElement('span');
    span.className = 'text-success small fw-bold';
    span.textContent = 'Retour noté à ' + heure + ' ⏳';
    form.parentNode.replaceChild(span, form);
  }

  function brancherRetours(items) {
    var enAttente = {};
    items.forEach(function (i) { if (i.type === 'permission_retour') { enAttente[i.payload.permission_id] = i.payload.heure; } });

    document.querySelectorAll('form').forEach(function (form) {
      var id = idPermission(form);
      if (!id) { return; }
      if (enAttente[id]) { marquerRetourEnAttente(form, enAttente[id]); return; }

      form.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var heure = maintenant();
        var ligne = form.closest('tr');
        var nom = ligne && ligne.cells[0] ? ligne.cells[0].textContent.replace(/\s+/g, ' ').trim() : '';
        var uuid = await OQ.enqueue('permission_retour', { permission_id: id, heure: heure }, 'Retour de ' + nom + ' à ' + heure);
        var res = await OQ.flush();
        var r = res[uuid];
        if (r && (r.status === 'ok' || r.status === 'duplicate')) { location.reload(); }
        else if (r && r.status === 'error') { await OQ.supprimer(uuid); alert(r.message); }
        else { marquerRetourEnAttente(form, heure); }
      });
    });
  }

  // ---------- Suppressions : elles demandent une connexion ----------
  function protegerSuppressions() {
    document.querySelectorAll('form').forEach(function (form) {
      var methode = form.querySelector('input[name="_method"]');
      if (!methode || methode.value.toUpperCase() !== 'DELETE') { return; }
      form.addEventListener('submit', async function (ev) {
        if (ev.defaultPrevented) { return; }   // l'utilisateur a refusé la confirmation
        ev.preventDefault();
        if (await OQ.enLigne()) { form.submit(); }
        else { alert('Supprimer demande une connexion Internet.'); }
      });
    });
  }

  // ---------- Démarrage ----------
  var formRetard = formulairePost('/retards');
  var formPerm = formulairePost('/permissions');

  if (formRetard) {
    brancher(formRetard, {
      type: 'retard',
      champsDuJour: [['date', aujourdhui], ['heure', maintenant]],
      label: function (p) { return 'Retard : ' + (p._affichage.nom || '') + ' · ' + dateFr(p.date) + ' ' + p.heure; },
      billet: billetRetard
    });
  }

  if (formPerm) {
    brancher(formPerm, {
      type: 'permission',
      champsDuJour: [['date_debut', aujourdhui], ['date_fin', aujourdhui], ['heure_sortie', maintenant]],
      label: function (p) { return 'Permission : ' + (p._affichage.nom || '') + ' · ' + dateFr(p.date_debut); },
      billet: billetPermission
    });
  }

  async function rafraichir() {
    var items = (await OQ.toutes()).filter(function (i) { return !i.error; });
    await afficherEnAttente(items);

    // Des éléments viennent d'être envoyés : on propose d'actualiser la liste
    if (items.length < enAttenteAvant && !document.getElementById('offline-actualiser')) {
      var zone = document.createElement('div');
      zone.id = 'offline-actualiser';
      zone.className = 'alert alert-success small py-2';
      zone.innerHTML = 'Envoyé au serveur. <a href="#" class="alert-link">Actualiser la liste</a>';
      zone.querySelector('a').addEventListener('click', function (e) { e.preventDefault(); location.reload(); });
      var conteneur = document.querySelector('.container');
      if (conteneur) { conteneur.insertBefore(zone, conteneur.firstChild); }
    }
    enAttenteAvant = items.length;
  }

  document.addEventListener('click', async function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-reimprimer]') : null;
    if (!b) { return; }
    var items = await OQ.toutes();
    var item = items.filter(function (i) { return i.uuid === b.getAttribute('data-reimprimer'); })[0];
    if (!item) { return; }
    ouvrirBillet(item.type === 'retard' ? billetRetard(item.payload) : billetPermission(item.payload));
  });

  protegerSuppressions();
  OQ.toutes().then(function (items) {
    brancherRetours(items.filter(function (i) { return !i.error; }));
    enAttenteAvant = items.filter(function (i) { return !i.error; }).length;
    return afficherEnAttente(items.filter(function (i) { return !i.error; }));
  });
  window.addEventListener('offline-queue-change', rafraichir);
})();
