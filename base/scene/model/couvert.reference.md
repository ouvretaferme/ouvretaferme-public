# Couvert · fiche de référence

Module générique pour les cultures semées dru en plein : engrais vert (phacélie, moutarde, vesce), sarrasin, quinoa, soja, lentille. Le couvert tel qu'il est pendant la plus grande partie de sa saison : levé, fermé, en début de floraison. Ce n'est pas un plant qu'on récolte à la pièce mais une nappe : le modèle est une **touffe**, un morceau de cette nappe, que la scène juxtapose sur la planche.

## Ce que les cinq cultures ont en commun
- **Semées dru, en couvert fermé.** Lentille 250 à 350 plants/m², rangs de 12 à 20 cm pour étouffer les adventices (Chambre d'agriculture de Saône-et-Loire) ; soja en rangs de 15 à 60 cm (Chambres de l'Aude et de la Drôme) ; sarrasin et phacélie forment un couvert dense qui étouffe les adventices (Vilmorin, GRAB). Entre deux plants, on ne voit pas le sol : le feuillage se touche d'un plant à l'autre et sur toute la largeur de la planche.
- **Port dressé, ramifié, ébouriffé.** Tiges dressées et ramifiées (sarrasin, soja, quinoa), buissonnant à semi-lianescent (lentille, ICARDA). Le dessus du couvert est une surface irrégulière, faite de pousses qui dépassent à des hauteurs différentes, pas un dôme lisse.
- **Hauteur de 40 à 60 cm pendant l'essentiel de la saison.** Sarrasin 40 à 90 cm (Jardiner Malin), 60 à 80 cm (Vilmorin) ; lentille 20 à 50 cm (ICARDA) ; phacélie jusqu'à 90 cm (Réussir) ; soja 60 à 100 cm en fin de cycle ; quinoa 1 à 2 m à maturité seulement. La valeur commune retenue : **50 cm**.
- **Fleurs petites et claires, en points épars** sur le dessus : blanches à roses en grappes serrées (sarrasin), blanc rosé (lentille, soja), bleu pâle (phacélie). On retient un blanc rosé, en quelques points, pas en tapis.

## Proportions
- Hauteur du couvert : 40 à 60 cm. Le dessus ondule de ±15 % d'une pousse à l'autre.
- La touffe : la scène espace les plants à 1,25 fois leur emprise (`CROP_DENSITY` de `banc.html`, même pas dans la planche). Une emprise étroite (17 cm, deux rangs par planche) donne des poteaux, une touffe pleine à flanc droit un objet solide (artichaut, cactus, selon les juges du tour 5). Retenu : emprise **34 cm** réels, hauteur déduite de la forme, environ 35 cm.
- Forme d'une touffe : une gerbe de tiges fines, écartées dans tous les sens à partir d'un pied serré, chargées de feuilles en fer de flèche (celles du sarrasin) plus longues en bas qu'en haut, avec des trous qui laissent voir la terre. Les fleurs sont en petits bouquets au bout des tiges, à des hauteurs inégales.
- Le bord du houppier est découpé en pointes inégales (pousses qui dépassent), pour que les touffes voisines s'emboitent au lieu de s'aligner en boules.

## Traits qui font reconnaître, dans l'ordre
1. Une **nappe continue et haute** qui couvre toute la planche, sans sol visible entre les plants.
2. Un **dessus ébouriffé** : bosses et pointes inégales, vert moyen éclairé, flancs plus sombres.
3. **Quelques points de fleurs blanc rosé** semés sur le dessus.

## Confusions et ce qui sépare
- **Épinard, mâche** : rosettes basses (10 à 20 cm), séparées, vert sombre, sol visible entre elles. Le couvert est trois fois plus haut, vert moyen, continu.
- **Herbe, gazon** : tapis ras et uniforme. Le couvert est haut, bosselé, piqué de fleurs.
- **Pomme de terre** : buissons séparés par rang, sur butte, fleurs blanches aussi. Le couvert ne laisse aucun creux entre les plants ni entre les rangs.
- **Haricot, pois** : rangs alignés de plants distincts. Le couvert n'a pas de rang lisible.

## Couleurs (quatre au plus)
- Clair : `#f2dfe2`, les fleurs (blanc rosé).
- Moyen : `#6fa043`, le dessus du houppier.
- Sombre : `#3f6f2e`, les flancs et le pied, qui donnent la profondeur entre deux bosses.
- Accent : `#8cb85a`, quelques pousses éclairées qui dépassent du dessus.

## Sources
- Sarrasin : [Jardiner Malin](https://www.jardiner-malin.fr/fiche/sarrasin.html), [Vilmorin, sachet](https://www.bricomarche.com/p/sarrasin-bio-375g/3182670002069), [areq.net](https://areq.net/lang/fr/Sarrasin_commun.html), [techno-science](https://www.techno-science.net/glossaire-definition/Sarrasin-plante.html).
- Lentille : [ICARDA / CGIAR](https://genebanks.cgiar.org/resources/publications/lentil-fr), [Chambre d'agriculture de Saône-et-Loire](https://saoneetloire.chambres-agriculture.fr/fileadmin/user_upload/232_chambre_dagriculture_de_saone-et-loire/8-Photos/Photos_G.Cultures/9.3_Lentilles_VF.pdf).
- Soja : [Chambre de l'Aude](https://aude.chambre-agriculture.fr/fileadmin/user_upload/National/FAL_commun/publications/Occitanie/DensitV_Vcartement_soja_sec_2015.pdf), [Agriculture drômoise](https://www.agriculture-dromoise.fr/media/ad/AD_2023/112023/agdro_p23_S11.pdf).
- Quinoa : [Futura](https://www.futura-sciences.com/planete/definitions/botanique-quinoa-4689/), [Kokopelli](https://blog.kokopelli-semences.fr/wp-content/uploads/2021/01/Fiche_multiplication-quinoa-LG.pdf).
- Engrais verts : [Réussir, la phacélie](https://www.reussir.fr/vigne/la-phacelie), [GRAB](https://www.grab.fr/wp-content/uploads/2020/02/FICHE-GRAB-engrais-verts.pdf).

## Contraintes du chantier
- Contrat `build(THREE, helpers)`, voir `BUDGET.md`. Plafond **60 triangles**, sans exception, **4 couleurs**.
- Ce qui doit marcher : le rang en jeu à 20 px/m, où deux rangs de touffes doivent se lire comme une seule masse.
