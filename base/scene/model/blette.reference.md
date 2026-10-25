# Blette · fiche de référence

Blette (poirée à côtes, *Beta vulgaris* var. *cicla*), plant adulte au potager, en pleine récolte des feuilles extérieures, de l'été à l'automne. La hampe florale est de deuxième année : elle n'est pas celle du potager.

## Proportions
- Plant entier : 40 à 60 cm de haut, 45 à 50 cm d'emprise. Rapport emprise sur hauteur ≈ 0,75 à 0,9 : une touffe haute et dressée, un peu plus haute que large, étroite au pied, évasée en haut.
- 8 à 12 grandes feuilles visibles, toutes basales, en touffe, sans tige ni pomme. Le cœur est dressé, presque vertical ; les feuilles extérieures s'ouvrent à 25-45° de la verticale et leur limbe retombe un peu au bout.
- Côte (pétiole et nervure médiane, la « carde ») : charnue, plate dessus, bombée dessous, 2 à 4 cm de large pour les variétés colorées, 4 à 6 cm pour les blanches (GRAB). Le pétiole nu égale la moitié aux deux tiers du limbe (FNA), soit 15 à 20 cm ; la côte file ensuite dans le limbe jusqu'au milieu au moins, aussi large et colorée.
- Limbe : ovale à oblong, base en cœur, 20 à 30 cm sur 15 à 20 cm en culture (Flora of New Zealand donne 20 × 15 cm comme valeur courante), entier, bord ondulé, luisant, cloqué entre les nervures, vert sombre.
- Ces valeurs tiennent ensemble : un pétiole de 18 cm ouvert à 25° monte de 16 cm et s'écarte de 8 cm ; un limbe de 25 cm ouvert à 40° en moyenne monte encore de 19 cm et s'écarte de 16 cm. Le plant fait ainsi 16 + 19 + 3 (collet) ≈ 38 à 45 cm de haut, et 2 × (2 + 8 + 16) ≈ 50 cm d'emprise au plus large, ramenés à 45 cm par les feuilles du cœur, plus dressées. Les fiches de semenciers donnent 40 cm (Vilmorin) à 60 cm (Bright Lights, 24 pouces de haut pour 18 d'emprise).
- Racine : enterrée, rien de visible hors du collet.

## Traits qui font reconnaître, dans l'ordre
1. Des côtes larges et charnues de couleur vive, qui montent du pied jusque dans le limbe : c'est la couleur des côtes, sous le vert sombre, qui fait la blette.
2. De grandes feuilles ovales dressées, vert sombre, luisantes et cloquées, plus grandes que celles de tous les voisins de la famille.
3. Une touffe haute et dressée, sans racine visible au pied ni bulbe.

## Couleur des côtes retenue
Les variétés du type « Bright Lights » ou « arc-en-ciel » ont des côtes jaunes, rouges, orange, roses ou blanches, une couleur par plant. La scène instancie la même géométrie pour tous les plants d'un rang : un plant à une seule couleur donnerait un rang uniforme, rouge (qui se lit betterave ou rhubarbe) ou blanc (qui se lit pak-choï, ou disparaît et se lit épinard). Le modèle porte donc **des côtes jaune d'or et des côtes rouge cerise sur le même plant**, plus une côte blanche : le mélange représente le rang arc-en-ciel, et le jaune, qu'aucune autre espèce du potager ne porte en pétiole, sépare à lui seul la blette de la betterave et de la rhubarbe.

## Confusions et ce qui sépare
- **Betterave** : dôme de racine pourpre sombre visible au pied, feuilles plus petites et plus fines, pétioles rouge pourpre seuls. La blette n'a pas de racine visible, des feuilles deux fois plus grandes et des côtes larges jaunes et rouges.
- **Pak-choï** : vase court et trapu, pétioles blancs en cuillère serrés en bulbe au pied, limbes ronds et lisses. La blette est plus haute que large, sans bulbe, avec des côtes colorées et des limbes ovales cloqués.
- **Rhubarbe** : pétioles rouges seuls, feuilles immenses en cœur, gaufrées, vert jaune, port étalé. La blette est plus petite, dressée, vert sombre, et ses côtes mêlent le jaune au rouge.
- **Épinard** : touffe basse de feuilles vert sombre sans côte colorée visible. La blette est haute, et la couleur des côtes se voit de loin.

## Couleurs (quatre au plus)
- Sombre : `#2b5f27`, le limbe, vert sombre luisant.
- Vif clair : `#f2c230`, côtes jaune d'or.
- Vif moyen : `#d4283f`, côtes rouge cerise.
- Clair : `#f1eedc`, une côte blanche.

## Sources
- Plant finder des pépinières (Platt Hill Nursery, [Bright Lights Swiss Chard](https://plants.platthillnursery.com/12120026/Plant/18535/Bright_Lights_Swiss_Chard)) : environ 60 cm de haut pour 45 cm d'emprise, feuilles crispées vert sombre arquées sur des côtes colorées.
- Pépinière.ca, [Bright Lights](https://www.pepiniere.ca/en/products/bright-lights-swiss-chard) : 60 à 80 cm de haut, côtes jaunes, or, orange, roses, pourpres ou vertes.
- Vilmorin, [poirée verte à carde blanche](https://www.bricomarche.com/p/bette-poiree-verte-a-carde-blanche-vilmorin/3182670256219) : environ 40 cm de haut, 40 cm entre plants.
- GRAB, [essai variétal blette sous abris 2012-2013](https://grab.fr/619) : côtes de 4 à 6 cm de large pour les blanches, 2 à 4 cm pour les colorées.
- Flora of New Zealand, [Beta vulgaris](https://floraseries.landcareresearch.co.nz/taxa/f8f2a874-0388-444f-b4f2-250ecf4f2769) : limbe ovale, luisant, souvent cloqué, jusqu'à 20 × 15 cm, pétiole jusqu'à 20 cm.
- Flora of North America via [OregonFlora, Beta vulgaris](https://oregonflora.org/taxa/index.php?taxon=3262) : pétiole de la moitié aux deux tiers du limbe ou l'égalant, nervure médiane marquée.
- Jardiner Malin, [Bette, blette](https://www.jardiner-malin.fr/fiche/bette-blette.html).

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles** (`especes.json`), sans exception, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait une vingtaine de pixels.
