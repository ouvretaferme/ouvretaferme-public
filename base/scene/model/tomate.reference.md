# Tomate · fiche de référence

Plant adulte en pleine saison (juillet à septembre), au potager, conduit sur un brin le long d'un tuteur, gourmands pincés. Les tomates mûres sont posées par le socle (`FRUITS.tomate`, deux grappes de deux ou trois boules rouges `#d8262b` de 0,17 m de scène, à 0,14 de l'emprise, à 0,35 et 0,6 de la hauteur) : le modèle laisse ces deux étages assez ouverts pour que les grappes s'y voient. La planche de capture ne pose pas les fruits du socle, seul le banc le fait : un juge qui ne voit que la planche juge le plant sans ses tomates.

## Proportions
- Hauteur : une tomate à port indéterminé pousse sans arrêt jusqu'aux froids, 1 à 3 m selon les sources ; au potager, on l'arrête vers 1,5 à 1,8 m en pinçant la tête. Retenir **1,6 m** de plant et un tuteur de **1,8 à 2 m** hors sol, qui dépasse la tête d'une vingtaine de centimètres.
- Emprise : plants espacés d'environ 50 cm sur le rang. Les feuilles basses, longues, s'écartent de 25 à 30 cm de part et d'autre de la tige : retenir **0,55 m**. Rapport largeur / hauteur ≈ **0,35** : une colonne haute et étroite, le contraire du buisson.
- Tige : un seul brin vert, attaché au tuteur tous les 15 à 20 cm, qui le suit sans s'en écarter.
- Feuilles : composées, imparipennées, **10 à 25 cm** (jusqu'à 45 cm sur un plant vigoureux), **5 à 9 folioles** ovales à lancéolées, au **bord denté**, de taille inégale. Alternes, une feuille tous les 15 à 20 cm de tige, en spirale. Le pétiole part vers le haut à 40-50° de la tige, puis la feuille s'arque et retombe : la foliole terminale pend.
- Recoupement : sur 1,6 m de tige, une feuille tous les 20 cm donne huit à neuf feuilles, dont les deux ou trois du bas sont retirées au potager (les 25 premiers centimètres restent nus). Une feuille de 30 à 35 cm qui part à 45° et retombe s'écarte d'environ 25 cm de la tige, ce qui tient avec l'emprise retenue.
- Fleurs : petites étoiles jaunes en bouquets, invisibles en jeu.

## Traits qui font reconnaître, dans l'ordre
1. **Des tomates rouges, rondes, en grappe**, bien visibles sur le plant (posées par le socle) : c'est le trait que nomment tous les juges, il doit avoir le plus fort contraste, rouge sur vert sombre.
2. **Des feuilles composées vert sombre, en folioles étroites et dentées, qui retombent** de part et d'autre du tuteur : une silhouette ajourée en arcs pendants, pas de feuilles larges et entières de cucurbitacée.
3. **Un tuteur clair, droit, qui dépasse la tête du plant** et porte la tige, et le pied nu sur les 25 premiers centimètres.

## Confusions et ce qui sépare
- **Poivron** (deuxième hypothèse à chaque tour des juges) : buisson dressé de 60 cm sans tuteur, plus haut que large mais trapu, feuilles entières, pointues, brillantes, vert sombre, portées à l'horizontale. La tomate a un tuteur qui dépasse, fait près de trois fois sa hauteur, et ses feuilles sont découpées et pendantes.
- **Aubergine** : port dressé de 60 à 80 cm, grandes feuilles ovales entières, ondulées, gris vert, tige pourpre, fleurs violettes. La tomate est vert franc moyen, sans gris ni violet, et ses feuilles sont des folioles séparées.
- **Haricot à rames** (deuxième hypothèse au tour 1) : deux rames croisées en tipi et une liane fine aux feuilles en trois folioles larges, sans dent. La tomate a un tuteur unique et droit.
- **Concombre tuteuré, haricot à rames, pois** (lecture des juges sur la planche sans fruits) : même port grimpant contre un support. Sans les tomates rouges, rien ne les sépare à 20 px/m ; le feuillage découpé et pendant, plus sombre que le vert franc des légumineuses et sans feuille large, ne fait que les écarter en second.
- **Pomme de terre** : mêmes feuilles composées, mais touffe basse et étalée de 50 cm, sans tuteur, fleurs blanches au sommet.

## Couleurs (quatre au plus)
- Clair : `#c9a066`, le tuteur (bois clair, le plus fort contraste avec le feuillage).
- Moyen : `#3a6e27`, les feuilles des deux étages hauts, vert sombre pour que le rouge des grappes s'en détache.
- Sombre : `#24501c`, les feuilles basses, plus vieilles et à l'ombre.
- Tige : `#6f9a3a`, vert plus jaune que les feuilles (non modélisée : invisible en jeu, ses triangles vont à une septième feuille).

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, **4 couleurs**. Famille « palissé », `aligned: true`, jusqu'à 356 plants un même jour.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait de 80 à 90 pixels de haut et une trentaine de large avec l'exagération ×2,5. Le tuteur doit y faire au moins deux pixels de large.

## Sources
- Wikifarmer, « Informations sur les plants de tomates » : feuilles généralement composées, port indéterminé tuteuré ([lien](https://wikifarmer.com/library/fr/article/informations-sur-les-plants-de-tomates)).
- Know your Tomatoes : feuilles de 10 à 25 cm, imparipennées, 5 à 9 folioles pétiolées ; plant de 1 à 3 m ([lien](https://www.sites.google.com/site/knowyourvegetables/know-your-tomatoes)).
- Illinois Wildflowers, « Tomato » : feuilles de 4 à 18 pouces sur 2 à 6 (10 à 46 cm), le plant s'étale au sol sauf s'il est tuteuré ou palissé ([lien](https://m.illinoiswildflowers.info/weeds/plants/tomato.html)).
- Virginia Cooperative Extension, SPES-508 : folioles ovales ou lancéolées, à bord denté ; croissance continue des indéterminées ([lien](https://turf.spes.vt.edu/content/dam/pubs_ext_vt_edu/spes/spes-508/SPES-508.pdf)).
- KPU Plant Database : 5 à 9 folioles de tailles variables, feuilles de 10 à 30 cm ([lien](https://plantdatabase.kpu.ca/Plant/lyly)).
- Chambre d'agriculture de Guyane, fiche tomate : environ 50 cm entre plants sur le rang ([lien](https://guyane.chambre-agriculture.fr/fileadmin/user_upload/National/FAL_commun/publications/Guyane/pi-tomate-rogn.pdf)).
- Futura Sciences et Jardiner malin : ébourgeonnage des gourmands, conduite sur un brin, tige attachée tous les 15 à 20 cm ([Futura](https://www.futura-sciences.com/maison/questions-reponses/jardinage-tomate-taille-entretien-vos-plants-9383/), [Jardiner malin](https://www.jardiner-malin.fr/fiche/tomates-en-cage.html)).
