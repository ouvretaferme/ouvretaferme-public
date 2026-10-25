# Référence du modèle d'avatar

Les détails que `SKILL.md` ne fait que citer. Les chiffres des règles se lisent dans `avatar.json`, qui fait foi ; ceux
qui suivent décrivent `corps.glb` et la façon dont le site lit le modèle.

## Repères

- Les chiffres d'`avatar.json` (`pivots`, `bounds`, `anchors`) sont en axes glTF : Y vers le haut, le personnage regarde
  vers +Z.
- Dans Blender, Z est vers le haut et le personnage regarde vers -Y. Un point glTF (x, y, z) est en (x, -z, y) dans
  Blender : le pivot du coude gauche, (0.225, 0.849, 0.014) en glTF, est en (0.225, -0.014, 0.849) dans Blender.
  L'export fait la conversion.
- G et D sont la gauche et la droite du personnage : sa gauche est du côté +X, en glTF comme dans Blender. Sur une vue de
  face, elle est donc à droite de l'image.
- Un mètre de Blender vaut un mètre.

## Le corps de départ, `corps.glb`

34 pièces, 1 780 triangles, 34 primitives. Il reste donc 1 420 triangles et 30 primitives pour habiller, coiffer et
équiper, sous les plafonds de 3 200 et 64. Cette table ne vaut que pour `corps.glb` : le modèle que `composer.mjs` écrit a
ses propres pièces, décrites plus bas.

| Pièce | Triangles | Ce que c'est |
|---|---|---|
| `bassin.001` | 48 | le bassin |
| `torse.001`, `torse.002` | 32, 16 | deux anneaux du ventre, entièrement cachés dans `torse.003` |
| `torse.003` | 112 | le buste |
| `torse.004` | 32 | le haut du buste, sous les épaules |
| `torse.005` à `torse.007` | 32 chacune | les épaules, en anneaux qui montent vers le cou |
| `torse.008` | 48 | le cou |
| `brasG.001`, `brasD.001` | 96 | le bras, de l'épaule au coude |
| `avantBrasG.001`, `avantBrasD.001` | 56 | l'avant-bras |
| `avantBrasG.002`, `avantBrasD.002` | 40 | la main |
| `jambeG.001`, `jambeD.001` | 100 | la cuisse |
| `tibiaG.001`, `tibiaD.001` | 60 | le tibia |
| `tibiaG.002`, `tibiaD.002` | 70 | le pied, dont le bas est le sol |
| `tete.001` | 280 | le visage et le tour du crâne, ouvert en haut |
| `tete.002` | 100 | la calotte, qui ferme le dessus du crâne |
| `tete.003`, `tete.004` | 32 | l'oreille gauche, l'oreille droite |
| `tete.005`, `tete.008` | 30 | le blanc de l'œil gauche, de l'œil droit |
| `tete.006`, `tete.009` | 8 | l'iris gauche, droit |
| `tete.007`, `tete.010` | 5 | le reflet de l'œil gauche, droit |
| `tete.011`, `tete.012` | 6 | le sourcil gauche, droit |
| `tete.013` | 10 | la bouche |

- **Retirer une pièce** de `corps.glb` est permis : une pièce qu'un vêtement ou des cheveux cachent entièrement
  (`tete.002`, `torse.001`, `torse.002`) rend ses triangles. Chaque os doit garder au moins une pièce.
- **Sans `tete.002`, le crâne est ouvert** : `tete.001` s'arrête sous le sommet de la tête, et le site ne dessine que la
  face avant, donc on voit le sol au travers. Ne retire `tete.002` que si ce qui la remplace ferme le dessus, des cheveux
  ou un chapeau d'un seul tenant. Une copie gonflée de `tete.001` ne le ferme pas : elle a le même trou. Le validateur
  refuse un crâne ouvert (`skull`).
- **La tête est tournée d'environ 5° vers sa gauche** : l'oreille gauche est plus en arrière que la droite, et le milieu
  des yeux est vers x = 0.04. Un accessoire symétrique (lunettes, chapeau, coiffure) se pose dans ce repère : mesure-le
  sur les oreilles ou sur les yeux, pas sur l'axe du modèle.
