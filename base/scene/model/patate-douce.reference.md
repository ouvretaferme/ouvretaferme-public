# Patate douce · fiche de référence

Plant adulte en plein été, au potager, lianes étalées sur la planche, avant l'arrachage. Les tubercules sont sous terre : le plant ne les dessine pas, le socle en pose deux à demi sortis au bord (`FRUITS` dans `socle.js`).

## Proportions
- Port rampant : une plante couchée, pas une touffe. Depuis un pied unique partent plusieurs lianes (5 à 8 au potager) qui courent au sol dans toutes les directions. Lianes de 1,5 à 2,5 m, jusqu'à 3 m (classes « étalé » 151 à 250 cm et « très étalé » au-delà, descripteurs CIP ; 2,5 à 3 m selon Techno-science).
- Hauteur du feuillage : 25 à 30 cm au plus haut, au-dessus du pied ; le long des lianes, 10 à 20 cm. Rapport hauteur sur emprise de 0,1 à 0,2 : presque à plat (NC State Extension, 30 cm de haut pour 0,9 à 1,5 m de large).
- Liane fine, 4 à 8 mm, entre-nœuds de 6 à 9 cm (classe « intermédiaire » CIP) : une feuille tous les 6 à 9 cm, alternes, de part et d'autre de la liane.
- Feuille : limbe de 8 à 15 cm (classe « moyen » CIP), 5 à 7 cm sur certaines variétés de jardin ('Bonita', Truffaut). Contour en cœur (deux lobes à la base, sinus au pétiole) ou lobé en main, 3 à 5 lobes (nombre impair, CIP). Nervation palmée.
- Pétiole aussi long que le limbe ou un peu plus, dressé depuis la liane couchée : le limbe se présente presque à plat, tourné vers le ciel, un peu au-dessus de la liane.
- Recoupement : 6 lianes de 1,5 m portent chacune une vingtaine de feuilles de 10 cm, soit un tapis lâche de 1,5 à 2 m de diamètre et 0,2 à 0,3 m de haut, ce qui tient avec les hauteurs et emprises citées. Dans le jeu, `d` reste l'emprise d'une butte (0,4, trois plants par planche de 4 m, `RENDU-lot3.md`) : la liane se raccourcit à l'emprise et les feuilles s'éclaircissent, une sur cinq environ, en gardant leur taille relative à peine grossie.

## Traits qui font reconnaître, dans l'ordre
1. Des lianes fines couchées en étoile irrégulière depuis un pied, plus large que haute, presque à plat sur la terre.
2. Des feuilles petites, en cœur ou lobées en main, serrées le long des lianes sur de courts pétioles dressés.
3. Des jeunes feuilles vert bronze au bout des lianes, plus claires que le reste du feuillage. Le plant est celui d'une variété à chair orange ('Beauregard', 'Covington'), au feuillage vert, et non d'une variété pourpre (décidé le 2026-10-11).

## Confusions et ce qui sépare
- **Courge, melon** : rampants aussi, mais grandes feuilles rondes ou palmées vert franc, peu nombreuses, sur de gros coureurs verts, avec fleurs jaunes. La patate douce a des feuilles plus petites, serrées, en cœur ou lobées.
- **Couvert végétal** : tapis continu et uniforme sans pied ni direction. La patate douce part d'un pied, ses lianes laissent voir la terre entre elles.
- **Fraisier** : touffe basse ronde de folioles par trois, stolons rouges. La patate douce n'a pas de touffe, ses lianes portent des feuilles tout du long.
- **Mâche, laitue jeune** : rosette pleine et ronde. La patate douce est une étoile de lignes, pas un disque.

## Couleurs (quatre au plus)
- Sombre : `#36602a`, le limbe vert sombre, plus sombre que la courge et l'herbe.
- Moyen : `#4f7330`, lianes et pétioles, entre les deux verts des feuilles.
- Bronze : `#a39a3e`, les jeunes feuilles au bout des lianes.
- Clair : `#7f9c3f`, quelques feuilles jeunes vert bronze, qui donnent la troisième valeur.

## Sources
- Descripteurs CIP de la patate douce (longueur des lianes, entre-nœuds, taille et contour des feuilles, lobes) : https://cropgenebank.sgrp.cgiar.org/images/file/learning_space/key_descriptors_sweetpotato.pdf
- NC State Extension, *Ipomoea batatas* (30 cm de haut, 0,9 à 1,5 m de large) : https://plants.ces.ncsu.edu/plants/all/ipomoea-batatas/
- Techno-science, Patate douce (tiges rampantes de 2,5 à 3 m, limbe entier, denté ou lobé, nervation palmée) : https://www.techno-science.net/fr/definitions/patate-douce
- Chambre d'agriculture de Martinique, guide variétal (feuilles sur long pétiole) : https://martinique.chambre-agriculture.fr/fileadmin/user_upload/National/FAL_commun/publications/Martinique/18-GUIDE_VARIETAL_PATATE_DOUCE_V06-2018.pdf
- Truffaut, 'Bonita' (port rampant, feuilles cordiformes de 5 à 7 cm) : https://www.truffaut.com/patate-douce-bonita-pot-diametre-10-5-cm-546962.html

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**.
- Ce qui doit marcher : le rang en jeu à 20 px/m, avec les deux tubercules du socle, sur une planche plate entourée d'herbe verte.
