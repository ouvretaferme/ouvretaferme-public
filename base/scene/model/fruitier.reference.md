# Arbre fruitier, fiche de référence

Couvre `fruitier.js` (sans fruits), `fruitier.rouge.js` et `fruitier.or.js`. Source : aucune photo. Le fruitier est l'arbre de
verger conduit en gobelet, pommier, poirier ou prunier en demi-tige : un tronc court, une fourche à hauteur d'épaule, une couronne
évidée au centre et étalée. Il est vu de loin, et l'essence ne se lit qu'à la couleur des fruits.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Couronne | 3,4 m de large | un demi-tige de verger adulte |
| Hauteur | 3 m | le fruitier reste à hauteur d'échelle, pour la cueillette |
| Fourche | 1,15 m | le tronc d'un demi-tige se partage à hauteur d'épaule |
| Charpentières | 2, opposées | ouverture inégale, elles entrent dans la couronne par dessous |
| Rameaux | 5 par charpentière, en éventail | chacun avec deux brindilles : nu, l'arbre garde la largeur de son parasol |
| Dessous de la couronne | écrasé, 0,35 du rayon contre 1,25 au-dessus | la couronne d'un gobelet est un parasol, pas une boule |
| Fruits | 4, de 0,2 à 0,27 m | plus gros que nature pour se voir de haut, côté ciel et bord |

## Traits qui le font reconnaitre, classés

1. Un parasol de feuillage large et aplati dessous, plus large que haut.
2. La fourche à nu sous le parasol, deux charpentières qui s'écartent.
3. Les fruits rouges ou dorés piqués sur le dessus, pour les variantes qui en portent.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le feuillu en boule (`arbre.js`) | le parasol aplati et la fourche visible ; le feuillu porte une boule sur un tronc droit, plus haute que large |
| Le buisson (`buisson.js`) | le tronc et la fourche à nu |
| Un arbre d'ornement | les fruits, pour les deux variantes qui en portent |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `feuille` `#4d9a4a` | les faces de la couronne tournées vers le soleil |
| Moyen | `#8a6444`, hors de `P` | les rameaux et les brindilles |
| Sombre | `feuilleSombre` `#2f7d3d` ; `boisSombre` `#6f4b32` | le dessous et le flanc à l'ombre ; le tronc et les charpentières |
| Trait | `rouge` `#d6452f` ou `or` `#f2b632` | les fruits de `fruitier.rouge` ou de `fruitier.or` |

## Modèles

| Slug | Triangles | Couleurs |
|---|---|---|
| `fruitier` | 266 | 4 |
| `fruitier.rouge` | 298 | 5 |
| `fruitier.or` | 298 | 5 |

Plafond d'un objet 900. Graine fixe 1 : les trois variantes ont la même charpente et la même couronne, seuls les fruits changent.
La couronne porte le nom `feuillage` et les fruits `fruit`, pour que la scène les teinte à la saison et les retire l'hiver. Les
faces du fond du parasol, que nulle caméra placée au-dessus ne voit, ne sont pas gardées. Ancres : `cime`, `pied`.

## Ce qui reste faible

- Le fruitier en fleur du prototype (bouquets blancs à la place des fruits) n'est pas cuit : aucune variante ne le demande.
- Le dessous du parasol est ouvert : une caméra au ras du sol, sous la couronne, verrait le ciel à travers.
