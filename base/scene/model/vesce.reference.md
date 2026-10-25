# Vesce · fiche de référence

Vesce de couvert, fabacées : *Vicia sativa* (vesce commune) et *Vicia villosa* (vesce velue), semées denses en couvert ou en fourrage, souvent mêlées à une céréale qui leur sert de tuteur. Seule, elle ne tient pas debout : ses tiges grêles rampent, s'accrochent les unes aux autres par leurs vrilles et font un tapis. L'état décrit est celui qu'elle garde la plus grande partie de sa saison, un tapis vert emmêlé en début de floraison. Le modèle prend le port et la feuille communs aux deux espèces, et la grappe unilatérale de la vesce velue. Reprise après le jugement à l'aveugle de la version u4 (1 juge sur 3, deux lectures « lavande ») : les folioles en barreaux fins faisaient un feuillage en lanières, le tapis écrasé disparaissait sur le rang et la vrille ne se voyait pas.

## Proportions
- Hauteur : un peu plus de 50 cm selon le Museum für Naturkunde de Berlin, 30 à 75 cm selon Illinois Wildflowers, 1 m et plus seulement avec un support (First Nature). En semis dense les pieds se tiennent les uns aux autres par leurs vrilles et le couvert monte. Retenu pour le modèle : **47 cm**, une touffe plus haute que large d'un tiers (rapport hauteur sur emprise de 1,33).
- Emprise : **35 cm** (consigne).
- Tiges : grêles, anguleuses, d'un vert pâle. Dans le modèle, **six** tiges partent du pied en s'écartant et s'arrêtent à six hauteurs étagées, du bas au sommet de la touffe.
- Feuilles : **pennées, 3 à 8 paires de folioles** (5 à 6 selon iNaturalist, 8 à 16 folioles selon l'université du Michigan). Retenu **trois paires** par feuille, pour que chaque foliole reste grosse et détachée à l'échelle du jeu, et **six feuilles**. Folioles en **ovale tronqué**, large au bout et terminé par une courte pointe, 8 à 30 mm de long pour 5 à 13 mm de large : dans le modèle, la largeur fait les deux tiers de la longueur. La foliole terminale est remplacée par une **vrille fourchue**, à deux brins coudés et ramenés en crochet. Chaque feuille est portée presque à plat et repasse au-dessus du cœur de la touffe.
- Stipules : 2 à 10 mm, dentées, invisibles à l'échelle du jeu (à l'opposé des grandes stipules du pois).
- Fleurs : chez la vesce commune, isolées ou par deux à l'aisselle, 12 à 30 mm, étendard violet et ailes rose pourpre. Chez la vesce velue, en **grappe unilatérale** sur un pédoncule qui sort du feuillage, les fleurs tubulaires pendant toutes du même côté, pourpre violacé (description de mémoire, sans source relue). Retenu : **trois grappes courtes de trois fleurs**, pourpres et violettes en alternance, penchées sur le flanc de la touffe, à mi-hauteur, sans jamais la coiffer.
- Gousses : 3 à 5 cm, étroites, brunes à maturité, hors modèle.
- Échelle d'écran : à 20 px/m, avec l'exagération ×2,5, une feuille de 8 cm fait 4 px et une fleur de 2 cm fait 1 px. Le modèle porte la feuille à 20 cm environ (six feuilles au lieu de vingt), la foliole à 8 cm et la fleur à 5 cm, pour que les folioles et le pourpre se lisent.

## Traits qui font reconnaître, dans l'ordre
1. La **feuille pennée à vrille** : des paires de folioles ovales et larges, opposées et détachées le long d'un axe pâle, terminée par une **vrille fourchue et enroulée**, plus claire que le feuillage, qui dépasse la touffe.
2. Un **coussin emmêlé**, plus haut que large, de ces feuilles croisées à des hauteurs différentes, d'un vert tendre clair, sans tuteur ni axe central.
3. Des **grappes unilatérales** courtes, pourpres et violettes, penchées sur le flanc, moins présentes que le feuillage.

