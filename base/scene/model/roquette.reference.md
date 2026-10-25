# Roquette · fiche de référence

Roquette cultivée (Eruca sativa) au stade de récolte, dans le potager, avant la montaison. C'est l'état de la plus grande partie de sa saison : on la coupe feuille à feuille, et elle est arrachée quand elle monte en fleur.

## Proportions
- Plant : 20 à 30 cm de haut, 25 à 35 cm d'emprise (rosette basale jusqu'à 20 cm de diamètre en jeune plant, écartement 15 à 25 cm sur le rang). Rapport hauteur / emprise ≈ 0,7 à 0,8 : une touffe plus large que haute, sans tige.
- Feuilles : 8 à 15 par plant, 10 à 20 cm de long, 2,5 à 4 cm de large au lobe terminal, soit un rapport longueur / largeur de 4 à 5. Partant toutes du collet, sans tige centrale.
- Port : rosette dressée et ouverte. Les feuilles du cœur montent à 20-30° de la verticale, les extérieures s'ouvrent à 50-70° et ne retombent qu'un peu au bout ; le centre reste creux, on voit la terre entre les feuilles vue de dessus.
- Limbe lyré : un pétiole court, puis 2 à 4 paires de lobes latéraux petits, arrondis, séparés par des sinus profonds jusqu'à la nervure, plus petits vers le bas, et un grand lobe terminal ovale-arrondi qui fait un tiers à la moitié de la longueur. La feuille s'élargit donc vers le bout.
- Fleurs : seulement à la montaison (hampe de 50 à 80 cm, fleurs crème veinées de violet). Hors du plant modélisé, ou une seule au plus.

Recoupement : 12 feuilles de 15 cm ouvertes à 55° donnent une emprise de 2 × 15 × sin 55° ≈ 25 cm et une hauteur de 15 × cos 25° ≈ 14 cm pour le cœur ; avec le relèvement du lobe terminal on atteint 18 à 22 cm. Hauteur / emprise ≈ 0,75, cohérent avec 20 à 30 cm sur 25 à 35.

## Traits qui font reconnaître, dans l'ordre
1. La feuille en lyre : une file de petits lobes ronds le long de la nervure, terminée par un grand lobe rond, en vert sombre mat.
2. Une rosette sans tige, dressée et ouverte en entonnoir évasé, plus large que haute, au cœur creux.
3. Une teinte vert sombre homogène, la nervure un peu plus claire.

## Confusions et ce qui sépare
- **Fraisier** : feuilles trifoliées (trois folioles dentées au bout d'un long pétiole nu), fleurs blanches et fruits rouges. La roquette n'a aucun pétiole nu long ni trois folioles, et aucune fleur : ses lobes sont rangés en file le long d'une seule nervure.
- **Mizuna** : fontaine bombée vert vif, feuilles en dentelle fine aux dents étroites, pétioles blancs. La roquette est plus sombre, ses lobes sont ronds et peu nombreux, son cœur est creux.
- **Radis** : rosette plus petite et plus basse, feuilles rugueuses vert moyen, racine rouge au collet. La roquette est plus haute, plus sombre, sans racine visible.
- **Pissenlit** : rosette plaquée au sol, lobes pointus en dents de scie tournés vers le cœur. La roquette est dressée, ses lobes sont arrondis et le lobe terminal domine.

## Couleurs (quatre au plus)
- Sombre : `#2c5e27`, le limbe des feuilles extérieures et les lobes latéraux.
- Moyen : `#3f7d33`, le grand lobe terminal des feuilles du cœur, qui prend la lumière.
- Clair : `#8fb862`, la nervure et le pétiole, courts, qui dessinent l'axe de la lyre.

## Sources
- Wisconsin Horticulture, « Arugula » : https://hort.extension.wisc.edu/articles/arugula (rosette, montaison jusqu'à 0,8 m)
- Master Gardener Wisconsin, fiche arugula : https://mastergardener.extension.wisc.edu/files/2015/12/arugula.pdf (rosette basale ≈ 20 cm, feuilles ≈ 10 × 2,5 cm)
- Greenweez, graines de roquette cultivée : https://www.greenweez.com/produit/graines-de-roquette-cultivee/1LASE0033 (20 à 30 cm de haut)
- Bishop Museum, Cook Islands Biodiversity : https://data.bishopmuseum.org/pbs/cookislands/species.asp?id=13750 (feuilles lyrées, 2 à 5 lobes latéraux et un grand lobe terminal)
- Ville de Trois-Rivières, fiche roquette de jardin : https://www.v3r.net/wp-content/uploads/2025/02/Fiche_8-5x11_BS_Roquette_De-jardin_2025.pdf

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles** (`especes.json`, sans exception), **4 couleurs**.
- Aucun fruit posé par le socle. Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m).

## Jugement à l'aveugle (version r)

Au lot 19, la version r est nommée roquette par les 3 juges : deux hésitent, le troisième ne sait pas. Leur deuxième hypothèse est le pissenlit. L'ancienne version est lue fraisier par deux juges ; le troisième ne la nomme roquette que sans être sûr. La version r compte 297 triangles : une rosette basse de 11 feuilles en lyre, au limbe continu.
