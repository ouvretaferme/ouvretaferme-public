<?php
namespace media;

/**
 * Le modèle 3D téléversé d'un avatar (`user\UserAvatarUpload`), au format cuit que lit `sceneBuildModel()`.
 *
 * L'application ne publie jamais l'adresse du fichier dans le stockage : la route `/user/avatar/modele/{id}` le lit
 * sur le disque après avoir vérifié les droits. L'adresse publique du stockage le sert pourtant à qui connait son
 * hash, et c'est l'imprévisibilité de celui-ci qui le protège, comme pour `external-file`. Un modèle remplacé ou
 * retiré quitte le disque par `deleteByHash()`, et non au ménage des médias dix jours plus tard.
 */
class UserAvatarUploadLib extends MediaLib {

}
?>
