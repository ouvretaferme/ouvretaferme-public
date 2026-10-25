<?php
namespace structure;

class Feature extends FeatureElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public static function properties(\PropertyList $l): void {

		// Éteindre reste toujours possible : seul l'allumage dépend du type de structure. Un groupe ou une AMAP
		// porte `production` vrai par défaut sans avoir de planning, d'où les deux étapes suivantes, qui ne
		// lisent pas le drapeau seul.
		$l->property('production')
			->addAfterCheck('producer', function(bool $value): bool {

				$this->expects(['structure']);

				return ($value === FALSE or $this['structure']->acceptProduction());

			});

		$l->property('hasHr')
			->addAfterCheck('production', function(bool $value): bool {

				$this->expects(['structure']);

				return (
					$value === FALSE or
					($this['structure']->acceptProduction() and $this['structure']->conf(Feature::class)['production'])
				);

			});

		// Le refus nomme les dates, il passe donc par une exception, seule voie qui porte des arguments
		// jusqu'au message.
		$l->property('hasCommunityLearning')
			->addAfterCheck('enrolled', function(bool $value): bool {

				if($value === TRUE) {
					return TRUE;
				}

				$this->expects(['structure']);

				$cDate = \learning\DateLib::getEnrolledByCommunity($this['structure']);

				if($cDate->notEmpty()) {
					throw new \FailException('structure\Feature::hasCommunityLearning.enrolled', ['cDate' => $cDate]);
				}

				return TRUE;

			});

	}

}
?>