# Haie de bocage

La haie libre de talus : des cépées inégales et un arbre de haut jet. Demandée le 2026-10-12 pour varier les tracés, recettée le même jour. Proportions citées de mémoire, non relues sur une photo.

## Proportions

| Mesure | Valeur |
|---|---|
| Cépées | 2 à 2,8 m de haut, 2,1 à 2,6 m d'épaisseur, crête en deux vagues inégales |
| Tronc | 3,4 m, visible entre les cépées et la tête |
| Tête de l'arbre | deux masses inégales, sommet à 5,1 m, 2,8 m de large |

## Ce qui la fait reconnaitre

1. Un arbre qui dépasse d'une masse d'arbustes.
2. Le tronc brun visible sous la tête.
3. Une crête qui monte et descend.

## Avec quoi on la confond

| Objet | Ce qui l'en sépare |
|---|---|
| Un arbre isolé avec un buisson | les cépées courent sur les 3 m et se raccordent au tronçon voisin |
| La haie à baies | pas d'arbre, des grappes rouges |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#4d9a4a` | les faces tournées vers le soleil |
| Sombre | `#2f7d3d` | les flancs |
| Accent | `#6b4a2f` | le tronc, rigide |

## Modèle

`haie.bocage.js`, 216 triangles, 4 couleurs. La tête a été grossie d'un tiers après la recette (verdict du 2026-10-12). Tronçon de 3 m selon +Z, pied à y = 0, axe à x = 0, les deux bouts portent la section des bouts de `base/scene/model/haie.js` (1,52 m de large aux épaules, dessus plat à 1,9 m) : un tronçon retourné, ou une haie voisine d'une autre forme, se raccorde sans marche. Exporté de Blender par `haies_blender.py`. Le bois fait la pièce `arbre`, immobile au vent : le tronc, quatre charpentières brunes `#6b4a2f` qui s'arrêtent à 0,45 du rayon de la grande tête et six rameaux plus clairs `#8a6444` à 0,66, tous couverts par la tête l'été, pour que l'arbre se lise feuilles tombées (4,64 m de haut sans sa tête). Les deux masses de la tête font la pièce `feuillage`, le nom que portent déjà les caducs du décor (`base/scene/model/arbre.js`, `chene.js`, `bouleau.js`), à part des cépées dont elle partage les deux verts. L'ancre `arbre` est au pied du tronc, à [0.1, 0, 1.5] : la scène remet l'arbre à l'échelle autour de ce point au lieu de l'étirer avec la travée (tranché par E-3D-P le 2026-10-12).
