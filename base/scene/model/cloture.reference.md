# Clôtures, fiche de référence

Couvre `cloture.mouton.js`, `cloture.ganivelle.js`, `cloture.rigide.js`, `cloture.electrique.js` et le piquet seul de chacune,
`cloture.<modèle>.bout.js`. Source : les clôtures du prototype de la scène de décor, où le grillage était
un motif peint et devient ici de la géométrie. Une travée porte son piquet à z = 0 et court selon +Z jusqu'au piquet de la
suivante ; la scène la répète le long d'une ligne dessinée, l'étire de 0,7 à 1,3 fois, et ferme le dernier segment par le
piquet seul. Les fils, les lattes et les rubans sont des lames à deux faces, le matériau du décor n'en éclairant qu'une.

## Proportions

| Modèle | Travée | Piquet | Remplissage | Triangles, par mètre |
|---|---|---|---|---|
| Mouton | 3 m | bois sombre, 12 cm, 1,3 m | 6 fils horizontaux serrés au pied, 4 verticaux, de 3 cm à 1,01 m | 60, 20 par mètre |
| Ganivelle | 2,5 m | bois clair, 12 cm, 1,32 m | 9 lattes de 10 cm à pointe en biseau, de 1,14 à 1,25 m, 2 rangs de fil | 82, 33 par mètre |
| Rigide | 2,5 m | carré vert sombre de 6 cm, 1,38 m, coiffe noire | panneau de 5 cm à 1,28 m, 4 fils horizontaux, 9 verticaux | 76, 30 par mètre |
| Électrique | 5 m | plastique blanc de 7 cm, 1,05 m, étrier à 14 cm | 2 rubans de 4 cm à 45 et 90 cm, fléchis de 4 cm au milieu | 44, 9 par mètre |

Les piquets seuls font 20 triangles (mouton, ganivelle), 24 (rigide) et 28 (électrique), pour un plafond de 30.

Les mailles sont plus larges que les vraies (25 cm au lieu de 5 pour le panneau rigide, 10 cm de latte au lieu de 5,5) et les
fils épaissis à 2,4 cm, le piquet électrique grossi d'une fois et demie : à l'échelle de la scène, une clôture se lit à son
rythme et à ses jours, pas à son grain.

## Traits qui les font reconnaitre, classés

1. La couleur du piquet et du remplissage : bois sombre et fil gris, châtaignier, vert, blanc et orange.
2. Le rythme du remplissage : grille carrée, lattes pointues, grille verticale serrée, deux rubans.
3. La hauteur : le ruban électrique reste sous le mètre, le panneau rigide monte au-dessus.

## On les confond entre elles

| Objet | Ce qui l'en sépare |
|---|---|
| Mouton et rigide | le piquet rond en bois contre le poteau carré vert, le fil gris contre le panneau vert |
| Ganivelle et une palissade | les jours entre les lattes et leurs pointes inégales |
| Électrique et un fil tendu | l'orange des rubans |

## Couleurs

| Modèle | Clair | Moyen | Sombre |
|---|---|---|---|
| Mouton | fil `#b9bebb` | | piquet `boisSombre` `#6f4b32` |
| Ganivelle | piquet `bois` `#c79a62` | lattes `#b48457` et `#9e7048` | fil `#5d5a55` |
| Rigide | | panneau `#2f5a3c` | poteau `#244630`, coiffe `noir` `#2c3136` |
| Électrique | piquet `#ece8dc` | rubans `#ee8a2e` | |
