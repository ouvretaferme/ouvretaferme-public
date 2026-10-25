# Mare, fiche de référence

Source : aucune photo. La mare est celle qu'une structure creuse ou garde au bord d'une parcelle, pour l'eau, les auxiliaires et la
faune : un plan d'eau de quelques dizaines de mètres carrés, aux berges en pente douce, bordé de roseaux.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Emprise de la berge | 9,2 × 6,7 m | un ovale irrégulier d'environ 50 m², la taille d'une mare de ferme |
| Eau | 84 % du rayon de la berge | une bande de sable de 0,7 m sur le grand axe, qui dessine le bord |
| Contour | 14 points, rayon tiré entre 82 et 100 % | assez irrégulier pour ne pas se lire comme un bassin |
| Roseaux | 7 touffes de 3 brins, 1,1 à 1,9 m | une massette brune sur un brin par touffe |
| Nénuphars | 3 feuilles de 0,4 à 0,65 m de rayon | entaillées, plus grandes que nature pour se voir de haut |
| Pierres | 3, de 0,3 à 0,6 m | posées sur la berge |

## Traits qui la font reconnaitre, classés

1. Une eau en aplat bleu pâle au contour irrégulier, cerclée d'une berge claire.
2. Des touffes de roseaux sombres sur le bord, dont les massettes brunes.
3. Des feuilles de nénuphar entaillées sur l'eau.

## On la confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| La poche d'eau (`poche-eau.js`) | une mare est à plat, sans volume, et son contour n'est pas un rectangle arrondi |
| Un bassin d'agrément | le contour irrégulier et la berge de sable, sans margelle |
| Un cours d'eau tracé | une mare est fermée, à peine plus longue que large |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `route` `#dccfae` ; `vitre` `#9fd0dc` ; `feuilleClaire` `#9ccc65` | la berge ; l'eau ; les nénuphars |
| Moyen | `gris` `#8d9499` | les pierres |
| Sombre | `feuilleSombre` `#2f7d3d` ; `boisSombre` `#6f4b32` | les roseaux ; les massettes |

## Modèle

`mare.js`, 221 triangles, 6 couleurs, plafond d'un objet 900. Graine fixe 11 : la cuisson rend toujours le même contour. L'eau
porte le nom `eau`, pour que la scène lui garde un matériau à reflets. Ancre : `centre`, à la surface de l'eau.

## Ce qui reste faible

- Six couleurs pour un objet : la berge, l'eau et les nénuphars font trois clairs voisins.
- Les nénuphars sont tirés au hasard de la graine : deux mares posées côte à côte ont les mêmes, la scène les distingue par la
  rotation et l'échelle.
