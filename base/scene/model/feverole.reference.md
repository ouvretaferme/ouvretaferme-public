# Féverole · fiche de référence

*Vicia faba* var. *minor*, la fève de plein champ, semée dense en rangs. Plant adulte en floraison et nouaison : les fleurs occupent encore le haut de la tige pendant que les premières gousses sont nouées en dessous. Les gousses sont dans le plant, pas posées par le socle.

## Proportions
- Hauteur : 0,8 à 1,5 m selon le type (hiver ou printemps) et la densité. Retenu : **1,1 m**.
- Emprise : **0,3 m**. Le plant est une chandelle, **3,7 fois plus haute que large**, plus étroite que la fève potagère (2,5).
- Tige : **une seule** en peuplement dense (la fève potagère talle en 2 à 4 tiges), **carrée**, creuse, raide, verticale, sans rame ni vrille, 8 à 12 mm de côté au pied.
- Feuilles : alternes, une par nœud, tous les 5 à 8 cm, soit une quinzaine de nœuds feuillés. Composées de **1 à 3 paires de folioles ovales**, entières, sans vrille. Foliole de 4 à 7 cm sur 2 à 3 cm (rapport 2), glauque, gris vert bleuté, mate.
- Fleurs : grappes courtes de 2 à 6 à l'aisselle des feuilles, **sur la moitié haute**, serrées contre la tige. Corolle blanche de 2 à 3 cm, **une macule noire sur chaque aile**.
- Gousses : **courtes, 5 à 8 cm** (10 à 20 cm chez la fève), cylindriques, 3 à 4 graines, **dressées contre la tige**, par 1 à 3 par nœud, vertes puis **noires** à maturité, du bas vers le haut.

Recoupement : une foliole de 6 cm au bout d'un pétiole de 3 cm, relevée à 35° de l'horizontale, déporte sa pointe de 12 à 14 cm de l'axe. Avec une tige unique dans l'axe, l'emprise est de 0,25 à 0,3 m, cohérente avec la mesure. Quinze nœuds à 6 cm font 0,9 m de tige feuillée sur 1,1 m : le pied est nu sur 15 à 20 cm.

Limite des sources : la hauteur et la couleur des gousses à maturité sont sourcées. La longueur des gousses de la variété *minor* (5 à 8 cm) et la tige unique en peuplement dense viennent de la consigne et de la pratique de la culture, les pages trouvées ne les chiffrent pas.

## Traits qui font reconnaître, dans l'ordre
1. Une tige unique, carrée, raide, en chandelle étroite, garnie de paires de folioles ovales gris vert bleuté.
2. Les fleurs blanches à macule noire, serrées à l'aisselle sur la moitié haute.
3. Sous les fleurs, de petites gousses courtes dressées contre la tige, sombres puis noires.

## Confusions et ce qui sépare
- **Fève** (`feve.n.js`) : 2 à 4 tiges en touffe, plus trapue (1 m pour 0,4 m), feuillage plus vert, grosses gousses de 10 à 20 cm posées par le socle. La féverole a une seule tige, elle est plus haute, plus étroite, plus grise, et ses gousses sont de petits traits noirs collés à la tige.
- **Pois** (`pois.n.js`) : grimpe sur un filet, vrilles, vert franc, gousses pendantes. La féverole tient seule et ne s'étale pas.
- **Haricot** (`haricot.n.js`) : feuilles trifoliées en cœur, vert vif, buisson bas ou rames. La féverole est une tige droite à folioles ovales.
- **Lupin** (`lupin.n.js`) : feuilles palmées en étoile, épi terminal en cône plein et coloré. La féverole n'a pas d'épi : ses fleurs sont à l'aisselle, le long de la tige, entre les feuilles.
- **Soja** (pas de modèle) : buisson bas, trifolié, velu, gousses pendantes en bouquets.

## Couleurs (quatre au plus)
- Moyen : `#8fa49a`, les folioles, gris vert bleuté, plus gris que la fève (`#86a084`).
- Sombre : `#4a6744`, la tige et les gousses jeunes du haut.
- Clair : `#f6f5ec`, la corolle.
- Accent : `#1d1a20`, la macule des ailes et les gousses noircies du bas.

## Sources
- PlantNet, *Vicia faba* : https://uses.plantnet-project.org/fr/Vicia_faba (dressée, robuste, 1 à 3 paires de folioles ovales entières, fleurs blanches à ailes noires, gousses dressées, variété *minor* à petites graines).
- NC State Extension, *Vicia faba* : https://plants.ces.ncsu.edu/plants/vicia-faba/ (0,6 à 1,5 m).
- Agence canadienne d'inspection des aliments, fiche d'examen d'une variété de féverole : https://active.inspection.gc.ca/francais/plaveg/pbrpov/cropreport/faba/faba129f.pdf (gousses demi-dressées, noir brun à maturité).
- Ontario, la féverole en alimentation animale : https://www.ontario.ca/fr/page/utilisation-de-la-feverole-comme-source-de-proteine-dans-lalimentation-animale (fleurs blanches à macule liées aux tanins, variétés sans tanins à fleurs toutes blanches).
- Fiche de la fève du même dossier, `feve.reference.md`, pour les folioles et la tige carrée.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » (rang à 20 px/m), où le plant fait une vingtaine de pixels de haut, semé dense.

## Jugement à l aveugle (lot 56, t3)

3 juges sur 3 : « fève en fleurs », « fève ou pois », « fève ou féverole ». La tache noire sur la fleur blanche a tranché. Deuxième hypothèse : lupin blanc.
