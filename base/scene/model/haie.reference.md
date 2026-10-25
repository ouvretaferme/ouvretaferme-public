# Haie taillée, fiche de référence

Couvre `haie.js`. Source : le cordon du prototype de la scène de décor, ramené au contrat des modèles
cuits. La haie est un tracé de décor : la scène répète le tronçon de 3 m le long d'une ligne dessinée, l'étire de 0,7 à 1,3 fois
selon sa longueur, retourne un tronçon sur deux et change son vert.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Longueur | 3 m, selon +Z depuis z = 0 | le pas du tracé |
| Largeur au pied | 1,4 à 1,5 m | une haie champêtre taillée au lamier |
| Largeur aux épaules | 1,4 à 1,6 m | les flancs penchent à peine, l'épaule est la plus large |
| Hauteur | 1,8 à 2 m | taillée à hauteur d'homme |
| Épaule | 0,5 à 0,7 m sous le dessus | le dessus est abattu en biseau des deux côtés |
| Sections | 7, tous les 50 cm | celles des deux bouts fixes et symétriques, pour que deux tronçons se raccordent |
| Budget | 68 triangles, soit 23 par mètre | plafond de 36 par mètre pour un tracé |

## Traits qui la font reconnaitre, classés

1. Un long cordon vert plus haut que large, sans tronc visible.
2. Le dessus clair, les flancs sombres : la coupe trapézoïde se lit à la lumière.
3. La ligne de crête qui ondule d'une section à l'autre.

## On la confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le buisson (`buisson.js`) | la longueur : le buisson est un bloc rond, la haie un cordon |
| Un mur | la couleur et la crête irrégulière |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `feuille` `#4d9a4a` | les faces tournées vers le soleil |
| Sombre | `feuilleSombre` `#2f7d3d` | les flancs, les tranches et les faces à l'ombre |
