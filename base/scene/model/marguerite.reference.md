# Marguerite (Leucanthemum vulgare) · fiche de référence

Touffe en pleine floraison, de mai à juillet, en parterre au potager. Après la première floraison, une coupe la fait remonter : c'est la touffe fleurie qu'on voit la plus grande partie de la saison. Forme sauvage, pas les grandes marguerites d'ornement (Leucanthemum × superbum, 1 m et fleurs de 10 cm).

## Proportions
- Hauteur en fleur : 30 à 80 cm (RHS 0,5 à 1 m, flore du Nord-Ouest pacifique 20 à 80 cm, guide britannique 60 cm au plus). On retient 0,6 m.
- Emprise d'une touffe : 0,1 à 0,5 m selon la RHS ; la plante s'étend par rhizomes en colonies. On retient d = 0,35 m. Rapport hauteur sur emprise d'environ 1,7.
- Port : une touffe dressée de tiges raides, simples ou peu ramifiées, garnies de feuilles dentées étagées jusqu'à mi-hauteur au moins, chacune terminée par un seul capitule. La rosette basale existe mais disparaît sous les feuilles de tige en floraison : c'est ce qui la sépare de la pâquerette, rosette plaquée à hampes nues. Les capitules sont étagés entre les quatre cinquièmes et le sommet, tournés vers le ciel.
- Feuilles basales : en spatule ou en cuillère, 5 à 13 cm, sur un long pétiole, le limbe élargi au bout, bordé de dents arrondies ou de lobes. Vert sombre, un peu luisant. Les feuilles de tige sont oblongues, dentées, sessiles et embrassantes, montantes, plus petites et plus rares en montant.
- Capitules : 3 à 6 cm de diamètre (jusqu'à 7,5 cm). 15 à 35 ligules blanches, étroites, serrées, à bout arrondi ou à peine denté, autour d'un disque jaune vif et plat qui fait environ le tiers du diamètre. Le capitule est plat, de face vers le ciel.
- Nombre de capitules ouverts en même temps : une dizaine sur une touffe adulte. On en dessine quatre, à des hauteurs différentes.

## Traits qui font reconnaître, dans l'ordre
1. Des capitules tout blancs, frangés de nombreuses ligules étroites, autour d'un gros cœur jaune vif bien visible : l'œil de bœuf.
2. Une touffe dressée de tiges feuillées de feuilles dentées vert soutenu jusqu'à mi-hauteur : le vert plein est en bas, le blanc en haut.
3. Une touffe une fois et demie à deux fois plus haute que large, qui fait en rang un tapis vert sombre piqué de points blancs à cœur jaune.

## Confusions et ce qui sépare
- **Cosmos (`cosmos.n.js`)** : touffe haute de 1 m, buisson de plumes vert franc jusqu'aux deux tiers, fleurs roses et une blanche à huit ligules larges en coin et petit disque. La marguerite est plus basse, n'a que du blanc, des ligules nombreuses et étroites autour d'un disque deux fois plus gros, et son feuillage est une rosette sombre au pied, pas un buisson plumeux.
- **Camomille (à venir)** : touffe plus petite, feuillage finement découpé en fils vert tendre jusqu'aux fleurs, beaucoup de petits capitules (1,5 à 2,5 cm) à disque bombé. La marguerite a des fleurs deux fois plus grandes, moins nombreuses, un disque plat, et des feuilles pleines en spatule sombres au pied.
- **Souci (`calendula.n.js`)** : coussin bas, feuilles vert clair, fleurs orange. La marguerite est plus haute, sombre au pied, et blanche.
- **Achillée (`achillee.n.js`)** : plateaux de petites fleurs fondues, sans cœur. La marguerite a des fleurs séparées, chacune avec son cœur jaune.

## Couleurs (quatre au plus)
- Clair : `#f6f4ec`, les ligules blanches, le plus fort contraste avec le feuillage sombre.
- Accent : `#f3be1c`, le disque jaune vif.
- Moyen : `#6f9a45`, les tiges et les petites feuilles de tige.
- Sombre : `#3d6a2e`, la rosette de feuilles basales.

## Sources
- RHS, *Leucanthemum vulgare* (hauteur 0,5 à 1 m, étalement 0,1 à 0,5 m) : https://www.rhs.org.uk/plants/32804/ox-eye-daisy/details
- Minnesota Wildflowers (15 à 35 ligules, feuilles basales en spatule à lobes ou grandes dents, longuement pétiolées, jusqu'à 13 cm) : https://minnesotawildflowers.info/flower/ox-eye-daisy
- Kansas Wildflowers (capitules de 3 à 6 cm, 15 à 35 ligules) : https://kswildflower.org/plants/leucanthemum-vulgare
- PNW Flowers (tiges de 20 à 80 cm) : https://www.pnwflowers.com/flower/leucanthemum-vulgare
- UK South West wildflowers (jusqu'à 60 cm, jusqu'à 35 ligules, feuilles basales oblancéolées) : https://www.uksouthwest.net/wildflowers/asteraceae/leucanthemum-vulgare.html
- Naturblick, Museum für Naturkunde Berlin (capitules solitaires, tige dressée simple ou peu ramifiée) : https://naturblick.museumfuernaturkunde.berlin/species/portrait/383?lang=en

Recoupement : 0,6 m de haut pour 0,35 m d'emprise, avec des capitules de 5 cm, donne une touffe où sept capitules tiennent dans la largeur et des tiges nues sur la moitié supérieure : cohérent avec le port décrit (rosette au pied, fleurs en tête). Le modèle prend des capitules d'environ 9 cm, au-delà de la fourchette, pour qu'ils restent lisibles à 20 px/m.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **60 triangles**, **4 couleurs**.
- Fleur en parterre : le socle pose les touffes à 0,7 × d avec un léger désordre. Le modèle est une touffe, et le rang doit se lire en tapis vert sombre piqué de blanc à cœur jaune.

## Jugement à l'aveugle (version t4)
Lot 38, trois juges. Le juge exigeant la nomme marguerite, hésitant ; les deux autres écrivent « pâquerette ou marguerite », sûrs du type, sur les capitules blancs à cœur jaune au-dessus d'une rosette basale. Comptée comme l'aneth au lot 29 : l'espèce est nommée dans la colonne de lecture chez les trois. Défaut nommé : le cœur jaune est un triangle décentré et non un disque bombé, les rayons sont courts et peu nombreux, et la taille ne sépare pas encore de la pâquerette.

## Jugement à l'aveugle (version t4, témoin au lot 43) et reprise
1 sur 3. Deux juges lisent une pâquerette (rosette basse, hampes fines, taille de la fleur introuvable) ; le juge exigeant la nomme grâce aux feuilles de tige mais relève des spatules lisses sans dents et un capitule petit, rien qui la sépare de la camomille. Reprise r1 à r4 : plus de rosette plaquée, quatre tiges garnies de deux feuilles dentées chacune jusqu'à mi-hauteur, quatre capitules de rayon 0,07 (contre 0,05) à sept ligules longues autour d'un cœur pentagonal plat, même budget de 60 triangles.

## Confusion ajoutée
- **Pâquerette (hors catalogue, confusion des juges)** : rosette plaquée au sol, hampes nues de 10 cm, capitule de 2 cm. La marguerite est dressée, feuillée sur la tige, et ses capitules sont deux à trois fois plus grands.

## Jugement à l'aveugle (lot 46, r4 face à la t4)
La r4 est nommée marguerite par le premier juge Sonnet (avec certitude) et par le juge exigeant, la t4 une fois seulement, deux juges y lisant une pâquerette. La r4 remplace la t4. Défaut relevé : des feuilles en triangles sombres sans caractère, alors qu'elles devraient être spatulées et crénelées pour la séparer de la camomille.
