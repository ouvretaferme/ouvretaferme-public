# Buisson, fiche de référence

Source : aucune photo. Le buisson est un seul pied de la haie du prototype :
un arbuste taillé ou laissé libre, noisetier, sureau ou aubépine, tel qu'on en trouve isolé au coin d'une parcelle ou en touffe
le long d'un chemin.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Emprise | 1,9 m | une masse de 1,8 m de diamètre et une épaule qui en sort |
| Hauteur | 1,5 m | plus large que haut, comme un arbuste rabattu |
| Épaule | la moitié du rayon de la masse | posée de côté, à 0,6 m du centre, plus basse |
| Dessous | écrasé à 0,8 du rayon | assis sur le sol, pas posé dessus |

## Traits qui le font reconnaitre, classés

1. Une masse de feuillage bosselée, assise sur le sol, sans tronc visible.
2. L'épaule plus petite qui casse la boule.
3. Les deux verts, clair dessus, sombre sur les flancs.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Un arbre (`arbre.js`, `fruitier.js`) | l'absence de tronc : la masse touche le sol |
| Une pierre ou un rocher | la couleur et la bosse, et l'épaule qui le rend asymétrique |
| La haie tracée | un buisson est une touffe isolée, la haie un cordon taillé |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `feuille` `#4d9a4a` | les faces tournées vers le soleil |
| Sombre | `feuilleSombre` `#2f7d3d` | les flancs et le dessous |

Deux couleurs suffisent : un buisson n'a rien d'autre que son feuillage, et la scène le teinte d'un des trois verts du décor.

## Modèle

`buisson.js`, 40 triangles, 2 couleurs, plafond d'un objet 900. Graine fixe 1 : la scène le varie par la rotation, l'échelle et
la teinte. Le kit `buissons` du décor le compose. Sans ancre.
