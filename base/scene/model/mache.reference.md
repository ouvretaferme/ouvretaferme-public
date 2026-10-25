# Mâche · fiche de référence

Rosette formée, au potager, de l'automne à la récolte en hiver. La montée à graines du printemps, qui dresse des tiges fourchues à petites fleurs bleu pâle, n'est pas l'état du potager.

## Proportions
- Plant : une seule rosette de 8 à 15 cm d'emprise, 10 à 12 cm le plus souvent ; 4 à 8 cm de haut, les feuilles presque couchées (rapport hauteur sur emprise ≈ 0,3 à 0,4). C'est la plus petite salade du potager : trois fois moins large que l'épinard (25 à 30 cm), deux à trois fois moins que la laitue.
- Feuilles : 10 à 14 par rosette, opposées par paires, chaque paire croisée à angle droit sur la précédente, ce qui donne une étoile régulière vue de dessus. Longueur 4 à 7 cm, pétiole compris.
- Limbe : en cuillère (spatulé) : étroit au pied, le plus large aux trois quarts, bout franchement arrondi, sans pointe ni oreillette. Bord entier, surface lisse et un peu luisante, légèrement creusé (bords relevés).
- Port : les feuilles du tour sont plaquées au sol, à 10-20° ; celles du cœur, plus courtes, se relèvent à 30-45°.
- Recoupement : une feuille de 6 cm qui monte de 15° porte à 5,8 cm de l'axe, d'où une emprise de 11,5 cm ; les feuilles du cœur, 4 cm à 40°, montent à 2,6 cm, plus le creux de la cuillère : 3 à 4 cm de haut. Hauteur et emprise tiennent ensemble avec le bas de la fourchette des sources, qui comptent les plants plus âgés et relevés.

## Traits qui font reconnaître, dans l'ordre
1. Une toute petite rosette plate, plaquée au sol, nettement plus petite que toute autre salade.
2. Des feuilles en cuillère arrondie, serrées en étoile par paires croisées.
3. Un vert moyen, franc et lisse, plus clair que l'épinard, avec un cœur plus clair.

## Confusions et ce qui sépare
- **Épinard** : rosette trois fois plus large, vert très sombre, feuilles en fer de lance pointues. La mâche est minuscule, vert moyen, ses feuilles sont rondes au bout.
- **Pourpier** : tiges charnues rougeâtres qui rampent et se ramifient, petites feuilles épaisses en coin. La mâche n'a pas de tige, toutes ses feuilles partent du collet.
- **Jeune laitue** : vert tendre et clair, bord ondulé, feuilles qui se recouvrent vers un cœur qui pomme. La mâche a des feuilles séparées en étoile, à bord lisse et entier, sans cœur pommé.

## Couleurs (quatre au plus)
- Sombre : `#2c6a2c`, le pied des feuilles, au collet : il dessine l'étoile et détache les cuillères les unes des autres.
- Moyen : `#5fa844`, le limbe des feuilles du tour.
- Clair : `#9bd066`, les feuilles du cœur.

## Sources
- RHS, [Valerianella locusta](https://www.rhs.org.uk/plants/67250/wd/details) : rosette de feuilles tendres en cuillère ; et [Cornsalad 'Medaillon'](https://www.rhs.org.uk/plants/119538/valerianella-locusta-medaillon/details) : hauteur jusqu'à 10 cm, emprise 0 à 10 cm.
- Runåbergs fröer, [Corn salad](https://en.runabergsfroer.se/?p=212) : 5 à 10 cm de haut, plants espacés de 10 à 15 cm.
- Specialty Produce, [Mâche](https://specialtyproduce.com/produce/Mache_518.php) : rosettes en grappe, feuilles de 8 à 10 cm, en cuillère à largement ovales selon la variété.
- Vilmorin, [Mâche Verte de Louviers](https://www.bricomarche.com/p/mache-verte-de-louviers/3211500007865) : feuilles en cuillère de 3 à 4 cm.
- Truffaut, [Mâche Verte de Cambrai](https://www.truffaut.com/mache-verte-de-cambrai-ca-3-gr-livraison-gratuite.html) : petites feuilles rondes formant des rosettes.
- Burren Flora, [Valerianella locusta](https://flora-burren.linnaeus.naturalis.nl/linnaeus_ng/app/views/species/taxon.php?id=38626) : feuilles opposées, entières, en cuillère à la base.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles** (`especes.json`), sans exception, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m).

## Jugement à l'aveugle (version p)

Au lot 26, la version p est nommée mâche par 1 juge sur 3, en hésitant ; les deux autres la lisent épinard. L'ancienne version n'est nommée mâche par aucun juge (épinard, blette, fraisier) : la p la bat. La version p compte 120 triangles : une rosette plate de 12 cuillères vert sombre en étoile. Défaut relevé : sur le rang, les plants sont minuscules et ne se lisent pas, et au gros plan la rosette rappelle un trèfle.

## Allègement (version q)

La mâche est semée dense : plus de 1 000 plants dans la pire semaine de la scène, et le budget global est presque plein. La version q (`mache.q.js`) reprend la version p à **60 triangles** au lieu de 120, mêmes 4 couleurs (`#22592a` tour, `#3a843c` cœur, `#347238` reflet, `#163d1a` pétiole).

- **10 feuilles au lieu de 12** : les huit du tour, couchées par paires croisées, restent toutes ; le cœur passe de deux paires à une. Le cœur ne se lit pas à 20 px/m, l'étoile du tour si.
- **Limbe à 5 pans au lieu de 9**, toujours en éventail autour d'un centre creusé : pétiole au tiers, plus grande largeur aux deux tiers, bout en pan plat à mi-largeur. Pas de sommet à la pointe : avec lui, la feuille prenait une pointe d'épinard.
- **Pétiole gardé à part**, un triangle noir-vert qui partage le sommet du collet avec le limbe. C'est lui qui détache les cuillères vues de dessus.
- **Reflet** : un pan par feuille du tour, d'un côté de la nervure qui alterne.

Tours (captures `mache-q-t1` à `t5`, comparées à `mache-p-t4-planche.png`) :
1. 8 feuilles à 7 triangles (56) : la lecture tient, mais le cœur clair est aussi grand que le tour.
2. Cœur raccourci et relevé : il ne reste qu'une croix de quatre grandes feuilles, lue comme un trèfle. Écarté.
3. 10 feuilles à 6 triangles (60), disposition de la p : silhouette et rang de nouveau ceux de la p, mais les bouts sont carrés.
4. Bout redessiné, plus grande largeur reculée aux deux tiers : le bout se lit rond.
5. Limbes élargis de 10 % : la cuillère se lit, pas un coin. Version retenue.

Ce que la q perd face à la p : au gros plan, le bout de la cuillère est un polygone à trois pans au lieu d'une courbe, et le cœur clair n'a plus que deux feuilles. La silhouette à 64 px et le rang à 20 px/m sont les mêmes que ceux de la p. Une version à 90 triangles n'a pas été nécessaire.

## Jugement à l'aveugle (version q, 60 triangles)
Lot 29, trois juges. La q est lue mâche par un juge sur trois (hésitant), épinard par les deux autres. L'actuelle (p, 120 triangles) obtient exactement le même score dans le même lot, avec les mêmes lectures. La q tient donc le score de la p pour moitié moins de triangles et la remplace. Défaut nommé par les juges : feuilles en losange, alors que la mâche a des feuilles rondes en cuillère.
