# Électrovanne, fiche de référence

Couvre `electrovanne.js`, la vanne commandée électriquement qui ouvre un secteur d'arrosage depuis un programmateur. Source :
aucune photo, les électrovannes 1" des catalogues d'arrosage (corps en ligne, bobine noire sur le dessus, connecteur et câble).
Elle reprend la vanne manuelle que la scène dessine en ligne sur la conduite, aux mêmes cotes, pour que les deux se lisent comme
deux organes de la même famille et se calent de la même façon.

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Tube | 12 cm de diamètre, 50 cm de long, axe à 8 cm du sol | celui de la vanne manuelle de la scène |
| Corps | 20 cm sur 16, 16 cm de haut | un peu plus long que celui de la vanne manuelle, comme le corps d'une électrovanne |
| Bobine | un cube de 12 cm sur un col de 3 cm, coiffé d'un écrou | plus étroite que le corps, comme la bobine réelle |
| Connecteur | 8 cm sur 9 et 5 d'épaisseur, sur le devant de la bobine | le boitier d'où part le câble |
| Purge | une manette de 9 cm au flanc du corps, côté opposé au câble | la commande manuelle de secours |
| Câble | 2,4 cm de diamètre, 40 cm de long au sol | il descend du connecteur et file au sol en s'écartant de la conduite |
| Hauteur | 36 cm | le dessus de la bobine |

Comme la vanne manuelle, elle est à la taille 1 de la scène, qui la grossit pour la lire.

## Traits qui la font reconnaitre, classés

1. La bobine noire carrée sur le corps, à la place du volant rouge.
2. Le câble orange qui sort du connecteur et court au sol.
3. Le tube bleu de la conduite qui la traverse.

## On la confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| La vanne manuelle | son volant rouge à plat sur une tige, sans câble |
| Un raccord ou un manchon | la bobine et le câble |
| Un compteur d'eau | le compteur a un cadran à plat sous un couvercle, pas de bobine ni de câble |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#f0a020` | le câble, qui tranche sur la terre et sur le bleu |
| Moyen | `#1c64b8` ; `#5e6b78` | le tube, le bleu du réseau ; le corps, le gris de la vanne manuelle |
| Sombre | `#26282b` | la bobine, le connecteur et la manette de purge |

## Modèle

`electrovanne.js`, 228 triangles, 4 couleurs, plafond d'un objet 300. Ancres : `entree` [-0,25, 0,08, 0] et `sortie`
[0,25, 0,08, 0], les deux bouts du tube sur son axe. `aligned: false` : la scène la tourne dans l'axe de la conduite où elle se cale.

## Juges à l'aveugle

Deux tours, trois juges neufs par tour, chacun devant l'électrovanne et la vanne manuelle de la scène en témoin, sans liste d'objets :

| Version | Juge 1 | Juge 2 | Juge 3 |
|---|---|---|---|
| Bobine ronde et large | électrovanne, 65 | électrovanne, 70 | électrovanne, 60 |
| Bobine carrée, purge (retenue) | électrovanne, 75 | électrovanne, 65 | compteur ou électrovanne, 55 |
| Témoin, vanne manuelle | vanne à volant, 85 à 95 | | |

Deuxième hypothèse constante : un capteur ou un compteur en ligne avec son câble. La bobine ronde se lisait comme un bouchon ou
une tête de champignon en silhouette, d'où la bobine carrée.

## Ce qui reste faible

- À l'échelle du jeu non grossie, la bobine est un bloc noir et le câble un trait : c'est le câble orange qui la sépare de la vanne
  manuelle, et l'absence de rouge. La scène la grossit 2 à 3 fois, comme la vanne.
- Le câble n'arrive à rien : il s'arrête au sol, le programmateur n'est pas modélisé.
- Le compteur d'eau reste la deuxième lecture ; une flèche de sens d'écoulement sur le corps a été proposée par un juge et non faite.
- Elle n'a pas été vue dans la scène qui la recevra.
