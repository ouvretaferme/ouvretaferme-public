# Pont

Le petit pont de pierre de campagne, à une arche et parapet bas, que la scène pose d'elle-même là où un chemin ou une route
croise un cours d'eau (demandé le 2026-10-23, lot F-pont de 3d-tech). Il n'est posé par personne : la scène le
déduit des deux tracés, l'oriente sur l'axe du chemin et l'étire à la largeur de l'eau et à celle du tracé.

## Proportions

| Mesure | Valeur | Source |
|---|---|---|
| Longueur hors tout | 5 m au modèle, de 3 à 7 m une fois étiré | l'eau du décor fait 1 à 3 m, sa berge claire déborde de 1,2 m de chaque côté (`STREAM`, `sceneDecor.js`) |
| Ouverture de l'arche | 2,6 m au modèle, soit la moitié de la longueur | les ponts de chemin à une arche, culées comprises, prennent deux fois leur ouverture |
| Flèche de l'arche | 0,75 m, un peu moins du tiers de l'ouverture | arche surbaissée des petits ponts ruraux, l'eau du décor étant au niveau du sol |
| Largeur hors tout | 2,6 m au modèle : tablier de 2 m, deux parapets de 0,3 m | un chemin de 2 m, la largeur médiane du kit `chemin` (1, 2, 3 m) |
| Tablier | 1,05 m au sommet, 0,12 m aux deux bouts | les bouts affleurent la bande du chemin, posée à 0,11 m |
| Parapet | 0,45 m au-dessus du tablier, chaperon clair de 0,08 m | « parapet bas », demande |
| Bandeau de l'arche | 0,25 m d'épaisseur | la couronne de voussoirs, plus claire que le mur |

Les proportions se recoupent : 2,6 m d'ouverture et 0,75 m de flèche laissent 0,3 m de maçonnerie au-dessus de la clé sous un
tablier à 1,05 m, et les deux rampes de 1,2 m montent de 0,93 m, une pente de 38 % qu'un pont en dos d'âne admet.

Aucune photo lisible par l'outil : les mesures viennent des cotes courantes d'un pont de chemin rural à une arche, et non d'une
source relevée.

## Ce qui le fait reconnaitre

1. L'arche sombre ouverte au-dessus de l'eau, de profil comme de trois quarts : un demi-cercle d'ombre sous la maçonnerie.
2. Le dos d'âne : le tablier monte puis redescend, et le parapet le suit des deux côtés.
3. Vu de haut, les deux lignes claires des chaperons de part et d'autre d'une bande qui prolonge le chemin.

## Avec quoi on le confond

| Objet | Ce qui l'en sépare |
|---|---|
| Un mur de pierre ou un muret | l'arche ouverte dessous, et deux parapets parallèles au lieu d'un mur |
| Une passerelle de bois | la masse pleine de la maçonnerie, la couleur de pierre, pas de garde-corps à claire-voie |
| Un tunnel ou une buse | le tablier et ses parapets au-dessus, l'arche large et basse plutôt qu'un rond |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#ece0c4` | les chaperons des parapets et la couronne de l'arche |
| Moyen | `#c7b591` | les murs de tête et les parapets |
| Moyen clair | `#dccfae` | le tablier, la couleur de la bande du chemin (`route` des helpers) pour qu'il le prolonge |
| Sombre | `#5c5446` | l'intrados de l'arche, l'ombre sous le pont |

## Étirement

Le pont s'étire par tranches, jamais à l'échelle non uniforme, qui épaissit les parapets d'une route jusqu'à 0,65 m et pince
l'arche d'un chemin d'un mètre en ogive. Sur la longueur, ce qui est entre les deux pieds de l'arche (ancre `arche`, en x = ±1,3)
s'ouvre à la largeur de l'eau, et ce qui est au-delà, rampes et bornes, se décale sans changer. Sur la largeur, les deux moitiés
s'écartent de part et d'autre de l'axe : parapets, bornes et couronne gardent leur épaisseur, seul le tablier s'élargit. La
flèche reste de 0,75 m : une arche ouverte à 3,6 m se surbaisse, une arche de 1,6 m se lève en plein cintre.

## Modèle

`pont.js`, 292 triangles, 4 couleurs, sous le plafond d'un modèle (300). Emprise de 5 × 2,68 m, 1,5 m de haut. Exporté de
Blender depuis `pont.blend` (parti A), une seule pièce rigide, la longueur sur X, posée à
y = 0. Ancre `arche` en [1,3, 0, 0], le pied de l'arche côté +X.
