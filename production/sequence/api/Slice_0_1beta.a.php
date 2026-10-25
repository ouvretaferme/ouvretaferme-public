<?php
namespace sequence;

/**
 * Pas de route propre, comme series\Slice : sert uniquement à rendre la répartition des variétés
 * exportable sous `varieties` dans une culture d'itinéraire (cf Crop_0_1betaApi).
 *
 * Une part se pose et se retire avec la culture qui la porte, jamais seule : SliceLib::prepare()
 * relit la liste entière à chaque écriture et refait la table.
 */
class Slice_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->element(
			'sequence\Slice',
			fn() => ['id', 'variety', 'partPercent']
		);

	}

	public function getNewModel(\ApiData $data): SliceModel {
		throw new \UnsupportedException();
	}

	public function getNewElement(\ApiData $data): Slice {
		throw new \UnsupportedException();
	}

}
?>
