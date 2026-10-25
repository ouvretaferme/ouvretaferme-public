# Melon · fiche de référence

Plant adulte au cœur de l'été, au potager, rampant au sol, coureurs déjà étalés, premiers fruits noués. Les melons mûrs sont posés par le socle (`FRUITS.melon` dans `socle.js`), pas par le modèle.

## Proportions
- Port : rampant, couché au sol. Le feuillage forme un tapis bombé de 25 à 35 cm de haut au cœur, plus bas vers les bords. Les coureurs s'allongent jusqu'à 1,5 m (RHS), mais au potager on plante à 80 cm en tous sens (Hornbach, Jardiner malin, fryd) et les plants se touchent : l'emprise d'un plant pendant la plus grande partie de la saison est de 80 à 100 cm. Rapport hauteur sur emprise ≈ 0,3.
- Feuille : limbe orbiculaire à réniforme, un peu plus large que long, base en cœur, marge anguleuse ou à 5 à 7 lobes peu profonds, finement dentée, poilue et terne. 6 à 15 cm de diamètre (NParks, PROSEA, JIRCAS). Pétiole de 4 à 10 cm, plus court que le limbe ou de même longueur : la feuille se tient en petit parasol au-dessus du tapis, pas au bout d'une longue tige.
- Rapport feuille sur emprise : environ 1/8 en vrai. Sur l'écran de travail, il faut l'exagérer comme le socle exagère les fruits (×1,5 à 1,8) : une feuille d'environ 1/4 de l'emprise.
- Coureurs : 3 à 5 tiges principales, fines, couchées, qui partent du collet en étoile irrégulière, avec des feuilles plus petites espacées le long et à leur bout ; vrilles simples, non ramifiées.
- Fleurs : jaunes, 2 à 3 cm, cinq pétales, à l'aisselle des feuilles, nombreuses et petites.
- Fruits (posés par le socle) : boule beige vert brodée, au sol, au bord du feuillage.

## Traits qui font reconnaître, dans l'ordre
1. Des boules pâles brodées couchées au sol, au bord d'un tapis de feuilles (socle).
2. Un tapis bas et bombé de feuilles rondes, petites, vert sombre et terne, sans grande feuille dressée.
3. Des coureurs qui débordent en étoile sur le sol, piqués de fleurs jaunes.

## Confusions et ce qui sépare
- **Courge, potiron** : feuilles bien plus grandes (25 à 40 cm), fruits orange et plus gros. Le melon a des feuilles petites et un fruit pâle.
- **Courgette** : buisson dressé, sans coureurs, grandes feuilles très découpées portées haut sur de longs pétioles creux. Le melon est couché.
- **Pastèque** : feuilles profondément découpées en lanières (pennatilobées), vert grisé bleuté ; fruit vert foncé rayé. Le melon a des feuilles rondes à lobes peu profonds et un fruit clair.
- **Concombre** : souvent palissé, feuilles triangulaires à pointe marquée, fruits allongés verts.
- **Patate douce, fraisier** : tapis bas aussi, mais feuilles en cœur pointu ou trifoliées, sans boule pâle.

## Couleurs (quatre au plus)
- Sombre : `#2f5a24`, les feuilles basses du tapis, qui font le fond sous les fruits.
- Moyen : `#4c7d2e`, les feuilles du dessus du dôme ; `#86a24a`, les coureurs.
- Clair : `#f2c230`, les fleurs (accent de `especes.json`). Le clair majeur est le fruit beige vert du socle, d'où un feuillage sombre.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, **4 couleurs**.
- `d` reste **0,4** : `FRUITS.melon` pose les melons à 46 % de l'emprise de scène. Avec `d` ≥ 0,52, le facteur de scène `k = min(2,5, max(1,2, 1,3 × largeur / d))` porte l'emprise de scène à 1,3 m sur une planche de 1 m, et les fruits tomberaient hors de la planche. Le modèle est donc le plant entier, proportions gardées (h/d ≈ 0,3), ramené à 0,4 ; `h` se déduit de la géométrie.
- Ce qui doit marcher : le rang à 20 px/m, où un plant fait une vingtaine de pixels et ses melons quatre à six.

## Sources
- RHS, *Cucumis melo* : https://www.rhs.org.uk/plants/105565/cucumis-melo/details (rampant, 1,5 m, grandes feuilles poilues, vrilles simples)
- NParks Flora & Fauna Web : https://www.nparks.gov.sg/florafaunaweb/flora/6/6/6663 (limbe 6 à 15 cm, réniforme à rond)
- PROSEA via Pl@ntUse : https://uses.plantnet-project.org/en/Cucumis_melo_(PROSEA) (limbe orbiculaire à réniforme, 3 à 15 cm, 5 à 7 lobes peu profonds, pétiole 4 à 10 cm)
- JIRCAS Thai vegetables : https://www.jircas.go.jp/en/database/thaivege/038
- Hornbach, planter des melons : https://www.hornbach.ch/projets/planter-des-melons/ ; Jardiner malin, melon Galia : https://www.jardiner-malin.fr/fiche/culture-melon-galia.html (80 cm en tous sens)
- fryd, Charentais : https://fryd.app/en/library/plants/1503-melonsugarmelon/varieties/6488-charentais (80 cm sur le rang, 150 cm entre rangs)
