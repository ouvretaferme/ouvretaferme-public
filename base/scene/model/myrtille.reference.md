# Myrtillier cultivé · fiche de référence

Myrtillier arbustif, dit myrtillier américain (Vaccinium corymbosum, éricacées), pied adulte de quatre ans et plus, planté au potager ou au verger en terre de bruyère, conduit par une taille de renouvellement des cannes. Caduc : il porte son feuillage d'avril à octobre, et c'est ce plant feuillé que la fiche décrit. Les baies mûrissent de juillet à septembre et sont posées par le socle (ligne `FRUITS`), pas dessinées dans le plant.

## Proportions
- Espèce : jusqu'à 2 m de haut pour 1,5 m de large ([Le potager de mes rêves](https://www.lepotager-demesreves.fr/?idu=41158)) ; 1,3 à 2 m de haut pour 1 à 1,5 m de large, arbuste dressé à plusieurs tiges ([KPU Plant Database](https://plantdatabase.kpu.ca/Plant/vaco)). Cultivars courants : 'Ivanhoe' 1,3 à 1,5 m pour 1 m, port érigé, peu dense ; 'Darrow' 1,8 m en tous sens, dressé et étalé, nombreuses tiges ([Truffaut, Ivanhoe](https://www.truffaut.com/myrtillier-d-amerique-ivanhoe-godet-de-8-9-cm.html), [Truffaut, Darrow](https://www.truffaut.com/myrtille-d-amerique-darrow-pot-de-1-5l-2l.html)) ; 'Premier', arbuste dressé à tiges multiples ([NC State](https://plants.ces.ncsu.edu/plants/vaccinium-corymbosum-premier/)).
- Unité modélisée : emprise **d = 1,2 m** (consigne). Rapport hauteur / emprise des sources : 1,5/1 (Ivanhoe), 1,65/1,25 (KPU, milieu des plages), 2/1,5 (espèce), soit 1,3 à 1,5. Retenu **h ≈ 1,5 m**, rapport 1,25, au bas de la fourchette parce qu'un pied taillé au jardin est tenu plus bas que l'espèce libre.
- Port : un vase. Six à dix cannes partent du collet ([UMaine Extension](https://extension.umaine.edu/highmoor/?p=10755)), presque verticales au centre, ouvertes de 15 à 30° sur le pourtour. Le pied est étroit au sol (40 % de l'emprise environ) et la ramure s'élargit jusqu'aux deux tiers de la hauteur, puis s'arrondit en tête. Les cannes sont nues sur le premier tiers (la taille dégarnit le bas, les rameaux fruitiers sont en haut).
- Bois : écorce gris-brun sur les vieilles cannes ; les rameaux de l'année sont lisses, vert rougeâtre à rouges, et le restent en hiver.
- Feuilles : simples, alternes, coriaces, elliptiques pointues, 3 à 6 cm sur 1,5 à 4 cm, vert sombre luisant ([KPU](https://plantdatabase.kpu.ca/Plant/vaco)), un peu bleutées. Illisibles une à une à l'écran : elles font une couronne dense de touffes au bout des rameaux, au contour bosselé.
- Fruits : baies rondes de 1 à 2 cm, bleu pruiné (bleu clair poudré), en grappes lâches de 5 à 10 au bout des rameaux de l'an passé, dans la moitié haute et sur le pourtour de la ramure.
- Recoupement : des cannes de 1 m qui s'ouvrent de 25° depuis un collet de 0,4 m s'écartent de 0,42 m de chaque côté, soit 1,25 m à leur tête ; la couronne de touffes qui les coiffe (0,25 à 0,35 m d'épaisseur) porte la hauteur à 1,4 à 1,5 m. Emprise et hauteur tiennent ensemble.

## Traits qui font reconnaître, dans l'ordre
1. Un arbuste en vase : plusieurs cannes ligneuses nues qui sortent du sol en gerbe, sans tronc unique, sous une couronne arrondie plus large que le pied.
2. Une couronne vert sombre bleuté, faite de touffes de petites feuilles pointues au bout des rameaux, rameaux jeunes rougeâtres.
3. Les grappes de baies bleu pruiné sur le pourtour haut (posées par le socle) : la seule culture du potager aux fruits bleus.

## Confusions et ce qui sépare
- **Arbre fruitier (famille `tree`, noyer)** : un tronc unique et une tête posée dessus. Le myrtillier n'a pas de tronc : cinq à sept cannes partent du sol, visibles jusqu'au tiers de la hauteur.
- **Groseillier, cassis** : buisson bas (1 à 1,2 m), en boule plus large que haute, feuilles palmées vert tendre, fruits rouges ou noirs en grappes pendantes. Le myrtillier est plus haut que large, en vase, à feuillage sombre bleuté et petites feuilles entières, fruits bleu clair.
- **Framboisier** : cannes en rang palissé, arquées, feuilles composées vert clair à revers blanc. Le myrtillier est un pied isolé en vase, sans palissage, à couronne dense.
- **Romarin** : petit arbrisseau d'aiguilles gris-vert, trois fois plus petit, sans couronne. **Physalis, piment** : plant herbacé bas, sans bois.
- **Buisson générique (famille `bush`, `berry`)** : une boule de feuillage posée au sol. Le myrtillier montre son pied ligneux sous la couronne.

## Couleurs (quatre au plus)
- Sombre : `#2e5440`, vert sombre bleuté, l'essentiel des feuilles.
- Moyen : `#5f8a5c`, les feuilles du dessus et des bouts de rameaux, touchées par la lumière.
- Bois : `#6f5f52`, gris-brun, les cannes.
- Accent : `#a5483a`, rouge brique, les rameaux de l'année.
- (Fruits, par le socle : `#5a6fa8` bleu pruiné, plus clair que tout le feuillage.)

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, absolu, **4 couleurs**. Famille de repli `berry`.
- Ce qui doit marcher : la vue « jeu » (rang à 20 px/m). Avec l'exagération ×2,5, le pied fait 60 px de large et 75 de haut : le vase de bois sous la couronne et le contraste sombre du feuillage contre les baies bleu clair portent la reconnaissance.

## Jugement à l'aveugle (au banc, avec FRUITS)
Lot 35, trois juges. Les trois le nomment myrtillier, sûrs, sur les baies bleu poudré, les rameaux rougeâtres et les petites feuilles ovales ; seconde hypothèse camerisier. Défaut nommé : en v2 le bleu se perd presque, les baies devraient pendre sous les rameaux.
