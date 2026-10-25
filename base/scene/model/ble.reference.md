# Blé · fiche de référence

Blé tendre (*Triticum aestivum*, Poacées), semé dense en plein champ. Le modèle montre une touffe de talles à maturité, en juillet, juste avant la moisson : c'est l'état qui fait reconnaître un champ de blé, doré et sec, épis formés.

## Proportions
- Chaumes : dressés, 60 à 100 cm, cinq à sept nœuds, trois ou quatre feuilles vraies ([Vikidia, Blé tendre](https://fr.vikidia.org/wiki/Bl%C3%A9_tendre)). Retenu : **0,9 m** épi compris (consigne).
- Tallage : la plante émet plusieurs tiges feuillées depuis un plateau de tallage au ras du sol, et chaque chaume porte un seul épi composé d'épillets insérés sur le rachis ([ACIA, biologie de Triticum aestivum](https://inspection.canada.ca/fr/varietes-vegetales/vegetaux-caracteres-nouveaux/demandeurs/directive-94-08/documents-biologie/triticum-aestivum)). Retenu : **5 chaumes épiés** par touffe.
- Épi : une dizaine de centimètres, à barbes courtes, sur une variété de blé tendre décrite ([fiche variétale Andry](https://soc-semences.miasa.gov.mg/wp-content/uploads/FICHES_VARIETALES_PDF/BLE/BLE-ANDRY.pdf)). Soit environ un neuvième de la hauteur. Largeur d'un épi de l'ordre de 1,5 cm, de section à peu près carrée (estimation, aucune source mesurée) : l'épi est six à sept fois plus long que large, dense, dressé dans l'axe du chaume.
- Emprise : **0,3 m** (consigne). Rapport hauteur sur emprise = 3 : une gerbe étroite, serrée au pied (plateau de tallage de 5 à 8 cm), qui s'évase à peine vers les épis.
- Recoupement : des chaumes de 0,75 m partis d'un pied de 4 cm de rayon et ouverts de 6 à 9° donnent un rayon en tête de 4 + 75 × sin 8° ≈ 14 cm, soit 0,28 m d'emprise. Les trois chiffres tiennent ensemble si aucun chaume ne s'ouvre à plus de 9°.
- Feuilles à maturité : rares, sèches, étroites (1 à 1,5 cm), pliées et pendantes à mi-hauteur. Le pied est une paille nue.
- Exagération pour l'écran : à 20 px/m et ×2,5, un épi réel de 10 × 1,5 cm ferait 5 × 0,75 px. Le modèle le porte à 20 cm × 6 cm, les chaumes restent fins. L'épi fait alors un peu moins du quart de la hauteur.

## Traits qui font reconnaître, dans l'ordre
1. Des **épis dressés, compacts, épais**, dans l'axe du chaume, bien détachés en tête de la touffe, d'un doré plus soutenu que la paille.
2. Des **chaumes droits et nus**, jaune paille, serrés en gerbe étroite.
3. Une teinte **dorée de bout en bout**, sans vert : la plante est sèche.

## Confusions et ce qui sépare
- **Orge** : épi **penché**, crosse en fin de maturité, hérissé de très longues barbes qui font un plumet. Le blé a l'épi droit dans l'axe, net, sans barbes : un fuseau trapu et non une brosse.
- **Seigle** : très haut (1,5 m et plus), bleuté, épi long et fin. Le blé est deux fois plus bas, doré, à épi court et épais.
- **Avoine** : panicule lâche, épillets pendants au bout de rameaux fins, silhouette aérée. Le blé a un épi plein, d'un seul bloc.
- **Millet** : panicule retombante ou en massue touffue, feuilles larges. Le blé a un épi droit et presque pas de feuilles.
- **Ciboulette** (`ciboulette.n.js`) : brosse de brins verts, pompons mauves **ronds**. Le blé est doré, trois fois plus haut, et ses têtes sont des fuseaux allongés.
- **Poireau, ail** : fût blanc ou bulbe, feuilles plates vert bleuté. Le blé n'a ni fût ni feuillage vert.
- **Lavande** (`lavande.n.js`) : épis dressés aussi, mais violets, au-dessus d'un coussin gris plus large que haut.

## Couleurs (quatre au plus)
- Clair : `#e3cc85`, paille, les chaumes.
- Moyen : `#dba63a`, doré, le haut des épis.
- Sombre : `#b57f24`, ambre, le bas des épis : c'est sur l'épi que porte le plus fort contraste, saturé contre la paille pâle.
- Accent : `#b9a468`, paille terne, les feuilles sèches.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **60 triangles**, absolu, **4 couleurs**.
- Semé en nappe : le socle pose les touffes à 0,7 fois l'emprise, comme une fleur en parterre. Le modèle est une touffe, et la planche doit se lire en nappe dorée continue, piquée d'épis.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m).

## Jugement à l'aveugle (lot 55, t5)

3/3. Blé pour les trois juges, hésitants entre blé et orge : épi doré dressé sans barbes. Épis trapus, paille qui se fond dans le sol clair sur le rang.
