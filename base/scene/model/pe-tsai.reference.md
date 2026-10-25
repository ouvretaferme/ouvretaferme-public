# Chou pë-tsaï · fiche de référence

Chou chinois à pomme (Brassica rapa subsp. pekinensis, types Granaat, Michihli, Bilko), plant adulte au potager, pomme formée, peu avant la récolte.

## Proportions
- Pomme : environ 40 cm de haut pour 16 cm de diamètre, soit 2,5 fois plus haute que large. Forme de tonneau : la plus grande largeur vers le milieu, le pied à peine resserré, le haut arrondi et fermé, les feuilles du cœur rabattues les unes sur les autres. C'est un volume plein, d'un seul tenant, pas une touffe de feuilles séparées.
- Côtes : les grosses nervures des feuilles de la pomme montent du pied en larges bandes verticales blanches, larges en bas (3 à 5 cm), qui s'amincissent et se perdent dans le limbe vers les deux tiers de la hauteur. Six à huit se voient sur le tour.
- Feuilles extérieures : trois à cinq, plus larges que hautes une fois ouvertes (25 à 30 cm de long, largeur ≈ 0,6 de la longueur), qui partent du pied et s'écartent en bas, à moitié ouvertes autour de la pomme. Leur bout monte au quart ou au tiers de la pomme, à 15 cm de l'axe. Emprise du plant, feuilles comprises : 30 cm.
- Hauteur rendue dans la scène : le module garde l'emprise de 0,3 m et une pomme de 0,48 m, soit h ≈ 0,49 m (l'ancien modèle rendait 0,591 m). Les pommes des variétés hautes (Michihli) dépassent 45 cm : la hauteur reste vraisemblable.
- Ces valeurs tiennent ensemble : une pomme de 19 cm de large au milieu sur 30 cm d'emprise laisse aux feuilles extérieures 5 à 6 cm de débord de chaque côté, ce qui fait une collerette basse et non un bouquet.

## Traits qui font reconnaitre, dans l'ordre
1. Une pomme en tonneau, haute et serrée, 2,5 fois plus haute que large, bombée au milieu, arrondie et fermée en haut. Un volume plein.
2. Le haut de la pomme vert franc, frisé ou cloqué ; le bas blanc, avec les grosses côtes blanches en larges bandes verticales.
3. Trois à cinq feuilles extérieures vert franc, plus larges, qui s'écartent en bas, à moitié ouvertes autour du pied.

## Confusions et ce qui sépare
- **Laitue romaine** : feuilles dressées ouvertes, finies en pointe au sommet, pas de tonneau fermé. Le pë-tsaï a un sommet rond et fermé, un volume plein.
- **Céleri branche** : tiges séparées qui montent en faisceau, feuillage découpé au bout. Le pë-tsaï est d'un seul tenant, sans jour entre les côtes.
- **Pak-choï** (module `pak-choi.js`) : côtes blanches en cuillère, rosette ouverte et basse, plus large que haute. Le pë-tsaï est fermé et deux fois et demie plus haut que large.
- **Chou pointu** (module `chou-pointu.js`) : cône fini en pointe, vert-bleu lisse, sans côte blanche. Le pë-tsaï a un sommet rond et frisé, et un bas blanc strié.

## Couleurs (quatre au plus)
- Clair : `#f6f6ee`, les côtes et le bas de la pomme (blanc neutre : un crème se lit jaune).
- Moyen clair : `#e3eecb` (vert blanchi) entre les côtes, puis `#9cc85a` (vert franc) pour le dôme frisé du haut.
- Sombre : `#5e9a3c`, les feuilles extérieures (vert franc). Le plus fort contraste est entre elles et le bas blanc de la pomme, sur le trait qui fait reconnaitre.

## Sources
- Wikipedia, [Chou chinois](https://fr.wikipedia.org/wiki/Chou_chinois) et [Napa cabbage](https://en.wikipedia.org/wiki/Napa_cabbage) : pomme cylindrique, haut vert pâle, nervures blanches larges.
- Proportions de la pomme données pour ce chantier (40 cm sur 16, tonneau, sommet fermé), recoupées sur les types Michihli (pomme de 40 à 45 cm sur 10 à 15 cm) et Granaat (plus trapu).
- Retour du 2026-10-10 sur l'ancien modèle : « les chou chinois ressemblent pas à des choux chinois » ; ses rangs montraient des touffes étroites et hautes de feuilles dressées en pointe, lues comme du céleri ou de la romaine.

## Contraintes
- Contrat `build(THREE, helpers)` rendant `{ d, h, parts }`, voir `base/scene/CLAUDE.md`. Plafond **300 triangles**, **4 couleurs**, pied à y = 0.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait une dizaine de pixels de haut. Le tonneau clair à pied blanc, posé dans une collerette verte, doit s'y lire.
