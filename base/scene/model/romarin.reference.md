# Romarin · fiche de référence

Romarin officinal (Salvia rosmarinus, ex-Rosmarinus officinalis), pied installé de deuxième ou troisième année sur une planche d'aromatiques, conduit par la coupe. Persistant, il reste sur la planche toute l'année ; il fleurit surtout au printemps et souvent une seconde fois en automne, de petites fleurs bleu pâle posées le long des rameaux, jamais en épi au bout d'une hampe.

## Proportions
- Arbrisseau dressé de 1 à 1,5 m pour l'espèce ([Jardiner Malin](https://jardiner-malin.fr/fiche/romarin.html)) ; 120 cm de haut pour 90 cm de large à maturité, port buissonnant, touffe érigée ([Truffaut](https://www.truffaut.com/romarin-varietes-culture-utilisation.html)). Les formes dressées montent plus haut que larges : 'Miss Jessopp's Upright', 1,5 à 2,5 m pour 1 à 1,5 m d'étalement ([RHS](https://rhs.org.uk/plants/82037/rosmarinus-officinalis-miss-jessopp-s-upright/details)), 1 m × 1 m chez [Shire Plants](https://www.shireplants.co.uk/rosmarinus-officinalis-miss-jessopps-upright-rosemary).
- Unité modélisée : un pied de 60 cm d'emprise (`d` 0,6 m), tenu par la coupe au potager. Hauteur / emprise ≈ 1,33 (Truffaut, 120/90), au milieu de 1 (Shire) et 1,6 (RHS) : **h ≈ 0,8 m**.
- Port : une souche courte d'où partent cinq à huit rameaux ligneux dressés, presque verticaux au centre, ouverts de 15 à 25° sur le pourtour. L'ensemble fait un vase, ou un fuseau évasé vers le haut, sans pointe unique : les têtes des rameaux finissent à des hauteurs différentes (60 à 100 % de la hauteur).
- Rameaux : bois gris-brun dégarni sur les 10 à 15 premiers centimètres, puis couverts sur toute leur longueur d'aiguilles serrées. Chaque rameau feuillé se lit comme un fuseau hérissé, plus épais au tiers inférieur, effilé en tête.
- Feuilles : aiguilles coriaces de 2 à 4 cm, vert foncé au-dessus, blanchâtres et feutrées en dessous ([Truffaut](https://www.truffaut.com/romarin-varietes-culture-utilisation.html)). Illisibles une à une : elles font le contour hérissé et le mélange sombre et gris des rameaux.
- Fleurs : petites (1 cm), bleu pâle, à l'aisselle des feuilles dans le haut des rameaux. Clairsemées, elles ponctuent le feuillage sans en changer la masse.
- Recoupement : six rameaux de 9 à 12 cm d'épaisseur feuillée, partant d'une souche de 15 cm, écartés de 20° au plus, couvrent 60 cm à 80 cm de haut (tan 20° × 0,7 m ≈ 25 cm de chaque côté, plus l'épaisseur du rameau). Emprise et hauteur tiennent ensemble.

## Traits qui font reconnaître, dans l'ordre
1. Une gerbe de rameaux dressés, vert sombre, plus haute que large, à têtes inégales : un petit buisson en vase.
2. Le contour hérissé de chaque rameau, sombre avec du gris (le revers des aiguilles).
3. Un pied de bois brun visible sous la gerbe, et quelques points bleu pâle dans le haut.

## Confusions et ce qui sépare
- **Thym** : petit dôme bas (22 cm), plus large que haut, gris-vert clair, cabossé. Le romarin est trois fois plus haut, plus haut que large, sombre, fait de rameaux distincts.
- **Lavande** : boule grise basse d'où jaillissent des épis violets sur de longues hampes nues. Le romarin n'a ni boule ni hampe : ce sont les rameaux feuillés eux-mêmes qui montent, et ses fleurs sont posées dessus, bleu pâle, pas en épi.
- **Sapin miniature** : un cône unique, symétrique, sur un tronc central, pointe nette au sommet. Le romarin a plusieurs têtes, s'évase vers le haut au lieu de se rétrécir, et n'a pas de tronc unique.
- **Asperge en feuillage** : grandes tiges vert tendre, plumeuses et transparentes, qui dépassent 1 m et ploient. Le romarin est dense, opaque, sombre, raide.

## Couleurs (quatre au plus)
- Sombre : `#34502f`, le dessus des aiguilles, l'essentiel des rameaux.
- Moyen : `#7d8f7c`, gris-vert, le revers des aiguilles sur les têtes et le flanc des rameaux extérieurs.
- Clair : `#a9c0e6`, bleu pâle, les fleurs.
- Bois : `#6e5940`, la souche et le bas des rameaux.

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **120 triangles**, absolu, **4 couleurs**.
- Ce qui doit marcher : la vue « jeu » de la planche (rang à 20 px/m). Avec l'exagération de la scène, le plant fait une trentaine de pixels de large et une quarantaine de haut. La gerbe sombre, dressée et à plusieurs têtes porte la reconnaissance ; le bois et les fleurs ne sont que des accents.

## Reprise
Trois juges à l'aveugle ont vu la t5 (douze baguettes pleines à trois pans, `h` déduit à 1,75 × `d`) : un seul a nommé le romarin, avec réserve ; les deux autres ont lu poireau, ou poireau, ail ou oignon. Les rameaux faisaient de larges lames plates vert très sombre en éventail serré, rubanées comme un poireau ou une agave, et les deux fleurs bleues disparaissaient dans le rang.

Ce que la reprise change (`romarin.n.js`, tours r1 à r5, retenu r5) :
- **Un rameau est une tige couverte d'aiguilles, plus une lame.** Un axe sans épaisseur, légèrement courbé vers l'extérieur, autour duquel quatre rangs de dents en croix pointent vers le haut et le dehors, une dent par segment et par rang. Les creux retombent sur l'axe : contour en dents de scie sous tous les angles. Trois rangs en étoile (r1 à r3) laissaient le rameau disparaître de profil et clairsemaient la masse au rang ; la croix (r4) le garde opaque.
- **Un buisson ligneux ramifié.** Quatre branches de bois brun (pyramides à trois pans, ouvertes de 20 à 30°) partent de la souche et portent chacune un rameau intérieur presque droit et un rameau extérieur plus ouvert. Le bois occupe le premier cinquième de la hauteur et se lit en jeu sous la gerbe. Ouvrir moins les branches (r5) a refermé le creux en V du centre.
- **Le revers gris plus présent** : un rang de dents sur quatre en gris-vert, deux sur la moitié des rameaux du pourtour, soit environ 30 % du feuillage. Le gris passe de `#7d8f7c` à `#8a9c88`, un cran plus clair pour éclaircir la masse.
- **Proportions corrigées** : `h` toujours déduit de la boite, mais la forme donne désormais 1,32 × `d` (planche r5 : 1,30 m × 1,71 m), contre 1,75 à la t5. Cible de la fiche : 1,33.
- Budget : 120 triangles tout juste (bois 12, rameaux 8 × 12 = 96, fleurs 3 × 4 = 12).

Ce qui reste faible : au rang à 20 px/m, la gerbe reste aérée, faite de brins hérissés plutôt que d'une masse continue ; les fleurs restent des accents à peine visibles. Voisines à surveiller au prochain jugement : thym (si la gerbe paraît trop basse et grise), lavande, sapin (rameaux à dents montantes), et toujours poireau et ail.

## Jugement à l'aveugle (version r5)
Lot 31, trois juges. Les trois la nomment romarin, un sûr et deux hésitants, avec la lavande en seconde hypothèse. La version précédente était lue poireau. Défaut restant, nommé par l'Opus : fleurs trop rares pour être lues, aiguilles assez grandes pour faire hésiter avec un petit conifère, touches bleues presque perdues dans le rang.