- **Fusionne les sommets avant de gonfler une pièce.** L'import glTF sépare les sommets à chaque arête vive : gonfler le
  long des normales ouvre alors des fentes. Dans Blender, « Merge by Distance » d'abord.
- **Une pièce ajoutée prend un numéro libre** après le nom de son os : `tete.020` pour un chapeau, `avantBrasD.010` pour un
  outil dans la main droite. Blender renomme en silence un nom déjà pris, ce qui est sans effet sur le validateur.
- Chaque pièce du corps a son propre matériau, nommé d'après sa couleur (`#e6b48f`, `#e6b48f.001`…). Joindre des pièces
  garde leurs matériaux, et chaque matériau compte pour une primitive : remplace ceux de même couleur par un seul.

## Le modèle composé

`composer.mjs` ne recopie pas les pièces de `corps.glb`. L'éditeur retire d'abord du corps ce que les vêtements et les
cheveux cachent, puis composer joint en un seul objet toutes les pièces d'un même os et d'une même couleur, et les
numérote dans l'ordre où il les liste. Un même nom ne désigne donc pas la même pièce dans `corps.glb` et dans le modèle
composé, ni d'une fiche à l'autre : ce que contient chaque objet se lit dans la liste que composer imprime, avec sa
couleur, ses triangles et les entrées qui le font (`corps` quand aucune entrée ne le change).

La fiche de l'étape 3 de `SKILL.md` donne ces 26 pièces, 2 093 triangles :

| Pièce | Couleur | Triangles | Ce que c'est |
|---|---|---|---|
| `torse.001` | `#d29c74`, la peau | 112 | le cou visible, du col au menton, à ne pas supprimer |
| `avantBrasG.001`, `avantBrasD.001` | `#d29c74` | 96 | l'avant-bras et la main |
| `tete.001` | `#d29c74` | 280 | le crâne et le visage |
| `tete.002` | `#c9956f` | 64 | les deux oreilles, dans la teinte de peau assombrie que l'éditeur leur donne |
| `torse.002`, `brasG.001`, `brasD.001` | `#2fbb75` | 192, 96 | le t-shirt et ses manches |
| `torse.003` | `#29a668` | 32 | le col du t-shirt, assombri |
| `bassin.001` | `#19444b` | 64 | le haut du pantalon, assombri |
| `bassin.002`, `torse.004`, `jambeG.001`, `jambeD.001`, `tibiaG.001`, `tibiaD.001` | `#1d4b53` | 48, 96, 100, 50 | le pantalon |
| `tibiaG.002`, `tibiaD.002` | `#3d4250` | 70 | les bottes |
| `tete.003` | `#2b2420` | 105 | les cheveux sous le chapeau |
| `tete.004` | `#fbfaf6` | 60 | le blanc des deux yeux |
| `tete.005` | `#4a2d17` | 16 | les deux iris |
| `tete.006` | `#ffffff` | 10 | les reflets des yeux |
| `tete.007` | `#5b4636` | 12 | les sourcils |
| `tete.008` | `#1d4b53` | 34 | la bouche et le ruban du chapeau, joints parce qu'ils ont la même couleur |
| `tete.009` | `#e8c872` | 120 | le chapeau de paille |
| `tete.010` | `#cdb064` | 24 | le tour intérieur du chapeau, assombri |

- **Une pièce de peau du modèle composé se voit.** Ce que les vêtements et les cheveux cachaient du corps est déjà
  retiré : il n'y a plus de peau cachée à supprimer pour gagner des triangles. `torse.001` y est le cou visible, les
  anneaux `torse.006` à `torse.008` de `corps.glb` joints. Le supprimer ouvre un trou entre le col et la tête.
  Sous un haut ou une veste qui couvre le cou (`polaire-zip`, `doudoune-longue`, `veste-pluie`), le torse n'a plus de
  peau, et `torse.001` est un vêtement.
- **Une pièce peut en réunir plusieurs.** Un bonnet, son pompon et des lunettes de la même couleur font un seul objet
  de la tête, comme des cheveux et leur queue de cheval, ou la bouche et le ruban d'un chapeau. Pour en retoucher une
  seule dans Blender, sépare l'objet en mode édition (Separate, By Loose Parts), puis rends à chaque morceau le nom de
  son os et un numéro libre.

