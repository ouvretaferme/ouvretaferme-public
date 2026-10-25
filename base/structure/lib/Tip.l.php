<?php
namespace structure;

class TipLib extends TipCrud {

	use \Notifiable;

	public static function getList(): array {
		return [
			'hr-start', 'sequence-weeks', 'mailing-campaign-help', 'series-forecast-help', 'selling-photo', 'selling-purchase', 'selling-market-start', 'selling-catalog-start', 'selling-wallet',
			'accounting-invoice-cashflow', 'invoicing-addresses',
			'accounting-cashflow-attach', 'accounting-operation-attach', 'accounting-pre-accounting-fec', 'accounting-pre-accounting-summary', 'accounting-cash-internal-transfer', 'accounting-custom-account'
		];
	}

	public static function getLink(string $id): ?string {

		return match($id) {
			'selling-photo' => \main\MainSetting::URL_PHOTOS,
			'hr-start' => '/doc/hr',
			default => self::getClosedLink($id)
		};

	}

	/**
	 * Une astuce des ventes ou de la comptabilité mène à une page de leur documentation, que seul le package qui la sert peut nommer.
	 */
	private static function getClosedLink(string $id): ?string {

		$links = [];

		if(StructureSetting::hasCommercialisationApp()) {
			$links = array_merge($links, self::notify('getTipLink', $id));
		}

		if(StructureSetting::hasAccountingApp()) {
			$links = array_merge($links, self::notify('getAccountingTipLink', $id));
		}

		return array_find($links, fn(?string $link) => $link !== NULL);

	}

	public static function pickOne(\user\User $eUser, string $id, bool $closedByUser = TRUE): ?string {
		return self::changeStatus($eUser, $id, 'shown', override: FALSE, closeByUser: $closedByUser) ? $id : NULL;
	}

	public static function changeStatus(\user\User $eUser, string $id, string $newStatus, bool $override = TRUE, bool $closeByUser = FALSE): bool {

		if(in_array($newStatus, ['shown', 'closed', 'clicked']) === FALSE) {
			throw new \Exception('Unknown status');
		}

		Tip::model()->beginTransaction();

		$eTip = Tip::model()
			->select('list')
			->whereUser($eUser)
			->get();

		$list = $eTip->empty() ? [] : $eTip['list'];

		if($override) {
			$list[$id] = $newStatus;
		} else {

			if(
				array_key_exists($id, $list) and
				($closeByUser === FALSE or $list[$id] === 'closed')
			) {
				// `commit()` et non `rollBack()`, qui remet toute la pile à zéro et emporterait la
				// transaction d'un appelant qui nous englobe : rien n'est écrit, il n'y a rien à défaire.
				Tip::model()->commit();
				return FALSE;
			}

			$list += [
				$id => $newStatus
			];

		}

		$values = array_count_values($list);
		$values['list'] = $list;
		$values['lastSeniority'] = $eUser['seniority'];

		if($eTip->empty()) {

			$eTip = new Tip([
				'user' => $eUser
			] + $values);

			Tip::model()
				->option('add-replace')
				->insert($eTip);

		} else {

			Tip::model()
				->whereUser($eUser)
				->update($values);

		}

		Tip::model()->commit();

		return TRUE;


	}

}
?>
