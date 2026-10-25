# Référence du modèle de bâtiment et des éléments du décor

Tout ce que `SKILL.md` cite. Un bâtiment suit toutes les sections jusqu'à « Une forme que le cadre n'a pas », un élément
d'Eau, de Nature ou de Matériel la section qui le nomme, puis « Les couleurs » et « En ligne de commande ». Les chiffres qui font foi sont dans `decor.json` (règles), `lib/choices.json` (choix de
l'éditeur) et `lib/building.mjs` (catalogue, réglages et leurs défauts, palette `P`).

## Repères

- En mètres, axes glTF : Y vers le haut, la façade `avant` regarde +Z. Les façades sont `avant`, `arriere`, `gauche`
  (-X) et `droite` (+X).
- Le bâtiment est centré sur l'origine et posé à y = 0. Le faitage d'un toit à deux pans court selon Z : `length` d'un
  bâtiment de l'éditeur, et `profondeur` d'une fiche du cadre, se mesurent le long de Z, `width` et `largeur` le long
  de X.
- Dans Blender, Z est vers le haut et la façade avant regarde vers -Y : l'export fait la conversion.
- La façade avant est celle où l'on entre. La scène tourne le bâtiment pour la présenter, et y cherche la porte.

## La fiche de l'éditeur

Celle que l'éditeur du décor enregistre : un type de `lib/choices.json` (`building` pour un bâtiment, `hangar`,
`house` pour une maison), ses cotes et un choix par réglage du type.

```json
{ "type": "building", "length": 14, "width": 9, "height": 4.2,
  "settings": { "roof": "deux-pans", "cover": "tuile", "coverTint": "tuile", "wall": "pierre",
                "wallTint": "pierre", "door": "porte-pleine", "windows": 2 } }
```

- Chaque cote se borne à `[min, max]` du type, et une cote `null` dans `lib/choices.json` est fixée par le type : elle
  reste `null` dans la fiche (la largeur d'un hangar, la hauteur d'une maison).
- Chaque réglage du type prend une valeur de sa liste, sans clé de plus ni de moins. `excluded` nomme les combinaisons
  qui ne vont pas ensemble.
- `colors` donne la couleur de chaque teinte proposée. `initial` est le bâtiment que l'éditeur propose au départ.
- Une fiche de l'éditeur tient toujours sous les plafonds : le site éprouve toutes les combinaisons.

## La fiche du cadre

Celle qui place chaque porte, fenêtre et élément que montre la photo, en citant le catalogue de `lib/building.mjs`
(`CATALOGUE`, une entrée par forme, décrite sous `decrit`, ses réglages et leurs défauts sous `reglages`).

```json
{ "nom": "grange",
  "corps": { "toit": "deux-pans", "largeur": 8, "profondeur": 16, "egout": 4, "faitage": 7, "socle": 0 },
  "mur": { "catalogue": "pierre" },
  "couverture": { "catalogue": "tuile", "reglages": { "couleur": "tuile" } },
  "ouvertures": [
    { "catalogue": "porte-pleine", "facade": "avant", "u": 0, "reglages": { "largeur": 2.4, "hauteur": 2.6 } },
    { "catalogue": "fenetre", "facade": "droite", "u": -3 } ],
  "elements": [ { "catalogue": "descente", "reglages": { "couleur": "gris" } } ] }
```

- **`corps`** : `toit` (`deux-pans`, `appentis`, `croupe`), `largeur`, `profondeur`, la hauteur à l'égout `egout` et au
  faitage `faitage`, le `socle`. Un `appentis` met son côté haut à `gauche` ou à `droite` par `haut`. Une `croupe`
  demande une profondeur au moins égale à la largeur.
- **`mur`** et **`couverture`** : une entrée de leur famille du catalogue.
- **`ouvertures`** : chacune sur une façade, placée par `u`, l'écart au milieu de la façade vu de dehors, positif vers la
  droite de qui la regarde ; une fenêtre aussi par son `appui`, la hauteur du bas du vitrage. Dès qu'il y a une porte,
  l'une d'elles est sur la façade avant.
- **`elements`** : cheminée, descente d'eau, bac, lanterneau, bottes, auvent, bêtes, caisses…
- Chaque entrée apporte ses couleurs, et une porte coulissante, une baie ou des volets en ajoutent vite deux : `composer.mjs`
  compte les couleurs face au plafond. Deux pièces proches reprennent la même clé (les volets de la teinte de la porte,
  la descente du gris des moellons).
