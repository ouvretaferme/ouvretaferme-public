# Fenouil bulbeux (de Florence) · fiche de référence

Plant adulte au potager, dans les trois ou quatre semaines qui précèdent la récolte : le bulbe est formé et le feuillage a atteint sa taille. Pas de hampe florale, elle signale un plant monté qu'on ne récolte plus.

## Proportions
- **Bulbe** : ce n'est pas une racine, ce sont les bases des pétioles, renflées et emboîtées. Il est posé **sur** le sol, entièrement hors de terre : seul le pivot est enterré. On le récolte à 8-12 cm de haut et 9-12 cm de large (GRAB), « rond et plat ». Les variétés basses font 8-10 cm de haut pour 9-11 cm de large. Vu de face, il est un peu plus large que haut (largeur / hauteur ≈ 1,1). Vu de côté, il est **aplati**, plus épais au pied qu'au sommet (épaisseur ≈ 0,7 fois la largeur). Ses écailles se lisent en couches, comme des gaines enroulées les unes dans les autres, et se resserrent vers le haut en un col.
- **Feuillage** : 60 à 90 cm de haut (Gardenia, UC Master Gardeners entre 30 et 90 cm). Le GRAB mesure 0,8 à 1 m en plein champ en Provence. On retient **70 cm**, bulbe compris, et une emprise de **40 à 50 cm** : la fourchette de 30 à 60 cm des catalogues, bornée par l'écartement de 25 à 30 cm entre lignes que le GRAB pratique. L'emprise vaut donc **0,6 à 0,7 fois la hauteur**, et le bulbe un **sixième de la hauteur** environ.
- **Feuilles** : 8 à 12 visibles. Le nombre n'est donné par aucune source : c'est une estimation sur photos, à ne pas tenir pour une mesure. Chacune part du sommet du bulbe en **pétiole nu, dressé et lisse**, sur le premier tiers de sa longueur, vert pâle au pied et plus vert vers le haut, plus fin qu'une côte de céleri.
- **Port** : touffe dressée en gerbe. Les pétioles du centre sont presque verticaux, ceux du bord s'ouvrent à 20-35° de la verticale. Rien ne retombe sous la mi-hauteur : la silhouette est un **vase**, étroit au col et large en haut.
- **Limbe** : trois ou quatre fois divisé en lanières filiformes (franco.wiki, d'après la flore), comme l'aneth. À distance, il se lit comme une masse **vaporeuse et ajourée** sur les deux tiers hauts du plant, sans aucune surface pleine. La masse est plus dense au centre et s'effiloche sur le pourtour.

Recoupement : un bulbe de 10 cm sur un plant de 70 cm donne le rapport 1/7, et l'exagération de l'organe signature (×1,5, levier retenu au tour 2 de l'aveugle) le porte à 1/5. Une emprise de 45 cm pour 70 cm de haut est cohérente avec une ouverture de 30° au bord : un pétiole de 45 cm incliné à 30° s'écarte de 22 cm, soit une demi-emprise.

## Traits qui font reconnaître, dans l'ordre
1. Un **bulbe blanc nacré, aplati et en couches**, posé sur le sol, nettement plus clair que tout le reste, avec des pétioles qui en sortent par le haut.
2. Au-dessus, un **plumet vaporeux vert tendre** : des fils fins, ajourés, qu'on voit à travers. Ni feuille large, ni lame plate.
3. Entre les deux, un **col de pétioles nus et serrés** : le bulbe et le plumet sont séparés par une tige visible, le feuillage ne part pas du sol.

## Confusions et ce qui sépare
- **Céleri-rave** (confusion répétée aux tours 2 et 4) : boule brun beige terne, à moitié enterrée, sous des feuilles larges, découpées et vert sombre. Le fenouil a un bulbe blanc franc posé sur le sol et un feuillage filiforme vert tendre, clair, sans limbe plein.
- **Chou-rave** (tour 3) : boule lisse sur une courte tige, d'où partent **sur les flancs** des feuilles larges bleu-vert à long pétiole. Les pétioles du fenouil partent tous du **sommet** du bulbe, et ses feuilles sont des fils.
- **Panais** (lu « fenouil » au tour 1, et inversement au tour 4) : collet crème au ras du sol, sans volume au-dessus. Ses feuilles sont pennées, à folioles larges et dentées, et forment une rosette basse. Le fenouil a un volume blanc net au-dessus du sol et un feuillage haut et fin.
- **Navet** : racine blanche ronde à collet violet, fanes basses en rosette, feuilles larges. Le fenouil n'a pas de violet, il porte un plumet haut et son bulbe est aplati.
- **Céleri branche** : botte de côtes larges et serrées jusqu'en haut, coiffée de folioles plates. Le fenouil a des côtes courtes, réunies en bulbe, et un feuillage de fils qui occupe les deux tiers du plant.
- **Aneth** : même plumet, mais sur une tige unique et haute, sans bulbe. Le bulbe sépare les deux.
- **Ail, oignon, poireau** : bulbe ou fût blanc aussi, mais leurs feuilles sont des lames ou des tubes pleins, sans ramification. Le plumet du fenouil est ramifié et ajouré.

## Couleurs (quatre au plus)
- Clair : `#eef2dc` (accent d'`especes.json`), le bulbe, blanc nacré à peine verdi. Un second ton plus vert, `#d5e2b0`, peut marquer le sommet des gaines et le col.
- Moyen : `#a7cf6b`, les pétioles nus.
- Plumet : `#8fcf4f`, vert tendre lumineux, le feuillage filiforme. Il reste nettement plus clair et plus jaune que le vert sombre du céleri-rave (`#2f6b2a`).
- Sombre : il n'y en a pas sur le plant, le contraste le plus fort est entre le bulbe clair et la terre. `leaf` d'`especes.json` (`#72b256`) peut ombrer le cœur du plumet si un troisième vert est nécessaire.

## Ce qu'on a déjà essayé (`fenouil.js`, lot 4)
Bulbe fait de deux icosphères, 5 tiges, 50 brins d'un triangle chacun, 120 triangles sur 120. Le rendu dit que « les plumets gardent un peu l'air d'épis de palmier » : les brins rayonnent d'un point au bout de la tige. Il dit aussi que « le bulbe taillé peut évoquer le céleri-rave de loin ». Les juges l'ont nommé fenouil, hésitants, aux tours 1, 2 et 3, puis céleri-rave au tour 4. Le défaut est ce qui ressemble aux autres : un bulbe rond et un feuillage en étoile au bout de tiges. Ni l'aplatissement du bulbe ni le vase ajouré ne se lisent encore.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, **4 couleurs**, famille de repli `upright`.
- Le socle ramène emprise et hauteur à 1 puis applique d, h et l'exagération ×2,5 de la scène. Avec 0,45 × 0,70 m réels, on vise `d ≈ 0,45` et un modèle 1,5 fois plus haut que large.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait une trentaine de pixels. Le bulbe y fait 6 à 8 px : il faut l'exagérer pour qu'il se lise.

## Sources
- RHS, *Florence fennel, grow your own* : bulbe blanc au niveau du sol, bases de pétioles renflées et emboîtées. https://www.rhs.org.uk/vegetables/florence-fennel/grow-your-own
- RHS, *Foeniculum vulgare var. azoricum 'Rondo'* : hauteur de 1 à 1,5 m en fin de vie, emprise de 0,5 à 1 m. Ces chiffres concernent un plant monté, on ne les retient pas pour le plant au potager. https://www.rhs.org.uk/plants/246768/foeniculum-vulgare-var-azoricum-rondo/details
- GRAB, *Dossier fenouil plein champ printemps-été en AB*, 2025 : bulbe idéal de 8-12 cm de haut et 9-12 cm de large, « bulbes bien ronds », « ronds et plats », lignes à 25-30 cm. https://www.grab.fr/wp-content/uploads/2025/01/dossier-fenouil-plein-champ-printemps-ete-en-AB-Refbio-PACA-janvier-fevrier-2025.pdf
- GRAB, *Essai variétal fenouil de printemps*, 2018 : végétation de 0,8 à 1 m. Bulbes bas (8-10 cm de haut pour 9-11 cm de large) ou hauts (9-13 cm pour 8-10 cm). https://www.grab.fr/wp-content/uploads/2025/01/CRT2018_varietesFENOUIL-PleinChamp-Printemps.pdf
- Gardenia, *Foeniculum vulgare var. azoricum* : 60-90 cm de tiges aériennes. https://www.gardenia.net/plant/foeniculum-vulgare-var-azoricum-fennel-bulb
- UC Master Gardeners, Santa Clara : variétés Florence de 30 à 90 cm, plants espacés de 20 à 30 cm. https://mgsantaclara.ucanr.edu/garden-help/vegetables/fennel/
- University of Wisconsin Extension, *Fennel* : feuilles finement disséquées à lobes très étroits, d'aspect plumeux comme l'aneth. https://hort.extension.wisc.edu/articles/fennel-foeniculum-vulgare/
- Fenouil commun (franco.wiki) : feuilles basales trois ou quatre fois pennatiséquées, à lanières filiformes, et gaine des feuilles supérieures plus longue que le limbe. https://franco.wiki/fr/Fenouil_commun.html

## Trois partis pris
- **a. Le bulbe d'abord.** Environ 50 triangles vont au bulbe : trois ou quatre gaines emboîtées, aplaties, qui se resserrent en col. Il est exagéré ×1,6 et ses couches restent lisibles. Pétioles et plumet sont réduits à une gerbe de 6 éventails plats de 8-10 triangles, sans tige distincte. On sacrifie le col : le plumet sort presque directement du bulbe.
- **b. Le vase ajouré.** Environ 80 triangles vont au feuillage : 9 à 10 pétioles nus de longueurs et d'ouvertures inégales, et le long de chacun des fils posés en arêtes de poisson sur les deux tiers hauts, pas en étoile au bout. Le bulbe est un seul volume aplati de 20 à 25 triangles, en blanc franc. On sacrifie les couches du bulbe : c'est la silhouette en vase, étroite en bas et effilochée en haut, qui porte l'espèce.
- **c. Le contraste en trois étages.** Le budget est réparti par bandes horizontales nettes : un bulbe blanc large et bas (35 triangles), un col étroit de pétioles serrés et parallèles (20 triangles), puis une couronne de plumet nettement plus large que le bulbe (65 triangles), en nuage de fils dont la densité baisse vers le bord. On sacrifie le détail de chaque feuille : l'œil doit lire de loin « blanc, tige, nuage vert clair ».
