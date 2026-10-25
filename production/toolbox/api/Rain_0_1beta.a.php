<?php
namespace toolbox;

/**
 * Les relevés de pluviomètre : le total de pluie d'un jour, en mm, sur un pluviomètre (`Gauge`).
 *
 * ⚠️ La création **ajoute** au jour, elle ne le pose pas. La table ne porte qu'une ligne par
 * pluviomètre et par jour (unicité sur `gauge` et `date`), et un second relevé du même jour augmente
 * le total au lieu d'écrire une seconde ligne : poster 12 mm deux fois sur la même date laisse donc
 * 24 mm, pas 12. C'est le comportement de l'écran, gardé tel quel pour l'API (décidé le
 * 2026-09-29). Un client qui réémet un appel après une coupure doit donc le savoir, et un outil qui
 * veut fixer un total emploie `PUT rain/{id}`, qui remplace.
 *
 * Une quantité négative retire des mm à un jour qui en porte déjà au moins autant
 * (`Rain::rain.exceeds`), et le jour ramené à zéro disparaît. C'est ainsi qu'une saisie en trop se
 * corrige sans repasser par la modification.
 *
 * La création rend le jour et non la ligne écrite : 200 et le total du jour après l'ajout, jamais
 * 201, et `rain: 0` avec `id: null` quand le jour vient d'être vidé (`pushCreated()` plus bas).
 *
 * Pas de borne de période obligatoire, contrairement à d'autres listes qui grossissent avec le temps
 * (`series\Harvest`) : l'unicité plafonne la table à une ligne par pluviomètre et par jour, soit
 * quelques milliers de lignes pour une structure qui relève depuis dix ans, et la liste est déjà
 * bornée par la structure.
 */
class Rain_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'rainList',
			[
				'?gauge' => Rain::getValidator('gauge'),
				'?dateFrom' => new \FilterValidator('date'),
				'?dateTo' => new \FilterValidator('date')
			]
		);

		$this->read('rain/{id}');
		$this->create('rain');
		$this->update('rain/{id}');
		$this->delete('rain/{id}');

		$this->element(
			'toolbox\Rain',
			fn() => [
				'id',
				'gauge',
				'date',
				'rain'
			]
		);

	}

	/**
	 * Un relevé s'ajoute au jour, il ne crée pas une ressource de plus : la réponse rend donc le jour
	 * tel qu'il est après l'ajout, en 200 et non en 201, qu'une ligne ait été écrite, augmentée ou
	 * vidée (tranché par M-Prod le 2026-09-30). Le jour se relit par (gauge, date), que l'unicité de
	 * la table rend fidèle.
	 *
	 * Un jour ramené à zéro n'a plus de ligne : il rend `rain: 0` et `id: null`, ce que l'écran
	 * montre, plutôt qu'un refus qui dirait qu'une écriture pourtant faite n'a pas eu lieu. Le défaut
	 * du cadriciel, qui relit par l'id, ne peut pas le rendre : l'id ne désigne plus rien, et un id nul
	 * y lève, `ApiData::get()` traitant une valeur nulle comme une clé absente.
	 */
	public function pushCreated(\ApiData $data): void {

		$data->httpCode = 200;

		$m = $this->getNewModel($data);

		$eRain = $m
			->select($this->getSelection($m))
			->whereGauge($data->e['gauge'])
			->whereDate($data->e['date'])
			->get();

		if($eRain->empty()) {

			$eRain = new Rain([
				'id' => NULL,
				'gauge' => $data->e['gauge'],
				'date' => $data->e['date'],
				'rain' => 0.0
			]);

		}

		$data->pushObject(NULL, $eRain);

	}

	public function getList(\ApiData $data): RainModel {

		return $this->getNewModel($data)
			->whereGauge(fn() => $data->get('gauge'), if: $data->exists('gauge'))
			->whereDate('>=', fn() => $data->get('dateFrom'), if: $data->exists('dateFrom'))
			->whereDate('<=', fn() => $data->get('dateTo'), if: $data->exists('dateTo'));

	}

	public function getNewModel(\ApiData $data): RainModel {

		return new RainModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Rain {

		return new Rain([
			'structure' => $data->eStructure
		]);

	}

}
?>
