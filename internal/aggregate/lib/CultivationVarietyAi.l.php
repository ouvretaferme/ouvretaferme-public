<?php
namespace aggregate;

/**
 * Les variétés semées ou plantées dans les cultures de la communauté.
 *
 * Une ligne par part variétale, rattachée à la ligne de fait correspondante. Le
 * nom de la variété de référence y est recopié : sans lui il faudrait rejoindre
 * `plantVariety` deux fois à chaque affichage — mesuré à 64 ms — pour n'obtenir
 * qu'un libellé déjà arbitré et qui ne bouge pas.
 *
 * Une part dont la variété n'est rattachée à aucune référence n'entre pas dans la
 * table : le référentiel a été arbitré à la main, et ce qu'il n'a pas retenu n'est
 * pas une variété qui lui aurait échappé.
 */
class CultivationVarietyAiLib extends CultivationVarietyCrud {

	// Même découpage que CultivationAiLib, et pour la même raison.
	private const CHUNK = 2000;

	/**
	 * Les parts variétales d'une espèce de référence.
	 *
	 * Rendues en bloc pour l'espèce, sans filtre : la page s'en sert à la fois pour
	 * la recherche par nom et pour le classement par créneau, et l'espèce entière
	 * tient en quelques milliers de lignes.
	 */
	public static function getByPlant(\plant\Plant $ePlant): \Collection {

		return CultivationVariety::model()
			->select(['cultivation', 'structure', 'variety', 'name', 'searchKey'])
			->wherePlant($ePlant)
			->whereOnline(TRUE)
			->getCollection();

	}

	/**
	 * Les variétés d'une espèce dont le nom contient la recherche.
	 *
	 * Bornée à l'espèce ouverte : la recherche sert à filtrer ce que la page montre,
	 * pas à changer de sujet. Une variété homonyme sur une autre espèce — « Black
	 * Beauty » existe en aubergine et en courgette — n'est donc pas proposée ici.
	 *
	 * Une ligne par variété de référence, et non par culture : une variété répandue
	 * est employée par des centaines de cultures et n'a à paraître qu'une fois. Le
	 * groupement porte sur la variété plutôt que sur son nom, pour que deux références
	 * homonymes restent distinctes si le référentiel en a jugé ainsi.
	 *
	 * Aucun échappement à faire sur la recherche : getSearchKey() ne laisse passer que
	 * des lettres, des chiffres et des espaces, donc ni `%` ni `_` n'arrivent jusqu'au
	 * LIKE.
	 */
	public static function query(\plant\Plant $ePlant, string $search, int $limit): \Collection {

		$searchKey = self::getSearchKey($search);

		if($searchKey === '') {
			return new \Collection();
		}

		return CultivationVariety::model()
			->select([
				'name',
				'plant' => ['id', 'name', 'fqn'],
			])
			->wherePlant($ePlant)
			->whereOnline(TRUE)
			->whereSearchKey('LIKE', '%'.$searchKey.'%')
			->group(new \Sql('variety, name'))
			->sort(['name' => SORT_ASC])
			->getCollection(0, $limit);

	}

	/**
	 * La forme sur laquelle la recherche par variété compare.
	 *
	 * Le suffixe F1 tombe : il désigne le type de semence et non la variété, et les
	 * structures le notent une fois sur deux. Les accents et la ponctuation tombent
	 * aussi. Les fautes de frappe, elles, sont laissées telles quelles — les
	 * rapprocher demanderait une distance d'édition, qui confondrait au passage des
	 * variétés réellement distinctes.
	 */
	public static function getSearchKey(string $name): string {

		$key = mb_strtolower(trim($name));

		$key = strtr($key, [
			'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
			'ù' => 'u', 'û' => 'u', 'ü' => 'u',
		]);

		$key = preg_replace('/\bf\s*1\b/', ' ', $key);
		$key = preg_replace('/[^a-z0-9]+/', ' ', $key);

		return trim($key);

	}

	/**
	 * Jette la version en préparation.
	 *
	 * Reliquat d'un passage interrompu : une version que personne n'a jamais servie.
	 */
	public static function deleteOffline(): void {

		CultivationVariety::model()
			->whereOnline(FALSE)
			->delete();

	}

	/**
	 * Jette la version en ligne, puis met la préparation à sa place.
	 *
	 * Les deux mouvements sont indissociables ; c'est l'appelant qui les enferme dans
	 * une transaction.
	 */
	public static function deleteOnline(): void {

		CultivationVariety::model()
			->whereOnline(TRUE)
			->delete();

	}

	public static function publish(): void {

		CultivationVariety::model()
			->whereOnline(FALSE)
			->update(['online' => TRUE]);

	}

	/**
	 * Dépose d'un bloc les lignes d'une espèce, hors ligne.
	 *
	 * Même parti que CultivationAiLib::createByPlant() : table de projection,
	 * écriture directe et par lot.
	 */
	public static function createByPlant(\Collection $cCultivationVariety): void {

		foreach(array_chunk($cCultivationVariety->getArrayCopy(), self::CHUNK) as $chunk) {

			$cChunk = new \Collection($chunk);

			CultivationVariety::model()->insert($cChunk);

		}

	}

}
?>
