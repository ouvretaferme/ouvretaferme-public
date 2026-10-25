# Poireau · fiche de référence

Plant adulte au potager, de la fin de l'été à la récolte d'hiver : fût blanchi par le buttage, éventail de feuilles complet. Pas de hampe florale (elle ne vient que la deuxième année, après la récolte).

## Proportions
- **Hauteur** du plant : 60 à 90 cm (Minnesota, 2 à 3 pieds ; RHS 'Jolant', 0,5 à 1 m ; Wisconsin, 'Rival' jusqu'à 90 cm). On retient **65 cm**, une variété d'hiver de potager ('Bleu de Solaise', 50 cm à maturité chez Truffaut, en est le bas de la fourchette).
- **Fût** (gaines emboîtées, blanchies) : 20 à 30 cm de long (RHS 'Jolant', jusqu'à 30 cm ; Wisconsin, 'Jolant' 20 à 23 cm, 'Dawn Giant' jusqu'à 38 cm), **2,5 à 5 cm** de diamètre (Minnesota, plus d'un pouce à maturité ; RHS 4 cm ; 'Dawn Giant' 5 cm). Droit, cylindrique, à peine renflé au pied. On retient **25 cm sur 4 cm**, soit **40 % de la hauteur** et un rapport longueur sur diamètre de 6. Le haut du fût passe du blanc au vert pâle sur quelques centimètres, là où les gaines sortent de terre et deviennent limbes.
- **Feuilles** : 30 à 60 cm de long (RHS 30 à 50 ; KPU 30 à 60), **2 à 5 cm** de large au milieu (KPU 2 à 3 ; PROSEA jusqu'à 7 pour les plus larges), plates ou pliées en V peu profond, une légère carène dessous chez les poireaux d'hiver (Ontario ; UF/IFAS). Entières, effilées en pointe.
- **Nombre** : 6 à 9 limbes visibles sur un plant en pleine saison (photos de rangs ; Flora of Victoria donne 4 à 10 pour l'espèce sauvage). Pas de source chiffrée pour le poireau cultivé.
- **Port** : feuilles alternes sur deux rangs, donc **en éventail dans un seul plan** (UF/IFAS : « en éventail, s'étalant dans deux directions »). Le cœur est dressé ; les feuilles extérieures s'arquent à partir du haut du fût, passent l'horizontale et **retombent**, la pointe plus basse que le coude.
- **Emprise** : dans le plan de l'éventail, une feuille de 45 cm qui s'arque jusqu'à l'horizontale s'écarte d'environ 25 cm du fût, soit **45 à 55 cm** d'un bout à l'autre ; en travers, 10 à 15 cm (le fût et le léger désalignement des feuilles). Recoupement : 25 cm de fût, plus la montée d'une feuille arquée (environ 0,6 × 45 ≈ 27 cm, elle se couche au-delà), donnent 52 cm au coude des extérieures et 65 cm avec les feuilles du cœur, dressées : cohérent avec la hauteur retenue. Emprise sur hauteur dans le plan : **0,75**.

Le modèle exagère, pour tenir à 20 px/m, le diamètre du fût (rapport longueur sur diamètre ramené vers 4) et la largeur des feuilles (rapport longueur sur largeur vers 7).

## Traits qui font reconnaître, dans l'ordre
1. Un **fût blanc, haut, droit et épais**, sans renflement en bulbe, qui porte le feuillage à 40 % de la hauteur.
2. Des **feuilles larges et plates, vert bleuté**, sorties du haut du fût **dans un seul plan**, qui s'arquent et **retombent** de part et d'autre : un éventail en fontaine, plus large en haut qu'au pied.
3. Un **passage du blanc au vert pâle** au haut du fût, là où les feuilles se séparent.

## Confusions et ce qui sépare
- **Ail** (lu au lieu du poireau aux tours 2 et 3) : tête blanche en goutte au pied, striée de violet, col court et fin, lames vert gris plus étroites, raides, peu retombantes. Le poireau n'a **aucune tête** : un fût droit, long et épais, puis des feuilles larges qui retombent.
- **Oignon** (deuxième hypothèse au tour 4) : gros bulbe doré posé au sol, tubes ronds dressés tout autour du col, pas d'éventail. Le poireau a un fût blanc sans bulbe et des feuilles plates en éventail dans un plan.
- **Maïs** (le poireau était sa deuxième hypothèse au tour 4) : tige verte bien plus haute, feuilles alternes tout le long de la tige, panicule au sommet. Le poireau garde ses feuilles au-dessus d'un fût blanc nu, et reste plus bas que large en haut.
- **Endive** (le poireau était sa deuxième hypothèse au tour 1) : pomme serrée blanc jaune, sans fût nu ni feuilles retombantes.
- **Palmier, yucca** (risque de la version actuelle) : feuilles raides et pointues dressées en étoile au sommet d'un tronc fin. Le poireau a un fût épais et des feuilles larges qui retombent, dans un plan.

## Couleurs (quatre au plus)
- Clair : `#f1ede0` (accent d'`especes.json`), le fût, blanc cassé.
- Clair vert : `#b9cf98`, le pied des feuilles, où le fût verdit.
- Moyen : `#5f9488`, les feuilles du cœur, vert bleuté.
- Sombre : `#3d6e66`, les feuilles extérieures et retombantes, vert bleuté profond (la feuille d'hiver « bleue »).

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond relevé à **120 triangles** (le modèle en compte 81), **4 couleurs**. Jusqu'à 1 344 plants le même jour.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où le plant fait une quinzaine de pixels de haut.

## Sources
- University of Minnesota Extension, *Growing leeks in home gardens* : hauteur 2 à 3 pieds, fût de plus d'un pouce à maturité, feuilles épaisses, plates et pliées. https://extension.umn.edu/vegetables/growing-leeks
- University of Wisconsin Extension, *Leeks* : éventail de feuilles plates bleu-vert d'un à deux pieds, 'Rival' 36 pouces avec 9 pouces de fût, 'Dawn Giant' 15 pouces sur 2. https://hort.extension.wisc.edu/articles/leeks/
- RHS, *Allium porrum (Swiss Giant Group) 'Jolant'* : 0,5 à 1 m, fût jusqu'à 30 cm sur 4 cm. https://www.rhs.org.uk/plants/165498/allium-porrum-swiss-giant-group-jolant/details
- UF/IFAS, *Plant identification: leek* : feuilles lisses et plates, en éventail dans deux directions. https://gardeningsolutions.ifas.ufl.edu/mastergardener/resources/plantid/vegetables/leek
- KPU Plant Database, *Allium ampeloprasum* : feuilles de 30 à 60 cm sur 2 à 3 cm au milieu. https://plantdatabase.kpu.ca/Plant/alam
- PROSEA, *Allium ampeloprasum* : limbes de 10 à 50 cm sur 0,2 à 7 cm, plats ou en V. https://uses.plantnet-project.org/en/Allium_ampeloprasum_(PROSEA)
- Ontario, *La culture du poireau* : poireaux d'hiver à feuilles larges bleu vert et fût court et épais. https://www.ontario.ca/fr/page/la-culture-du-poireau
- Truffaut, *Poireau 'Bleu de Solaise'* : 50 cm à maturité, gros fût. https://www.truffaut.com/poireau-bleu-solaise-b50-518221.html
- Flora of Victoria, *Allium ampeloprasum* : 4 à 10 feuilles (espèce sauvage). https://vicflora-test.rbg.vic.gov.au/flora/taxon/cd111252-34ed-4dfa-845e-e9f4349fdbb5

## Jugement à l'aveugle (version p)

Au lot 25, la version p est nommée poireau par les 3 juges, tous sûrs. L'ancienne est nommée poireau par les 3 juges, deux en hésitant : son panache court et touffu la rapproche de la ciboule. La version p compte 60 triangles : feuilles repliées en V, alternées de part et d'autre de la gaine, les extérieures arquées qui retombent. Défauts relevés par le juge exigeant : les feuilles ne s'ouvrent pas encore sur un seul plan, et un plant vu par la tranche se réduit à un bâton à plumet.

## Restructuration du 2026-10-08

Demandée sur une capture de la scène, où le fût se lisait comme une chandelle blanche nue sous un plumet. Le modèle s'écarte de la fiche sur deux points, voulus : le fût blanc ne monte plus qu'à 14 cm (22 % de la hauteur au lieu de 40 %), et les feuilles sortent du plan de l'éventail de 0,3 rad de part et d'autre, pour qu'un plant vu par la tranche garde une largeur. La gaine vert pâle s'élargit en éventail sur trois étages jusqu'à 36 cm, et les six feuilles sont plus larges. 81 triangles, 4 couleurs. Cette version n'a pas été rejugée à l'aveugle.

## Raccord des feuilles au fût, 2026-10-10

Demandé sur une capture de la scène, où les feuilles se lisaient posées sur le haut de la gaine. Chaque feuille prend naissance 11 cm plus bas, dans la gaine, par un pied vert pâle de la couleur de la gaine, qui en sort sur le premier tiers de la lame avant de virer au bleuté. 99 triangles, 4 couleurs. Cette version n'a pas été rejugée à l'aveugle.
