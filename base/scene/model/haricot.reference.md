# Haricot à rames · fiche de référence

Plant adulte en pleine saison (juillet à septembre), dans le potager : la liane a atteint le haut des rames et porte des gousses.

## Proportions
- Rames : perches de 2,4 à 2,7 m, enfoncées d'une vingtaine de centimètres, soit 2,2 à 2,5 m hors de terre. Montées par paires en A (ou en tipi de trois à six), pieds écartés de 0,5 à 1 m, liées vers le haut. Les pointes dépassent au-dessus du lien. Sources : Mother Earth News (tipi de perches de 9 pieds, pieds écartés de 3 à 4 pieds) ; Epic Gardening (variétés à rames de 6 à 8 pieds, jusqu'à 13 pieds pour 'Kentucky Wonder').
- Liane : 2 à 2,5 m, elle couvre la rame jusqu'au lien ; au-dessus, la perche nue. Trois ou quatre pieds par rame.
- Emprise retenue pour le modèle : 0,55 m dans l'axe de la planche (écart des pieds de rame), 2,1 m de haut ; rapport hauteur sur largeur ≈ 3,8. Le socle pose les rames du modèle aux bords de l'emprise (± 0,45) et les fait se croiser vers 83 % de la hauteur (`onPoles`, `socle.js`) : la géométrie suit ce placement.
- Feuilles : alternes, trifoliées, pétiole de 8 à 15 cm. Folioles largement ovales à deltoïdes, pointe effilée (acuminée), 6 à 15 cm de long sur 4 à 8 de large ; la terminale symétrique sur un pétiolule plus long, les latérales asymétriques. Sources : Michigan Climbers (4-16 × 2,5-11 cm, largement ovales, acuminées) ; Flora Zambesiaca via Colplanta (4,5-15 × 2,5-6,5 cm) ; NYBG (terminale 6-10 × 5-7,5 cm, deltoïde ou rhombique). Une feuille entière fait 15 à 25 cm, soit environ le dixième de la hauteur du plant.
- Port : les feuilles pendent un peu de part et d'autre de la rame, en manchon irrégulier plus épais au milieu de la hauteur qu'au pied, où les premières feuilles sont tombées, et qu'au sommet.

## Traits qui font reconnaître, dans l'ordre
1. Deux hautes perches brunes en A, les plus hautes du potager, dont les pointes nues se croisent au-dessus du feuillage.
2. Un manchon de feuillage vert franc qui grimpe le long de chaque perche jusqu'au lien, fait de grandes feuilles en cœur pointu par trois.
3. Des gousses fines vert clair qui pendent sous les feuilles (posées par le socle).

## Confusions et ce qui sépare
- **Pois** : 1,5 m au plus, sur grillage ou filet plat, vert glauque pâle, folioles rondes et vrilles. Le haricot monte sur deux perches en A, plus haut, vert plus franc et plus sombre.
- **Concombre** : sur filet vertical, grandes feuilles palmées rugueuses, fruits vert sombre. Le haricot a des perches croisées et des feuilles par trois.
- **Tomate** : tuteur unique droit, feuilles composées découpées, grappes rouges. Le haricot a deux perches en A et des gousses vertes pendantes.
- **Poire de terre, maïs** : grandes tiges sans support. Le haricot n'existe pas sans ses rames.

## Couleurs (quatre au plus, gousses du socle à part)
- Clair : les gousses `#9fca4a`, posées par le socle ; le feuillage reste nettement plus sombre pour qu'elles s'en détachent.
- Moyen : `#4a8a34`, les feuilles du dehors et du haut.
- Sombre : `#2f6326`, les feuilles du cœur, contre la rame.
- Brun : `#8a6440`, les rames.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **60 triangles**, **4 couleurs**.
- Ce qui doit marcher : le rang en jeu à 20 px/m, où le plant fait une cinquantaine de pixels de haut, gousses du socle comprises.
