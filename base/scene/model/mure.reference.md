# Mûre cultivée · fiche de référence

*Rubus fruticosus*, ronce sans épines de culture (types 'Thornfree', 'Loch Ness'), rosacées. Plant adulte de juin à septembre, au potager, conduit en éventail sur un palissage de fils. Les mûres sont posées par le socle (ligne `FRUITS` du compte rendu), pas par le modèle.

## Conduite et palissage
- Vivace : la souche dure une dizaine d'années (chambre d'agriculture de l'Ain). Elle donne chaque année des cannes neuves qui ne fruitent que l'année suivante, puis sont coupées au pied après la récolte (RHS).
- Palissage retenu : **piquets de bois de 75 mm**, hors sol **1,7 m** (piquets de 2,5 m, RHS), un tous les deux plants ; **quatre fils** tendus le long du rang à **0,6 m, 0,9 m, 1,2 m et 1,5 m** (RHS : fils tous les 30 cm ; guide Lareault : premier fil à 60 cm ; Deep Green Permaculture : 90 à 180 cm sur trellis haut, trois fils sur trellis bas).
- Conduite en éventail (RHS) : les **cannes de l'année, qui fruitent, s'ouvrent en éventail des deux côtés de la souche** et se couchent le long des trois fils du bas, jusqu'à huit de chaque côté sur les variétés modernes. Les **cannes neuves montent en botte au centre** et se couchent le long du fil du haut. Ce sont les deux gestes qui font la silhouette.
- Une canne fruitière porte des rameaux latéraux courts (10 à 30 cm) qui sortent du plan du palissage, de part et d'autre, chargés de feuilles et de grappes : la haie a une épaisseur.

## Proportions
- Hauteur : **1,7 m** au sommet des piquets, le feuillage arrive au fil du haut (1,5 m) et le dépasse un peu au centre.
- Emprise : **d = 0,8 m** (consigne). Le socle prend `d` comme la plus grande dimension horizontale du modèle et en tire l'écartement des plants (1,25 d). Le plant est donc modélisé **0,8 m le long du rang**, l'éventail réel de 2 à 3 m (1 à 2 m entre plants, Tennessee Extension, FiBL) y est ramassé. En travers, les latéraux sortent de 15 cm de chaque côté des fils : épaisseur **0,3 m**.
- Rapport hauteur sur emprise ≈ **2,1** dans le modèle. Sur une planche de 1 m, le socle le porte à 1,3 m sur 2,4 m.
- Éventail : six cannes fruitières, trois par côté, qui partent de la souche à 50-75° de l'horizontale et se couchent sur les fils de 0,6, 0,9 et 1,2 m jusqu'au bout du plant. Trois cannes neuves au centre, dressées, dont deux couchées sur le fil de 1,5 m à gauche et à droite.
- Canne : 1,5 à 2 cm de diamètre à la base, lisse, verte virant au **rouge pourpre** au soleil.
- Feuille : composée **palmée, 3 à 5 folioles** ovales pointues, dentées, vert sombre luisant dessus. Foliole de 6 à 10 cm, feuille de 15 à 20 cm. Modélisée à **trois folioles**, grossie à environ 25 cm pour se lire en jeu.
- Fruits (posés par le socle) : mûre de **2 à 3 cm**, ovoïde, faite de drupéoles, en **grappes de 4 à 8 au bout des latéraux**. Sur une grappe, les mûres mûrissent l'une après l'autre : **noires** luisantes, **rouges** avant maturité, quelques vertes.

## Traits qui font reconnaître, dans l'ordre
1. **Un éventail** : des cannes qui rayonnent en V depuis une souche unique et se couchent le long de fils horizontaux. La silhouette est un triangle ouvert vers le haut, barré de lignes horizontales.
2. **Le palissage** : piquets clairs et fils horizontaux qui courent le long du rang, d'un plant à l'autre.
3. **Des grappes mêlées de noir et de rouge** pendues à mi-hauteur, devant un feuillage vert sombre (socle).

