# Ciboulette · fiche de référence

*Allium schoenoprasum*. Touffe adulte au potager, pendant la plus grande partie de la saison : une botte dense de brins fins, coupée régulièrement, qui fleurit de mai à juillet en pompons mauves si on la laisse faire. Le modèle montre la touffe avec quelques pompons, son signe le plus lisible.

## Proportions
- **Hauteur** du feuillage : 15 à 30 cm (Truffaut, 15 à 30 cm ; Jardiner malin, 20 à 30 cm ; Perennials.com, 25 à 30 cm). Les hampes florales montent un peu au-dessus, jusqu'à 35 à 45 cm (NC State Extension). On retient **25 cm de feuillage** et **des pompons à 30 à 33 cm**.
- **Brins** : des dizaines par touffe, **cylindriques et creux**, de **2 à 3 mm** de diamètre (Botany Brisbane), effilés en pointe, raides et dressés ; les brins extérieurs s'ouvrent à 20-30° et leur pointe s'incline un peu vers l'extérieur.
- **Pied** : pas de fût blanc visible ni de bulbe, les brins sortent serrés d'une base de 4 à 6 cm, à ras du sol. Seuls les tout premiers millimètres pâlissent.
- **Pompons** : ombelle **sphérique de 2 à 3 cm**, jusqu'à 30 fleurs serrées, **mauve à rose violacé** (Futura-Sciences, Truffaut, Jardiner malin), au bout d'une hampe droite qui ressemble à un brin. Une touffe en porte quelques-uns à la fois ; on en retient **3**.
- **Emprise** : 20 cm en haut de la touffe pour une touffe de l'année (les fiches anglaises donnent 30 à 40 cm pour une vieille touffe jamais divisée). Recoupement : une base de 5 cm, plus des brins de 25 cm ouverts jusqu'à 20° (25 × sin 20° ≈ 8,5 cm de chaque côté), donnent 22 cm ; 25 × cos 20° ≈ 23,5 cm de haut pour les brins extérieurs, 25 cm pour ceux du cœur. Emprise sur hauteur ≈ **0,8** au feuillage, **0,65** pompons compris, cohérent avec `d` = 0,2 m.

Le modèle exagère, pour tenir à 20 px/m, la largeur des brins (2 cm au lieu de 3 mm : douze brins larges tiennent la masse de dizaines de brins fins) et le pompon (5 cm au lieu de 2,5) : à cette échelle la touffe fait une quinzaine de pixels de haut.

## Traits qui font reconnaître, dans l'ordre
1. **Des pompons mauves ronds** posés au-dessus d'une touffe verte : aucune autre voisine n'a de rond coloré en tête.
2. **Une touffe dense de brins fins, raides et dressés**, nombreux, qui partent tous d'un pied étroit et s'ouvrent en gerbe : une brosse, plus haute que large.
3. **Pas de blanc au pied** : du vert du sol à la pointe, ni fût, ni bulbe.

## Confusions et ce qui sépare
- **Ciboule** (déjà modélisée, `ciboule-de-chine.n.js`) : quatre fûts blancs nus et peu de tubes épais, une touffe en V haute de 45 cm. La ciboulette a des dizaines de brins fins, **aucun blanc**, et des pompons mauves.
- **Oignon** : pied unique, gros bulbe doré au sol, peu de tubes épais. La ciboulette n'a pas de bulbe et fait une brosse de brins.
- **Poireau** : un seul fût épais et des lames plates en éventail dans un plan, qui retombent. La ciboulette a des brins fins dressés tout autour, rien de plat ni de retombant.
- **Herbe** (touffe de graminée) : brins plats qui s'arquent et retombent, vert plus jaune, épis beiges. La ciboulette a des brins raides, à peine arqués, un vert plus sombre et bleuté, et des pompons mauves.

## Couleurs (quatre au plus)
- Clair : `#b98fd0`, les pompons, mauve.
- Moyen : `#4f8f3e`, les brins du cœur et les hampes.
- Sombre : `#2f6232`, les brins extérieurs, pour donner deux valeurs à la touffe.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **60 triangles**, **4 couleurs**, `d` = **0,2 m**.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où la touffe fait une quinzaine de pixels de haut et le pompon deux.

## Sources
- Truffaut, *Ciboulette, variétés, plantation, entretien* : touffes de 15 à 30 cm, floraison de mai à juillet, fleurs mauves en sphères de quelques centimètres. https://www.truffaut.com/ciboulette-varietes-plantation-entretien.html
- Jardiner malin, *Ciboulette* : 20 à 30 cm, pompons mauves de mai à juillet. https://www.jardiner-malin.fr/fiche/ciboulette.html
- Futura-Sciences, *Ciboulette* : inflorescences en pompons en mai, du violet au blanc selon la variété. https://www.futura-sciences.com/planete/definitions/botanique-ciboulette-19933/
- Perennials.com, *Allium schoenoprasum* : hauteur 25 à 30 cm, étalement 30 à 40 cm. https://www.perennials.com/plants/allium-schoenoprasum.html
- Botany Brisbane, *Allium schoenoprasum* : feuilles creuses jusqu'à 50 cm, 2 à 3 mm de diamètre, tête sphérique d'une trentaine de fleurs. https://www.botanybrisbane.com/plants/amaryllidaceae-s-l/allium/allium-schoenoprasum/
- NC State Extension, *Allium schoenoprasum* : fleurs pourpres en têtes d'environ 2,5 cm, hampes jusqu'à 45 cm. https://plants.ces.ncsu.edu/plants/allium-schoenoprasum

## Jugement à l'aveugle (version t4)

Au lot 26, la version t4 est nommée ciboulette par les 3 juges, tous sûrs, avec l'ail en fleur en deuxième hypothèse. Les pompons mauves ont tranché sur les trois vues. Elle compte 60 triangles : 12 brins pliés en V, 3 hampes et 3 pompons.
