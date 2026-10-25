# Delphinium · fiche de référence

Pied-d'alouette vivace (Delphinium elatum et ses hybrides de jardin, groupe Elatum, Pacific Giants), Renonculacées. Pied installé de deuxième ou troisième année, planté isolé en fond de massif, en fleur. Le feuillage de base pousse au printemps, les hampes montent en mai et fleurissent de juin à juillet, puis remontent en septembre si on les coupe. Le modèle montre la touffe fleurie, l'état qui la fait reconnaître et qui occupe l'essentiel de sa saison au jardin.

## Proportions
- Espèce : vivace en touffe jusqu'à 1,8 m, feuilles profondément lobées, épis raides de fleurs bleu violacé foncé en été ; hauteur 1,5 à 2,5 m, emprise 0,5 à 1 m ([RHS, Delphinium elatum](https://www.rhs.org.uk/plants/5398/delphinium-elatum-candle-larkspur-alpine-delphinium/details)).
- Hybrides Pacific : hampes jusqu'à 1,8 m, densément garnies de grandes fleurs semi-doubles ([RHS, Delphinium Pacific Hybrids](https://www.rhs.org.uk/plants/52839/delphinium-pacific-hybrids/details)) ; hauteur 1,2 à 2 m, fleurons jusqu'à 7 cm de diamètre ([Tesselaar, Pacific Giants](https://www.tesselaar.net.au/product/2346-delphinium-pacific-giants-mixed)).
- Nombre de hampes : la RHS conseille d'éclaircir à 2 ou 3 pousses sur un jeune pied, 5 à 7 sur un pied installé ([RHS](https://www.rhs.org.uk/plants/5398/delphinium-elatum-candle-larkspur-alpine-delphinium/details)). On en dessine trois : une hampe maîtresse et deux secondaires plus courtes.
- Feuilles : basales, palmées, lobées et dentées ([RHS, 'Blushing Brides'](https://www.rhs.org.uk/plants/256277/delphinium-elatum-blushing-brides-new-millennium-series/details)), de l'ordre de 15 cm sur 16 cm (brevet du cultivar 'Coadelcha', [Justia](https://patents.justia.com/patent/20160302343)), divisées en cinq à sept lobes étroits et pointus. Elles forment un dôme de feuillage de 40 à 50 cm de haut au pied, quelques feuilles plus petites montent le long des tiges.
- Unité modélisée : une touffe de 60 cm d'emprise (`d` 0,6 m), hauteur 1,5 m, soit hauteur / emprise ≈ 2,5.
  - Dôme de feuilles palmées : 60 cm de large, 45 cm de haut.
  - Tiges nues de 45 à 75 cm au-dessus du feuillage, raides, presque verticales (moins de 10° au centre, 15° pour les secondaires).
  - Épi maître : de 0,7 à 1,5 m, soit 80 cm de long sur 16 à 18 cm de diamètre à la base (fleurons de 7 cm tout autour d'un axe), effilé en chandelle jusqu'à la pointe en boutons. Épis secondaires : 50 à 60 cm de long, plus fins, culminant à 1,15 et 1,25 m.
- Recoupement : trois épis de 18 cm de diamètre partant à moins de 10 cm de l'axe et inclinés de 15° au plus restent dans un cercle de 0,3 m de rayon à 1,2 m de haut (8 + 60 × sin 15° + 9 ≈ 33 cm) : l'emprise reste celle du feuillage à 10 % près, et la touffe se lit en colonne au-dessus d'un dôme. Les feuilles de 30 cm d'envergure (exagérées deux fois pour l'écran) dont le pétiole part du centre donnent bien 0,6 m.
- Exagération pour l'écran : à 20 px/m, avec le facteur 2,5 de la scène, un épi de 18 cm fait 9 px de large et 40 px de haut : il se lit tel quel. Les feuilles sont doublées pour que le dôme vert se lise en tapis continu.

## Traits qui font reconnaître, dans l'ordre
1. De hautes chandelles bleues, denses, effilées, dressées bien au-dessus de tout le reste du massif : la touffe est deux fois et demie plus haute que large.
2. Un dôme bas de feuilles palmées profondément découpées, vert franc, d'où les hampes sortent nues.
3. Le bleu franc, cobalt à outremer, plus foncé à la pointe en boutons, plus clair sur les fleurons ouverts du bas.

## Confusions et ce qui sépare
- **Lavande** (`lavande.n.js`, la voisine désignée) : épis violets courts (8 cm) et nombreux, en brosse, sur un coussin gris argenté rond et bas, hauteur égale à l'emprise. Le delphinium a trois épis seulement, gros, longs comme la moitié de la plante et bleu franc, sur un dôme vert découpé, et il est deux fois et demie plus haut que large. En silhouette : coussin hérissé contre trois chandelles au-dessus d'un dôme. En rang : brosse basse violette contre une haie de colonnes bleues.
- **Bleuet** : fleurs bleues isolées en petits pompons sur une touffe grêle et basse. Le delphinium porte son bleu en épi plein, pas en points.
- **Lupin, digitale** (hors catalogue) : même port en chandelle ; le lupin a des feuilles digitées en étoile et des épis plus courts, la digitale des clochettes pendantes roses sur une rosette de feuilles entières. On ne les cultive pas sur la planche.
- **Tournesol, topinambour, maïs** : aussi hauts, mais une colonne de feuilles le long d'une tige, sans épi bleu. Le delphinium a ses feuilles en bas et sa couleur en haut.
- **Asperge, fenouil** : hauts plumeaux verts, sans couleur.

## Couleurs (quatre au plus)
- Clair : `#7c9be6`, bleu ciel soutenu, les fleurons ouverts du corps des épis : le plus fort contraste, clair et saturé contre le vert sombre du dôme.
- Moyen : `#4f8a3a`, vert franc, le dessus des feuilles et les tiges.
- Sombre : `#2f3f9e`, outremer, la pointe des épis en boutons.
- Ombre : `#2f5a26`, vert sombre, les feuilles basses et l'intérieur du dôme, pour que le feuillage ait du relief.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Grande fleur : plafond **120 triangles**, absolu, **4 couleurs**.
- Fleur en parterre : le socle pose les touffes à 0,7 × `d` avec un léger désordre. Les dômes verts se touchent et font un tapis continu d'où montent les chandelles bleues.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m). Les épis portent la reconnaissance et reçoivent le plus de triangles.

## Jugement à l'aveugle (lot 42, r5)
Nommé par les trois juges, une fois avec certitude, avec le lupin en deuxième hypothèse. Défauts relevés : une hampe clairsemée et l'absence d'éperon. Le modèle posé est la r5 : 12 fleurs étoilées en spirale sur une hampe maîtresse et une secondaire, bleu gentiane, œil blanc et boutons outremer. Il ne suit plus les « trois chandelles denses » décrites plus haut.
