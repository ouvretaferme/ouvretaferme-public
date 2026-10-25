# Concombre · fiche de référence

Plant adulte en pleine saison (mi-juillet à septembre), au potager, palissé sur un filet tendu le long du rang, en fleurs. Les concombres sont posés par le socle (`FRUITS.concombre`, quatre cylindres vert sombre `#1f4a1c` à ±0,375 de l'emprise le long du rang et ±0,2 en travers, entre 0,25 et 0,6 de la hauteur) : le modèle doit laisser cette bande à demi découverte devant le filet.

## Proportions
- Support : filet ou grillage de 1,2 m (fiche de la chambre d'agriculture de Martinique) à 1,8 m (guides de palissage : 1,5 à 1,8 m, les lianes font 1,2 à 1,8 m et parfois plus). Retenir un filet de **1,2 m** et un plant qui le dépasse un peu : **1,3 m**.
- Écartement sur le rang : 40 cm quand le plant est palissé (chambre d'agriculture de Guyane : 50 cm, réduit à 40 cm sur palissage). Emprise le long du rang : **0,45 m**. En travers, les feuilles sortent de 15 à 20 cm de chaque côté du filet : épaisseur **0,35 m**.
- Rapport hauteur / emprise ≈ **2,9** : un mur étroit et haut, jamais un buisson. C'est le contraire des trois cucurbitacées rampantes du potager.
- Tige : une liane fine, anguleuse, qui monte dans les mailles en zigzag, accrochée par des vrilles nées aux nœuds (invisibles en jeu, non modélisées).
- Feuilles : alternes, simples, **triangulaires ovales**, base en cœur, **3 à 5 lobes peu profonds et pointus**, bord denté. Limbe de 7 à 15 cm de long sur 7 à 12 cm de large (base JIRCAS), donc presque aussi large que long ; pétiole de 5 à 15 cm, qui écarte le limbe du filet. Une feuille fait environ le dixième de la hauteur du plant ; on la grossit d'un quart pour qu'elle se lise en jeu. Le pétiole sort à l'horizontale et le limbe pend devant le filet, pointe vers le bas : un mur de boucliers.
- Masse : une quarantaine de feuilles réparties sur toute la hauteur, de part et d'autre du filet, qui laissent voir les mailles par endroits.
- Fleurs : jaunes, 3 à 4 cm, à cinq pétales, aux aisselles. Petites taches jaunes en jeu.

## Traits qui font reconnaître, dans l'ordre
1. **Il grimpe** : un mur de feuillage vert franc, haut et étroit, porté par un filet clair dont les poteaux et la traverse du haut se voient.
2. Des feuilles larges, triangulaires et pointues, pendantes devant le filet, plus grandes que celles du pois.
3. Des concombres vert sombre pendus devant les feuilles (posés par le socle).

## Confusions et ce qui sépare
- **Pastèque** : tapis bas au ras du sol, feuilles gris bleuté profondément découpées en lanières, fruit rayé couché. Le concombre est dressé sur un filet, ses feuilles sont vert franc, à lobes peu profonds et pointus.
- **Courgette** : buisson sans support, en vase, à 60 cm au plus, très grandes feuilles palmées (30 à 40 cm) marbrées d'argent sur pétioles raides qui partent tous du cœur. Le concombre grimpe, deux fois plus haut que large, ses feuilles sont trois fois plus petites et étagées le long d'une liane.
- **Melon** : butte basse qui rampe, feuilles rondes ou réniformes à lobes arrondis, fruit brodé au sol. Le concombre est vertical et sa feuille a une pointe.
- **Pois** (confusion des tours 1 à 3) : grimpe aussi sur un grillage, mais plus bas (1 m au plus), en feuilles composées de petites folioles vert glauque et clair. Le concombre a des feuilles simples cinq fois plus grandes, d'un vert plus franc et plus sombre.
- **Haricot à rames** (tour 4) : rames en tipi, feuilles trifoliées. Le concombre a un filet plan et des feuilles entières lobées.

## Couleurs (quatre au plus)
- Clair : `#efe8d4`, le filet (poteaux, traverse, fils). C'est le contraste fort avec le feuillage : il dit « palissé ».
- Moyen : `#3f8a2c`, les feuilles du dehors, vert franc.
- Sombre : `#2b6623`, les feuilles du dedans et du bas, qui font la profondeur du mur ; plus claires que le fruit `#1f4a1c`, pour qu'il s'en détache.
- Accent : `#f6c51f`, les fleurs.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**. Famille « palissé », jusqu'à 101 plants un même jour.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m). Le socle plafonne la hauteur affichée (2,4 m dans la planche) : le plant y fait une cinquantaine de pixels de haut pour une vingtaine de large.
- La planche tourne chaque plant au hasard alors que la scène l'aligne sur la planche (`aligned: true`) : en jeu sur la planche, certains filets se voient par la tranche, ce qui n'arrive pas dans la scène.

## Sources
- JIRCAS, *Cucumis sativus* : feuilles alternes, simples, triangulaires ovales, 3 à 7 lobes, bord denté, 7–15 × 7–12 cm, pétiole 5–15 cm ([lien](https://www.jircas.go.jp/en/database/thaivege/039)).
- NParks Flora & Fauna Web : feuilles triangulaires ovales, base en cœur, bord denté ([lien](https://www.nparks.gov.sg/florafaunaweb/flora/3/8/3879)).
- Florida Plant Atlas, clé du genre : limbe peu profondément lobé chez l'espèce cultivée ([lien](https://florida.plantatlas.usf.edu/genus/378)).
- Ontario, guide d'identification, famille du concombre : feuilles à 3 ou 5 lobes, vrilles aux nœuds, tige anguleuse ([lien](https://www.ontario.ca/fr/document/guide-dindentification-des-mauvaises-herbes-agricoles-de-lontario/concombre-grimpant)).
- Chambre d'agriculture de Guyane, fiche concombre 2020 : 50 cm entre plants, 40 cm en palissage, 1,5 m entre rangs ([lien](https://guyane.chambre-agriculture.fr/fileadmin/user_upload/National/FAL_commun/publications/Guyane/pi-concombre-2020.pdf)).
- Chambre d'agriculture de Martinique, FIT concombre 2014 : grillage de 1,20 m en palissage ([lien](https://martinique.chambre-agriculture.fr/fileadmin/user_upload/National/FAL_commun/publications/Outre-Mer/FIT_Concombre_2014.pdf)).
- Homestead and Chill, palissage : lianes de 1,2 à 1,8 m, support de 1,5 à 1,8 m, vrilles qui s'accrochent seules ([lien](https://homesteadandchill.com/grow-trellis-cucumber-plants/)).
- Recoupement : un limbe de 13 cm sur 1,3 m de haut, à une quarantaine de feuilles sur deux faces d'un filet de 0,45 × 1,2 m, couvre à peu près la moitié de chaque face, ce qui tient avec un mur où les mailles se voient par endroits.
