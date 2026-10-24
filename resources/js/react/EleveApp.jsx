import { useEffect, useState, useCallback } from 'react';

// Statuts possibles d'un élève (affichés sur le bulletin). À adapter si besoin.
const STATUTS = [
    { valeur: 'N', libelle: 'Nouveau' },
    { valeur: 'A', libelle: 'Ancien' },
    { valeur: 'R', libelle: 'Redoublant' },
];

const FORM_VIDE = {
    nom: '',
    prenom: '',
    sexe: '',
    date_naissance: '',
    statut: '',
    classes_id: '',
};

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function getClasseIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('classe_id') || '';
}

async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(options.headers || {}),
        },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw { status: response.status, data };
    }

    return data;
}

// Affiche le premier message de validation renvoyé par le serveur
function messageErreur(e, defaut) {
    const erreurs = e?.data?.errors;
    if (erreurs) {
        const premiere = Object.values(erreurs)[0];
        if (Array.isArray(premiere) && premiere.length) {
            return premiere[0];
        }
    }
    return e?.data?.message || defaut;
}

function calculAge(dateNaissance) {
    const naissance = new Date(dateNaissance);
    const diff = new Date() - naissance;
    return Math.max(0, Math.floor(diff / (365.25 * 24 * 3600 * 1000)));
}

// ---- Dates au format jj/mm/aaaa ----

// 2012-03-05 -> 05/03/2012
function isoVersFr(iso) {
    if (!iso) return '';
    const [a, m, j] = String(iso).slice(0, 10).split('-');
    return `${j}/${m}/${a}`;
}

// 05/03/2012 -> 2012-03-05 (ou '' si la date n'existe pas)
function frVersIso(fr) {
    const morceaux = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(fr);
    if (!morceaux) return '';
    const [, j, m, a] = morceaux;
    const date = new Date(`${a}-${m}-${j}T00:00:00`);
    if (isNaN(date.getTime()) || date.getDate() !== Number(j) || date.getMonth() + 1 !== Number(m)) {
        return '';
    }
    return `${a}-${m}-${j}`;
}

// Champ de date : on tape 05032012, les "/" s'ajoutent tout seuls
function ChampDate({ value, onChange, className = 'form-control' }) {
    const [texte, setTexte] = useState(isoVersFr(value));

    useEffect(() => {
        // Synchronise le texte quand le formulaire est vidé ou rempli de l'extérieur
        if (frVersIso(texte) !== (value || '')) {
            setTexte(isoVersFr(value));
        }
    }, [value]);

    function handleChange(e) {
        const chiffres = e.target.value.replace(/\D/g, '').slice(0, 8);
        let t = chiffres;
        if (chiffres.length > 4) {
            t = `${chiffres.slice(0, 2)}/${chiffres.slice(2, 4)}/${chiffres.slice(4)}`;
        } else if (chiffres.length > 2) {
            t = `${chiffres.slice(0, 2)}/${chiffres.slice(2)}`;
        }
        setTexte(t);
        onChange(frVersIso(t));
    }

    const invalide = texte.length === 10 && !frVersIso(texte);

    return (
        <input
            type="text"
            inputMode="numeric"
            className={`${className}${invalide ? ' is-invalid' : ''}`}
            placeholder="jj/mm/aaaa"
            maxLength={10}
            required
            value={texte}
            onChange={handleChange}
        />
    );
}

function StatutEcolage({ eleve }) {
    const e = eleve.ecolage;
    const f = (n) => Number(n).toLocaleString('fr-FR');

    if (!e || e.statut === 'non_defini') {
        return (
            <span className="badge bg-secondary text-white w-100 py-1" title="Le comptable doit définir l'écolage de cette classe">
                ⚪ Écolage non défini
            </span>
        );
    }
    if (e.statut === 'surplus') {
        return (
            <>
                <span className="badge bg-danger text-white w-100 py-1">🔴 Surplus de {f(e.surplus)} F</span>
                <small className="d-block text-danger text-center small">à vérifier · écolage {f(e.du)} F</small>
            </>
        );
    }
    if (e.statut === 'solde') {
        return <span className="badge bg-success text-white w-100 py-1">🟢 Soldé ({f(e.du)} F)</span>;
    }
    if (e.statut === 'partiel') {
        return (
            <>
                <span className="badge bg-warning text-dark w-100 py-1">🟡 Reste {f(e.reste)} F</span>
                <small className="d-block text-muted text-center small">
                    Payé: {f(e.paye)} F sur {f(e.du)} F
                </small>
            </>
        );
    }
    return <span className="badge bg-danger text-white w-100 py-1">🔴 Non payé (-{f(e.du)} F)</span>;
}

