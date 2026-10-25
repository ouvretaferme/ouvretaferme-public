# Lupin · fiche de référence

Lupin vivace (Lupinus polyphyllus et ses hybrides Russell), Fabacées. Pied installé de deuxième année, planté en ligne ou isolé, en fleur. Les feuilles sortent en avril, les épis fleurissent de fin mai à juillet et remontent si on les coupe avant les gousses. Le modèle montre la touffe fleurie, l'état qui la fait reconnaître.

## Proportions
- Hauteur 0,5 à 1 m, emprise 0,5 à 1 m pour une souche de type Russell ('Tutti Frutti', [RHS](https://www.rhs.org.uk/plants/176495/lupinus-tutti-frutti/details)) ; 0,7 à 1,3 m de haut sur 0,3 à 0,6 m de large, port raide, dressé, en pyramide étroite ([KPU Plant Database, Lupinus polyphyllus](https://plantdatabase.kpu.ca/Plant/lupo)) ; épis denses jusqu'à 90 cm ([West Coast Seeds, Russell Hybrids](https://www.westcoastseeds.com/products/russell-hybrids)).
- Feuilles palmées en étoile, 9 à 17 folioles de 3 à 15 cm, étroites, lancéolées, rayonnant d'un même point au bout d'un long pétiole ([Wikipedia, Lupinus polyphyllus](https://en.wikipedia.org/wiki/Lupinus_polyphyllus) ; KPU). Les feuilles forment un dôme de 40 à 50 cm au pied, tenues presque à plat, en parasol.
- Épi : jusqu'à 50 cm, garni serré de fleurs papilionacées de 1 à 3 cm en verticilles ([Promesse de Fleurs, 'Les Pages'](https://www.promessedefleurs.ie/perennials/perennials-by-variety/lupinus-lupin/lupinus-polyphyllus-les-pages.html)) ; fleurs de 1 à 1,5 cm (Wikipedia). Les fleurs s'ouvrent du bas vers le haut : la pointe reste en boutons plus pâles. Les Russell sont souvent bicolores, étendard blanc ou crème sur des ailes colorées.
- Unité modélisée : emprise `d` 0,5 m, hauteur 1 m, soit hauteur / emprise = 2.
  - Dôme de trois feuilles en étoile de 10 à 11 folioles : 0,5 m de large, étagées de 0,14 à 0,33 m, en parasol penché vers dehors.
  - Épi maître : de 0,42 à 1 m, soit 58 cm de long (50 cm au plus en vrai, allongé de 15 % pour l'écran), 15 cm de diamètre à la base (10 cm en vrai, exagéré pour rester un cône plein en jeu), conique jusqu'à la pointe en boutons pâles sur le dernier quart. Deux épis secondaires de 40 et 34 cm, culminant vers 0,77 et 0,67 m, penchés de 8 à 11°.
- Recoupement : un épi de 15 cm de diamètre long de 58 cm a un rapport longueur / largeur de 4, contre 6 à 9 pour la chandelle du delphinium ; trois épis partant à moins de 5 cm de l'axe et penchés de 11° au plus restent dans un cercle de 0,2 m à 0,7 m de haut (4,5 + 40 × sin 11° + 6 ≈ 18 cm) : l'emprise est celle du feuillage, et la touffe se lit en trois cônes serrés au-dessus d'un dôme de deux fois leur largeur. Le pied fait 1 m sur 0,5 m comme demandé.
- Exagération pour l'écran : à 20 px/m et ×2,5, l'épi maître fait 7 px de large et 29 px de haut, assez pour un cône plein. Les folioles sont allongées (17 cm) pour que l'étoile reste lisible.

## Traits qui font reconnaître, dans l'ordre
1. Des épis en cône plein et dense, larges à la base, effilés en pointe, de fleurs serrées sans jour entre elles, violet mauve avec la pointe pâle en boutons.
2. Des feuilles palmées en étoile plate : 9 à 15 folioles étroites rayonnant d'un point, comme les rayons d'une roue.
3. Plusieurs épis de longueurs inégales, à peine plus hauts que larges le double du dôme : la touffe est deux fois plus haute que large, pas trois.

## Confusions et ce qui sépare
- **Delphinium** (`delphinium.n.js`) : chandelles bleu profond, minces et aérées, où l'on voit la hampe entre des fleurs plates à œil blanc, pointe sombre ; feuilles découpées en coin, en lanières fourchues retombant en éventail ; deux fois et demie plus haut que large. Le lupin a un cône plein et trapu, violet mauve à pointe claire, sur des étoiles plates de folioles fines.
- **Digitale** (hors catalogue) : clochettes tubulaires pendantes d'un seul côté de la hampe, roses tachetées, sur une rosette de grandes feuilles entières et duveteuses. Le lupin porte ses fleurs tout autour de l'axe, en cône symétrique, et sa feuille est digitée.
- **Lavande** : brosse de petits épis sur un coussin gris. Le lupin a trois gros épis sur un dôme vert.
- **Muflier** : épis plus lâches de fleurs à gueule, feuilles étroites le long de la tige. Le lupin a ses feuilles toutes au pied, en étoile.

## Couleurs (quatre au plus)
- Moyen : `#7a52c2`, violet mauve, le corps des épis.
- Clair : `#ece2f7`, blanc lilas, la pointe des épis en boutons et l'étendard des fleurs bicolores : le plus fort contraste, contre le violet et le vert.
- Feuille : `#5c9440`, vert franc un peu glauque, les étoiles de folioles.
- Sombre : `#33602a`, vert sombre, les tiges et le dessous des folioles basses.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Grande fleur : plafond **120 triangles**, absolu, **4 couleurs**.
- Pas de parterre : le pied se plante en ligne ou isolé. Il doit se lire seul, sur un rang à 20 px/m.
- Les épis portent la reconnaissance et reçoivent le plus de triangles après les étoiles de feuilles, qui sont le second trait.

## Jugement à l'aveugle (lot 44, t5)
Nommé sûr par les deux premiers juges, delphinium en deuxième hypothèse : épis coniques denses et feuilles palmées en étoile ont tranché, posé en côte à côte avec la digitale et le delphinium.
