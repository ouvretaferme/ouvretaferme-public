# Poivron · fiche de référence

Plant adulte en pleine saison (juillet à septembre), au potager, en fleurs et en fruits verts. Les poivrons mûrs, rouges ou jaunes, sont posés par le socle (`FRUITS.poivron`, trois cloches à 0,27 de l'emprise, entre 0,3 et 0,4 de la hauteur) : le modèle doit laisser cette couronne basse à découvert.

## Proportions
- Hauteur : 50 à 70 cm pour un poivron doux de plein champ (fiches variétales Truffaut : H 50 à 60 cm ; flore descriptive : 0,5 à 1,5 m pour l'espèce, les grandes tailles étant des piments sous abri). Retenir **0,6 m**.
- Emprise : 40 à 50 cm. Les fiches de vente donnent L 30 cm, mesure du plant serré en pot ; au potager, planté à 40-50 cm, il touche son voisin en fin d'été. Retenir **0,45 m**, donc rapport largeur / hauteur ≈ **0,75** : le plant est plus haut que large, dressé, jamais un dôme aplati. Le modèle actuel (`d` 0,5, `h` 0,42, rapport 1,2) est à l'envers.
- Tige principale : un tronc court, nu et assez gros (jusqu'à 1 cm), de 15 à 25 cm, qui se divise en **fourche à deux ou trois branches** au niveau de la première fleur. Chaque branche refourche à son tour tous les 10 à 15 cm : la charpente est en Y emboîtés, en vase, ouverte à 30-45° de la verticale.
- Feuilles : simples, entières (bord lisse, sans lobe ni dent), ovales à lancéolées, pointe effilée. Limbe de 8 à 10 cm de long sur 4 à 5 cm de large (rapport ≈ 2), pétiole de 3 à 5 cm. Une feuille fait donc environ le sixième de la hauteur du plant. Alternes, regroupées par deux ou trois à chaque fourche, portées presque à l'horizontale et légèrement retombantes au bout.
- Masse : un plant adulte porte une centaine de feuilles, concentrées dans la moitié haute et aux extrémités des branches. Le bas de la charpente (sous 25 cm) est presque nu, c'est là que pendent les fruits.
- Fleurs : petites (1 à 1,5 cm), blanches, isolées à chaque fourche, penchées vers le bas. Invisibles en jeu : ne pas les modéliser (elles le faisaient lire en pomme de terre, `RENDU-lot3.md`).

## Traits qui font reconnaître, dans l'ordre
1. Un buisson dressé, plus haut que large, de grandes feuilles **entières, pointues et brillantes** vert sombre, portées en étages à l'horizontale.
2. Une charpente en fourches (Y) visible sous le feuillage : tronc court et gros, puis branches qui s'écartent, et du vide en dessous où pendent les cloches.
3. Les fruits gros et lobés, pendants sous les feuilles (posés par le socle) : c'est leur taille par rapport à la feuille qui sépare le poivron du piment.

## Confusions et ce qui sépare
- **Pomme de terre** (confusion des tours 1 et 3) : feuilles composées en folioles, mates, touffe basse et étalée plus large que haute, fleurs blanches au sommet. Le poivron a des feuilles entières, luisantes, un port dressé sur une charpente lisible, et rien de blanc.
- **Tomate** (deuxième hypothèse à chaque tour) : plus haute (1,5 m et plus), tuteur brun, feuilles composées retombantes, colonne étroite. Le poivron n'a pas de tuteur, s'arrête à 60 cm et a des feuilles pleines.
- **Aubergine** : feuilles plus grandes, larges, ondulées, gris vert et duveteuses, tige violacée. Le poivron est vert franc, brillant, aux feuilles plus étroites et pointues.
- **Piment** (tour 2) : même plante, mais feuilles plus petites et plus étroites, fruits fins. Différence tenue par le fruit et la feuille plus large.
- **Fraisier** (tour 4) : touffe basse au ras du sol, feuilles en trois folioles dentées sur pétioles rouges. Le poivron se dresse à 60 cm, ses fruits pendent à mi-hauteur et pas au sol.
- **Courgette** (tour 4, inversé) : grandes feuilles palmées en vase, fruits couchés au sol. Le poivron a des feuilles entières dix fois plus petites.

## Couleurs (quatre au plus)
- Sombre : `#1f6b2a`, le dessous et le cœur du feuillage (vert foncé franc, pas olive).
- Moyen : `#2f8f34`, la masse des feuilles. La couleur actuelle de `especes.json` (`#6c8a38`, olive terne) est celle de la famille et lit en pomme de terre ; celle du modèle (`#25922d`) est juste.
- Clair : `#6fc75a`, les faces supérieures du sommet qui prennent la lumière : c'est le brillant, le trait qui sépare de la pomme de terre et de l'aubergine.
- Charpente : `#5f8f3a`, tronc et branches, vert plus jaune que les feuilles (la tige est verte, pas brune).

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**. Famille « palissé », jusqu'à 48 plants un même jour.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait une trentaine de pixels de haut avec l'exagération ×2,5.
- Les cloches du socle sont à 0,27 de l'emprise et entre 0,3 et 0,4 de la hauteur : laisser cette zone libre de feuilles, sinon les fruits s'y noient (`RENDU-lot3.md`, tour 3).

## Sources
- Fiches variétales Truffaut : « Petit Marseillais » H 50 L 30 cm, « Sonar », « Lipari », « Lamuyo » H 60 L 30 cm, port érigé, feuilles vert foncé ovales terminées en pointe ([Sonar](https://www.truffaut.com/poivron-doux-sonar-f1-c-0-5l-64660.html), [Petit Marseillais](https://www.truffaut.com/poivron-jaune-petit-marseillais-barquette-6-plants-13950.html)).
- Techno-science, « Poivron » : port dressé, presque arbustif, très ramifié, feuilles alternes lancéolées pointues vert brillant, 40 à 50 cm ([lien](https://www.techno-science.net/fr/definitions/poivron)).
- Flore descriptive de *Capsicum annuum* (via farmknowledge.info et levypreserve.org) : 0,5 à 1,5 m, dressé, très ramifié, tige jusqu'à 1 cm de diamètre ; limbe ovale jusqu'à 10 cm sur 5 cm, pointe acuminée, bord entier, pétiole jusqu'à 10 cm ([farmknowledge](https://farmknowledge.info/issue/capsicum-annuum), [levypreserve](https://levypreserve.org/?p=5306)).
- Fourche au niveau de la première fleur (« crown flower »), à deux ou trois branches ([Pepper Joe](https://pepperjoe.com/blogs/grow-with-joe/pepper-plant-anatomy-a-growers-guide-to-how-it-all-works), [THP, 2 ou 3 branches](https://thehotpepper.com/threads/2-vs-3-way-branching.21708)).
- Recoupement : un limbe de 10 cm sur un plant de 60 cm, à raison d'un étage de feuilles tous les 10 à 15 cm de branche sur trois ou quatre niveaux de fourche, donne une couronne de 45 cm environ, ce qui tient avec l'emprise retenue.

## Trois partis pris

**a. La charpente en Y d'abord.** Un tiers du budget (≈ 100 triangles) va au tronc et à deux niveaux de fourches bien lisibles, qui s'ouvrent en vase ; les feuilles (≈ 20, grandes, à 10 triangles) ne se posent qu'au bout des branches, par grappes de trois. On sacrifie la densité : le plant reste ajouré, la charpente et les cloches se voient à travers.

**b. Les étages de feuilles horizontales.** Tout le budget va aux feuilles (≈ 35 à 40 lancéolées, pointe marquée, pliées sur la nervure), disposées en trois plateaux superposés qui rétrécissent vers le haut, avec le clair en face supérieure. La tige se réduit à un tronc court. Silhouette en pagode dressée, rapport 0,75, sous laquelle le bas reste vide.

**c. La colonne dense et brillante.** Un volume ovoïde haut, plein, fait d'une cinquantaine de petites feuilles serrées à 6 triangles, dont le tiers supérieur clair fait le reflet ; le bas du volume s'arrête net à 0,35 de la hauteur sur un tronc nu. On sacrifie la forme de chaque feuille pour la masse : le poivron se lit à sa hauteur, sa brillance et la jupe vide où pendent les fruits.

## Jugement à l'aveugle (version p)

Au lot 24, jugé au banc avec les cloches du socle, la version p est nommée poivron par les 3 juges, tous sûrs. L'ancienne version est nommée poivron par les 3 juges, aucun sûr : sa charpente en bâtons nus porte les fruits hors du feuillage, sous une couronne en ombrelle. La version p compte 298 triangles : un buisson feuillu jusqu'au sol, en vase, de 32 feuilles pliées sur la nervure, trois tiges laissant la place aux cloches. Défaut relevé par le juge exigeant : les cloches n'ont ni pédoncule ni calice, et le haut clair se lit encore un peu en plateau au-dessus de la masse.
