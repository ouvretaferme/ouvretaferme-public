# Lavande · fiche de référence

Lavande vraie (Lavandula angustifolia, Lamiacées), pied installé de deuxième ou troisième année, plantée en ligne sur la planche, en fleur. Sous-arbrisseau persistant : le coussin gris reste toute l'année, les hampes fleuries montent en juin-juillet et sèchent sur pied jusqu'à la taille de fin d'été. Le modèle montre la touffe fleurie, état qui la fait reconnaître et qui dure le plus longtemps hors hiver.

## Proportions
- Espèce : arbuste compact et buissonnant, 0,5 à 1 m de haut, un peu plus large que haut ; feuilles étroites gris-vert ; longues tiges nues non ramifiées portant des épis courts et denses, violet pâle à soutenu ([RHS, Lavandula angustifolia](https://www.rhs.org.uk/plants/91398/lavandula-angustifolia/details)).
- Cultivars de jardin mesurés par la RHS : 'Dwarf Blue', 40 cm de haut, hampes de 20 à 25 cm, épis de 3 à 4,5 cm ([RHS](https://rhs.org.uk/plants/70902/lavandula-angustifolia-dwarf-blue/details)) ; 'Middachten', 50 cm, hampes jusqu'à 22 cm, épis de 3 à 4 cm ([RHS](https://www.rhs.org.uk/plants/155994/lavandula-angustifolia-middachten/details)) ; 'Ashdown Forest', 60 cm, hampes jusqu'à 18 cm, épis de 2 à 3 cm ([RHS](https://www.rhs.org.uk/plants/103385/lavandula-angustifolia-ashdown-forest/details)).
- Jardinerie : 50 cm × 50 cm pour 'Anna', 60 × 60 cm pour la lavande vraie standard ([Truffaut](https://www.truffaut.com/lavandula-anna-pot-7-5l-1101177.html)).
- Unité modélisée : une touffe de 50 cm d'emprise (`d` 0,5 m). Coussin de feuillage : 50 cm de large, 28 cm de haut, en demi-boule un peu aplatie, posé sur le sol sans pied visible. Hampes : 20 cm au-dessus du coussin, épis de 4 cm. Hauteur totale ≈ 0,5 m, soit hauteur / emprise ≈ 1.
- Port des hampes : plusieurs dizaines par pied, nues, raides, fines, qui sortent de toute la calotte du coussin et rayonnent en gerbe, droites au centre, ouvertes jusqu'à 40° de la verticale au bord. Les épis finissent tous à peu près à la même distance du coussin : leurs têtes dessinent une seconde coupole, violette, au-dessus de la grise.
- Recoupement : un coussin de 25 cm de rayon et des hampes de 20 cm ouvertes à 40° au bord donnent une emprise de 25 + 20 × sin 40° ≈ 38 cm de rayon, un peu plus que les 50 cm annoncés ; on ouvre donc à 30° au plus et on part de la calotte, pas du bord, pour que l'emprise reste celle du coussin à 10 % près.
- Exagération pour l'écran : à 20 px/m un épi de 4 cm fait 2 px. Les épis sont portés à 8-9 cm et épaissis, les hampes restent fines. La gerbe violette doit se lire comme une couronne de points dressés au-dessus du gris, pas comme un trait continu.

## Traits qui font reconnaître, dans l'ordre
1. Des épis violets courts et dressés, nombreux, qui flottent au-dessus de la touffe au bout de hampes nues et rayonnent en gerbe.
2. Un coussin rond, bas, gris argenté, plein jusqu'au sol, sans tige ni bois visible.
3. Le jour entre le coussin et les épis : la lavande est en deux étages, gris en bas, violet en haut.

## Confusions et ce qui sépare
- **Romarin** (`romarin.n.js`, la voisine que les juges hésitent à départager) : un buisson en vase plus haut que large, vert sombre hérissé, sur un pied de bois brun, fleurs bleu pâle posées le long des rameaux. La lavande est ronde et basse, grise et non verte, sans bois visible, et son violet est en épis au bout de hampes nues qui dépassent nettement du feuillage. En silhouette : vase ouvert vers le haut contre coussin surmonté d'une couronne de piquets.
- **Thym** : petit dôme bosselé gris-vert, deux fois plus petit, rien ne dépasse. La lavande a la gerbe d'épis.
- **Ciboulette** : pompons ronds mauves sur hampes, mais dans une touffe d'herbe vert franc, dressée, sans coussin. La lavande a un coussin gris et des épis allongés, pas des boules.
- **Sauge officinale** : touffe gris-vert à larges feuilles, épis violets plus lâches et plus bas. La lavande a des épis fins et serrés bien au-dessus d'un coussin lisse.
- **Chou, caillou** : un dôme seul se lit pommé ou minéral. Les épis sont la garde : sans eux, pas de lavande.

## Couleurs (quatre au plus)
- Clair : `#aab4a0`, gris argenté, la calotte du coussin.
- Moyen : `#7f8c74`, gris-vert, le bas du coussin et les hampes.
- Sombre : `#5b3f8f`, violet profond, les épis : c'est sur eux que porte le plus fort contraste, foncé et saturé contre le gris clair.
- Accent : `#8f74c4`, violet lavande plus clair, la tête des épis.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **60 triangles**, absolu, **4 couleurs**.
- Plantée en ligne, pas en parterre : le socle pose les touffes sur le rang comme un légume. Le modèle est une touffe.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m), où la touffe fait une vingtaine de pixels. La couronne d'épis violets porte la reconnaissance ; le coussin gris n'est que son socle.

## Jugement à l'aveugle (version t5)
Lot 34, trois juges. Les trois la nomment lavande, deux sûrs, un hésitant, sur les épis violets au-dessus d'un coussin gris-vert ; seconde hypothèse sauge ou romarin en fleur. Défaut nommé par le juge exigeant : le coussin est un bloc plein et lisse qui se lit comme un galet, et les épis sont des pointes isolées plutôt que des massues serrées.

Reprise r1 à r8 sur ce défaut : le coussin n'est plus une calotte lisse mais un cœur bas et sombre débordé de quatre faisceaux de tiges claires, au contour hérissé ; les épis passent à 19, plus larges, sur hampes fines, serrés en boule au-dessus du gris.

## Jugement à l'aveugle (reprise r8, contre la t5)
Lot 39, trois juges, les deux versions côte à côte sans le savoir. r8 : lavande 3 sur 3, sûrs (« touffe gris-vert de feuilles linéaires, épis violets dressés, lecture immédiate au rang »). t5 : lavande 3 sur 3, hésitants, et le défaut du rocher nommé par les trois. r8 bat la t5 et lève le défaut, à 59 triangles contre 60. Reste nommé : épis en flammes larges plutôt que fins sur hampes nues.
