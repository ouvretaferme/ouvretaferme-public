<?php
namespace sequence;

class Sequence extends SequenceElement {

	public static function getSelection(?\structure\Structure $eStructure = NULL): array {

		return parent::getSelection() + [
			'structure' => ['name'],
			'cCrop' => Crop::model()
				->select(Crop::getSelection())
				->delegateCollection('sequence', 'id', fn($c) => $c->sort(CropLib::sortByPlant())),
		];

	}

	public function active(): bool {
		return ($this['status'] === Sequence::ACTIVE);
	}

	public function canRead(): bool {

		$this->expects(['structure']);

		return (
			$this['structure']->canWrite() or
			$this['visibility'] !== Sequence::PRIVATE
		);

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('bedWidth')
			->addAfterCast('required', function(?int &$bedWidth): bool {

				try {
					$this->expects(['use']);
				} catch(\Exception) {
					return FALSE;
				}

				$this->expects(['use']);

				if($this['use'] === Sequence::BLOCK) {
					$bedWidth = NULL;
				}

				if($this['use'] === Sequence::BED and $bedWidth === NULL) {
					return FALSE;
				}

				return TRUE;

			});

		$l->property('alleyWidth')
			->addAfterCast('use', function(?int &$alleyWidth): bool {

				try {
					$this->expects(['use']);
				} catch(\Exception) {
					return FALSE;
				}

				$this->expects(['use']);

				if($this['use'] === Sequence::BLOCK) {
					$alleyWidth = NULL;
				}

				return TRUE;

			});

		$l->property('plantsList')
			->replaceCheck(function($plants): bool {

				$plants = (array)($plants ?? []);

				$cPlant = \plant\Plant::model()
					->select('id', 'structure')
					->whereId('IN', $plants)
					->getCollection();

				if($cPlant->empty()) {
					return FALSE;
				} else {

					foreach($cPlant as $ePlant) {
						if($ePlant->canRead() === FALSE) {
							return FALSE;
						}
					}

					$this['cPlant'] = $cPlant;
					return TRUE;
				}

			});

		$l->property('perennialLifetime')
			->addAfterCast('cycle', function(?int &$lifetime): bool {

				$this->expects(['id', 'cycle']);

				if($this['cycle'] === Sequence::ANNUAL) {
					$lifetime = NULL;
				}

				return TRUE;

			});

	}

}
?>