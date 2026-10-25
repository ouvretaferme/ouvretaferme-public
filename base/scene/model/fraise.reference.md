# Fraisier · fiche de référence

Plant adulte en pleine saison, au potager, en fleurs et en fruits (variété courante, remontante ou non). Les fraises mûres sont posées par la scène (`FRUITS` dans `socle.js` : cinq cônes rouges à 44 % de l'emprise depuis le pied, à 30 % de la hauteur), pas par le modèle.

## Proportions
- Plant : touffe basse en dôme, 20 à 25 cm de haut pour 25 à 40 cm d'emprise (fiches variétales Truffaut : 25 × 25 cm pour 'Ciflorette', 'Maestro', 'Anaïs', 'Surprise des Halles' ; 30 cm entre plants au potager). Rapport hauteur sur emprise ≈ 0,55 à 0,7 : plus large que haut, nettement plus bas qu'une touffe de persil.
- Pas de tige : toutes les feuilles partent d'un cœur (la couronne) au ras du sol. 10 à 20 feuilles sur un plant adulte.
- Pétiole : long et grêle, 7 à 20 cm au jardin (descriptions variétales USPTO : 7,0 cm pour 'Crystalina', 17,4 cm pour 'DrisStrawEighteen', jusqu'à 30-40 cm sur des plants forcés). Il monte à 30-60° de la verticale, et les feuilles extérieures se couchent vers le bord.
- Limbe : **trifolié**, trois folioles au bout du pétiole sur des pétiolules de quelques millimètres, donc serrées en trèfle. Foliole terminale de 6 à 11 cm, presque ronde ou ovale (longueur ≈ largeur, ACIA : « aussi longue que large », base obtuse), les deux latérales un peu plus petites et asymétriques, écartées de 60-80° de la terminale.
- Bord des folioles nettement **denté** (15 à 25 dents par foliole), dents larges et arrondies, nervures en creux, foliole un peu pliée en V le long de la nervure centrale.
- Folioles tenues presque à plat, face supérieure vers le ciel : de dessus, le plant est un tapis de trèfles ronds qui se chevauchent.
- Cohérence : un pétiole de 12 cm à 40° de la verticale monte de 9 cm et s'écarte de 8 cm ; avec une foliole de 8 cm presque à plat au-dessus, la feuille culmine vers 12-15 cm au cœur (20-25 cm pour les plus dressées) et s'étend à 15-18 cm du pied, soit 30-36 cm d'emprise. Ça tient.
- Fleurs : petites (2 à 3 cm), cinq pétales blancs ronds autour d'un cœur jaune, sur des hampes plus courtes que les feuilles, en bordure de touffe.

## Traits qui font reconnaître, dans l'ordre
1. Les fraises rouges qui pendent au bord de la touffe, sous les feuilles ou posées sur la paille (posées par la scène).
2. La feuille en trèfle : trois folioles larges, rondes et dentées, serrées au bout d'un pétiole nu. C'est le trait que le modèle doit porter.
3. Une touffe basse en dôme, plus large que haute, sans tige, vert franc à vert sombre ; quelques fleurs blanches à cinq pétales.

## Confusions et ce qui sépare
- **Persil** : touffe dressée, plus haute que large, feuilles finement découpées en folioles pointues et nombreuses. Le fraisier est bas et en dôme, et chaque feuille n'a que trois folioles larges et rondes.
- **Roquette** : rosette de feuilles allongées en lyre, lobes le long de la nervure, sans pétiole nu. Le fraisier porte un trèfle rond au bout d'un pétiole.
- **Épinard, mâche** : rosettes de feuilles entières, lisses, sans découpe ni dents. Le fraisier a trois folioles dentées par feuille.
- **Trèfle, haricot** : le trèfle est minuscule et le haricot grimpe ; le fraisier est une touffe ronde de 30 cm, aux folioles dentées.
- **Patate douce** : tapis rampant de feuilles en cœur ; ses tubercules rose vif se sont déjà lus en fraises.

## Couleurs (quatre au plus)
- Sombre : `#2e6a2b`, les folioles extérieures, basses, et le dessous du dôme.
- Moyen : `#4f8f39`, les folioles du cœur, qui prennent la lumière.
- Clair : `#fbf6ee`, les fleurs (l'accent de `especes.json`).
- Pétioles : `#8fa04a`, vert jaune, à peine visibles en jeu.
Le rouge des fraises (`#e3242b`, socle) doit trancher sur les deux verts : pas de rouge dans le plant, qui se confondrait avec les fruits.

## Sources
- Truffaut, fiches plants de fraisier ('Ciflorette', 'Surprise des Halles', 'Maestro', 'Anaïs', 'Mount Everest') : hauteur et largeur à maturité ; feuilles à trois folioles dentées au bout d'un pétiole grêle. https://www.truffaut.com/fraisier-ciflorette-bio-barquette-3-plants-937596.html
- Agence canadienne d'inspection des aliments, descriptions variétales de *Fragaria × ananassa* : pétiole long, foliole terminale aussi longue que large, base obtuse, marge crénelée. https://active.inspection.gc.ca/francais/plaveg/pbrpov/cropreport/str/str126f.pdf
- Brevets de plantes USPTO (PP25867 'Crystalina', PP23107, PP31233, PP32305…) : foliole terminale 5,9 à 10,9 cm, pétiole 7 à 39 cm, pétiolules de quelques millimètres, 15 à 25 dents par foliole. https://image-ppubs.uspto.gov/dirsearch-public/print/downloadPdf/PP25867

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » (rang à 20 px/m), où le plant fait une quinzaine de pixels, avec les fraises du socle autour. Les feuilles du bord restent basses pour que la couronne de fraises (44 % de l'emprise, 30 % de la hauteur) reste visible.
