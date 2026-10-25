<?php
namespace scene;

/**
 * Ce que le client lit de la cuisson et que les fichiers servis ne portent pas encore : l'empreinte de
 * chacun des deux fichiers cuits, qui fait leur adresse, et le catalogue des espèces.
 *
 * Provisoire, jusqu'à la cuisson qui écrit `static/js/bake.js` et recopie le catalogue dans l'index :
 * `SceneLoader` lira alors l'un et l'autre dans les fichiers servis, et cette classe disparaitra avec
 * l'argument qui la porte jusqu'au conteneur de la scène.
 *
 * L'empreinte n'est pas la version des assets : celle-ci ne bouge pas sur un commit de cuisson seule,
 * qui ne touche aucun `static/js/`, et changerait à chaque déploiement d'un script sans que les modèles
 * aient changé. `model/` n'est pas servi, d'où le catalogue lu ici plutôt que par le navigateur.
 */
class BakeLib {

	/**
	 * @return array{json: string, bin: string, species: array, alias: array}
	 */
	public static function getCatalogAndVersions(): array {

		$directory = LIME_DIRECTORY.'/base/scene';

		return [
			'json' => hash_file('crc32b', $directory.'/static/model/models.json'),
			'bin' => hash_file('crc32b', $directory.'/static/model/models.bin'),
			'species' => json_decode(file_get_contents($directory.'/model/species.json'), TRUE, flags: JSON_THROW_ON_ERROR),
			'alias' => json_decode(file_get_contents($directory.'/model/alias.json'), TRUE, flags: JSON_THROW_ON_ERROR),
		];

	}

	/**
	 * La cuisson d'un package qui porte ses propres modèles (`node base/scene/bake/bake.mjs <dossier du package>`),
	 * ou la paire commune sous `scene`, pour une scène qui en monte des modèles sans le socle des cultures. Elle se
	 * lit par `SceneLoader.loadBake()`, sous le nom qu'on lui donne dans la clé `pairs` de `data-scene-bake` :
	 * `['pairs' => ['<nom>' => BakeLib::getPair('<package>')]]`. Le répertoire servi du package voyage avec les
	 * deux empreintes, parce que ses fichiers ne sont pas dans celui de la scène.
	 *
	 * @return array{directory: string, json: string, bin: string}
	 */
	public static function getPair(string $package): array {

		$directory = \Package::getPath($package).'/static/model';

		return [
			'directory' => \Asset::directory($package),
			'json' => hash_file('crc32b', $directory.'/models.json'),
			'bin' => hash_file('crc32b', $directory.'/models.bin'),
		];

	}

}
?>