## Confusions et ce qui sépare
- **Framboisier** : cannes **dressées et parallèles**, serrées en une bande étroite entre deux fils, nombreuses (10 à 15 au mètre), feuillage plus clair au revers blanchâtre, fruit **rouge** seul, creux. La mûre a des cannes **couchées en éventail** depuis un seul pied, plus épaisses et pourpres, des fruits **noirs mêlés de rouges**. À la silhouette : un V ouvert contre une bande verticale.
- **Concombre** : mur étroit sur filet clair à mailles, grandes feuilles triangulaires pendues, fruits vert sombre allongés. La mûre n'a pas de filet mais des fils espacés, des feuilles petites et composées, des tiges ligneuses pourpres visibles.
- **Haricot à rames** : rames en A, lianes qui s'enroulent vers le haut, gousses vertes. La mûre est en V depuis le pied, sur des fils horizontaux, sans rame.
- **Tomate** : un pied tuteuré seul, sans fil, fruits rouges ronds gros. La mûre a des fruits petits, noirs en majorité.
- **Fraise** : même famille, touffe basse au sol.

## Couleurs (quatre au plus)
- Sombre : `#264a1f`, les feuilles du dedans, près de la souche et derrière les fils.
- Moyen : `#4a8636`, les feuilles des latéraux, au dehors.
- Accent : `#7d3b4c`, les cannes, rouge pourpre.
- Clair : `#cdbb94`, piquet et fils, bois et fil galvanisé. Le noir et le rouge des fruits viennent du socle.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, absolu, **4 couleurs**, famille de repli `berry`.
- `aligned: true` : l'éventail et ses fils suivent l'axe de la planche.
- Ce qui doit marcher : le rang à 20 px/m, où un plant fait 26 px de large sur 48 de haut.

## Sources
- RHS, *Blackberry and hybrid berry: pruning and training* : éventail, cannes fruitières sur les trois fils du bas, cannes neuves en botte au centre, fils tous les 30 cm, piquets de 2,5 m de 75 mm, jusqu'à 16 cannes ([lien](https://www.rhs.org.uk/fruit/blackberries-and-hybrid-berries/pruning-training)).
- Deep Green Permaculture, *How to prune and train blackberries* : fils à 90, 120, 150 et 180 cm, trois fils sur trellis bas ([lien](https://deepgreenpermaculture.com/2021/07/31/how-to-prune-and-train-blackberries-and-their-hybrids/)).
- Guide des méthodes culturales du mûrier sans épine (Lareault) : premier fil à 60 cm, 1 à 2 m sur le rang ([lien](https://dev2.lareault.com/img/cms/Methodes-culturales/guide-methodes-culturales-murier.pdf)).
- Chambre d'agriculture de l'Ain, *Production de mûres en AB* : plantation productive une dizaine d'années ([lien](https://ain.chambres-agriculture.fr/fileadmin/user_upload/National/FAL_commun/publications/Auvergne-Rhone-Alpes/AB_Production-mures.pdf)).
- University of Tennessee, *Blackberry Production* (2023) : 2 à 4 ft sur le rang pour les types dressés, fils à 2-3 ft et 4-5 ft ([lien](https://vegetables.tennessee.edu/wp-content/uploads/sites/167/2023/03/Lockwood_Blackberry-Production_2023.pdf)).
- FiBL, *Technical guide, bush berries* : 1 à 2 m entre plants en éventail, 4 à 6 cannes par pied ([lien](https://www.fibl.org/fileadmin/documents/shop/0010-bushberries.pdf)).
- Jardiner malin, palissage de la mûre ([lien](https://www.jardiner-malin.fr/fiche/palissage-palisser-mure-ronce.html)).

## Jugement à l'aveugle (au banc, avec FRUITS)
Lot 35, trois juges. Les trois la nomment mûre ou ronce palissée, un sûr, deux hésitants, sur les cannes pourpres arquées au fil et le mélange de baies noires et rouges ; seconde hypothèse framboisier. Défaut nommé : des baies lisses plutôt qu'en drupéoles, pas d'épines.
