<?php
namespace data;

/**
 * Compteurs par structure de l'écran d'administration de l'e-facturation.
 *
 * Ce qui reste d'un dispositif plus large : les compteurs comptables qui vivaient ici alimentaient
 * l'écran Gestion, supprimé, et l'onglet Comptabilité des statistiques, qui les mesure désormais
 * lui-même dans sa propre traversée (StatisticAccountingAiLib). Trois compteurs subsistent, ceux
 * dont l'écran d'e-facturation se sert encore.
 *
 * Le modèle de cette table reste ce qu'il ne faut pas reproduire ailleurs : un scalaire par couple
 * structure et compteur, sans date, dans un int16 borné à 32 767. Il convient à un tableau de bord
 * qui n'affiche qu'un instantané ; il ne convient à aucune série.
 */
Class AnalyticsLib extends AnalyticsCrud {

	use \Notifiable;

	const GROUP_INVOICING = [DataSetting::ANALYTICS_INVOICING_OUT, DataSetting::ANALYTICS_INVOICING_IN, DataSetting::ANALYTICS_INVOICING_ADDRESSES];

	public static function create(Analytics $e): void {

		Analytics::model()
			->option('add-replace')
			->insert($e);

	}

	public static function calculate(): void {

		// La même population que la traversée statistique des factures : le parc e-facturation est
		// défini une fois, dans StructureLib::runPdpAll(). Les deux écrans comptent les mêmes factures,
		// et une définition écrite deux fois est ce qui les a fait diverger.
		$failed = \structure\StructureLib::runPdpAll(function(\structure\Structure $eStructure) {

			if($eStructure->isFixture()) {
				return;
			}

			// Les compteurs sont posés par l'observateur du package pdp : l'export public n'a pas accounting/.
			$values = [];

			self::notify('calculateAnalytics', $values);

			foreach($values as $data => $value) {
				self::save($eStructure, $data, $value);
			}

		});

		if($failed > 0) {
			trigger_error('AnalyticsLib: '.$failed.' structure(s) non parcourue(s)');
		}

	}

	public static function save(\structure\Structure $eStructure, int $data, int $value) {

		$eAnalytics = new \data\Analytics([
			'data' => $data,
			'structure' => $eStructure,
			'value' => $value,
		]);

		\data\AnalyticsLib::create($eAnalytics);

	}

}
?>
