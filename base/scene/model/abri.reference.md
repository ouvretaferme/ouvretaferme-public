# Abri de jardin en bois, fiche de référence

Source : aucune photo. L'abri est la cabane de bois du commerce, de 3 × 4 m au sol, qu'une structure pose pour ranger ses outils.
Les cotes sont celles des abris en kit de cette surface : murs de 2 m à 2,2 m, faitage vers 2,8 m, porte pleine d'un mètre.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Emprise des murs | 3 × 4 m | un abri de 12 m², faitage selon la longueur |
| Hauteur à l'égout | 2,1 m | une porte de 1,75 m passe dessous avec son linteau |
| Hauteur au faitage | 2,8 m | 0,7 m de montée sur 1,5 m, soit 25° |
| Débord du toit | 0,2 m | sur les quatre côtés |
| Socle | 0,2 m | une dalle grise qui déborde des murs de 6 cm |
| Porte | 1,1 × 1,75 m | décalée à gauche du pignon, posée sur le socle |
| Fenêtre | 0,62 m de côté | à droite de la porte, à 1,4 m du sol en son centre |

## Traits qui le font reconnaitre, classés

1. Une cabane de bois clair rythmée de couvre-joints sombres, un par mètre sur les longs côtés.
2. Une porte pleine décalée sur le pignon, et une petite fenêtre carrée à côté d'elle.
3. Un toit de tôle sombre à deux pans qui déborde, coiffé d'une faitière plus claire.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le hangar (`hangar.js`) en réduction | le bois et ses couvre-joints sombres, la porte décalée à taille d'homme et la fenêtre |
| Le bureau (`bureau.js`) | le bois et non le crépi, la tôle et non la tuile, aucun volet |
| Une caisse ou un coffre | le toit à deux pans et la porte |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `bois` `#c79a62` ; `vitre` `#9fd0dc` | les murs ; la fenêtre |
| Moyen | `tole` `#aab3b8` ; `gris` `#8d9499` | la faitière ; le socle |
| Sombre | `boisSombre` `#6f4b32` ; `toleSombre` `#6f7a80` | les couvre-joints et la porte ; les deux pans du toit |

## Modèle

`abri.js`, 184 triangles, 6 couleurs, plafond d'un bâtiment 1 500. Pignon de la porte vers +Z, emprise de 3,45 × 4,4 m avec les
débords. Ancres : `porte` (milieu du seuil, au sol), `porteCoin` (coin haut droit de l'ouverture, d'où la scène lit 1,1 m de large
et 1,95 m du sol au linteau), `faitage`.
