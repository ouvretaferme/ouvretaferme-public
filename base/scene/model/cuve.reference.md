# Cuve de 1 000 litres, fiche de référence

Source : aucune photo. La cuve est le conteneur de 1 000 litres du commerce (IBC), que les structures récupèrent pour stocker l'eau
de pluie : une outre de plastique blanc dans une cage de tubes galvanisés, sur une palette.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Emprise | 1,2 × 1 m | la palette d'une cuve du commerce |
| Hauteur | 1,16 m | cote du commerce ; le modèle rend 1,25 m avec le bouchon |
| Palette | 0,15 m | deux semelles et un plateau |
| Cage | 4 montants, 2 ceintures | une cage réelle a des barreaux tous les 20 cm, trop fins pour la scène |
| Bouchon | 0,28 m de diamètre | sur le dessus, au centre |
| Vanne | au pied de la face avant | à 0,3 m du sol |

## Traits qui la font reconnaitre, classés

1. Un cube blanc presque aussi haut que large, sur une palette de bois.
2. La cage grise qui l'enserre, montants aux angles et ceintures horizontales.
3. La vanne rouge au pied de la face avant, et le bouchon noir dessus.

## On la confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Une caisse ou une palette chargée | la cage grise et la vanne rouge |
| La poche d'eau (`poche-eau.js`) | des arêtes droites et une hauteur égale à la largeur |
| Un bac à compost (`compost.js`) | fermé, blanc, sans planches |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `blanc` `#f6f3ea` | l'outre |
| Moyen | `tole` `#aab3b8` ; `bois` `#c79a62` | la cage ; la palette |
| Sombre | `noir` `#2c3136` | le bouchon |
| Trait | `rouge` `#d6452f` | la vanne |

## Modèle

`cuve.js`, 156 triangles, 5 couleurs, plafond d'un objet 900. La vanne regarde +Z. Chaque ceinture est une dalle qui traverse
l'outre, dont seule la tranche dépasse. Ancre : `vanne`, devant la vanne, où la scène raccorde un tuyau ou pose un arrosoir.
