<?php

declare(strict_types=1);

return [

    'navigation' => [
        'label' => 'Horaires d’ouverture',
    ],

    'page' => [
        'title' => 'Horaires d’ouverture',
        'heading' => 'Horaires d’ouverture',
        'subheading' => 'Gérez les horaires hebdomadaires, les fermetures exceptionnelles et les périodes de vacances.',
    ],

    'actions' => [
        'save' => 'Enregistrer',
    ],

    'days' => [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ],

    'grid' => [
        'heading' => 'Horaires hebdomadaires',
        'description' => 'Fuseau horaire : :timezone. Grille par pas de :step minutes.',
        'hint' => 'Glissez sur une journée pour créer une plage, déplacez une plage pour la décaler, étirez ses bords pour la redimensionner. Cliquez sur une plage (ou clic droit, ou touche Entrée) pour saisir des horaires précis ou la supprimer.',
        'closed' => 'Fermé',
        'overnight' => '(+1)',
        'total_hours' => ':hours h',
        'total_hours_minutes' => ':hours h :minutes',
        'add_range' => 'Ajouter une plage',
        'copy_day' => 'Copier ce jour vers…',
        'copy_day_heading' => 'Copier les horaires de « :day » vers…',
        'copy' => 'Copier',
        'clear_day' => 'Vider ce jour',
        'editor' => [
            'edit' => 'Modifier la plage',
            'from' => 'De',
            'until' => 'À',
            'delete' => 'Supprimer',
            'done' => 'OK',
            'invalid' => 'La fin doit être après le début.',
        ],
    ],

    'settings' => [
        'label' => 'Réglages',
        'heading' => 'Réglages des horaires',
        'description' => 'Ces réglages sont appliqués à l’enregistrement.',
        'apply' => 'Appliquer',
        'timezone' => 'Fuseau horaire',
        'timezone_placeholder' => 'Fuseau de l’application (:timezone)',
        'timezone_helper' => 'Le fuseau horaire dans lequel les horaires sont exprimés.',
        'overflow' => 'Plages après minuit',
        'overflow_helper' => 'Autorise les plages qui se terminent après minuit, par exemple 20:00-02:00 (drapeau « overflow » de spatie/opening-hours). Elles ne peuvent être saisies que dans l’éditeur de plage.',
    ],

    'exceptions' => [
        'heading' => 'Journées exceptionnelles',
        'description' => 'Jours où l’établissement est exceptionnellement fermé, ou ouvert sur des horaires particuliers.',
        'empty' => 'Aucune journée exceptionnelle pour le moment.',
        'add' => 'Ajouter une date',
        'add_heading' => 'Ajouter une journée exceptionnelle',
        'edit' => 'Modifier',
        'edit_heading' => 'Modifier la journée exceptionnelle',
        'remove' => 'Supprimer',
        'remove_heading' => 'Supprimer cette journée exceptionnelle ?',
        'recurring_badge' => 'Chaque année',
        'closed_badge' => 'Fermé',
        'form' => [
            'date' => 'Date',
            'recurring' => 'Répéter chaque année',
            'recurring_helper' => 'L’année de la date choisie est ignorée.',
            'closed' => 'Fermé toute la journée',
            'hours' => 'Horaires d’ouverture ce jour-là',
            'add_range' => 'Ajouter une plage',
            'from' => 'De',
            'until' => 'À',
            'label' => 'Libellé',
            'label_placeholder' => 'Noël, inventaire, …',
        ],
    ],

    'vacations' => [
        'heading' => 'Périodes de vacances',
        'description' => 'Plages de dates pendant lesquelles l’établissement est fermé.',
        'empty' => 'Aucune période de vacances pour le moment.',
        'add' => 'Ajouter une période',
        'add_heading' => 'Ajouter une période de vacances',
        'edit' => 'Modifier',
        'edit_heading' => 'Modifier la période de vacances',
        'remove' => 'Supprimer',
        'remove_heading' => 'Supprimer cette période de vacances ?',
        'range' => 'Du :from au :until',
        'days_count' => '{1} :count jour|[2,*] :count jours',
        'fallback_label' => 'Vacances',
        'form' => [
            'from' => 'Premier jour fermé',
            'until' => 'Dernier jour fermé',
            'label' => 'Libellé',
            'label_placeholder' => 'Congés d’été, …',
        ],
    ],

    'save' => [
        'success_title' => 'Horaires enregistrés',
        'invalid_title' => 'Les horaires n’ont pas pu être enregistrés',
        'conflicting_dates' => 'Le :date est couvert deux fois (par « :first » et « :second »). Retirez l’un des deux.',
        'invalid_period' => 'Une période de vacances comporte une date invalide.',
        'period_too_long' => 'Une période de vacances ne peut pas dépasser :max jours.',
        'too_many_entries' => 'Trop de journées exceptionnelles et de périodes de vacances (au plus :max au total).',
        'too_many_ranges' => 'Trop de plages le :day (au plus :max).',
        'invalid_timezone' => '« :timezone » n’est pas un fuseau horaire valide.',
    ],

];
