import { useEffect, useState } from 'react';

// Constantes et petits outils du registre des élèves
export const STATUTS = [
    { valeur: 'N', libelle: 'Nouveau' },
    { valeur: 'A', libelle: 'Ancien' },
    { valeur: 'R', libelle: 'Redoublant' },
];

export const FORM_VIDE = {
    nom: '',
    prenom: '',
    sexe: '',
    date_naissance: '',
    statut: '',
    classes_id: '',
};

export function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

export function getClasseIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('classe_id') || '';
}

// Filtre la liste (recherche + classe) aussi côté téléphone : la copie gardée hors ligne est la liste complète
export function filtrer(liste, terme, classe) {
    const t = (terme || '').trim().toLowerCase();
    return (liste || []).filter((e) => {
        if (classe && String(e.classes_id) !== String(classe)) return false;
        if (!t) return true;
        return [e.nom, e.prenom, e.matricule].some((v) => String(v || '').toLowerCase().includes(t));
    });
}

export async function apiFetch(url, options = {}) {
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
export function messageErreur(e, defaut) {
    const erreurs = e?.data?.errors;
    if (erreurs) {
        const premiere = Object.values(erreurs)[0];
        if (Array.isArray(premiere) && premiere.length) {
            return premiere[0];
        }
    }
    return e?.data?.message || defaut;
}

export function calculAge(dateNaissance) {
    const naissance = new Date(dateNaissance);
    const diff = new Date() - naissance;
    return Math.max(0, Math.floor(diff / (365.25 * 24 * 3600 * 1000)));
}

// ---- Dates au format jj/mm/aaaa ----

// 2012-03-05 -> 05/03/2012
export function isoVersFr(iso) {
    if (!iso) return '';
    const [a, m, j] = String(iso).slice(0, 10).split('-');
    return `${j}/${m}/${a}`;
}

// 05/03/2012 -> 2012-03-05 (ou '' si la date n'existe pas)
export function frVersIso(fr) {
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
export function ChampDate({ value, onChange, className = 'form-control' }) {
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

export function StatutEcolage({ eleve }) {
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