## La pose de repos

Le modèle s'exporte au repos : debout, jambes droites, bras le long du corps, comme `corps.glb`. Une photo prise bras en
croix ou en marchant se remodèle dans cette pose. Ne tourne aucune pièce autour de son articulation : le site anime à
partir du repos.

## Les poses

`poses` donne, pour chaque pose animée par le site (`marche`, `assis`, `recolte`, `accable`, `lance`), les angles des os
qui bougent :

- en radians, dans les axes glTF ;
- en Euler XYZ comme three.js, c'est-à-dire la matrice Rx · Ry · Rz (l'ordre `'ZYX'` de `mathutils.Euler` dans Blender) ;
- chaque os tourne autour de son pivot des règles, dans le repère de son parent (`parents`) : la tête suit le torse,
  l'avant-bras suit le bras ;
- un angle X positif penche vers l'avant ce qui est au-dessus du pivot, et ramène vers l'arrière ce qui pend dessous.

`poser.py` met la scène Blender dans une pose, et `node rendre.mjs <modele>.glb <dossier>` rend le modèle dans chacune,
`--pose=<nom>` pour les trois vues d'une seule. Regarde au moins `assis`,
`recolte` et `lance` : une pièce rigide qui traverse sa voisine (une jupe dans la cuisse assise, un bord de veste dans
le bras levé) se voit là.

## Le sol et la descente

- **Le sol se mesure sur les objets `tibiaG` et `tibiaD`.** Chaussures, bottes et semelles appartiennent à ces os, et leur
  point le plus bas doit être à 0, à `ground` près. Une chaussure nommée d'après un autre os ne compte pas pour le sol.
- Aucune pièce, quel que soit son os, ne descend sous -`ground`.
- `descenteAssis` (0,31 m) est une longueur : l'unité des descentes. Pour chaque pose, le site calcule sur les pièces des
  tibias de combien le corps doit descendre pour que le pied le plus bas touche le sol, et l'écrit en part de
  `descenteAssis` (1 pour `assis`). Il n'y a rien à régler : il suffit que les pieds soient dans les tibias.

## Les ancres

`anchors` vient des règles et part tel quel avec le modèle : ton fichier n'en porte pas, et le validateur ne les contrôle
pas. Il n'y a rien à y régler ni à vérifier. `mains`, `pieds` et `tete` ne désignent pas les mains, les pieds et la tête
du corps au repos.

## Les couleurs

- Une couleur par primitive : le site lit la couleur de base du matériau (`baseColorFactor`), sans texture.
- glTF écrit cette couleur en linéaire, et le site la convertit en sRGB, la couleur que l'on voit. Les noms des
  matériaux de `corps.glb` sont des couleurs sRGB.
- Dans Blender, la Base Color du Principled BSDF est linéaire. Dans le sélecteur de couleur, le champ Hex est en sRGB :
  y taper `#e6b48f` donne la bonne couleur. Par script, convertis d'abord chaque composante c (de 0 à 1) :
  `c / 12.92` si c ≤ 0.04045, sinon `((c + 0.055) / 1.055) ** 2.4`.
- Ne règle que Base Color, Roughness et Metallic. Émission, transmission, transparence, vernis, reflet coloré ou indice de
  réfraction modifié font écrire à l'export une extension glTF (`KHR_materials_…`), que le site refuse (`external`).
- **Les palettes sont un conseil de style, pas une règle du validateur** : `upload.mjs` lit la couleur de chaque
  primitive et n'en refuse aucune. `composer.mjs` est plus strict, comme l'éditeur : la peau dans la palette `skin`,
  le métal des bijoux dans `metal`, et pour les autres familles une teinte de leur palette ou une couleur libre en
  `#rrggbb` minuscule. Les teintes assombries que l'éditeur dérive (un col, des oreilles, le tour d'un chapeau) sortent
  des palettes, et le validateur les accepte de même. Une pièce séparée dans Blender peut prendre sa propre couleur.
