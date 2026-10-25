# Bureau de la ferme, fiche de référence

Source : deux photos fournies le 2026-10-08, gardées hors du dépôt. La première montre
le pignon d'entrée et le long côté gauche, la seconde le même pignon vu de l'autre bord et le long côté droit. Aucune
cote n'est connue : les dimensions sont estimées à partir d'une porte de 2,1 m de haut.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Hauteur de la porte | 2,1 m | hypothèse de départ |
| Hauteur à l'égout | 2,8 m | la porte monte aux trois quarts du mur |
| Hauteur au faitage | 3,8 m | pente faible, 1 m de montée sur 2,5 m, soit 22° |
| Largeur du pignon | 5 m | un peu plus de deux fois la hauteur de la porte |
| Longueur | 7,5 m | une fois et demie le pignon, la moins sûre des cotes |
| Débord des rives | 0,45 m | plaques grises portées par trois bouts de pannes par pan |

## Traits qui le font reconnaitre, classés

1. Une maisonnette basse en crépi beige sous un toit de tuiles rouge brun à deux pans.
2. Des volets blancs à toutes les ouvertures, ouverts à plat sur le mur ou clos.
3. Au pignon d'entrée, la porte vitrée blanche au bord droit, entre ses deux volets ouverts à pentures noires.
4. Un bac de lavage en béton contre ce pignon, à gauche de la porte, avec son robinet.
5. Les rives du toit qui débordent en plaques grises sur des bouts de pannes en bois, et une gouttière blanche par long côté.
6. Une petite cheminée à chapeau vers le fond du pan droit.

Long côté droit : une large fenêtre à petits bois entre deux volets ouverts. Long côté gauche : une petite fenêtre haute près
de la façade, puis deux fenêtres aux volets clos.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le hangar (`hangar.js`) | la tuile rouge brun, les volets blancs et la cheminée, une porte à taille d'homme |
| L'abri (`abri.js`) | le crépi clair et non le bois, le toit de tuile et non de tôle, les fenêtres à volets |

## Couleurs

Relevées sur les photos :

| Pièce | Couleur |
|---|---|
| Crépi, cheminée | `#cdbfa9` |
| Tuiles | `#9c5646` |
| Plaques de rive | `#a7a8a4` |
| Bouts de pannes | `#8a5a3a` |
| Volets, porte, gouttières | `#eef0f1` |
| Vitrages, eau du bac | `#2f3842` |
| Béton (bac, appuis, chapeau) | `#a9a69c` |
| Pentures, robinet | `#2b2b2b` |

Rendues avec la palette `P` de `helpers.js` : clair `blanc` (volets, porte, gouttières) et `creme` (crépi, cheminée), moyen
`gris` (plaques de rive, béton) et `vitre` (vitrages, eau du bac), sombre `tuile` (toit) et `boisSombre` (bouts de pannes). Les
pentures ne sont pas modelées : à l'échelle de la scène, les barres en relief des volets portent le trait.

## Modèle

`bureau.js`, 568 triangles, 6 couleurs, plafond d'un bâtiment 1 500. Pignon de la porte vers +Z, sol à y = 0, centré sur le
volume de 5 × 7,5 m. Les vitres et l'eau du bac portent le nom `vitre`. Ancres : `porte` (milieu du seuil), `porteCoin` (coin haut
droit de l'ouverture, d'où la scène lit 1,1 × 2,1 m), `cheminee`, `bac`. Le bac de lavage dépasse de 0,8 m devant le pignon : l'emprise
cuite, 5,9 × 8,7 m, le compte.
