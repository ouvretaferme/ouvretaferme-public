# Asperge · fiche de référence

Aspergeraie adulte (troisième année et après), en été. L'asperge est une vivace : on récolte les turions d'avril à juin, puis on laisse pousser les tiges, qui forment de juin à l'automne la fougère qui nourrit la griffe pour l'année suivante. **La scène montre cette fougère**, qui occupe la planche cinq mois sur sept. Les turions de printemps ne durent que quelques semaines et ne se voient qu'au ras d'une butte nue : ils ne sont pas modélisés. Pas de fruits posés par le socle : les baies rouges des pieds femelles sont petites, rares en culture (variétés à pieds mâles) et ne se récoltent pas.

## Proportions
- **Hauteur** : 1 à 1,5 m pour la plupart des flores (Wikipédia 100-150 cm, POWO et KPU 0,7-1,3 m), 1 à 2,5 m en culture vigoureuse (Growables), 3 à 7 pieds (0,9-2,1 m) pour l'extension de l'Arizona. On retient **1,3 m**.
- **Emprise** : aucune source ne la donne. On la borne par la plantation : griffes à 30-45 cm sur le rang, rangs à 1,3-1,5 m (guide des méthodes culturales, Growables). La fougère d'une griffe s'ouvre en vase et ses tiges du bord se penchent dans l'allée sans la fermer : on retient **0,6 m**, soit une emprise de 0,45 fois la hauteur. Le plant est **deux fois plus haut que large**, c'est la plus haute et la plus étroite des touffes du potager hors tuteur.
- **Tiges** : 6 à 12 par griffe adulte. Le nombre n'est donné par aucune source trouvée : c'est une estimation sur photos, à ne pas tenir pour une mesure. Chaque tige sort **directement du sol**, sans collet ni bulbe, droite et presque verticale au cœur, ouverte à 10-25° de la verticale au bord. Elle est **nue sur le premier quart** de sa hauteur (30 à 40 cm), vert pâle, de la grosseur d'un doigt au pied.
- **Ramification** : au-dessus du quart, la tige porte des rameaux alternes, étalés à 45-60° de la tige, plus longs en bas (20-25 cm) qu'en haut (quelques cm). Chaque tige est donc un **fuseau effilé**, plus large au tiers bas de la partie feuillée, pointu au sommet.
- **« Feuilles »** : les vraies feuilles sont des écailles. Ce qu'on voit sont des cladodes en aiguilles de 0,5 à 3 cm, groupés par 2 à 15 à chaque nœud (Flora of New Zealand, KPU, Wikipédia). À distance, la masse se lit **vaporeuse et ajourée**, un brouillard vert, sans aucune surface pleine, plus dense qu'un plumet de fenouil.

Recoupement : une tige de 1,3 m ouverte à 20° s'écarte de 0,45 m au sommet, mais la fougère se termine en pointe ; les rameaux du bas, à 0,4 m de haut et de 0,2 m de long, l'élargissent de 0,1 à 0,15 m à cette hauteur. Deux tiges du bord opposées donnent donc 0,5 à 0,7 m, cohérent avec les 0,6 m retenus.

## Traits qui font reconnaître, dans l'ordre
1. **Une colonne haute et étroite de fougère vaporeuse vert franc**, deux fois plus haute que large, dont le haut se termine en plusieurs pointes de tiges.
2. **Des tiges nues, pâles et droites au pied**, qui sortent du sol en faisceau, sans bulbe, sans rosette, sans feuille basse : la masse verte flotte sur des tiges.
3. Chaque tige est un **fuseau en sapin** : rameaux plus longs en bas, courts au sommet, qui fait du bord de la touffe une suite de pointes.

## Confusions et ce qui sépare
- **Fenouil** : même feuillage filiforme, mais un plant bas (0,7 m), en vase évasé vers le haut, posé sur un **bulbe blanc** au sol, vert tendre jaune. L'asperge n'a aucun volume au pied, elle fait le double de haut, sa masse est plus large au milieu qu'en haut et d'un vert plus soutenu.
- **Aneth** : tige unique, creuse, peu ramifiée, coiffée d'**ombelles jaunes plates** ; feuillage clairsemé, bleuté. L'asperge porte un faisceau de tiges, aucune fleur visible, et une masse de feuillage dense sur les trois quarts hauts.
- **Carotte montée** : hampe à **ombelles blanches** plates en tête, feuillage plat et découpé en bas, plus basse (1 m), touffe ébouriffée sans axe. L'asperge n'a ni ombelle ni rosette basse ; sa tête est une pointe, pas un plateau.
- **Céleri branche, poireau** : verticaux aussi, mais en botte pleine et lisse, sans haze. L'asperge est ajourée.
- **Maïs, tournesol** : aussi hauts, mais à feuilles larges en lame.

## Couleurs (quatre au plus)
- Sombre : `#3f7a32`, le cœur et le dessous des rameaux, la fougère en ombre.
- Moyen : `#74a94a`, la fougère éclairée, rameaux du bord et du haut. Vert franc, plus bleu et plus sombre que le plumet du fenouil (`#8fcf4f`).
- Clair : `#c3d48f`, les tiges nues du pied, vert pâle presque paille, le plus fort contraste contre la terre et contre la masse sombre au-dessus.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, **4 couleurs**, famille de repli `tall`.
- Le socle ramène emprise et hauteur à 1 puis applique d, h et l'exagération ×2,5 de la scène. On vise `d = 0,6` et un modèle 2,2 fois plus haut que large (`h ≈ 1,3`).
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait 30 px de large et 65 de haut. Les tiges nues y font un ou deux pixels : elles se lisent par le jour sous la masse plus que par leur trait.

## Sources
- Wikipédia, *Asparagus officinalis* : 100-150 cm, cladodes 6-32 mm par touffes jusqu'à 15, feuilles réduites à des écailles. https://en.wikipedia.org/wiki/Asparagus
- Flora of New Zealand : tiges de 60-120 cm, cladodes de 5-15 mm, 2 à 8 par nœud. https://www.nzflora.info/factsheet/taxon/Asparagus-officinalis.html
- KPU Plant Database : 0,7-1,3 m, cladodes par 3-5, 1-2 cm. https://plantdatabase.kpu.ca/Plant/asof
- University of Arizona, Yavapai plants : 3 à 7 pieds. https://yavapaiplants.extension.arizona.edu/asparagus-officinalis
- Growables : port dressé de 1 à 2,5 m, rangs à 1,5 m. https://growables.org/informationVeg/Asparagus.htm
- Guide des méthodes culturales de l'asperge : griffes à 30 cm, sillons espacés de 1,3 à 1,5 m. https://dev2.lareault.com/img/cms/Methodes-culturales/guide-methodes-culturales-asperge.pdf
- Colorado State Extension, *How to plant asparagus* : la fougère d'été nourrit la griffe pour la récolte suivante. https://growgive.extension.colostate.edu/wp-content/uploads/sites/63/2021/09/How-to-Plant-Asparagus.pdf

## Jugement à l'aveugle

Lot 16, contre l'artichaut, le chou-fleur, le tournesol et le fenouil : 2 sur 3, hésitants. Le troisième juge lit l'aneth, faute d'ombelle pour trancher.
