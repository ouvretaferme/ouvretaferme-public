# Camion

Un camion de livraison, pour une scène qui montre un fournisseur (commandé par V-3D le 2026-10-13). Cotes réelles, à l'échelle du tracteur, de la remorque et de l'utilitaire.

## Proportions

| Mesure | Valeur | Source |
|---|---|---|
| Hors tout | 7,0 × 2,3 × 3,08 m | porteur de livraison de 12 tonnes courant, dimensions courantes citées de mémoire, non relues sur une photo |
| Cabine | 1,55 m de long, 2,2 m de large, toit à 2,45 m, face plate | cabine avancée, posée sur l'essieu avant |
| Caisse | 5,1 × 2,3 × 2,05 m, plancher à 1,05 m | fermée, plus haute et plus large que la cabine |
| Roues | 0,96 m de diamètre, empattement 4,5 m | deux essieux, porte-à-faux arrière de 1,5 m |

## Ce qui le fait reconnaitre

1. Une caisse blanche fermée, en boite, qui dépasse la cabine de 0,65 m en hauteur.
2. Une cabine avancée à face plate, d'une autre couleur que la caisse.
3. Le châssis noir visible entre la cabine et la caisse, et sous la caisse.

## Avec quoi on le confond

| Objet | Ce qui l'en sépare |
|---|---|
| L'utilitaire | un seul volume blanc, capot plongeant, 4,5 m, toit continu |
| Le tracteur et sa remorque | roues arrière énormes, ridelles ouvertes, deux pièces attelées |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#ecebe4` | la caisse, les jantes |
| Moyen | `#e0b23a`, `#8fc1d0` | la cabine ; le pare-brise et les vitres de cabine |
| Sombre | `#2a2c2e` | les pneus, le châssis, le pare-chocs, la barre arrière, l'ouverture de la caisse derrière le hayon |

## Modèle

`camion.js`, 264 triangles, 4 couleurs. Emprise de 7,0 m, 3,08 m de haut. Exporté de Blender par `vehicules_blender.py`, l'avant vers +X. Deux pièces : `corps`, rigide, et `hayon`, qui se rabat de 90° vers l'arrière autour de son pivot, au seuil de la caisse, sur l'axe de la largeur, et devient un quai à hauteur de plancher. L'aplat sombre de l'ouverture déborde le hayon de 10 cm sur les côtés et de 12 cm en haut : fermé, ce liseré dessine la porte sur l'arrière blanc. Une ancre `chargement` au seuil de la caisse.
