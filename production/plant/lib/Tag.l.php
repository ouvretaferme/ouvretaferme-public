<?php
namespace plant;

/**
 * Le vocabulaire de tags par lesquels on décrit une variété.
 *
 * Une seule ligne par tag, quel que soit le nombre d'espèces qui l'emploient :
 * « rouge » ou « ronde » reviennent d'une espèce à l'autre et c'est bien le même
 * tag. Ce sont les espèces qui désignent ceux qui les concernent, dans
 * `Plant.tagsAccepted`.
 */
class TagLib extends TagCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['fqn', 'usage', 'name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	/**
	 * Les tags qui décrivent une espèce, dans l'ordre où elle les a acceptés :
	 * `tagsAccepted` va du plus structurant au plus anecdotique, et une liste de
	 * cases à cocher se lit d'autant mieux qu'elle suit cet ordre.
	 *
	 * Les tags acceptés vivent sur la ligne du référentiel, pas sur la copie que
	 * possède chaque structure : on part donc du fqn, seul identifiant partagé de
	 * l'espèce. Une espèce créée par une structure, qui n'a pas de fqn, n'a pas de
	 * tags — elle n'est dans aucun référentiel.
	 */
	public static function getByPlant(Plant $ePlant): \Collection {

		$ePlantReference = Plant::model()
			->select(['id', 'tagsAccepted'])
			->whereFqn($ePlant['fqn'])
			->whereStructure(NULL)
			->get();

		if(
			$ePlantReference->empty() or
			$ePlantReference['tagsAccepted'] === []
		) {
			return new \Collection();
		}

		return Tag::model()
			->select(Tag::getSelection())
			->whereId('IN', $ePlantReference['tagsAccepted'])
			->getCollection();

	}

}
?>
