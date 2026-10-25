# Haie à baies

La haie d'arbustes à petits fruits, laissés en boule. Demandée le 2026-10-12 pour varier les tracés, recettée le même jour. Proportions citées de mémoire, non relues sur une photo.

## Proportions

| Mesure | Valeur |
|---|---|
| Hauteur | 1,6 à 1,9 m |
| Épaisseur | 1,4 à 1,7 m |
| Arbustes | deux boules par tronçon, creux entre les deux |
| Grappes | 13, de 0,2 à 0,26 m, sur le dessus et les épaules |

## Ce qui la fait reconnaitre

1. Une crête bosselée d'arbustes ronds.
2. Les grappes rouge sombre semées sur le dessus.

## Avec quoi on la confond

| Objet | Ce qui l'en sépare |
|---|---|
| Une haie fleurie | les juges hésitent entre fruit et fleur : la couleur rouge sombre est celle d'une baie mûre |
| La haie basse | deux fois et demie plus haute, bosselée |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#4d9a4a` | les faces tournées vers le soleil |
| Sombre | `#2f7d3d` | les flancs |
| Accent | `#7a1f3d` | les grappes, pièce nommée `fruit` que la scène montre selon la saison |

## Modèle

`haie.baies.js`, 210 triangles dont 104 pour les grappes, 3 couleurs. Tronçon de 3 m selon +Z, pied à y = 0, axe à x = 0, les deux bouts portent la section des bouts de `base/scene/model/haie.js` (1,52 m de large aux épaules, dessus plat à 1,9 m) : un tronçon retourné, ou une haie voisine d'une autre forme, se raccorde sans marche. Exporté de Blender par `haies_blender.py`, sans ancre. Les grappes sont la pièce `fruit`.
