# Vigne · fiche de référence

Vigne à raisin, Vitis vinifera, vitacées. Cep adulte conduit en cordon bilatéral sur palissage vertical, en feuilles, de juin à octobre. Les grappes sont posées par le socle.

## Proportions
- Emprise : un cep tous les 1,2 à 1,5 m sur le rang (Guyot double au Tessin : 120 à 140 cm). Retenu : **d = 1,5 m** le long du rang, **h = 1,8 m**. Le plant est un rideau plat : 1,5 m de long, 0,35 m d'épaisseur de feuillage, donc plus haut que large et presque sans profondeur.
- Cep : tronc court de 60 à 65 cm (essais suisses : 60 et 65 cm, plafond de 0,60 m du cordon de Royat dans les cahiers des charges), épais de 6 à 10 cm, tordu, à écorce brun sombre qui se délite en lanières. Il fait le tiers de la hauteur totale.
- Bras : deux bras horizontaux de 60 à 70 cm chacun, couchés sur le fil porteur, de part et d'autre de la tête du cep. Avec le tronc ils dessinent un T.
- Sarments : six à huit rameaux de l'année, dressés à la verticale depuis les bras, espacés de 15 à 25 cm, hauts de 1,1 à 1,3 m (haie foliaire de 120 à 135 cm dans les essais suisses), rognés au-dessus du dernier fil.
- Feuilles : 10 à 20 cm, aussi larges que longues, à cinq lobes pointus et dentés, sinus peu profonds (un tiers à la moitié du limbe), échancrure en cœur au pétiole. Elles pendent pointe en bas, face tournée vers le dehors du rang, alternes sur le sarment. Vert franc, plus sombre à l'intérieur de la haie.
- Grappes : 15 à 25 cm, en cône renversé, une ou deux par sarment, à sa base. Elles pendent toutes à la même hauteur, juste au-dessus et au-dessous du fil porteur (0,5 à 0,8 m), sous le feuillage, dans la zone effeuillée.
- À l'échelle du jeu une feuille de 15 cm ferait 3 px : le modèle les grossit (40 cm environ) et en garde 26.

Les proportions tiennent ensemble : un tronc de 0,6 m, des sarments de 1,15 m et le piquet à 1,8 m donnent 1,8 m de haut ; deux bras de 0,68 m et la tête font 1,5 m, soit un cep par emprise, bras bout à bout avec ceux des voisins.

## Palissage (posé par le modèle, continu d'un plant à l'autre)
- Un piquet de bois de 1,8 m hors sol, au bout gauche de l'emprise.
- Trois rangs de fil : le fil porteur à 0,6 m, qui tient les bras, et deux fils releveurs à 1,05 m et 1,5 m, qui tiennent les sarments dressés.

## Traits qui font reconnaître, dans l'ordre
1. Un cep court, noueux et sombre, et ses deux bras horizontaux : un T de bois noir sous le feuillage, sur un fil bas.
2. Un rideau de feuillage droit, à hauteur d'homme, qui commence au-dessus des bras et laisse le pied nu.
3. De grandes feuilles à cinq lobes pointus, vert franc, qui pendent pointe en bas.
4. Les grappes alignées à hauteur du fil porteur, sous le feuillage (socle).

## Confusions et ce qui sépare
- **Mûre** (`mure.n.js`) : un éventail de cannes pourpres qui partent toutes du sol en V et se couchent sur quatre fils ; feuilles à trois folioles tout le long, jusqu'en bas. La vigne a un seul tronc sombre et épais, un T, un pied nu, et un rideau de feuilles entières lobées qui ne commence qu'à 0,8 m.
- **Framboise** (`framboise.n.js`) : une bande de cannes fines et dressées qui sortent du sol tout le long du rang, mur étroit de folioles à revers pâle. La vigne n'a qu'un point d'ancrage au sol par 1,5 m, et du bois horizontal.
- **Haricot à rames** (`haricot.n.js`) : rames croisées en A, tiges fines, feuillage du sol au sommet. Pas de bois, pas de fil.
- **Kiwi** : liane sur pergola en T, toit de feuillage horizontal à 1,8 m, feuilles rondes entières. La vigne est un rideau vertical à feuilles lobées.
- **Figue** : mêmes feuilles lobées, mais sur un arbre libre à plusieurs troncs gris clair, rond en plan.
- **Houblon, chanvre** : une tige qui monte à plusieurs mètres, sans tronc ni bras.

## Couleurs (quatre au plus)
- Sombre : `#3a2a22`, le cep, les bras et les sarments. Le plus fort contraste est entre ce bois et le feuillage.
- Moyen : `#3f7d2b`, les feuilles du bas et du dedans de la haie.
- Franc : `#6aa838`, les feuilles du haut et du dehors.
- Clair : `#cdbb94`, piquet et fils, la même valeur que le palissage de la mûre.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**, famille de repli `tree`.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m).
- Fruits : posés par le socle, sous le feuillage, de part et d'autre du fil porteur.

## Sources
- https://www.revuevitiarbohorti.ch/wp-content/uploads/2005_06_f_898.pdf et https://www.revuevitiarbohorti.ch/wp-content/uploads/2005_04_f_931.pdf (Guyot double, 120 à 140 cm entre ceps, tronc de 60 à 65 cm, haie foliaire de 120 à 135 cm)
- https://wikifarmer.com/library/fr/article/systemes-et-methodes-de-taille-de-la-vigne (cordon unilatéral ou bilatéral sur tronc court)
- Cahiers des charges d'appellation publiés au bulletin officiel de l'agriculture (charpente horizontale à 0,60 m au plus, coursons espacés de 0,15 m, relevage obligatoire par des fils)
- Taille des feuilles et des grappes, hauteur des fils releveurs et du piquet : ordres de grandeur viticoles courants, non mesurés sur une source ouverte pendant cette passe.

## Jugement à l'aveugle (lot 54, t3, au banc avec ses fruits)

3/3. Vigne pour les trois juges, sûrs : cep noueux, cordon sur fil, grappes violet sombre sous le rideau de feuilles. Lisible aux trois distances.