## Confusions et ce qui sépare
- **Lavande et sauge** : épis violets dressés en tête d'une touffe de feuilles en lanières gris-vert ou olive. La vesce a des folioles ovales et larges d'un vert tendre, des vrilles, et ses grappes sont peu nombreuses, penchées, sur le flanc et jamais en tête.
- **Fenouil** (`fenouil.js`) : feuillage en fils, bulbe blanc. La vesce n'a aucune pièce filiforme en dehors de ses vrilles, et pas de bulbe.
- **Pois** (`pois.js`) : rideau palissé sur un grillage clair, grandes folioles rondes glauques par une à trois paires, fleurs blanches. La vesce n'a ni filet ni rideau, sa touffe tient seule, son vert est tendre et ses fleurs pourpres.
- **Lentille** : petit buisson bas à folioles minuscules et nombreuses, fleurs pâles isolées, vrille courte. La vesce a des folioles larges et peu nombreuses, une vrille longue et la couleur en grappes.
- **Luzerne** (`luzerne.n.js`) : touffe dressée de tiges droites, feuilles à trois folioles, grappes mauves au bout des tiges. Les feuilles de la vesce sont pennées à vrille et ses grappes pendent sur le flanc.
- **Haricot** (`haricot.js`) : deux perches brunes en A, grandes feuilles par trois en cœur. La vesce n'a aucun tuteur.
- **Pois de senteur** (`pois-de-senteur.n.js`) : colonne haute sur une rame, fleurs grandes et mêlées en tête. La vesce n'a pas de rame et ses fleurs, petites, restent sous le feuillage.

## Couleurs (quatre)
- Clair : `#e2f0a2`, les vrilles, les axes des feuilles, les tiges et les pédoncules.
- Moyen : `#a4dc62`, toutes les folioles, d'un vert tendre. Leurs normales sont tirées vers le ciel pour qu'aucune ne vire à l'olive.
- Sombre et accent : `#a43cb4` (pourpre) et `#6e58d8` (violet), les fleurs, en alternance sur une même grappe. Il n'y a pas de vert sombre : c'est l'ombre portée entre les étages de feuilles qui donne la troisième valeur.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, **4 couleurs**. Le modèle en compte 120 : six feuilles de 17 triangles et leur tige (108), trois grappes de 4 (12).
- Fleur en parterre : le socle pose les touffes à 0,7 × d, soit tous les 25 cm. La nappe à ce pas n'a pas été recapturée pour la version w5 ; la planche ne montre que le rang (`captures/vesce-n-w5-planche.png`).

## Sources
- [Museum für Naturkunde Berlin, Vicia sativa](https://naturblick.museumfuernaturkunde.berlin/species/portrait/16270?lang=en) : un peu plus d'un demi-mètre de haut.
- [Illinois Wildflowers, Common Vetch](https://illinoiswildflowers.info/weeds/plants/cm_vetch.html) : 30 à 75 cm, tiges peu ramifiées, fleurs isolées ou par deux à l'aisselle, stipules d'environ 6 mm.
- [First Nature, Vicia sativa](https://mail.first-nature.com/flowers/vicia-sativa.php) : 1 m et plus seulement sur un support, 3 à 8 paires de folioles.
- [iNaturalist, Common Vetch](https://www.inaturalist.org/guide_taxa/355870) : 5 à 6 paires de folioles, vrille terminale.
- [Université du Michigan, Vicia sativa](https://climbers.lsa.umich.edu/vicia-sativa) : 8 à 16 folioles, la terminale changée en vrille fourchue.
- [Flora of the Burren, Naturalis, Vicia sativa](https://flora-burren.linnaeus.naturalis.nl/linnaeus_ng/app/views/species/taxon.php?id=38652) : fleurs de 12 à 30 mm, presque sessiles.
- [HerbiGuide, Common Vetch](https://www.herbiguide.com.au/Descriptions/hg_common_vetch.htm) : folioles de 8 à 30 mm, gousses de 3 à 5 cm.

Recoupement : six tiges s'écartent de 9 cm du pied et portent chacune une feuille de 20 cm qui repasse au-dessus du cœur ; la feuille déborde donc de 11 cm du côté opposé, et la touffe tient dans les 35 cm avec ses vrilles. Les tiges s'arrêtent entre 9 et 50 cm du sol à l'échelle du module, que le socle ramène à 47 cm de haut. La feuille réelle (5 à 10 cm) est grossie d'un facteur deux à trois, comme le prévoit la ligne « Échelle d'écran ».

## Jugement à l aveugle (lot 63, version w5, plafond relevé à 120)

120 triangles, 4 couleurs. Deux juges sur trois, de justesse : un « vesce, hésitant » (folioles en paires, fils lus comme des
vrilles, fleurs violettes) et un « pois (ou vesce) à fleurs violettes », compté parce que l'espèce y est nommée. Le troisième ne
nomme rien (« buisson à petites feuilles vert clair »). Ce qui manque selon eux : les fleurs sont minuscules et basses, invisibles
sur le rang, et les folioles ne dessinent pas une feuille composée. Versions précédentes : t5 et r5 à 60 triangles (1/3), u4 à
120 (1/3, lue lavande).
