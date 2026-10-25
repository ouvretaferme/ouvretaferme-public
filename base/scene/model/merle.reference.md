# Merle

Le merle noir mâle du thème « Matin calme », commandé par la scène d'animation le 2026-10-11 : il marche au sol entre les rangs
et s'envole quand on approche. Deux poses, `merle.js` posé et `merle.vol.js` ailes ouvertes.

## Proportions

| Mesure | Valeur | Source |
|---|---|---|
| Longueur | 0,26 m du bec à la queue | le merle noir adulte, 24 à 27 cm |
| Hauteur posé | 0,12 m | port droit, queue un peu relevée |
| Envergure en vol | 0,35 m | 34 à 38 cm, ramenée à l'aile en battement |
| Bec | 3,6 cm, un septième de la longueur | grossi pour se lire en jeu |

## Ce qui le fait reconnaitre

1. Le bec orange vif sur un oiseau entièrement noir, de profil, de trois quarts et de dessus.
2. Le cercle orange autour de l'œil.
3. La longue queue relevée, qui le sépare des oiseaux ronds.

## Avec quoi on le confond

| Objet | Ce qui l'en sépare |
|---|---|
| Une corneille | deux fois plus grande, bec noir |
| Un étourneau | plumage tacheté et irisé, queue courte |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#e8a317` | le bec et le cercle de l'œil |
| Moyen | `#4a3b2e` | les pattes |
| Sombre | `#1f1d22` | le plumage |

## Modèle

Os `corps`, `tete`, `queue`, `aile-g`, `aile-d`, pivots aux articulations, les pattes portées par `corps`. Modelé dans
`merle.blend` et `merle.vol.blend`, les deux poses d'un même oiseau : une peau unique remaillée puis coupée au cou, une rotule
qui ferme la nuque quand la tête tourne, l'œil cerclé d'orange posé sur la tête. `merle.js` fait 295 triangles et `merle.vol.js`
293, 3 couleurs chacun. Posé, 0,27 m de long et 0,12 m de haut ; en vol, 0,33 m d'envergure. Trois juges à l'aveugle sur trois
l'ont nommé dans les deux poses, 92 posé et 85 en vol.
