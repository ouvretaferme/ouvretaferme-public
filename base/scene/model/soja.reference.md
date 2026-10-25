# Soja · fiche de référence

*Glycine max*, fabacée annuelle de plein champ, semée en rangs. Stade retenu : **plant vert en pleine végétation, au remplissage des gousses**. Le feuillage est entier du pied à la tête, la tige est verte, les gousses sont formées et d'un vert jaune plus clair que les feuilles. Les gousses sont dans le plant, pas posées par le socle.

Stade écarté : le début de maturité (version t5, gardée dans `soja.t5.js`). Trois juges à l'aveugle sur trois y ont lu une vigne ou un houblon : la tige brune passait pour un sarment, les gousses paille pour des feuilles sèches, et elles se perdaient sur le sol sable.

## Proportions
- Hauteur : 0,2 à 1,8 m selon les variétés, 0,6 à 0,8 m pour celles cultivées en France. Retenu : **0,65 à 0,7 m**.
- Emprise : **0,35 m**. Sur pied, le plant est deux fois plus haut que large. Le modèle est **trapu, à peine plus haut que large** (h rendu 0,39 m pour d = 0,35 m) : c'est un écart voulu à la consigne de reprise, qui demande un buisson, et il sort de la marge de 20 %.
- Port : buisson dressé. Une tige principale raide, velue, verte tant que le plant végète. Le modèle ne porte que la tige principale.
- Feuilles : alternes sur deux rangs, étagées du pied à la tête, **à trois folioles** au bout d'un long pétiole de 8 à 15 cm. Foliole ovale à pointe, 3 à 15 cm sur 2 à 6 cm, soit 9 à 10 cm sur 5 à 6 cm pour une variété courante (rapport 1,7). La terminale est dans l'axe, les deux latérales s'ouvrent de part et d'autre.
- Gousses : **courtes, 2,5 à 8 cm sur 1 à 1,5 cm**, 4 à 5 cm le plus souvent, velues, 2 à 3 graines, **en paquets de deux à quatre à chaque nœud**, à l'aisselle des feuilles, légèrement arquées, bosselées par les grains. Vert jaune au remplissage, paille à brun fauve à maturité.

Recoupement : un pétiole de 7 cm relevé à 40° et une foliole de 10 cm déportent la pointe de 15 à 17 cm de l'axe, soit 0,3 à 0,35 m d'emprise. Une douzaine de nœuds à 5 cm font 0,6 m de tige. Les deux tiennent avec 0,35 m sur 0,7 m.

Limite des sources : la taille des folioles et des gousses et la pilosité sont sourcées. Le nombre de gousses par nœud (deux à quatre) et la disposition des feuilles sur deux rangs viennent de la consigne et de la pratique de la culture, les pages trouvées ne les chiffrent pas. Le modèle grossit les gousses (environ 11 cm à l'échelle du plant, soit la longueur d'une foliole) et n'en garde que trois paquets de trois.

## Traits qui font reconnaître, dans l'ordre
1. Des feuilles à trois folioles ovales nettement séparées, au bout d'un pétiole relevé, étagées sur toute la hauteur.
2. Des paquets de gousses courtes et épaisses, vert jaune, qui pendent contre la tige entre les étages de feuilles.
3. Un buisson trapu sur une tige verte, sans rame ni palissage.

## Confusions et ce qui sépare
- **Vigne, houblon** : feuille d'une seule pièce à lobes pointus, sarment brun, palissage. Le soja a trois ovales distincts par feuille, une tige verte et tient seul. C'est la confusion des juges sur la t5.
- **Haricot nain** : feuilles par trois aussi, longues gousses fines pendantes posées par le socle. Les gousses du soja sont courtes, ventrues, en paquets contre la tige.
- **Féverole** (`feverole.n.js`) : chandelle unique de 1,1 m, gris vert bleuté, folioles par paires, fleurs blanches à macule noire. Le soja est un buisson bas et large, vert franc, à feuilles par trois.
- **Luzerne** (`luzerne.n.js`) : touffe en nappe de petits trèfles, grappes mauves. Le soja montre sa tige, n'a aucune fleur visible et porte des gousses.
- **Pois** (`pois.n.js`) : rideau glauque sur filet. Le soja n'a ni filet ni vrille.

## Couleurs (quatre au plus)
- Clair : `#dfdb50`, les gousses, vert jaune.
- Moyen : `#5fa83a`, les trois feuilles de tête, vert tendre.
- Sombre : `#2f7d32`, les cinq feuilles du bas.
- Tige : `#8cbf52`, la tige et les pétioles.

## Sources
- Madhya Pradesh State Biodiversity Board, herbier virtuel, *Glycine max* : https://mpforest.gov.in/MPSBB/virtualherbarium/Home/PlantDetail/360 (herbe annuelle buissonnante de 20 à 180 cm, tiges à poils bruns ou gris, folioles de 3 à 15 cm sur 2 à 6, gousses velues de 2,5 à 8 cm sur 1 à 1,5, 2 à 3 graines).
- GRIN Czech, fiches de variétés de soja : https://grinczech.carc.cz/gringlobal/accessiondetail.aspx?id=28471 (foliole centrale de 9 à 10 cm sur 5 à 6, pilosité brun jaune).
- Laji.fi, *Glycine max* : https://laji.fi/en/taxon/MX.38970.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » (rang à 20 px/m), où le plant fait une trentaine de pixels de haut.

## Jugement à l aveugle (lot 59, reprise r5)

2 juges sur 3 : « haricot ou soja », « soja ou haricot » ; le troisième lit un haricot nain beurre, soja en second. La t5 (plant mûr) avait fait 0 sur 3, lue vigne ou houblon. Défauts nommés : feuilles pas lisiblement trifoliolées, gousses en lames plates qui peuvent passer pour un piment jaune ou des feuilles jaunies.