export default function EleveApp() {
    const [classeId] = useState(getClasseIdFromUrl());
    const [eleves, setEleves] = useState([]);
    const [classes, setClasses] = useState([]);
    const [prochainMatricule, setProchainMatricule] = useState('');
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const [editingId, setEditingId] = useState(null);

    const [form, setForm] = useState(FORM_VIDE);
    const [editForm, setEditForm] = useState(FORM_VIDE);

    const load = useCallback(async (searchTerm = '') => {
        setLoading(true);
        setError(null);
        try {
            const qs = new URLSearchParams();
            if (searchTerm) qs.set('search', searchTerm);
            if (classeId) qs.set('classe_id', classeId);
            const data = await apiFetch(`/api/eleves?${qs.toString()}`);
            setEleves(data.eleves);
            setClasses(data.classes);
            setProchainMatricule(data.prochain_matricule);
        } catch (e) {
            setError("Impossible de charger le registre des élèves.");
        } finally {
            setLoading(false);
        }
    }, [classeId]);

    useEffect(() => {
        load();
    }, [load]);

    function handleSearchSubmit(e) {
        e.preventDefault();
        load(search);
    }

    async function handleCreate(e) {
        e.preventDefault();
        setError(null);
        setSuccessMsg(null);

        if (!form.date_naissance) {
            setError('La date de naissance doit être au format jj/mm/aaaa (ex. 05/03/2012).');
            return;
        }

        try {
            const data = await apiFetch('/api/eleves', {
                method: 'POST',
                body: JSON.stringify({ ...form, matricule: prochainMatricule }),
            });
            setSuccessMsg(data.message);
            setForm(FORM_VIDE);
            load(search);
        } catch (e) {
            setError(messageErreur(e, "Erreur lors de l'inscription de l'élève."));
        }
    }

    function startEdit(eleve) {
        setEditingId(eleve.id);
        setEditForm({
            nom: eleve.nom,
            prenom: eleve.prenom,
            sexe: eleve.sexe ?? '',
            date_naissance: eleve.date_naissance ? String(eleve.date_naissance).slice(0, 10) : '',
            statut: eleve.statut ?? '',
            classes_id: eleve.classes_id,
        });
    }

    async function handleUpdate(e, id) {
        e.preventDefault();
        setError(null);
        setSuccessMsg(null);

        if (!editForm.date_naissance) {
            setError('La date de naissance doit être au format jj/mm/aaaa (ex. 05/03/2012).');
            return;
        }

        try {
            const data = await apiFetch(`/api/eleves/${id}`, {
                method: 'PUT',
                body: JSON.stringify(editForm),
            });
            setSuccessMsg(data.message);
            setEditingId(null);
            load(search);
        } catch (e) {
            setError(messageErreur(e, "Erreur lors de la modification de l'élève."));
        }
    }

    async function handleDelete(id) {
        if (!window.confirm('Êtes-vous certain de vouloir supprimer cet élève ? Cette action effacera également ses notes et paiements.')) {
            return;
        }
        setError(null);
        setSuccessMsg(null);
        try {
            const data = await apiFetch(`/api/eleves/${id}`, { method: 'DELETE' });
            setSuccessMsg(data.message);
            load(search);
        } catch (e) {
            setError("Erreur lors de la suppression de l'élève.");
        }
    }

    const classeActuelle = classeId ? classes.find((c) => String(c.id) === String(classeId)) : null;
    const titreRegistre = classeActuelle
        ? `Élèves de ${classeActuelle.niveau} - ${classeActuelle.nom_classe}`
        : 'Registre Général des Élèves';

    return (
        <div className="container my-4">
            <div className="row mb-4">
                <div className="col-md-8 mx-auto">
                    <div className="card shadow-sm border-0">
                        <div className="card-body">
                            <form onSubmit={handleSearchSubmit} className="d-flex gap-2">
                                <input
                                    type="text"
                                    className="form-control form-control-lg"
                                    placeholder="Rechercher par nom, prénom ou numéro..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                                <button type="submit" className="btn btn-primary px-4 fw-bold">
                                    Rechercher
                                </button>
                                {search && (
                                    <button
                                        type="button"
                                        className="btn btn-outline-secondary"
                                        onClick={() => {
                                            setSearch('');
                                            load('');
                                        }}
                                    >
                                        Effacer les filtres
                                    </button>
                                )}
                                {classeId && (
                                    <a href="/eleves" className="btn btn-outline-dark">
                                        Voir tous les élèves
                                    </a>
                                )}
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {error && <div className="alert alert-danger py-2">{error}</div>}
            {successMsg && <div className="alert alert-success py-2">{successMsg}</div>}

            <div className="row">
                <div className="col-md-4 mb-4">
                    <div className="card shadow-sm border-0">
                        <div className="card-body">
                            <h5 className="card-title fw-bold text-primary mb-3">Inscrire un Élève</h5>

                            <form onSubmit={handleCreate}>
                                <div className="mb-3">
                                    <label className="form-label small fw-bold">Nom</label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="ex: KOFFI"
                                        required
                                        value={form.nom}
                                        onChange={(e) => setForm({ ...form, nom: e.target.value })}
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label small fw-bold">Prénom</label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="ex: Yao"
                                        required
                                        value={form.prenom}
                                        onChange={(e) => setForm({ ...form, prenom: e.target.value })}
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label small fw-bold">Sexe</label>
                                    <select
                                        className="form-select"
                                        required
                                        value={form.sexe}
                                        onChange={(e) => setForm({ ...form, sexe: e.target.value })}
                                    >
                                        <option value="">Choisir...</option>
                                        <option value="M">Masculin (M)</option>
                                        <option value="F">Féminin (F)</option>
                                    </select>
                                </div>
                                <div className="mb-3">
                                    <label className="form-label small fw-bold">Date de naissance</label>
                                    <ChampDate
                                        value={form.date_naissance}
                                        onChange={(iso) => setForm({ ...form, date_naissance: iso })}
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label small fw-bold">Statut</label>
                                    <select
                                        className="form-select"
                                        value={form.statut}
                                        onChange={(e) => setForm({ ...form, statut: e.target.value })}
                                    >
                                        <option value="">Non précisé</option>
                                        {STATUTS.map((s) => (
                                            <option key={s.valeur} value={s.valeur}>
                                                {s.libelle} ({s.valeur})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="mb-3">
                                    <label className="form-label small fw-bold">Matricule (Automatique)</label>
                                    <input
                                        type="text"
                                        className="form-control bg-light fw-bold text-secondary"
                                        value={prochainMatricule}
                                        readOnly
                                    />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label small fw-bold">Classe d'affectation</label>
                                    <select
                                        className="form-select"
                                        required
                                        value={form.classes_id}
                                        onChange={(e) => setForm({ ...form, classes_id: e.target.value })}
                                    >
                                        <option value="">Choisir une classe...</option>
                                        {classes.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.niveau} - {c.nom_classe}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <button type="submit" className="btn btn-primary w-100 fw-bold text-white">
                                    Inscrire l'élève
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div className="col-md-8">
                    <div className="card shadow-sm border-0">
                        <div className="card-body">
                            <h5 className="card-title fw-bold mb-3">
                                {titreRegistre} ({eleves.length})
                            </h5>

                            {loading ? (
                                <p className="text-muted small">Chargement...</p>
                            ) : (
                                <div className="table-responsive" style={{ maxHeight: 550, overflowY: 'auto' }}>
                                    <table className="table table-hover align-middle">
                                        <thead className="table-light sticky-top">
                                            <tr>
                                                <th>Matricule</th>
                                                <th>Nom &amp; Prénom</th>
                                                <th>Classe / Âge</th>
                                                <th>Statut Écolage</th>
                                                <th className="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {eleves.length === 0 && (
                                                <tr>
                                                    <td colSpan={5} className="text-center text-muted small py-4">
                                                        Aucun élève trouvé.
                                                    </td>
                                                </tr>
                                            )}

                                            {eleves.map((eleve) =>
                                                editingId === eleve.id ? (
                                                    <tr key={eleve.id}>
                                                        <td colSpan={5}>
                                                            <form
                                                                onSubmit={(e) => handleUpdate(e, eleve.id)}
                                                                className="d-flex flex-wrap gap-2 align-items-end py-2"
                                                            >
                                                                <div>
                                                                    <label className="form-label small fw-bold mb-0">Nom</label>
                                                                    <input
                                                                        className="form-control form-control-sm"
                                                                        value={editForm.nom}
                                                                        onChange={(e) => setEditForm({ ...editForm, nom: e.target.value })}
                                                                        required
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <label className="form-label small fw-bold mb-0">Prénom</label>
                                                                    <input
                                                                        className="form-control form-control-sm"
                                                                        value={editForm.prenom}
                                                                        onChange={(e) => setEditForm({ ...editForm, prenom: e.target.value })}
                                                                        required
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <label className="form-label small fw-bold mb-0">Sexe</label>
                                                                    <select
                                                                        className="form-select form-select-sm"
                                                                        value={editForm.sexe}
                                                                        onChange={(e) => setEditForm({ ...editForm, sexe: e.target.value })}
                                                                        required
                                                                    >
                                                                        <option value="">Choisir...</option>
                                                                        <option value="M">Masculin (M)</option>
                                                                        <option value="F">Féminin (F)</option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label className="form-label small fw-bold mb-0">Naissance</label>
                                                                    <ChampDate
                                                                        className="form-control form-control-sm"
                                                                        value={editForm.date_naissance}
                                                                        onChange={(iso) => setEditForm({ ...editForm, date_naissance: iso })}
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <label className="form-label small fw-bold mb-0">Statut</label>
                                                                    <select
                                                                        className="form-select form-select-sm"
                                                                        value={editForm.statut}
                                                                        onChange={(e) => setEditForm({ ...editForm, statut: e.target.value })}
                                                                    >
                                                                        <option value="">Non précisé</option>
                                                                        {STATUTS.map((s) => (
                                                                            <option key={s.valeur} value={s.valeur}>
                                                                                {s.libelle} ({s.valeur})
                                                                            </option>
                                                                        ))}
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label className="form-label small fw-bold mb-0">Classe</label>
                                                                    <select
                                                                        className="form-select form-select-sm"
                                                                        value={editForm.classes_id}
                                                                        onChange={(e) => setEditForm({ ...editForm, classes_id: e.target.value })}
                                                                        required
                                                                    >
                                                                        {classes.map((c) => (
                                                                            <option key={c.id} value={c.id}>
                                                                                {c.niveau} - {c.nom_classe}
                                                                            </option>
                                                                        ))}
                                                                    </select>
                                                                </div>
                                                                <button type="submit" className="btn btn-sm btn-success fw-bold">
                                                                    Enregistrer
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-sm btn-outline-secondary fw-bold"
                                                                    onClick={() => setEditingId(null)}
                                                                >
                                                                    Annuler
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    <tr key={eleve.id}>
                                                        <td className="text-muted small fw-bold">{eleve.matricule}</td>
                                                        <td className="fw-bold text-uppercase">
                                                            {eleve.nom} <span className="text-capitalize fw-normal">{eleve.prenom}</span>
                                                            {eleve.statut && (
                                                                <span className="badge bg-warning text-dark ms-2">{eleve.statut}</span>
                                                            )}
                                                            <small className="d-block text-muted fw-normal text-capitalize">
                                                                {eleve.sexe === 'M' ? 'Masculin' : eleve.sexe === 'F' ? 'Féminin' : 'Sexe non renseigné'}
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <span className="badge bg-success text-white px-2 py-1">
                                                                {eleve.classe ? `${eleve.classe.niveau} ${eleve.classe.nom_classe}` : 'N/A'}
                                                            </span>
                                                            <small className="d-block text-muted">
                                                                {calculAge(eleve.date_naissance)} ans · né(e) le {isoVersFr(eleve.date_naissance)}
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <StatutEcolage eleve={eleve} />
                                                        </td>
                                                        <td className="text-end">
                                                            <div className="d-flex gap-1 justify-content-end">
                                                                <button
                                                                    className="btn btn-sm btn-outline-primary py-0 px-2 fw-bold"
                                                                    onClick={() => startEdit(eleve)}
                                                                >
                                                                    Modifier
                                                                </button>
                                                                <button
                                                                    className="btn btn-sm btn-outline-danger py-0 px-2 fw-bold"
                                                                    onClick={() => handleDelete(eleve.id)}
                                                                >
                                                                    Supprimer
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
