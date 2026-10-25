# Hérisson

Le hérisson d'Europe du thème « Matin calme », commandé par la scène d'animation le 2026-10-11 : il trotte au pied des haies à
l'aube. Une pose, `herisson.js`, en marche, museau en avant.

## Proportions

| Mesure | Valeur | Source |
|---|---|---|
| Longueur | 0,25 m de la croupe à la truffe | le hérisson adulte, 20 à 30 cm |
| Hauteur | 0,12 m | dôme bas, pattes presque cachées |
| Museau | un cinquième de la longueur, pointu | ce qui le sépare d'une souche ou d'un rocher |

## Ce qui le fait reconnaitre

1. Le museau pointu et clair, terminé par une truffe noire.
2. Le dôme hérissé, pointes claires sur fond brun, couchées vers l'arrière.
3. Les pattes courtes, à peine visibles sous la jupe.

## Avec quoi on le confond

| Objet | Ce qui l'en sépare |
|---|---|
| Un porc-épic | trois fois plus grand, longs piquants dressés |
| Une pomme de pin ou une pierre | ni museau ni pattes ; c'est ce qu'il devient vu de dos |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#c9b89a` | la pointe des piquants |
| Moyen | `#b8946b`, `#6b5a48` | la face, le ventre et la jupe ; le fond des piquants |
| Sombre | `#1c1a1a` | la truffe et les yeux |

## Modèle

Os `corps` (dôme et pattes) et `tete` (museau, yeux, truffe), pivots [0, .061, 0] et [.045, .04, 0], à la place qu'ils
avaient dans le modèle écrit à la main. Modelé dans Blender (skill `3d`), source `herisson.blend` posée à côté du module : le
corps et ses pattes sont une seule peau fondue, coupée au sol puis réduite, la tête une seconde peau dont l'arrière se cache
sous le front du manteau. Le manteau se hérisse facette par facette, chaque touffe tirée vers l'arrière sur une longueur qui
varie, une sur deux portant une facette claire. `herisson.js` fait 298 triangles et 4 couleurs, sous le plafond commun de 300,
pour 0,277 m d'emprise et 0,130 m de haut. Trois juges à l'aveugle sur trois l'ont nommé, de 95 à 96 sur 100, l'échidné en
deuxième hypothèse à cause du museau long ; aucun n'a pris le témoin (le rocher) pour lui. Les oreilles ne survivent pas à la
réduction, la silhouette seule reste ambigüe, et vu de dos, museau caché, il se lit comme une boule ou une pomme de pin.
