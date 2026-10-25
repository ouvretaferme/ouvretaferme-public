# Groseillier à grappes · fiche de référence

Groseillier à grappes (Ribes rubrum, grossulariacées), arbuste fruitier caduc, pied adulte conduit en touffe au jardin et taillé chaque fin d'hiver. La fiche le décrit en feuilles, d'avril à octobre. Les grappes de groseilles rouges mûrissent en juillet et sont posées par le socle (ligne `FRUITS`), pas par le modèle.

## Proportions
- Hauteur adulte 1,5 m pour 1 m d'étalement ('Versaillaise Blanche', [Truffaut](https://www.truffaut.com/groseiller-grappes-versaillaise-blanche-5-litres-523588.html), [Nature & Découvertes](https://www.natureetdecouvertes.com/jardin/jardinage/semis-fruits/groseillier-a-grappes-versaillaise-blanche-pot-de-1.5l-92592980)) ; 1,5 m sur 1,5 m pour 'London Market' ([Truffaut](https://www.truffaut.com/groseillier-grappes-rouge-london-market-pot-1-5-litres-454446.html)) ; 1,5 à 2,5 m de haut sur 1 à 1,5 m pour l'espèce non taillée ([RHS](https://www.rhs.org.uk/plants/15884/common-redcurrant/details)).
- Unité modélisée : un pied de 1 m d'emprise (`d` 1,0 m), tenu par la taille. Hauteur / emprise entre 1 (London Market) et 1,5 (Versaillaise) : **h ≈ 1,2 m**.
- Port : une touffe d'une dizaine de branches charpentières (« conservez les 12 branches principales », [Jette, fiche groseillier](https://jette.brussels/fr/pdf/proprete-et-environnement/arbres-fruitiers-groseillier)) qui partent d'une souche étroite, s'ouvrent de 20 à 35° de la verticale et se redressent en tête. Le centre est dégagé à la taille pour la lumière (même fiche) : la touffe fait un **gobelet**, étroit au pied, large en tête.
- Bois : écorce gris-brun, nue sur le premier quart de la hauteur. Les grappes naissent sur les coursonnes du bois de deux ans et plus, le long des charpentières, donc dans la moitié basse et moyenne de la touffe, sous les feuilles.
- Feuilles : palmées à 3 ou 5 lobes ([RHS](https://www.rhs.org.uk/plants/15884/common-redcurrant/details)), lobes deltoïdes grossièrement dentés, 6 à 11 cm sur 8 à 13 cm ([NZ Flora](https://nzflora.info/factsheet/Taxon/Ribes-rubrum.html)), plus larges que longues, à long pétiole. Vert franc assez clair, mat, revers plus pâle. Alternes, en bouquets sur les coursonnes, elles couvrent surtout le haut et le pourtour des branches.
- Grappes : pendantes, 6 à 20 baies ([Truffaut, London Market](https://www.truffaut.com/groseillier-grappes-rouge-london-market-pot-1-5-litres-454446.html)), une grappe de 6 à 10 cm de long, baies rondes de 8 à 10 mm, rouge vif translucide, alignées en chapelet sur un axe fin.
- Recoupement : dix branches ouvertes de 25° en moyenne sur 1,2 m donnent tan 25° × 1,1 m ≈ 0,5 m de chaque côté au sommet, plus 0,1 m de souche : 1,0 à 1,1 m d'emprise. Emprise, hauteur et ouverture tiennent ensemble.

## Traits qui font reconnaître, dans l'ordre
1. Les grappes pendantes de petites baies rouges translucides en chapelet, accrochées sous les feuilles le long des branches (posées par le socle).
2. Un gobelet de branches dressées et ouvertes, bois gris-brun visible au pied, tête plus large que la base, centre aéré.
3. Des feuilles palmées à lobes pointus, vert franc clair, en bouquets étagés le long des branches.

## Confusions et ce qui sépare
- **Cassissier** (modélisé en parallèle) : touffe plus dense, plus ronde, garnie jusqu'au sol de jeunes pousses, feuillage plus grand et plus sombre ; baies noires, grappes courtes de 5 à 10 baies qui ne pendent guère. Le groseillier est un gobelet ouvert, bois nu au pied, feuillage plus clair et plus fin, et ses longues grappes rouges pendent sous les feuilles.
- **Groseillier à maquereau** : buisson bas, branches arquées vers le sol, épineux, petites feuilles, gros fruits isolés. Le groseillier à grappes est plus haut, dressé, et porte des grappes.
- **Framboisier** : cannes droites non ramifiées en rang palissé, feuilles composées (3 à 5 folioles) à revers blanc. Le groseillier est une touffe ronde en gobelet, à feuilles simples lobées.
- **Tomate, physalis** : plantes herbacées tuteurées ou ramifiées sur une tige verte. Le groseillier a du bois gris-brun et sa tête est plus large que son pied.

## Couleurs (quatre au plus)
- Clair : `#7fb043`, le dessus des feuilles du haut et du pourtour, qui prennent la lumière.
- Moyen : `#4c7e2c`, les feuilles du dedans et du bas, à l'ombre.
- Sombre : `#6b5a48`, le bois gris-brun des charpentières.
- Accent (socle) : `#d42a2e`, les groseilles mûres, avec l'éclat translucide qui les sépare du cassis noir.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **300 triangles**, absolu, **4 couleurs**. Famille de repli `berry`.
- Échelle : sur une planche de 1 m, la scène affiche ce pied en 1,3 m d'emprise sur 1,95 m de haut (`plantSize`, `socle.js`). Le modèle se dessine dans ces proportions de scène.
- Ce qui doit marcher : le rang à 20 px/m, où le pied fait environ 26 px sur 39. Le gobelet clair sur pied brun porte la silhouette ; les grappes rouges posées par le socle doivent se détacher sous les feuilles, donc le feuillage leur laisse un étage dégagé à mi-hauteur.

## Jugement à l'aveugle (au banc, avec FRUITS)
Lot 35, trois juges. Les trois le nomment groseillier à grappes, sûrs, sur les chapelets rouges pendants ; seconde hypothèse cassis. Défaut nommé : les feuilles en étoile font un peu érable.
