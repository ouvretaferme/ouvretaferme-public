# Panneau, fiche de référence

Source : aucune photo. Le panneau est celui qu'une structure plante à l'entrée d'une parcelle ou au bord du chemin : une planche
peinte sur deux poteaux, sous un petit chaperon qui la protège de la pluie.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Largeur | 2 m | planche du panneau, 2,16 m avec le chaperon |
| Hauteur | 1,9 m | assez haut pour se lire par-dessus une culture basse |
| Planche | 0,9 m de haut, 0,2 m d'épaisseur | épaissie pour ne pas disparaitre de profil |
| Poteaux | 0,15 m de côté, écartés de 1,5 m | ils s'arrêtent sous la planche, qui pose dessus |
| Face | 1,8 × 0,7 m | l'aplat clair où la scène écrit, sur chaque côté |
| Chaperon | 0,36 m de profondeur | il déborde de 8 cm de chaque côté |

## Traits qui le font reconnaitre, classés

1. Une planche claire et large, posée haut sur deux poteaux sombres.
2. Le chaperon sombre qui la coiffe et déborde.
3. Deux lignes d'écriture figurées sur la face, tant que la scène n'y pose pas de texte.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Un portail ou une barrière | la planche est pleine, plus haute que les poteaux sous elle, et rien ne passe dessous qu'on ouvrirait |
| Un banc | la hauteur : la planche est à hauteur d'yeux, dressée et non couchée |
| L'enseigne d'un bâtiment | le panneau tient seul sur ses poteaux, loin de tout mur |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `creme` `#efe3cc` | les deux faces |
| Moyen | `bois` `#c79a62` | le chant de la planche |
| Sombre | `boisSombre` `#6f4b32` ; `encre` `#1d4b53` | les poteaux et le chaperon ; l'écriture figurée |

## Modèle

`panneau.js`, 96 triangles, 4 couleurs, plafond d'un objet 900. Les faces regardent ±Z et portent le nom `face`. Ancres :
`texte` (centre de la face avant) et `texteCoin` (son coin haut droit), d'où la scène lit le rectangle de 1,8 × 0,7 m où elle écrit.

## Ce qui reste faible

- L'écriture figurée est sur la face avant seulement, et la scène la recouvre quand elle y pose un nom. Le panneau de zone du
  prototype, plus grand et sans écriture, n'est pas cuit : la scène le tire de celui-ci par l'échelle.
