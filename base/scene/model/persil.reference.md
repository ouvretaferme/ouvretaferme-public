# Persil · fiche de référence

Persil plat (*Petroselinum crispum* var. *neapolitanum*), plant adulte au potager pendant la plus grande partie de sa saison : la première année, touffe de feuilles basales qu'on coupe au fur et à mesure, sans tige florale (le persil est bisannuel, il ne monte qu'au printemps de la deuxième année).

Pourquoi le plat et non le frisé : les traits qui doivent faire reconnaître l'espèce (feuilles triangulaires très découpées sur de longs pétioles fins, vert profond) sont ceux du persil plat. Le frisé se lit en boule mousseuse sans forme de feuille, et c'est précisément ce que trois juges n'ont pas su nommer sur l'actuel.

Pourquoi le plat est celui du maraîchage : c'est le persil commun des étals et des planches de maraîchers. Un relevé de FranceAgriMer sur les herbes fraîches coupées le trouve dans 60 % des points de vente, contre 49 % pour le frisé ; le « Géant d'Italie », un persil plat, est donné comme la variété la plus courante. Aucune source consultée ne ventile les 1 613 ha de persil français (FranceAgriMer 2018) entre plat et frisé : la prépondérance du plat est donc une lecture de la distribution, pas une mesure des surfaces. Le GRAB essaie d'ailleurs les deux sous abri, signe que le frisé garde sa place chez certains maraîchers.

