# Garage du tracteur, fiche de référence

Source : aucune photo. C'est le garage du bourg de la compta, où rentre le tracteur. Le POC `bourg3d-plan` (`js/monde.js`, l'aire
`garage`) n'y pose qu'une aire de terre où le tracteur fait demi-tour, sans dessiner de bâtiment. Les cotes sont celles d'une remise
de tracteur courante, réglées pour que le tracteur du bourg, posé à l'échelle 1,5, passe la porte.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Emprise des murs | 8 × 10 m | une travée de remise, faitage selon la profondeur |
| Hauteur à l'égout | 4,75 m | la porte de 4,3 m et son socle passent dessous |
| Hauteur au faitage | 5,7 m | un mètre de montée sur 4 m, soit 13° : un toit bas, loin du pignon élancé d'une grange |
| Débord du toit | 0,3 m | aux rives comme à l'égout |
| Socle | 0,15 m | gris, il déborde des murs de 6 cm |
| Porte de garage | 5,4 × 4,3 m | centrée sur le pignon avant, plus large que haute ; le tracteur posé à 1,5 fait 3,4 m de large et 4,1 m de haut |
| Porte de service | 1,1 × 2,1 m | sur le long côté droit, à 2,5 m du milieu |
| Jours | deux, de 1,4 × 0,7 m, appui à 2,2 m | en bande haute sur le long côté gauche |

## Traits qui le font reconnaitre, classés

1. Une grande porte grise et pleine, plus large que haute, qui prend le pignon. Elle n'a ni vantaux ni rail.
2. Des murs enduits, lisses, sans planches.
3. Un toit d'ardoise bas à deux pans, qui le sépare du hangar de tôle et de la tuile rouge d'une grange.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le hangar (`hangar.js`) | l'enduit et l'ardoise au lieu de la tôle beige, la porte pleine qui ferme le pignon |
| L'abri (`abri.js`) | la taille, et la porte à la mesure d'un tracteur et non d'un homme |
| Une grange | l'enduit au lieu du bardage, le toit bas, la porte pleine sans vantaux |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `creme` `#efe3cc` ; `blanc` `#f6f3ea` ; `vitre` `#9fd0dc` | les murs ; les petits bois des jours ; leur vitrage |
| Moyen | `gris` `#8d9499` ; `ardoise` `#55677a` | la porte de garage, le socle et les appuis ; le toit |
| Sombre | `boisSombre` `#6f4b32` | la porte de service |

## Modèle

`garage.js`, 208 triangles, 6 couleurs, plafond d'un bâtiment 1 500. Engendré par le skill `3d-batiment` depuis la fiche qu'il
embarque. Pignon de la porte vers +Z, emprise de 10,64 m avec les débords, 5,94 m de haut. Ancres :
- `porte` (milieu du seuil, au sol) : `[0, 0, 5]` ;
- `porteCoin` (coin haut droit de l'ouverture) : `[2.7, 4.45, 5]`, d'où la scène lit 5,4 m de large et 4,45 m du sol au linteau ;
- `enseigne` : `[0, 5.05, 5.24]` ;
- `faitage` : `[0, 5.94, 0]`.

La porte de garage n'a ni panneaux horizontaux ni encadrement. Le catalogue partagé de `base/scene/static/js/building.js` n'a pas
de porte sectionnelle, et n'a pas non plus de dalle à poser devant la porte. Créer ces deux entrées touche le cadre commun à tous
les bâtiments : cela reste à faire par une entrée du catalogue (skill `3d-batiment`, « Créer une entrée »).