- **Un réglage absent prend le défaut de l'entrée.** Une couleur est une clé de la palette `P`, et `null` retire la pièce
  qu'elle colore (pas d'appui, pas de gouttière, pas de volets).
- Une fiche que le cadre ne sait pas monter est refusée en une seule erreur, qui nomme tous ses écarts : une clé ou un
  réglage inconnus, une cote hors de ses bornes, un faitage sous l'égout, une ouverture qui déborde de sa façade ou monte
  plus haut que le mur au-dessus d'elle, deux ouvertures qui se chevauchent.

### Lire une photo

Dans l'ordre où le bâtiment se monte :

1. **Les cotes.** Une porte d'homme fait 2 m, une personne 1,75 m, un tracteur 3 m : la hauteur à l'égout et au faitage,
   la largeur du pignon et la longueur se mesurent sur l'un d'eux, jamais à l'estime.
2. **Le toit.** Sa forme, puis sa couverture et sa couleur, prise sur un pan à mi-ombre.
3. **Le mur.** Le revêtement, sa couleur, le pas des couvre-joints, un soubassement.
4. **Les ouvertures, façade par façade**, de gauche à droite vue de dehors.
5. **Les éléments.**

À la taille de la scène, un bâtiment se lit par sa silhouette (la forme du toit, le rapport de l'égout au faitage), puis
par la couleur du toit, puis par la porte. Une fenêtre de plus ou de moins ne se voit pas : le budget va d'abord à ce qui
fait reconnaitre.

## Les couleurs

Tous les bâtiments et les objets du décor du site taillent dans la même palette, `P`, en tête de `lib/building.mjs` : une
couleur hors palette se voit dans la scène. `rendre.mjs` dit pour chaque couleur la clé qui la porte.

Seul écart : un bâtiment composé dans l'éditeur à mur de pierre porte ses chaines d'angle et ses moellons dans la teinte du
mur assombrie de 20 %, parce qu'aucune clé fixe ne tranche sur toutes les teintes de mur proposées. `rendre.mjs` la nomme
`pierre <teinte>`. Une fiche du cadre garde la pierre du catalogue, en clés de `P`.

Le validateur ne refuse aucune couleur, seulement leur nombre (`maxColors`) : une pièce modelée dans Blender reste libre,
mais elle prend une couleur de la palette dès qu'une s'en approche.

## Le budget

`maxTriangles` triangles et `maxColors` couleurs pour tout le bâtiment. `composer.mjs` imprime ce que prend la
composition, et donc ce qui reste à une pièce ajoutée dans Blender. Ce que coûtent les pièces du cadre :

- un couvre-joint de bardage, 12 triangles ;
- une fenêtre à volets ouverts, 48 ;
- une chaine d'angle de pierre, 4 par assise et par angle ;
- un moellon, 2. Un mur de pierre porte au plus 220 moellons : au-delà, leur pas s'élargit.

Une fiche du cadre peut dépasser le plafond : moins d'ouvertures, un pas de couvre-joints ou de moellons plus large
(`pas`, `pasMoellons`) la font tenir.

## Les pièces

- `batiment` : tout le volume, mur, toit, menuiseries et éléments. Obligatoire.
- `vitre` : ce qui reflète, vitrages et eau d'un bac.
- `vantailGauche`, `vantailDroit` : les deux vantaux d'une porte coulissante, que la scène peut faire glisser.

Chaque pièce se joint en un objet par couleur, nommé d'après elle et suivi d'un numéro : `batiment.001`, `batiment.002`.

## Les ancres

Des objets vides, nommés d'après elles, que la scène lit pour poser ce qui accompagne le bâtiment :

- `porte` : le milieu du seuil de la porte principale, au sol ;
- `porteCoin` : le coin haut droit de son ouverture, d'où la scène lit la largeur et la hauteur de la porte ;
- `enseigne` : au-dessus de la porte, un peu en avant du mur ;
- `faitage` : le sommet du toit. Sans lui, le site le pose au milieu de l'emprise, au point le plus haut.

La porte principale est la première porte de la façade avant. `composer.mjs` pose les ancres que le cadre calcule ; une
pièce faite dans Blender qui déplace la porte déplace aussi les ancres `porte`, `porteCoin` et `enseigne`. Chacune est
facultative, une seule par nom, et reste dans l'emprise du bâtiment.

## Une forme que le cadre n'a pas

Un silo, une serre-tunnel, un toit à la Mansart : la pièce se modèle dans Blender sur le bâtiment composé, au plus près du
style du cadre.

- **Des volumes francs et des facettes plates** : des boites, des prismes, des cylindres de 8 à 12 côtés. Aucun lissage,
  aucun biseau, aucune subdivision : le site garde la normale de chaque face.
- **Les couleurs de la palette**, et le moins de couleurs neuves possible : le plafond en compte `maxColors` pour
  tout le bâtiment.
- **Le budget restant** que `composer.mjs` a imprimé.
- **Les faces tournées vers l'extérieur** : le site ne dessine que la face avant de chaque triangle, et une face retournée
  se voit comme un trou dans `rendre.mjs`.

## Un élément d'Eau, de Nature ou de Matériel

Un élément ne passe pas par le cadre des bâtiments : il se modèle dans Blender, dans le même style, celui des formes de
« Une forme que le cadre n'a pas ».

- **Sa famille fixe les règles** : lis dans `decor.json` le bloc de `families` de sa rangée, ses triangles
  (`maxTriangles`), son emprise (`bounds`), ses pièces et ses ancres. L'emprise est large pour Nature, qui accepte un
  arbre de haut jet ou une haie entière, et étroite pour Eau et Matériel.
- **Eau** (`water`) et **Matériel** (`other`) : une seule pièce, `objet`, obligatoire, qui porte tout le volume, en un objet
  par couleur (`objet.001`, `objet.002`). Un abreuvoir, une citerne, un lavoir ; un broyeur, un motoculteur, un tas de
  palettes. Pas d'ancre.
- **Nature** (`tree`) : `arbre` porte le bois, tronc et branches, `feuillage` la ramure, `fruit` les fruits. Aucune n'est
  obligatoire : un rocher ou une souche n'a que `arbre`, un buisson que `feuillage`. L'ancre `arbre`, au sol au pied du
  tronc, dit à la scène où le tronc touche terre, ce qu'elle lit pour l'ombre et la place des animaux. Sans elle, la scène
  prend le milieu de l'emprise. Un modèle sans pièce `arbre` se pose comme un buisson, sans tronc.
- **Centré sur l'origine, posé au sol** comme un bâtiment, Y vers le haut. La face qu'on regarde le plus souvent vers +Z.
- **À l'échelle réelle** : la scène pose l'élément à sa taille, puis l'utilisateur l'agrandit ou le réduit avec l'outil de
  taille de la rangée.
- **Pas de saison** : un feuillage importé garde sa couleur toute l'année.

Pour l'exporter, passe la famille : `exporter('<sortie>.glb', '<dossier>', 'tree')` dans Blender, ou
`-- <sortie>.glb tree` en ligne de commande. Pour l'image, `node rendre.mjs --famille=tree <sortie>.glb rendus`.
`exemples/conforme-eau.glb`, `conforme-nature.glb` et `conforme-materiel.glb` montrent la forme d'un élément accepté.

## En ligne de commande

Lance toujours Blender avec `--factory-startup` : sans lui, Blender charge les préférences et les extensions de
l'utilisateur, qui peuvent changer l'import ou l'export. Travaille dans un dossier à part, et remplace ci-dessous chaque
fichier du skill par son chemin complet.

```
# la fiche composée en .glb, puis en scène Blender
node composer.mjs fiche.json batiment.glb
blender -b --factory-startup --python-expr "import bpy; bpy.ops.wm.read_factory_settings(use_empty=True); bpy.ops.import_scene.gltf(filepath='batiment.glb'); bpy.ops.wm.save_as_mainfile(filepath='modele.blend')"

# contrôle, export et jugement du validateur
blender -b --factory-startup modele.blend --python exporter.py -- batiment-final.glb

# les images du fichier exporté, de face, de trois-quarts, de dos et de dessus
node rendre.mjs batiment-final.glb rendus
```

## Ce que contrôle `exporter.py`

Avant d'exporter, sur la scène : les noms de pièces, la pièce obligatoire, les images, l'armature, les formes clés,
l'animation, les triangles, les couleurs, le nombre d'objets et leur imbrication, les primitives, l'emprise centrée sur
l'origine, le sol et les ancres. Après l'export, sur le fichier : le poids et les extensions glTF. Puis il fait juger le
fichier par `upload.mjs`.

Il ne voit pas ce que le validateur ne juge pas non plus : une face retournée (`rendre.mjs`), une couleur hors palette
(`rendre.mjs`).