## Proportions
- **Hauteur** : 0,4 à 0,7 m (KPU), jusqu'à 60 cm pour le persil plat 'French' (RHS), 30 à 60 cm au potager selon les fiches de culture. On retient **35 cm** pour la touffe feuillée d'un plant coupé régulièrement.
- **Emprise** : 0,3 à 0,6 m (KPU), environ 30 cm (Texas Tech). On retient **30 cm**, soit une touffe **un peu plus haute que large** (rapport hauteur / emprise ≈ 1,15) : dressée, pas étalée.
- **Feuilles** : 10 à 20 par plant adulte, toutes basales, en rosette serrée au cœur (estimation sur photos).
- **Pétiole** : 5 à 20 cm (KPU), 20 à 25 cm pour les grandes feuilles (Mahoney's). Il fait **la moitié ou plus** de la longueur de la feuille, fin, nu, dressé : 15 à 35° de la verticale au cœur, 45 à 60° pour les feuilles extérieures.
- **Limbe** : 5 à 15 cm de long sur 2 à 7 cm de large (KPU), **triangulaire (deltoïde)**, deux à trois fois penné, folioles plates à lobes dentés et pointus (KPU : « tripinnate, deltoid, serrate »). Lustré, vert sombre.

Recoupement : une feuille de 30 cm (pétiole 18 cm, limbe 12 cm) ouverte à 25° monte de 27 cm et s'écarte de 13 cm ; ouverte à 55° au bord de la touffe, elle s'écarte de 25 cm et monte de 17 cm. Les feuilles du cœur font la hauteur (35 cm avec le limbe qui se redresse), celles du bord l'emprise (2 × 15 cm). Les trois mesures tiennent ensemble.

## Traits qui font reconnaître, dans l'ordre
1. Une **touffe dressée et dense, vert profond**, plus haute que large.
2. Des **feuilles triangulaires très découpées** en folioles pointues, nettement séparées les unes des autres, qu'on lit une à une.
3. De **longs pétioles fins et nus** qui partent tous du cœur et portent les limbes en hauteur.

## Confusions et ce qui sépare
- **Coriandre** (`coriandre.n.js`) : vert clair, touffe basse et étalée, limbes ronds en éventail, ombelles blanches. Le persil est plus sombre, dressé, à limbes triangulaires pointus.
- **Carotte** : fanes en plumet fin et vaporeux, sans surface pleine, collet orange. Le persil a des folioles plates et larges, lisibles.
- **Céleri branche** : grosses côtes pleines vert clair en botte serrée, feuilles seulement au sommet. Le persil a des pétioles fins comme des fils, et le limbe en occupe le tiers supérieur au moins.
- **Céleri à couper** (`celeri-couper.n.js`) : couronne de folioles en coin, élargies en tête, couchées presque à plat sur des tiges pâles nues. Le persil a des folioles qui s'affinent en pointe dentée, une touffe plus dressée et plus pleine, vert plus profond.
- **Cerfeuil** : touffe basse, vert tendre, limbe très finement divisé en dentelle. Le persil a trois grandes folioles larges et plates par feuille, vert sombre.
- **Persil frisé, laitue batavia, chicorée frisée** (lectures des juges sur l'actuel) : boule crépue sans feuille lisible. Le plat a une touffe ajourée de triangles sur tiges.

## Couleurs (quatre au plus)
- Sombre : `#006833` (couleur de l'espèce dans `especes.json`), les limbes : le vert profond qui le sépare de la coriandre.
- Moyen : `#2e8a3c`, une partie des limbes, pour lire les feuilles l'une sur l'autre.
- Clair : `#6eb453` (`leaf` de `especes.json`), les pétioles, qui se lisent comme des traits clairs entre les limbes sombres.
- Accent : `#8a6a45` (`accent` de `especes.json`), le collet au ras du sol, s'il est retenu.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, sans exception, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m).

## Sources
- FranceAgriMer, bilan de campagne des PPAM fraîches : présence en magasin du persil plat (60 %) et du frisé (49 %). https://www.franceagrimer.fr/layout/set/ajax/content/download/69877/1087856/file/bilan_campagne-ppam-fraiche.pdf
- Pleinchamp, la Cavac et le persil bio : 1 613 ha de persil en France (chiffres FAM 2018). https://www.pleinchamp.com/actualite/la-cavac-augmente-ses-surfaces-de-persil-bio-pour-repondre-a-la-demande
- La Nutrition, *Persil et agronomie* : le « Géant d'Italie » (plat) comme variété courante. https://www.lanutrition.fr/node/6289
- GRAB, essai DIVERMABIO de variétés de persil plat et frisé sous abri. https://www.grab.fr/wp-content/uploads/2020/09/L19-01107-3-DIVERMABIO-variétés-persil-plat-et-frisé-sous-abris.pdf
- KPU Plant Database, *Petroselinum crispum* : 0,4 à 0,7 m de haut, 0,3 à 0,6 m d'emprise, feuilles basales tripennées, deltoïdes, dentées, lustrées ; limbe 5 à 15 × 2 à 7 cm ; pétiole 5 à 20 cm. https://plantdatabase.kpu.ca/PlantPrint/pecr
- RHS, *Petroselinum crispum* 'French' : bisannuelle dressée jusqu'à 60 cm, feuilles plates vert sombre, divisées. https://www.rhs.org.uk/plants/104119/petroselinum-crispum-french/details
- Texas Tech, *Flat-Leaf Parsley* : 15 à 30 cm de haut, 30 cm d'emprise. https://www.depts.ttu.edu/plantresources/Pages/directories/landscape-info-sheets/edible-info/Petroselinum-crispumvarneapolitanum.php
- Mahoney's Garden : feuilles plates sur tiges de 20 à 25 cm. https://shop.mahoneysgarden.com/?p=167755

## Jugement à l'aveugle (version p)

Au lot 25, la version p et l'ancienne sont toutes deux nommées persil par les 3 juges, tous hésitants, avec le céleri ou la coriandre en deuxième hypothèse. Le juge exigeant lit sur la version p des folioles nettement trilobées et pointues, et sur l'ancienne des éclats de vert : le défaut que la reprise devait lever. La version p compte 286 triangles : 8 feuilles et une jeune, trois folioles plates par pétiole. Défaut restant : dans le rang, la touffe est maigre et se lit mal sur la terre.
