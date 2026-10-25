# Sarrasin · fiche de référence

*Fagopyrum esculentum*, polygonacées. Plante de champ annuelle, semée en nappe, en pleine floraison (elle fleurit pendant plus de la moitié de son cycle, du 30e jour jusqu'à la récolte). C'est l'état qui la fait reconnaître : une nappe verte, feuillue, sous un voile de petites fleurs blanc rosé.

## Proportions
- Hauteur : 30 à 50 cm (HLS), jusqu'à 70 cm (Pharmacopée française), jusqu'à 1 m en bonne terre (Le Potager de mes rêves), 60 à 80 cm (Vilmorin). Retenu : **45 cm**, le bas de la fourchette de culture, pour que le feuillage pèse plus que la tige.
- Emprise de la touffe : **30 cm** (consigne). Rapport hauteur sur emprise ≈ 1,5 (1,3 mesuré sur le modèle, bouquets et feuilles débordant de l'emprise) : bien plus trapu que le lin (3,2) et que la moutarde (3), plus haut que le couvert générique (1).
- Tige : dressée, creuse, **souvent rougeâtre**, à rameaux étalés. Une tige principale et trois à cinq rameaux qui partent du tiers bas et de la mi-hauteur, écartés de 30 à 35° de la verticale. La tige est fine : elle se devine entre les feuilles, elle ne porte pas la couleur.
- Feuilles : alternes, à limbe en cœur, profondément échancré à la base et rétréci en pointe aiguë, triangulaire sur les feuilles du haut. Celles du bas ont un pétiole au moins aussi long que le limbe, celles du haut sont sessiles. Longueur du limbe de 5 à 8 cm (ordre de grandeur admis, non recoupé par les sources ci-dessous). Une dizaine de feuilles par pied, étagées du pied jusque sous les fleurs : les grandes en cœur en bas, les fers de flèche en haut.
- Fleurs : 4 mm chacune, blanches ou rosées, en grappes courtes et serrées, longuement pédonculées, réunies en petits corymbes aplatis de 3 à 6 cm, plusieurs par rameau, au sommet et aux aisselles du haut. Aucun n'est une boule : de loin ils font une écume claire au-dessus du feuillage. Les boutons et les fleurs qui passent sont roses.
- Échelle d'écran : à 20 px/m et ×2,5, la touffe fait 15 px de large sur 30 de haut, un bouquet réel de 5 cm 2,5 px. Le modèle porte chaque bouquet à deux ailes minces de 4 à 6 cm, la grande feuille à 13 cm et la tige à 1,4 cm de large.

Recoupement : des rameaux de 15 à 24 cm écartés de 32 à 35° arrivent à 8 à 13 cm de l'axe, dans l'emprise de 30 cm, et leurs bouts montent de 31 à 39 cm sous une tête à 42 cm. Douze bouquets couvrent environ un tiers du disque vu de dessus : le vert reste visible dessous, c'est un voile et non un couvercle.

## Traits qui font reconnaître, dans l'ordre
1. Une **écume de petits bouquets plats, blanc rosé**, nombreux et légers, au-dessus du feuillage : un voile clair sur la planche, jamais des têtes.
2. De **grandes feuilles en cœur pointu**, larges, étagées sur toute la tige, qui font une nappe verte.
3. Une **tige fine, brun rouge**, ramifiée, qui se devine entre les feuilles.

## Confusions et ce qui sépare
- **Coton** : la version t3 a été lue « coton » par trois juges sur trois, à cause de ses cinq dômes blancs pleins au bout de rameaux rouges presque nus. Le sarrasin n'a aucune boule : ses fleurs sont des plaques minces, petites, plus de dix par pied, et le vert l'emporte sur le blanc.
- **Lin** (`lin.n.js`) : faisceau de tiges vertes droites, sans rameau, fleurs bleues plates et boules dorées. Le sarrasin fourche, ses feuilles sont larges, ses fleurs sont blanc rosé et en bouquets plats.
- **Moutarde** (`moutarde.n.js`) : croix jaune citron en grappe le long d'un axe, siliques horizontales, feuillage lyré au pied. Le sarrasin porte un voile blanc à plat et des feuilles en cœur sur toute la hauteur.
- **Coriandre** (`coriandre.n.js`) : botte basse de limbes festonnés vert tendre, sans tige ni fleur. Le sarrasin est plus haut, ramifié, et fleuri de blanc.
- **Luzerne** (`luzerne.n.js`) : trèfles larges qui coiffent chaque tige, grappes violettes cachées dans la masse. Le sarrasin finit chaque rameau par une fleur, jamais par une feuille, et ses fleurs sont blanches, au-dessus de la masse.
- **Couvert** (`couvert.n.js`, forme générique qu'il remplace) : nappe verte ébouriffée piquée de points blanc rosé. Le sarrasin a de grandes feuilles en cœur et ses bouquets flottent au-dessus.
- **Achillée, sureau** : plateau blanc aussi, mais sur un feuillage plumeux ou un arbuste. Ici la feuille est entière, en cœur, et les bouquets sont épars.

## Couleurs (quatre au plus)
- Clair : `#fbf1ee`, les fleurettes, blanc rosé : le plus fort contraste de la touffe, sur le premier trait.
- Accent : `#f0b4c0`, une fleurette sur cinq, rose pâle, pour les boutons et les fleurs qui passent.
- Moyen : `#86bd52`, les feuilles, vert clair, peu nombreuses (reprise à 120 triangles : le vert sombre et touffu faisait lire une pomme de terre en fleur).
- Sombre : `#c63f3c`, les tiges et les rameaux, rouge franc, à découvert sur toute la hauteur.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles** (relevé de 60 à la reprise), **4 couleurs**.
- Culture en nappe : le socle pose les touffes à 0,7 × d, soit tous les 21 cm. La nappe se lit par la répétition et le désordre que pose le socle.

## Sources
- [Pharmacopée française, Fagopyrum esculentum, ANSM](https://ansm.sante.fr/uploads/2020/10/22/fagopyrum-esculentum-sarrasin-pph.pdf) : annuelle jusqu'à 70 cm, tige dressée creuse souvent rougeâtre à rameaux étalés, limbe cordiforme échancré à pointe aiguë, feuilles du haut sessiles, grappes courtes et serrées longuement pédonculées, fleur de 4 mm blanche ou rosée.
- [Dictionnaire historique de la Suisse, sarrasin](https://hls-dhs-dss.ch/fr/articles/027666) : 30 à 50 cm.
- [Le Potager de mes rêves](https://www.lepotager-demesreves.fr/index.php?idu=14243) : herbacée dressée jusqu'à 1 m.
- [PlantUse, Fagopyrum esculentum](https://uses.plantnet-project.org/fr/Fagopyrum_esculentum).
- Hauteurs de culture déjà relevées dans `couvert.reference.md` (Jardiner Malin, Vilmorin).

## Jugement à l aveugle (lot 62, version u3, plafond relevé à 120)

120 triangles, 4 couleurs. Deux juges sur trois nomment le sarrasin (un « hésitant », un « sûr »), le troisième lit une amarante
et donne le sarrasin en seconde hypothèse. Ce qui a tranché : les tiges rouges fines sous des bouquets blanc rosé. Ce qui manque
selon eux : la feuille en cœur, qui ne se lit qu'en gros plan. Versions précédentes à 60 triangles : lue coton (t3), puis pomme de
terre en fleur (r3). Les paragraphes « Proportions » et « Traits » ci-dessus datent du parti pris précédent (nappe verte à grandes
feuilles) : le modèle posé montre les tiges à découvert.
