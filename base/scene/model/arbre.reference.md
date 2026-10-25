# Arbres du décor, fiche de référence

Couvre `arbre.js` (le feuillu en boule), `arbre.sapin.js` et `arbre.peuplier.js`. Source : aucune photo. Les trois essences sont
celles des arbres de la scène comptable, ramenées à la taille des arbres du décor. Ce sont des arbres
d'image, réduits à leur silhouette : un vrai chêne ou un vrai peuplier d'Italie ferait plusieurs fois la hauteur d'un hangar, et
écraserait la scène. Seuls les rapports de la silhouette sont tenus.

## Proportions

| Essence | Emprise | Hauteur | Rapport | Ce qui la dessine |
|---|---|---|---|---|
| Feuillu en boule | 3,6 m | 5,3 m | 1,45 fois plus haut que large | une boule de couronne sur un tronc qui monte à mi-hauteur, une bosse sur l'épaule |
| Sapin | 2,9 m | 5,7 m | 2 fois plus haut que large | trois jupes coniques qui se recouvrent, sur un tronc court |
| Peuplier | 1,8 m | 6,1 m | 3,4 fois plus haut que large | un fuseau au ventre dans le tiers bas, à pointe effilée |

Le feuillu porte cinq branches maitresses et une flèche, coudées à mi-rayon, chacune finie par un éventail de quatre rameaux,
sous le feuillage : nu, l'arbre garde la largeur de sa couronne. Le peuplier porte une flèche et huit branches dressées contre elle.

## Traits qui le font reconnaitre, classés

1. La silhouette de l'essence : boule, étages pointus, fuseau.
2. Le feuillage taillé à facettes en deux verts, clair au soleil et sombre dessous.
3. Le tronc sombre, court sous la boule et le sapin, à peine visible sous le fuseau.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le fruitier (`fruitier.js`) | le feuillu a une boule pleine sur un tronc droit ; le fruitier, un parasol aplati sur une fourche à nu |
| Le buisson (`buisson.js`) | le tronc : un buisson est assis sur le sol |
| Un cyprès, pour le sapin | le cran entre deux étages et le bord dentelé des jupes ; un cône lisse se lirait comme un cyprès |
| Un cyprès, pour le peuplier | le ventre bas et la pointe effilée ; le cyprès est un cône plus régulier |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `feuille` `#4d9a4a` | les faces du feuillage tournées vers le soleil |
| Moyen | `#8a6444`, hors de `P` | les rameaux et les brindilles, plus clairs que le tronc pour détacher le bout de la charpente sur le pré |
| Sombre | `feuilleSombre` `#2f7d3d` ; `boisSombre` `#6f4b32` | le dessous et le flanc à l'ombre ; le tronc et les branches maitresses |

Le sapin n'a ni rameau ni branche, d'où ses trois couleurs. La scène teinte le feuillage d'un des trois verts voisins du décor :
les deux verts du modèle sont ceux qu'elle remplace.

## Modèles

| Slug | Triangles | Couleurs |
|---|---|---|
| `arbre` | 364 | 4 |
| `arbre.sapin` | 60 | 3 |
| `arbre.peuplier` | 200 | 4 |

Plafond d'un objet 900. Graine fixe 1 : la cuisson rend toujours le même arbre, et la scène le varie par la rotation, l'échelle et
la teinte du feuillage. Le feuillage porte le nom `feuillage`, pour que la scène le teinte à la saison et retire l'hiver celui des
feuillus. Ancre : `cime`, au sommet.

## Ce qui reste faible

- Le feuillu porte encore 252 triangles de charpente nue, qu'on ne voit qu'en hiver. Ses brindilles sont retirées pour qu'un bosquet
  de feuillus tienne sous 1 500 triangles en moyenne, comme demandé le 2026-10-11.
- Une seule graine par essence : un bosquet de feuillus montre le même arbre tourné, que l'échelle et la teinte ne déguisent qu'à
  distance.
