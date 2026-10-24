<?php

// Informations de l'école, utilisées sur le bulletin.
// Pour changer un texte du bulletin, modifie-le ici (une seule fois).
return [
    'nom' => 'CEG ADJENGRE II',
    'devise' => 'Travail - Discipline - Succès',

    'republique' => 'REPUBLIQUE TOGOLAISE',
    'devise_nationale' => 'Travail - Liberté - Patrie',
    'ministere' => "MINISTERE DE L'EDUCATION NATIONALE",
    'direction_regionale' => "DIRECTION REGIONALE DE L'EDUCATION CENTRALE",
    'inspection' => "INSPECTION DE L'ENSEIGNEMENT SECONDAIRE GENERAL DE SOTOUBOUA",

    'ville' => 'Adjengré II',                // « Fait à Adjengré II, le ... »
    'directeur' => 'DOUTI Tambane Nanwadja', // à vérifier : nom imprimé sous « Le Directeur »

    'logo' => 'logo.png',                    // fichier placé dans le dossier public/
    'contact_developpeur' => null,           // ex. '90 00 00 00' : s'imprime en bas du bulletin

    // Absences : comptées en heures, puis converties en jours
    'heures_par_jour' => 7,   // 7 heures d'absence = 1 jour
    'nombre_creneaux' => 7,   // nombre d'heures de cours dans une journée
];