- **La bouche ne prend pas de couleur dans la fiche** : la famille `bouche` n'a pas de palette. `composer.mjs` lui
  donne celle de l'éditeur, `#1d4b53` sur les six peaux les plus claires de `skin`, jusqu'à `#8d5a3b`, et une teinte
  plus claire que la peau sur les cinq plus sombres, où l'iris et les sourcils s'éclaircissent aussi pour rester
  lisibles. Le validateur ne la contrôle pas : une bouche recolorée dans Blender est acceptée, mais ne ressemble plus à
  celles de l'éditeur.

## Le style du site

Un avatar se lit comme ceux de l'éditeur quand ses pièces suivent les formes du catalogue. `composer.mjs` les donne
toutes pour ce que le catalogue porte ; quand l'utilisateur repart des modèles de Ouvretaferme, une pièce modelée dans
Blender reprend les mêmes règles. Pour un personnage créé de zéro, ce qui suit n'est qu'un conseil, hors la couleur
unie de chaque pièce que le site exige : le validateur accepte d'autres formes, une autre densité et toutes les
couleurs, sous les plafonds d'`avatar.json`.

| Forme | Règle du catalogue |
|---|---|
| Tête | une boule de 20 côtés sur 10 rangs, plus le pôle : 280 triangles pour le visage, 100 pour la calotte. Elle fait le tiers de la hauteur du personnage |
| Tronc, jambes, bras | 16, 10 et 8 côtés. Le haut des cuisses est à 44 % de la hauteur |
| Vêtement | le membre qu'il couvre, gonflé de 7 à 8 mm, au même nombre de côtés. Un t-shirt complet fait 416 triangles |
| Coiffure | un fond de 140 triangles et des mèches de 12, avec environ 4 cm de volume de chaque côté. `courte` en fait 236, les coiffures vont de 168 à 312 |
| Couvre-chef | de 38 à 262 triangles |
| Accessoire, bijou, lunettes | de 16 à 152 triangles |
| Chaussures | 30 triangles par pied (`chaussures`), 70 (`bottes`) |

- **Peu de facettes.** Une pièce modelée plus dense que le catalogue se voit à côté des autres. Garde les nombres de
  côtés du tableau, même sur une forme neuve.
- **Des mèches et des lisières, pas des blocs.** Des cheveux ou un bonnet d'un seul volume lisse se lisent comme un
  casque : ajoute des mèches, un bord, un revers.
- **Les couleurs des palettes.** Chaque famille du catalogue a sa palette (`families`, clé `palette`, dans
  `lib/catalog.json`), dont les teintes sont dans `palettes` : `skin` pour la peau, `hair` pour la coiffure, `eyes` pour
  les yeux, `tint` pour les vêtements, les chaussures, le couvre-chef et les accessoires, `frame` pour les lunettes,
  `metal` pour les bijoux. Une pièce neuve prend une teinte de la palette de ce qu'elle est : un pelage dans `hair`, un
  vêtement dans `tint`.
- **Une couleur par pièce.** Le fondu d'une chevelure ou un motif peint de l'éditeur ne passent pas dans un modèle
  importé : `composer.mjs` donne à ces pièces leur couleur principale, et avertit d'une entrée qui ne ferait que teindre
  des pièces déjà là, comme un motif (`motif-carreaux`) ou un aspect de peau (`peau-rosee`). Un motif se rend dans
  Blender, par des pièces de sa couleur posées sur le vêtement.
- **Les couleurs secondaires restent celles de l'éditeur.** La fiche ne règle que la couleur principale d'une entrée.
  Le ruban d'un chapeau, la jugulaire, le motif d'un bandeau ou la bande d'une écharpe gardent la teinte que l'éditeur
  leur donne, qui peut être celle d'un autre vêtement choisi : `composer.mjs` liste chaque pièce avec sa couleur et les
  entrées qui la font. Une barbe ne prend pas de couleur dans la fiche, et `barbe-courte` se mêle à la peau.
- **Le catalogue seul.** `lib/avatar.mjs` monte aussi les pièces des personnages des scènes, que l'éditeur n'offre pas
  (`chemise-carreaux`, `barbe-pleine`, `barbe-taillee`) : `composer.mjs` ne prend que les entrées de `lib/catalog.json`,
  décrites sous sa clé `labels`.

