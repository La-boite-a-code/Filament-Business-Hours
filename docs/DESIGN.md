# Filament Business Hours : design

Date : 2026-07-22
Statut : implémenté (v1)

## Objectif

Un plugin Filament (v4/v5) « simple » qui ajoute une page au panel pour gérer les
horaires d'ouverture d'un établissement :

- grille hebdomadaire 24 h × 7 jours (lundi à dimanche, premier jour configurable) ;
- création de plages par glisser, déplacement, redimensionnement ;
- édition précise d'une plage (clic ou clic droit sur la plage : éditeur) ;
- journées de fermeture exceptionnelle (date unique, éventuellement récurrente
  chaque année, fermée ou avec horaires particuliers) ;
- périodes de vacances (plage de dates, fermé) ;
- choix du fuseau horaire ;
- données stockées **au format natif `spatie/opening-hours`**, pour que les
  applications consommatrices puissent les exploiter directement.

Le package est le pendant de `laboiteacode/filament-logs-explorer` : mêmes
conventions (namespace `LaBoiteACode\`, service provider spatie/laravel-package-tools,
plugin fluent > config > défaut, assets JS/CSS écrits à la main dans
`resources/dist`, Pest + Testbench, pint + larastan, workflow CI identique).

## Décision structurante : le format de stockage EST le format spatie

`spatie/opening-hours` 4.x accepte nativement :

```php
OpeningHours::create([
    'monday'     => ['09:00-12:00', '14:00-18:00'],
    // ...
    'exceptions' => [
        '2026-12-25'                 => ['hours' => [], 'data' => 'Noël'],  // fermeture exceptionnelle
        '12-25'                      => [],                                  // récurrente chaque année
        '2026-08-01 to 2026-08-15'   => ['hours' => [], 'data' => 'Congés'], // période (vacances)
        '2026-12-24'                 => ['10:00-16:00'],                     // horaires particuliers
    ],
    'overflow'   => false,
    'timezone'   => 'Europe/Paris',
]);
```

Les clés « plage de dates » (`Y-m-d to Y-m-d`) sont expansées par spatie lui-même
(`readDatesRange()`) : les vacances n'ont donc **aucun format propriétaire**, la
table stocke exactement ce que `OpeningHours::create()` consomme.

### Modèle Eloquent `BusinessHours`

Table `business_hours` :

| colonne      | type   | contenu                                                        |
| ------------ | ------ | -------------------------------------------------------------- |
| `key`        | string | identifiant du jeu d'horaires (`default`, `shop-paris`, ...)   |
| `timezone`   | string nullable | identifiant IANA                                      |
| `overflow`   | bool   | plages débordant minuit (spatie `overflow`)                    |
| `hours`      | json   | `{monday: ["09:00-12:00"], ...}`, format spatie tel quel       |
| `exceptions` | json   | map spatie : dates uniques, récurrentes (`m-d`), plages (`to`) |

API du modèle :

- `toOpeningHoursArray(): array` : fusionne hours + exceptions + overflow + timezone ;
- `toOpeningHours(): Spatie\OpeningHours\OpeningHours` ;
- `BusinessHours::openingHours('default'): ?OpeningHours` : raccourci statique ;
- `BusinessHours::resolve('default'): static` : firstOrNew avec valeurs par défaut.

Le modèle est remplaçable (config `model`) tant qu'il étend celui du package.

### Représentation des exceptions côté page

La page sépare pour l'UX ce que la base fusionne : `Support\ExceptionEntry`
(value object) parse chaque paire clé/valeur spatie en `{type: date|recurring|period,
date|from|until, hours, label}` et sait se resérialiser. La page maintient deux
listes d'état (`$exceptions` pour les dates, `$vacations` pour les périodes
fermées) recompilées en une seule map spatie à l'enregistrement. Tout ce que
l'UI ne sait pas représenter (clés inconnues, périodes récurrentes, périodes
avec horaires, `data` structurés) part dans `$rawExceptions` et est refusionné
tel quel à la sauvegarde : pas de perte silencieuse.

## Composants

```
src/
├── FilamentBusinessHoursServiceProvider.php   # package-tools + FilamentAsset
├── FilamentBusinessHoursPlugin.php            # fluent > config > défaut (pattern logs-explorer)
├── Models/BusinessHours.php
├── Pages/ManageBusinessHours.php              # page Livewire custom
└── Support/
    ├── ExceptionEntry.php                     # parse/serialize une exception spatie
    ├── HourRanges.php                         # normalisation des plages HH:MM-HH:MM
    └── Weekdays.php                           # ordre des jours, premier jour configurable
resources/
├── dist/filament-business-hours.{js,css}      # Alpine + CSS préfixé fbh-, à la main
├── lang/{en,fr,es}/filament-business-hours.php
└── views/pages/manage-business-hours.blade.php
database/migrations/create_business_hours_table.php.stub
```

### Plugin (fluent API)

`navigationIcon/Group/Label/Sort/ParentItem`, `registerNavigation`, `slug`,
`cluster`, `record(string)`, `model(string)`, `firstDayOfWeek(string)`,
`slotStep(int)`, `timezones(?array)`, `defaultTimezone(?string)`,
`canAccessUsing(Closure)`. Chaque getter résout fluent puis config puis défaut,
comme dans logs-explorer.

### Page `ManageBusinessHours`

État Livewire :

- `array $hours` : `{monday: ["09:00-12:00"], ...}`, entangled (deferred) avec la grille Alpine ;
- `array $exceptions` : entrées structurées (date / récurrente / horaires / libellé) ;
- `array $vacations` : entrées structurées (du / au / libellé) ;
- `array $rawExceptions` : paires spatie préservées telles quelles ;
- `?string $timezone`, `bool $overflow`.

Actions :

- **Enregistrer** (header, primaire) : normalise chaque jour via
  `HourRanges::normalize()` (tri, fusion des chevauchements, validation du
  format), recompile la map spatie, détecte les dates couvertes deux fois avec
  un message lisible, valide en instanciant réellement `OpeningHours::create()`
  (toute donnée enregistrée est donc garantie valide pour spatie), persiste,
  notifie. Échec : notification danger avec le message.
- **Réglages** (header) : modal fuseau horaire (Select searchable) + overflow (Toggle).
- **Fermetures exceptionnelles** : section sous la grille, liste + actions
  add/edit/delete en modal (DatePicker, « chaque année », « fermé toute la
  journée », Repeater de plages TimePicker sinon, libellé).
- **Vacances** : section identique (DatePicker du/au, libellé).
- **Copier un jour** (boutons de l'en-tête de colonne) : modal CheckboxList des
  jours cibles. **Vider un jour** au même endroit.

La sauvegarde est unique et globale (une page = un enregistrement) : les modals
modifient l'état, « Enregistrer » persiste tout, mêmes sémantiques qu'un
Repeater Filament.

### Grille (Alpine, `resources/dist`)

- Colonne des heures (00 à 23) + 7 colonnes jour ordonnées selon `firstDayOfWeek`,
  en-têtes sticky avec total d'heures par jour, scroll horizontal sur mobile.
- **Rendu « cellules »** : chaque jour est une pile de 24 cellules bordées et
  arrondies (une par heure, style planning). Une plage logique est dessinée en
  un segment plein par cellule couverte : cellule entière remplie quand l'heure
  est couverte en entier, remplissage proportionnel sinon, avec un badge de
  minutes sur les cellules partielles (« 30 » sur la cellule 05:00 = borne à
  05:30). Le survol met en évidence tous les segments d'une même plage.
- Couleurs : variables CSS runtime de Filament (`--primary-*`, `--gray-*`) avec
  `color-mix()` pour les transparences et des fallbacks neutres ; la grille
  suit donc la palette du panel, clair comme sombre.
- Interactions pointer events : glisser sur zone vide = créer (snap `slotStep`),
  glisser une plage = déplacer, poignées sur premier/dernier segment =
  redimensionner, clic / clic droit / Entrée = éditeur (popover ancré :
  2 `<input type="time">`, suppression, fermeture Échap/extérieur). Suppr sur
  plage focalisée = retirer. Après chaque geste : tri + fusion des
  chevauchements côté client.
- État canonique = chaînes spatie `"HH:MM-HH:MM"` ; conversions minutes/chaînes
  internes au composant. `24:00` autorisé en fin de plage.

## Points rejetés (YAGNI)

- Widget public d'affichage des horaires (l'app consommatrice a spatie pour ça).
- Contrat de stockage abstrait (le modèle remplaçable suffit).
- Vacances avec horaires réduits (une vacance = fermé ; sinon utiliser des
  exceptions par date, ou écrire la période à la main : elle sera préservée).
- Données par plage horaire (spatie le permet, l'UI v1 non).

## Tests

- Unit : `HourRanges` (tri, fusion, formats invalides, overflow), `Weekdays`,
  `ExceptionEntry` (parse/serialize les 3 formes + label + « fermé » + refus
  des paires non représentables), modèle (`toOpeningHoursArray`,
  `toOpeningHours` vérifiés en interrogeant réellement spatie : `isOpenAt`
  pendant vacances/fermetures, fuseau appliqué).
- Feature : rendu de la page dans un panel de test, chargement de l'état,
  enregistrement complet (normalisation, exceptions, vacances, fuseau,
  overflow), rejet des données invalides et des dates en conflit, préservation
  des exceptions non gérées, copie/vidage de jour, édition/suppression par
  index, autorisation (`canAccessUsing`).
