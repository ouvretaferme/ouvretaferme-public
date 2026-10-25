# Pastèque · fiche de référence

Plant adulte en pleine saison (juillet à septembre), au potager, coureurs étalés et fleurs jaunes, avant que les fruits ne grossissent. Les pastèques mûres sont posées par le socle (`FRUITS.pasteque`, une pastèque rayée couchée au sol à 0,3 de l'emprise) : le modèle doit laisser du sol nu autour.

## Proportions
- Port : rampant. Les tiges (coureurs) restent couchées au sol, anguleuses, 5 mm de diamètre au plus, et s'allongent de 2 à 4 m selon la variété. Trois à cinq coureurs principaux partent du collet en étoile, avec des vrilles ramifiées aux nœuds.
- Hauteur du feuillage : les feuilles se relèvent sur leur pétiole au-dessus des coureurs, sans que la plante se dresse. Fiche variétale « Fabiola » : H 50 cm pour une largeur de 250 cm, donc rapport hauteur / emprise ≈ **0,2**. Au potager, la masse des feuilles tient entre 20 et 40 cm du sol.
- Emprise retenue par le chantier : `d` 0,4, l'emprise d'une butte et non l'étalement réel des coureurs (`RENDU-lot3.md` : trois plants par planche de 4 m au lieu d'un). Le rapport 0,2 s'applique à cette butte : **`h` ≈ 0,06 à 0,08**. Le modèle actuel (`h` 0,044, rapport 0,11) est à moitié trop plat.
- Feuilles : alternes, pétiolées, limbe de 6 à 20 cm de long sur 4 à 15 cm de large (jusqu'à 25 cm de long), contour général triangulaire à ovale. **Profondément pennatilobé** : trois lobes principaux (deux latéraux et un terminal) dont les sinus descendent presque jusqu'à la nervure, chaque lobe lui-même redécoupé en deux ou trois lobules arrondis. Sinus et lobules arrondis, pas pointus. Une feuille fait le dixième à le cinquième de l'étalement d'un coureur.
- Masse : le feuillage couvre le sol par plaques le long des coureurs, plus dense près du collet, et laisse la terre visible entre les coureurs en début de saison.
- Fleurs : jaunes, 2 à 3 cm, isolées à l'aisselle des feuilles, au ras du feuillage.

## Traits qui font reconnaître, dans l'ordre
1. Des feuilles **découpées jusqu'à la nervure en lobes pennés**, qui se lisent en dentelle ou en fougère couchée, jamais en disque ni en main.
2. Un port **rampant** : un tapis bas qui ne monte pas, des coureurs qui partent en étoile au ras du sol.
3. Une couleur **vert bleuté, glauque**, plus sombre et plus froide que celle du melon et de la courgette.

## Confusions et ce qui sépare
- **Melon** : même port rampant, mais feuilles rondes à réniformes, à peine lobées (lobes peu profonds, bord ondulé), vert franc. La pastèque a des feuilles découpées jusqu'à la nervure, vert bleuté. C'est la confusion la plus proche : la feuille seule les sépare.
- **Courgette** : buisson dressé en vase, sans coureurs, grandes feuilles palmées (en main, lobes partant d'un point) de 30 à 40 cm sur pétioles raides. La pastèque est couchée, ses lobes sont pennés le long d'une nervure, ses feuilles trois fois plus petites.
- **Concombre** : palissé sur un filet, ou rampant ; feuilles en cœur anguleux à trois à cinq pointes peu profondes, vert franc. La pastèque n'a ni filet ni pointes : ses lobes sont profonds et arrondis, et elle reste au sol.
- **Courge** : coureurs semblables mais feuilles très grandes (30 cm et plus), palmées et peu découpées. La pastèque a de petites feuilles en dentelle.

## Couleurs (quatre au plus)
- Sombre : `#3d6a58`, le limbe (vert bleuté glauque), qui fait le contraste avec la terre et les coureurs.
- Moyen : `#6f9a7f`, les jeunes feuilles au bout des coureurs, plus pâles et plus grises.
- Clair : `#b3c98a`, coureurs et pétioles, vert jaune pâle, qui dessinent l'étoile au sol.
- Accent : `#f2c230`, les fleurs (accent de `especes.json`).

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**. Famille « rampant », jusqu'à 60 plants un même jour.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où une butte fait une vingtaine de pixels avec l'exagération ×2,5. Moins de feuilles et plus grandes : c'est la découpe de chaque feuille qui doit se voir.
- La pastèque du socle se couche à 0,3 de l'emprise : pas de feuille épaisse à cet endroit sur tout le tour, ou du moins des trouées.

## Sources
- Missouri Plants, *Citrullus lanatus* : tiges rampantes, feuilles alternes pétiolées, profondément pennatilobées, lobes eux-mêmes lobés, vrilles ramifiées ([lien](https://missouriplants.com/Citrullus_lanatus_page.html)).
- Food Plants International (ECHO) : feuilles ovales à oblongues jusqu'à 25 cm, lobes profonds eux-mêmes lobés ; tiges jusqu'à 3 m ([lien](https://www.echocommunity.org/resources/d49e12a8-0a13-40b0-ab73-74e2b8e8c8d5)).
- NC State Extension : étalement 3 m ([lien](https://plants.ces.ncsu.edu/plants/citrullus-lanatus)) ; Growables : feuilles de 6 à 20 cm sur 4 à 15 cm ([lien](https://growables.org/informationVeg/Watermelon.htm)).
- Techno-science, « Pastèque » : tiges rampantes jusqu'à 3 m, feuille triangulaire très découpée à lobes et sinus arrondis ([lien](https://www.techno-science.net/fr/definitions/pasteque)).
- Truffaut, « Fabiola » F1 : H 50 cm, L 250 cm, port rampant ; feuilles de 15 à 30 cm, bleu-vert, profondément découpées ([lien](https://www.truffaut.com/pasteque-fabiola-f1-c-0-5l-327697.html)).

Recoupement : un limbe de 15 cm sur un coureur de 2,5 m fait 6 % de l'étalement réel ; ramené à la butte de 0,4, il ferait 2,4 cm, illisible. Le modèle grossit donc les feuilles (environ le quart de la butte) et en réduit le nombre, ce qui garde le rapport de forme de chaque feuille, pas son rapport à l'étalement.
