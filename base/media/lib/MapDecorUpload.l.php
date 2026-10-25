<?php
namespace media;

/**
 * Le modèle 3D téléversé à la place d'un élément du décor (`map\DecorUpload`), au format cuit que lit `sceneBuildModel()`.
 *
 * Même régime que le modèle d'un avatar (`UserAvatarUploadLib`) : l'application ne publie jamais l'adresse du fichier dans
 * le stockage, la route `/map/decorUpload:model` le lit sur le disque après avoir vérifié les droits, et c'est
 * l'imprévisibilité du hash qui protège l'adresse publique du stockage. Un modèle remplacé ou retiré quitte le disque par
 * `deleteByHash()`.
 */
class MapDecorUploadLib extends MediaLib {

}
?>
