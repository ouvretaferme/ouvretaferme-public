# Quad, fiche de référence

Source : aucune photo. C'est le quad utilitaire du bourg de la compta (POC `bourg3d-plan`, `modeles/quad.js`), refait dans
Blender à l'échelle réelle : la scène le pose à 1,5 comme les autres véhicules du bourg. Les cotes sont celles d'un quad
utilitaire de ferme, recoupées avec la commande du POC (2,1 × 1,25 × 1,25 m).

## Proportions

| Mesure | Valeur | D'où elle vient |
|---|---|---|
| Hors tout | 2,16 × 1,24 × 1,10 m | longueur pare-buffle compris, largeur aux poignées |
| Roues | 0,66 m de diamètre derrière, 0,64 m devant, 0,27 et 0,24 m de large | pneus ballon, empattement 1,26 m, voie 0,94 m |
| Garde-boue | 1,24 m de large, dessus plat à 0,75 m, coupés droit au-dessus du moyeu | ils portent les porte-bagages ; une retombée en pointe se lisait comme des crocs |
| Tunnel | rouge, de la selle aux repose-pieds, sur un longeron noir qui joint les essieux | un bloc noir entre les repose-pieds se lisait comme une caisse posée |
| Selle | 0,58 m de long, à 0,92 m du sol | à califourchon, sans dossier, posée sur le réservoir et le garde-boue arrière |
| Guidon | 1,10 m de large aux poignées, à 1,05 m au milieu et 1,09 m aux bouts | presque à plat, les bouts à peine ramenés vers le pilote |
| Porte-bagages | deux cadres de tubes plats, 0,42 m devant à 0,88 m, 0,44 m derrière à 0,84 m | sur quatre pieds courts plantés dans le garde-boue, sous le dessus de la selle ; celui de derrière commence où finit la selle |
| Phares | deux, de 0,18 × 0,14 m, sur le nez | sous le porte-bagages avant |

## Traits qui le font reconnaitre, classés

1. Quatre roues ballon égales sous deux garde-boue rouges en arc, sans cabine ni volant.
2. Une selle noire à califourchon entre les garde-boue, et un guidon large.
3. Deux porte-bagages de tubes gris, devant et derrière.

## On le confond avec

| Objet | Ce qui l'en sépare |
|---|---|
| Le tracteur (`tracteur.js`) | quatre roues égales, aucune cabine, une selle et un guidon au lieu d'un volant |
| Une tondeuse autoportée | des roues avant aussi grandes que les arrière, aucun plateau de coupe, les porte-bagages |
| Une moto | quatre roues et une voie large |

## Couleurs

| Valeur | Couleur | Ce qu'elle colore |
|---|---|---|
| Clair | `#d9d5c7` | les jantes et les phares |
| Moyen | `#c8402f` ; `#8d9396` | les garde-boue, le capot et le réservoir ; les porte-bagages, le guidon, les repose-pieds et le pare-buffle |
| Sombre | `#2a2c2e` | les pneus, le châssis, les essieux, la selle, la colonne et les poignées |

## Modèle

`quad.js`, 900 triangles, 4 couleurs, plafond d'un objet 900. Exporté de Blender depuis `quad.blend`, une seule pièce rigide,
sans os ni ancre, l'avant vers +X, emprise de 2,16 m, 1,10 m de haut.
