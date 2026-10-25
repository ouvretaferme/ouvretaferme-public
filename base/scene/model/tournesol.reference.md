# Tournesol · fiche de référence

Plant adulte en fleur, au potager, de juillet à la fin de l'été : c'est l'état qu'il garde la plus grande partie de sa saison visible. Le capitule fait partie du plant (comme les épis du maïs) : le socle ne pose aucun fruit dessus.

## Proportions
- Tige : unique, droite, non ramifiée, 1,5 à 2,5 m pour une variété de potager (2 m en culture tempérée selon Larousse ; 1 à 3 m pour la plante sauvage). Diamètre 2 à 7 cm, retenu 3 à 4 cm : une tige épaisse mais qui reste une ligne à côté du capitule. Retenu : 1,9 m.
- Capitule : 15 à 30 cm de diamètre (Larousse). Retenu pour le jeu : 56 cm, le double du réel, faute de quoi il ne fait que 8 px sur le rang à 20 px/m ; c'est le trait qui fait reconnaître, il passe avant la proportion. Disque central brun sombre d'environ la moitié du diamètre, entouré d'une couronne de ligules jaune vif (20 à 30 ligules, chacune 6 à 10 cm). Au champ, il est incliné vers l'avant, la face à 30-50° de la verticale, porté par le haut de la tige qui se courbe. Retenu : face à 16° de la verticale seulement, la tige courbée au sommet portant l'inclinaison, parce que la caméra plongeante du jeu et l'azimut au hasard des plants montrent sinon un disque vu par la tranche une fois sur deux.
- Feuilles : 20 à 30 sur les hybrides (Larousse), alternes sauf les cinq premières, opposées. Limbe en cœur, pointu, denté, 20 cm de long et 10 à 15 cm de large en moyenne (jusqu'à 40 × 35 cm en bas, Missouri Plants), pétiole de 2 à 20 cm. Les feuilles basses sont les plus grandes, elles retombent ; les hautes sont plus petites et plus dressées. La moitié basse de la tige se dégarnit en fin de saison.
- Emprise : feuille + pétiole de chaque côté, 0,25 à 0,35 m, donc 0,5 à 0,6 m de diamètre ; 1 à 1,5 m pour les très grandes variétés (KPU). Retenu 0,6 m : rapport hauteur sur emprise ≈ 3.
- Recoupement : 28 cm de capitule réel sur 1,9 m font 15 % ; 0,6 m d'emprise pour des feuilles de 20 cm sur pétiole de 10 cm donne 2 × 0,3 m, cohérent.

## Traits qui font reconnaître, dans l'ordre
1. Au sommet d'une tige nue, un grand disque jaune vif au cœur brun sombre, incliné vers l'avant.
2. Une haute tige unique, droite, plus haute que tout le potager.
3. De grandes feuilles en cœur, alternes, qui pendent le long de la tige, de plus en plus petites vers le haut.

## Confusions et ce qui sépare
- **Maïs** : feuilles en longs rubans arqués, panicule en plumeau au sommet, épis à mi-hauteur. Le tournesol a des feuilles larges en cœur sur pétiole et un disque jaune au sommet.
- **Artichaut** : touffe basse et large de feuilles grises découpées, têtes globuleuses vertes. Le tournesol est haut, sur une seule tige, et sa tête est un disque plat jaune.
- **Poire de terre, topinambour** : plusieurs tiges, petites fleurs jaunes nombreuses sans cœur sombre marqué. Le tournesol a une seule tige et un seul grand capitule à cœur brun.

## Couleurs (quatre au plus)
- Clair : `#f6c21c`, les ligules, jaune vif.
- Sombre : `#4a2a12`, le disque central, brun très sombre : le plus fort contraste du plant est entre lui et les ligules.
- Moyen : `#5d8f2e`, les feuilles.
- Quatrième : `#7f9f3c`, la tige et les pétioles, un vert plus clair et plus jaune que le limbe.

## Sources
- [Larousse, tournesol](https://www.larousse.fr/encyclopedie/divers/tournesol/82742) : 2 m, tige de 2 à 7 cm, capitule 15 à 30 cm, 20 à 30 feuilles.
- [Missouri Plants, Helianthus annuus](https://missouriplants.com/Helianthus_annuus_page.html) : feuilles 7 à 40 cm × 3 à 35 cm, 8 à 25 nœuds, jusqu'à 3 m.
- [KPU Plant Database](https://plantdatabase.kpu.ca/PlantPrint/hean) : 2 à 3 m × 1 à 1,5 m.
- [Agence canadienne d'inspection des aliments](https://inspection.canada.ca/fr/varietes-vegetales/vegetaux-caracteres-nouveaux/demandeurs/directive-94-08/documents-biologie/helianthus-annuus) : feuilles opposées à la base, alternes au-dessus ; hauteur 50 à 500 cm selon les variétés.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait une centaine de pixels de haut et le capitule une quinzaine.
