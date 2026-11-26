import { ChampDate, STATUTS } from './eleveOutils.jsx';

// Ligne du tableau en mode modification
export default function LigneEdition({ eleve, editForm, setEditForm, classes, onSubmit, onCancel }) {
    return (
            <tr key={eleve.id}>
                <td colSpan={5}>
                    <form
                        onSubmit={onSubmit}
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
                            onClick={onCancel}
                        >
                            Annuler
                        </button>
                    </form>
                </td>
            </tr>

    );
}
