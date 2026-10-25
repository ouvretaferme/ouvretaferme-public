# Oignon · fiche de référence

Plant adulte au potager, de la mi-saison à la tombaison : feuillage complet, bulbe grossi au ras du sol, quelques feuilles qui plient. Pas de hampe florale (oignon de semis ou de bulbille, récolté la première année).

## Proportions
- **Feuilles** : 7 à 11 par plant (essai de Satna, Pusa Red, 7,8 à 8,7 ; 28 génotypes blancs du Gujarat, jusqu'à 11,2). Longueur 30 à 50 cm (flores : 10 à 50 cm). Diamètre **0,4 à 2 cm** : un **tube creux, rond en coupe**, aplati d'un côté seulement, qui s'effile en pointe. Vert bleuté, cireux.
- **Hauteur** du plant : 42 à 55 cm en culture (Satna 42,6 à 44,9 cm ; Nasik Red 54 cm ; jusqu'à 75 cm chez certains génotypes). On retient **45 cm**.
- **Port** : feuilles **dressées, en touffe tout autour du col**, à 5-20° de la verticale. Les plus vieilles s'écartent davantage et une ou deux **plient** au tiers supérieur (observation sur photos de potager, aucune source chiffrée).
- **Emprise** : une feuille de 40 cm ouverte à 15-20° s'écarte de 10 à 14 cm du col, soit **20 à 28 cm** d'emprise, environ **0,5 fois la hauteur**. Recoupement : 40 × cos 20° ≈ 38 cm de feuille, plus 6 à 8 cm de bulbe, donne bien 45 cm.
- **Bulbe** : **5,5 à 7,4 cm** de diamètre (Satna 5,5 à 6,7 ; Gujarat jusqu'à 7,4), plus large que haut, en toupie (rapport largeur sur hauteur 1,2 à 1,4), épaules arrondies qui se resserrent en col sous les feuilles. En fin de saison il sort de terre aux deux tiers, posé sur le sol. Réel, il fait le septième de la hauteur ; **exagéré pour la scène** (organe signature, même levier que l'ail) : environ le tiers de la hauteur et les deux tiers de l'emprise.

## Traits qui font reconnaître, dans l'ordre
1. Un **gros bulbe doré à cuivré**, plus large que haut, **posé sur le sol**, sous un col étroit.
2. Des **tubes ronds vert bleuté, dressés** tout autour du col, effilés en pointe, dont un ou deux **plient**.
3. Une silhouette **plus haute que large** au-dessus du bulbe, sans éventail.

## Confusions et ce qui sépare
- **Ail** (confusions des tours 1, 2 et 4) : tête blanche striée de violet, lames **plates** et pliées en gouttière, ouvertes **en éventail dans un seul plan**. L'oignon a un bulbe doré ou cuivré, des feuilles en **tubes ronds**, dressées **tout autour** du col.
- **Poireau** (tour 4) : long fût blanc droit, éventail de lames plates bleu-vert, pas de bulbe. L'oignon a un col court et un bulbe large au pied.
- **Échalote, ciboule** (tour 1) : touffe de plusieurs pieds, tubes plus fins, bulbes petits ou absents. L'oignon a un pied unique et un bulbe unique et gros.
- **Carotte** (tours 2 et 3, par le collet orangé) : feuillage fin et découpé, vert franc, collet étroit au ras du sol. L'oignon a des tubes lisses et bleutés et un bulbe large hors de terre.

## Couleurs (quatre au plus)
- Clair : `#d9a24b` (accent d'`especes.json`), le bulbe, doré.
- Accent : `#a5612f`, le bas du bulbe, cuivré, par bande entière du tour, jamais face par face.
- Moyen : `#76a08a`, les tubes, vert bleuté.
- Sombre : `#4e7a68`, les tubes extérieurs et pliés, pour que la touffe ait deux valeurs.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **60 triangles**, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait une dizaine de pixels.

## Sources
- Wikipedia, *Onion* : https://en.wikipedia.org/wiki/Onion (feuilles creuses, bleu-vert, bulbe jusqu'à 10 cm).
- Botany Brisbane, *Allium cepa* : https://www.botanybrisbane.com/plants/amaryllidaceae-s-l/allium/allium-cepa/ (4 à 10 feuilles, 10 à 50 cm, 4 mm à 2 cm de large).
- Essai de Satna, Pusa Red : https://phytojournal.com/special-issue/2018.v7.i4S.6827/to-study-the-interactive-effect-of-age-of-seedling-and-plant-spacing-on-growth-and-yield-of-onion-bulb-ltemgtallium-cepaltemgt-l-cv-pusa-red (hauteur, nombre de feuilles, diamètre du bulbe).
- Génotypes d'oignon blanc : https://phytojournal.com/archives/2020.v9.i5.12269/evaluation-of-white-onion-allium-cepa-l-genotypes-for-growth-yield-and-yield-attributing-characters (11,2 feuilles, bulbe 7,36 cm, hauteur 74,6 cm).
- Essai de Beyeda, Éthiopie : https://agris.fao.org/search/fr/records/6800f805974c899542f80262 (Nasik Red, 54 cm).