## Ce que le site affiche

- **Des pièces lissées, angles droits compris.** Le site recalcule les normales et ignore celles du fichier, comme
  l'éditeur pour l'avatar composé : il lisse chaque os séparément. En chaque sommet, il fait la moyenne des faces qui s'y
  touchent, et ne garde la normale de la face que si cette moyenne s'en écarte de plus de 60°. Une boite ou une semelle
  sortent donc arrondies, comme dans l'éditeur, et seule une lame ou un bord mince replié sur lui-même (deux faces dont
  les normales s'écartent de plus de 120°) reste vif. Deux pièces de deux os ne se fondent jamais l'une dans l'autre.
  `rendre.mjs` montre ce lissage.
- **La face avant seulement.** Un triangle se voit du côté où ses sommets tournent dans le sens inverse des aiguilles d'une
  montre. Une face retournée est un trou sur le site. Ferme les pièces, ou oriente leurs faces vers l'extérieur
  (« Recalculate Outside » dans Blender). `rendre.mjs` montre une face retournée comme un trou.
- Un éclairage en aplats : cinq tons par couleur, du plus sombre au plus clair. Des couleurs franches et bien séparées
  se lisent mieux que des nuances proches.
- Le visage ne s'anime pas : un modèle importé ne cligne pas des yeux.

## Un enfant, une personne de petite taille

Les pivots sont ceux d'un adulte, et le site anime toujours autour d'eux : la hanche à 0,68 m, le genou à 0,37 m,
l'épaule à 1,08 m, le cou à 1,19 m. Les jambes vont donc de la hanche au sol et les bras partent des épaules, quelle que
soit la personne. Une jambe raccourcie plierait au mauvais endroit en s'asseyant.

Ce qui reste libre : la tête au-dessus du cou, plus petite ou plus grande, et l'épaisseur de chaque pièce. Un enfant se
dit par la tête, les proportions des pièces et les vêtements, pas par la stature. Dis-le à l'utilisateur avant de
commencer.

## Une personne réelle et les marques

L'utilisateur s'engage à ce que le modèle le représente ou ne représente personne, soit habillé, ne reprenne aucune
marque et respecte la réglementation en vigueur. D'un vêtement qui porte un logo, un texte ou un écusson, garde la
forme et les couleurs, sans le logo ni le texte. Ne reproduis ni une autre personne, ni un personnage protégé.

## En ligne de commande

Lance toujours Blender avec `--factory-startup` : sans lui, Blender charge les préférences et les extensions de
l'utilisateur, qui peuvent changer l'import ou l'export. Travaille dans un dossier à part, et remplace ci-dessous chaque
fichier du skill par son chemin complet.

```
# corps.glb en scène Blender, à habiller ensuite par script
blender -b --factory-startup --python-expr "import bpy; bpy.ops.wm.read_factory_settings(use_empty=True); bpy.ops.import_scene.gltf(filepath='corps.glb'); bpy.ops.wm.save_as_mainfile(filepath='modele.blend')"

# contrôle, export et jugement du validateur
blender -b --factory-startup modele.blend --python exporter.py -- avatar.glb

# les images du fichier exporté, au repos et dans chaque pose, puis les trois vues d'une seule pose, ou du repos seul
node rendre.mjs avatar.glb rendus
node rendre.mjs avatar.glb rendus --pose=assis
node rendre.mjs avatar.glb rendus --pose=repos
```

À l'export, Blender peut écrire « Draco mesh compression is not available » : le message est sans conséquence, la
compression n'est pas demandée.

## Ce que contrôle `exporter.py`

Avant d'exporter, sur la scène : les noms d'os, chaque os pourvu, les images, l'armature, les formes clés, l'animation,
les triangles, le nombre d'objets et leur imbrication, les primitives, l'emprise sur les trois axes et le sol. Après
l'export, sur le fichier : le poids et les extensions glTF. Puis il fait juger le fichier par `upload.mjs`.

Il ne voit pas ce que le validateur ne juge pas non plus : une pièce qui en traverse une autre dans une pose (`poser.py`,
`rendre.mjs`), une face retournée (`rendre.mjs`).
