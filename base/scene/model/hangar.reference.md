# Hangar agricole en bardage métallique, fiche de référence

Source : une photo de rue fournie le 2026-10-08, gardée hors du dépôt, prise de trois
quarts face au pignon de la porte. Aucune cote n'est connue : les dimensions sont estimées sur la photo, à partir d'une porte de
hangar agricole de 4,2 m de haut.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Hauteur de la porte | 4,2 m | hypothèse de départ |
| Hauteur à l'égout | 5 m | la porte monte à 84 % du mur |
| Hauteur au faitage | 6,6 m | pente faible, 1,6 m de montée sur une demi-largeur de 5 m, soit 18° |
| Largeur du pignon | 10 m | la porte en occupe 44 % |
| Longueur | 18 m | estimée sur le long côté en fuite, la moins sûre des cotes |
| Largeur de la porte | 4,4 m | deux vantaux coulissants |
| Soubassement de béton | 0,55 m | visible sur le long côté seulement |

## Traits qui le font reconnaitre, classés

1. Un volume long et simple sous un toit gris à deux pans de faible pente, sans débord marqué.
2. Un bardage de tôle nervurée beige rosé, à nervures verticales, posé jusqu'au sol en façade.
3. Une grande porte coulissante à deux vantaux, bardée comme le mur, décalée à droite du milieu du pignon, avec une
   traverse à mi-hauteur.
4. Un montant rouillé au bord droit de la porte.
5. Un soubassement de béton gris sur le long côté.
6. Une descente d'eau grise à l'angle gauche.

Le vieux panneau rouillé de la photo, posé contre le mur à gauche de la porte, est retiré (2026-10-12).

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le bureau (`bureau.js`) | le toit gris de tôle et non de tuile, aucune fenêtre, une porte plus large que haute qui prend près de la moitié du pignon |
| L'abri (`abri.js`) | l'échelle (dix fois l'emprise), le bardage clair et non le bois, la porte à deux vantaux |
| Un tunnel de culture | des murs droits et un toit à deux pans, là où le tunnel est un demi-cylindre translucide |

## Couleurs

Relevées sur la photo :

| Pièce | Couleur |
|---|---|
| Bardage | `#dcc4b4` |
| Nervures, rail, traverses | `#bfa390` |
| Vantaux | `#d2b8a6` |
| Toit | `#8b8882` |
| Béton | `#9c978d` |
| Montant rouillé | `#7c3d2b` |
| Descente d'eau | `#a3a8aa` |

Rendues avec la palette `P` de `helpers.js` : clair `creme` (vantaux) et `pierre` (bardage, couvre-joints),
moyen `bois` (rail, traverses, fond de la porte) et `gris` (béton, descente d'eau), sombre `toleSombre` (toit) et `tuile`
(montant rouillé).

## Modèle

`hangar.js`, 616 triangles, 6 couleurs, plafond d'un bâtiment 1 500. Pignon de la porte vers +Z, 10 × 18 m, faitage à 6,6 m,
emprise de 11 × 18,7 m avec les débords. Les vantaux portent les noms `vantailGauche` et `vantailDroit`. Ancres : `porte` (milieu
du seuil), `porteCoin` (coin haut droit de l'ouverture, d'où la scène lit 4,4 × 4,2 m), `enseigne`, `faitage`.

À 18,7 m, la cuisson sur 16 bits s'écarte de la source de 0,14 mm, un demi-pas de sa boite : c'est le seul modèle au-delà du
dixième de millimètre.

## Relecture du 2026-10-08

Un relecteur a confronté la scène à la photo. Corrigé dans le modèle : bardage éclairci et rosé (il virait au caramel sous
l'éclairage de la scène), toit aminci à 7 cm et débord ramené de 35 à 15 cm, faitière réduite, soubassement de béton ramené au nu
du bardage, panneau rouillé élargi au quart de la façade avec sa moitié crème.
