# Cardon · fiche de référence

Cardon (*Cynara cardunculus*, groupe Altilis), cultivé en annuelle au potager : semé en mai, il forme tout l'été et l'automne une grande touffe de feuilles, puis il est lié et blanchi quelques semaines avant la récolte des côtes en octobre et novembre. Il ne monte pas en fleur dans l'année (on coupe la hampe si elle apparait) : la fiche décrit la touffe d'été et d'automne, sans capitule.

## Proportions
- Touffe entière, sans hampe : 1,2 à 1,5 m de haut, 1,2 à 1,5 m d'emprise. RHS donne 1,5 à 2,5 m de haut hampe comprise et 1 à 1,5 m d'emprise ; Truffaut, 1,4 m de largeur moyenne ; jardiner-malin et la fiche Semences paysannes plantent à 1 m en tous sens. Rapport hauteur sur emprise ≈ 1 : la touffe est aussi haute que large, nettement plus haute que l'artichaut, dont le feuillage seul fait environ 0,8 m. On retient **d = 1,3 m**, h tiré de la forme modelée (≈ 1,3 m).
- Feuilles : jusqu'à 1 m de long (RHS ; Texas Tech : jusqu'à 90 cm en rosette), une dizaine de bien formées, toutes basales. Port en vase, que RHS dit « colonnaire, dressé » : les feuilles du cœur montent presque droites (10 à 20° de la verticale), celles du tour s'ouvrent à 35-50° et ne retombent que par le dernier quart. Une feuille de 1 m ouverte à 45° et arquée au bout s'écarte de 60 à 65 cm du centre : longueur, ouverture et emprise de 1,3 m tiennent ensemble.
- Côte (pétiole, la « carde » qu'on mange) : large, charnue, vert pâle blanchâtre et cannelée, nue sur le premier tiers de la feuille, 5 à 8 cm de large au pied. C'est elle qui serre la touffe en botte au ras du sol.
- Limbe : pennatiséqué, découpé presque jusqu'à la nervure en 6 à 8 paires de segments étroits, eux-mêmes dentés ; chaque dent finit en épine jaune paille de 5 à 15 mm (Semences paysannes, races épineuses comme le Plein blanc épineux de Tours). Dessus gris argenté, dessous plus blanc, tomenteux.

## Traits qui font reconnaître, dans l'ordre
1. Une grande touffe gris argenté en vase, aussi haute que large, la plus haute et la plus claire du potager, et rien au-dessus : pas de tête, pas de hampe.
2. Des feuilles très découpées en segments étroits et dentés, hérissées d'épines, qui donnent un contour déchiqueté à la touffe.
3. Des côtes larges et pâles, serrées en botte au pied, d'où partent toutes les feuilles.

## Confusions et ce qui sépare
- **Artichaut** : même feuillage argenté découpé, mais une touffe plus basse en fontaine (feuilles qui retombent jusqu'au sol, feuillage seul vers 0,8 m), et au-dessus trois têtes rondes vert sombre à sommet violet sur des hampes nues. Le cardon est plus haut, dressé en vase, sans aucune tête ni hampe, et ses feuilles plus longues et plus déchiquetées montent jusqu'au sommet de la silhouette.
- **Rhubarbe** : touffe aussi grande, mais dôme bas plus large que haut, limbes entiers presque ronds vert jaune et pétioles rouges. Le cardon est gris, aussi haut que large, à feuilles découpées et côtes pâles.
- **Chardon, cirse** : plante maigre verte à petites têtes violettes. Le cardon est massif, argenté, sans fleur dans la saison.
- **Sauge, armoise** : touffe grise mais petite et basse, feuilles entières ou fines.

## Couleurs (quatre au plus)
- Clair : `#c3cfc6`, le dessus argenté des feuilles du tour, un gris plus blanc que celui de l'artichaut.
- Moyen : `#8a9e90`, les feuilles du cœur et celles du dedans, plus sombres, qui creusent la touffe et donnent son relief au vase.
- Pâle : `#e2e4cf`, les côtes, vert pâle blanchâtre.
- Accent : `#c4a64e`, les épines jaune paille au bout des dents.

## Sources
- RHS, *Cynara cardunculus* : https://www.rhs.org.uk/plants/76926/wd/details (1,5 à 2,5 m hampe comprise, 1 à 1,5 m d'emprise, feuilles argentées pennatifides épineuses jusqu'à 1 m, port colonnaire dressé).
- Texas Tech, Cardoon : https://www.depts.ttu.edu/plantresources/Pages/directories/landscape-info-sheets/edible-info/Cynara-cardunculus.php (feuilles jusqu'à 90 cm en rosette).
- Semences paysannes, fiche cardon : https://ressources.semencespaysannes.org/docs/mm3.si.pdf (épines de 5 à 15 mm, écartement, blanchiment).
- Jardiner malin, Cardon : https://www.jardiner-malin.fr/fiche/cardon.html (feuilles grandes, très découpées, gris argenté ; plantation à 1 m).
- Truffaut, Cardon blanc : https://www.truffaut.com/cardon-blanc-c-0-5l-43416.html (largeur moyenne 1,4 m).
- Futura Sciences, Cardon : https://www.futura-sciences.com/planete/definitions/botanique-cardon-19940/ (côte large vert pâle, blanchie avant récolte).

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**.
- Pas de fruit posé par le socle : le cardon se mange par ses côtes, rien ne pend.
- La scène grossit le plant d'un facteur k = min(2,5, max(1,2, largeur × 1,3 / d)) et la hauteur un peu plus (k × 1,25) : à d = 1,3 sur une planche de 1 m, la touffe paraît encore un peu plus haute que large, ce qui sert le trait qui la sépare de l'artichaut.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m).

## Jugement à l'aveugle

Lot 16, contre l'artichaut, le chou-fleur, le tournesol et le fenouil : 2 sur 3, hésitants. Le troisième lit un artichaut sans capitule.
