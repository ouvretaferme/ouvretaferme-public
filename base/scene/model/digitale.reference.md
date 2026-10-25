# Digitale · fiche de référence

Digitale pourpre (Digitalis purpurea), Plantaginacées. Bisannuelle : la première année une rosette seule, la deuxième une hampe florale unique qui monte en mai et fleurit de juin à août. Le modèle montre le pied de deuxième année en fleur, planté en ligne ou isolé, l'état qui la fait reconnaître. Pas de parterre.

## Proportions
- Espèce : bisannuelle ou vivace de courte vie, rosette de feuilles ovales doucement velues, fleurs pendantes et tubulaires rose pourpre d'environ 6 cm, tachetées à l'intérieur ; hauteur 1,5 à 2,5 m, emprise 0,1 à 0,5 m ([RHS, Digitalis purpurea](https://www.rhs.org.uk/Plants/5862/Digitalis-purpurea/Details)). Le genre : rosette de feuilles simples, fleurs en cloche en grappes minces, dressées, **généralement unilatérales** (même source).
- Feuilles de la rosette : limbe de 10 à 25 cm sur 3,5 à 12 cm, lancéolé à ovale, crénelé, gris tomenteux dessous, pétiole ailé ([Flora of New Zealand, vol. IV](https://floraseries.landcareresearch.co.nz/taxa/dcb5f81e-43fd-46e2-bdf2-0dc9b73920f6)) ; jusqu'à 30 cm ([Naturalis, flore du Burren](https://flora-burren.linnaeus.naturalis.nl/linnaeus_ng/app/views/species/taxon.php?id=37892)). Les feuilles de la hampe sont plus petites et sessiles, de plus en plus courtes vers le haut.
- Grappe : jusqu'à 60 cm, nombreuses fleurs penchées (Flora of New Zealand) ; 50 cm ([VicFlora](https://vicflora.rbg.vic.gov.au/flora/taxon/be8bf9ca-eb0c-428d-aeed-e3424e9a786c)). Corolle de 3,5 à 5 cm (Flora of New Zealand), 4 à 5 cm (Burren).
- Unité modélisée, d'après la consigne du chantier : `d` 0,45 m, `h` 1,2 m, soit hauteur / emprise ≈ 2,7.
  - Rosette : 5 grandes feuilles de 22 cm (pétiole ailé compris), presque couchées, ouvertes en étoile irrégulière : 45 cm de diamètre, 12 cm de haut.
  - Hampe unique, droite, nue de 12 à 55 cm hormis deux ou trois petites feuilles sessiles.
  - Grappe de 55 cm à 1,2 m : environ 10 cloches ouvertes de 5 cm sur 2,5 cm, pendantes à 40-60° sous l'horizontale, **toutes du même côté** dans un secteur de 120°, sur deux rangs décalés ; au-dessus, une pointe de boutons serrés de 12 cm.
- Recoupement : des cloches de 5 cm écartées de 3 cm de l'axe sortent à 8 cm d'un seul côté ; la grappe reste dans le cercle de la rosette (22 cm de rayon). L'emprise est donc celle de la rosette, et la plante se lit en tige mince à drapeau d'un seul côté au-dessus d'un plateau de feuilles.
- Exagération pour l'écran : à 20 px/m, avec le facteur 2,5 de la scène, une cloche de 5 cm ferait 2,5 px. Les cloches sont doublées (10 cm sur 5 cm) pour rester des points roses séparés, la rosette garde sa taille réelle, déjà large.

## Traits qui font reconnaître, dans l'ordre
1. Une hampe **unique**, droite et haute, garnie d'un seul côté de clochettes tubulaires roses **pendantes**, qui laissent voir la tige de l'autre côté ; boutons serrés plus foncés en pointe.
2. Une rosette basale plate de grandes feuilles **entières**, ovales et pointues, gris-vert, nettement séparée de la grappe par une tige presque nue.
3. Le rose pourpre des fleurs, seule couleur vive, contre le vert gris du feuillage.

## Confusions et ce qui sépare
- **Lupin** (hors catalogue) : épi conique dense, fleurs papilionacées serrées tout autour de l'axe, feuilles palmées en étoile au bout de longs pétioles, touffe à plusieurs épis. La digitale : une seule hampe, fleurs en cloches pendantes d'un seul côté, épi aéré et dissymétrique, feuilles entières ovales en rosette plate.
- **Delphinium** (`delphinium.n.js`) : épi bleu aéré de fleurs plates ouvertes en étoile tout autour de l'axe, deux hampes, dôme de feuilles en lanières. La digitale : rose et non bleu, cloches tombantes et non étoiles ouvertes, d'un seul côté, une seule hampe, feuilles pleines.
- **Muflier** (`muflier.n.js`) : épi plus court (60 cm), fleurs à deux lèvres tout autour, touffe ramifiée. La digitale est deux fois plus haute, unilatérale, sur une rosette.
- **Lavande, bleuet** : touffe basse en brosse ou en pompons. La digitale porte sa couleur en drapeau sur une seule tige haute.

## Couleurs (quatre au plus)
- Clair : `#d0609f`, rose pourpre, les cloches ouvertes : le plus fort contraste, contre le vert gris de la rosette.
- Sombre : `#7c2a63`, pourpre foncé, la pointe en boutons.
- Moyen : `#7f9c5a`, vert clair, la hampe et ses petites feuilles.
- Ombre : `#4a6a3e`, vert gris sombre, la rosette.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Grande fleur : plafond **120 triangles**, absolu, **4 couleurs**.
- Plantée en ligne ou isolée, pas en parterre : le modèle est un pied, pas une touffe.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m). Les cloches portent la reconnaissance et reçoivent le plus de triangles.

## Jugement à l'aveugle (lot 44, t4)
Nommée sûre par les deux premiers juges, campanule pyramidale en deuxième hypothèse : hampe de cloches roses tombantes et rosette de grandes feuilles ovales ont tranché, jugée à côté du lupin et du delphinium.
