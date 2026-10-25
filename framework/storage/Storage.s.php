<?php
namespace storage;

// Les types de médias se déclarent dans base/media seul : StorageSetting partage les propriétés statiques de
// MediaSetting, donc les redéclarer ici réécrirait celles de tout le monde, à un moment qui dépend de l'ordre de
// chargement des classes
class StorageSetting extends \media\MediaSetting {

}
?>
