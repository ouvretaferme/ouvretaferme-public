# Bout de charmille

La travée qui ouvre et ferme une série de charmilles dans la haie champêtre mêlée : le mur étroit s'évase jusqu'à la section commune des haies. Demandé par E-3D-Tech le 2026-10-12, forme tranchée par E-3D-P le même jour. Proportions dérivées de `haie.charmille` et de `base/scene/model/haie.js`, pas d'une photo.

## Proportions

| Mesure | Valeur |
|---|---|
| Bout à z = 0 | la section de `haie.charmille` : 0,8 m de large au pied, 0,6 m au dessus, 2,2 m de haut |
| Bout à z = 3 | la section des bouts de `haie.js` : 1,52 m de large aux épaules, dessus plat de 0,76 m à 1,9 m |
| Entre les deux | sept sections, passage adouci aux deux bouts, sans variation aléatoire |

## Ce qui le fait reconnaitre

1. Le mur taillé de la charmille, dessus plat à arêtes vives.
2. Un évasement régulier, sans marche.

## Avec quoi on le confond

| Objet | Ce qui l'en sépare |
|---|---|
| La charmille | une section constante d'un bout à l'autre |
| La haie d'origine | des flancs en épaule sur toute la longueur |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#4d9a4a` | les faces tournées vers le soleil |
| Sombre | `#2f7d3d` | les flancs |

## Modèle

`haie.charmille.bout.js`, 68 triangles, 2 couleurs, d 3, h 2,2. Tronçon de 3 m selon +Z, pied à y = 0, axe à x = 0. Il n'est pas symétrique : posé tel quel à la fin d'une série (charmille à z = 0, voisine à z = 3), retourné au début. Exporté de Blender par `haies_blender.py`, sans ancre ni pièce nommée.
