<?php
namespace toolbox;

/**
 * Le carnet de traitement (lot L4' du chantier production-finir, planche pf-l4-v3). Il ne porte
 * aucune donnée : il lit les interventions de traitement faites sur une année civile, et les range
 * par mois. Le produit d'un traitement est le premier de ses produits (`series\Task.products`), comme
 * sur l'intervention (`series\TaskLib::fillTreatment()`).
 *
 * L'année est celle du jour du traitement, jamais la saison de sa série (retour du
 * 27/09) : le contrôle porte sur ce qui a été épandu dans l'année, et une saison déborde sur
 * l'année d'avant. Un traitement noté à la semaine prend l'année ISO de sa semaine.
 */
class TreatmentLib {

	public static function getAction(\structure\Structure $eStructure): \work\Action {
		return \work\ActionLib::getByStructure($eStructure, fqn: ACTION_TRAITEMENT);
	}

	/**
	 * Les produits de traitement de la structure, désactivés et supprimés compris : un produit retiré
	 * reste celui des traitements qui l'ont noté.
	 */
	public static function getProducts(\structure\Structure $eStructure): \Collection {

		return \work\Tool::model()
			->select(\work\Tool::getSelection())
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->getCollection(index: 'id')
			->sort('name', natural: TRUE);

	}

	public static function hasProducts(\structure\Structure $eStructure): bool {

		return \work\Tool::model()
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->whereStatus(\work\Tool::ACTIVE)
			->exists();

	}

	/**
	 * Les traitements faits dans l'année, le plus récent en tête, avec ce que le carnet en montre.
	 * La recherche borne à une espèce (`plant`) et à un produit (`tool`) : un traitement porte son
	 * produit dans la liste json `products`, que seul JSON_CONTAINS sait lire.
	 */
	public static function getTasks(\structure\Structure $eStructure, \work\Action $eAction, int $year, \Search $search = new \Search()): \Collection {

		if($eAction->empty()) {
			return new \Collection();
		}

		$ePlant = $search->get('plant', new \plant\Plant());
		$eTool = $search->get('tool', new \work\Tool());

		if($eTool->notEmpty()) {
			\series\Task::model()->where(new \Sql('JSON_CONTAINS(products, '.\series\Task::model()->format((string)$eTool['id']).')'));
		}

		return \series\Task::model()
			->select([
				'id', 'structure', 'status', 'doneWeek', 'doneDate', 'timesheetStart', 'timesheetStop', 'createdAt', 'updatedAt', 'description', 'tools', 'products', 'treatment',
				'plant' => ['name', 'fqn', 'vignette'],
				'series' => ['name', 'mode', 'area', 'areaTarget', 'use', 'cccPlace' => \series\PlaceLib::delegateBySeries()],
			])
			->whereStructure($eStructure)
			->where(self::getYearCondition($year))
			->whereStatus(\series\Task::DONE)
			->whereAction($eAction)
			->wherePlant($ePlant, if: $ePlant->notEmpty())
			->sort(['doneWeek' => SORT_DESC, 'doneDate' => SORT_DESC, 'id' => SORT_DESC])
			->getCollection(index: 'id');

	}

	/**
	 * Les traitements cochés dans le carnet, pour leur donner un produit (`TreatmentUi::updateProduct()`).
	 * Une intervention d'une autre structure ou d'une autre action ne passe pas : l'id vient du POST.
	 */
	public static function getTasksByIds(\structure\Structure $eStructure, \work\Action $eAction, array $ids): \Collection {

		if($eAction->empty() or $ids === []) {
			return new \Collection();
		}

		$select = \series\Task::getSelection();
		$select['action'] = ['fqn'];

		return \series\Task::model()
			->select($select)
			->whereId('IN', $ids)
			->whereStructure($eStructure)
			->whereAction($eAction)
			->getCollection(index: 'id');

	}

	/**
	 * Les années qui portent au moins un traitement, la plus récente en tête : ce sont les onglets du
	 * carnet. L'année en cours en est toujours, puisque c'est là qu'un nouveau traitement se range.
	 */
	public static function getYears(\structure\Structure $eStructure, \work\Action $eAction): array {

		$years = [\Clock::year()];

		if($eAction->notEmpty()) {

			$years = array_merge($years, \series\Task::model()
				->whereStructure($eStructure)
				->whereStatus(\series\Task::DONE)
				->whereAction($eAction)
				->getColumn(new \Sql('DISTINCT IF(doneDate IS NULL, CAST(SUBSTRING(doneWeek, 1, 4) AS UNSIGNED), YEAR(doneDate))', 'int')));

		}

		$years = array_unique($years);
		rsort($years);

		return $years;

	}

	/**
	 * Le carnet d'une année, tel que la recherche le borne : ses traitements et ce que `fill()` en tire.
	 * Les chiffres en tête du carnet se comptent sur les mêmes traitements que ses lignes.
	 */
	public static function getBook(\structure\Structure $eStructure, \work\Action $eAction, int $year, \Search $search): array {

		$cTask = self::getTasks($eStructure, $eAction, $year, $search);

		\series\TaskLib::fillDoneProposed($eStructure, $eAction, $cTask);
		self::fillTools($eStructure, $cTask);

		return ['cTask' => $cTask] + self::fill($cTask, self::getProducts($eStructure), $search->get('without', FALSE));

	}

	/**
	 * Pose sur chaque traitement le matériel employé (`cTool`). Les produits n'y sont pas : ils ont
	 * leur propre liste (`products`), que le carnet montre dans sa colonne.
	 */
	public static function fillTools(\structure\Structure $eStructure, \Collection $cTask): void {

		foreach($cTask as $eTask) {
			$eTask['cTool'] = \work\ToolLib::askCollection($eTask['tools'], $eStructure);
		}

	}

	/**
	 * L'intervention que le panneau « + Produit » édite : celle qui est cochée seule, avec son produit
	 * et son traitement, ou une intervention vide quand plusieurs le sont, puisque leurs produits
	 * diffèrent. Le traitement vide se propose d'après le produit, comme sur le formulaire complet.
	 */
	public static function getProductTask(\structure\Structure $eStructure, \work\Action $eAction, \Collection $cTask): \series\Task {

		if($cTask->count() === 1) {
			$eTask = $cTask->first();
		} else {
			$eTask = new \series\Task([
				'products' => [],
				'treatment' => NULL,
				'cProduct?' => fn() => new \Collection(),
			]);
		}

		$eTask['structure'] = $eStructure;
		$eTask['action'] = $eAction;
		$eTask['hasProducts'] = \work\ToolLib::getProductsForWork($eStructure, $eAction);

		if($eTask['treatment'] === NULL) {
			$eTask['treatment'] = \series\TaskLib::getTreatmentDefault($eTask);
		}

		return $eTask;

	}

	/**
	 * Un traitement daté au jour se range par l'année de son jour, un traitement noté à la semaine par
	 * l'année ISO de sa semaine, comme `getYears()`.
	 */
	private static function getYearCondition(int $year): \Sql {

		$format = fn(string $value) => \series\Task::model()->format($value);

		return new \Sql('doneDate BETWEEN '.$format($year.'-01-01').' AND '.$format($year.'-12-31').' OR (doneDate IS NULL AND doneWeek LIKE '.$format($year.'-%').')');

	}

	/**
	 * Ce que la carte de la Boîte à outils montre, sur l'année en cours (`year`) : le nombre de
	 * traitements, et le dernier (`eTaskLast`, vide s'il n'y en a pas).
	 */
	public static function getSummary(\structure\Structure $eStructure): array {

		$year = \Clock::year();
		$eAction = self::getAction($eStructure);

		if($eAction->empty()) {
			return ['year' => $year, 'count' => 0, 'eTaskLast' => new \series\Task()];
		}

		$count = \series\Task::model()
			->whereStructure($eStructure)
			->where(self::getYearCondition($year))
			->whereStatus(\series\Task::DONE)
			->whereAction($eAction)
			->count();

		$eTaskLast = \series\Task::model()
			->select(['id', 'doneWeek', 'doneDate'])
			->whereStructure($eStructure)
			->where(self::getYearCondition($year))
			->whereStatus(\series\Task::DONE)
			->whereAction($eAction)
			->sort(['doneWeek' => SORT_DESC, 'doneDate' => SORT_DESC, 'id' => SORT_DESC])
			->get();

		return ['year' => $year, 'count' => $count, 'eTaskLast' => $eTaskLast];

	}

	/**
	 * Pose sur chaque traitement son produit (`eToolPhyto`), sa surface, et la quantité de produit que
	 * sa dose donne sur cette surface (`quantity`, dans l'unité de sa dose, `unit`). Rend les
	 * traitements rangés par mois, le plus récent en tête (`tasksByMonth`), et ceux qui n'ont pas de
	 * produit (`cTaskWithout`). Avec `$without`, les mois ne rangent que ces derniers : c'est le filtre
	 * « Produit à renseigner » du carnet, qui garde les deux comptes de ses chiffres.
	 *
	 * La surface est celle de la série, que ses emplacements font : un traitement hors série n'en a
	 * pas, ni de quantité.
	 */
	public static function fill(\Collection $cTask, \Collection $cToolPhyto, bool $without = FALSE): array {

		$cTaskWithout = new \Collection();
		$tasksByMonth = [];

		foreach($cTask as $eTask) {

			$eToolPhyto = new \work\Tool();

			foreach($eTask['products'] as $product) {
				if($cToolPhyto->offsetExists($product)) {
					$eToolPhyto = $cToolPhyto[$product];
					break;
				}
			}

			$eTask['eToolPhyto'] = $eToolPhyto;
			// Une série qui n'est pas encore placée n'a que sa surface prévue : le carnet la montre comme
			// telle (`areaPlanned`), pour un ordre de grandeur.
			if($eTask['series']->empty()) {
				$eTask['area'] = NULL;
				$eTask['areaPlanned'] = FALSE;
			} else if($eTask['series']['area'] === NULL and $eTask['series']['areaTarget'] !== NULL) {
				$eTask['area'] = $eTask['series']['areaTarget'];
				$eTask['areaPlanned'] = TRUE;
			} else {
				$eTask['area'] = $eTask['series']['area'];
				$eTask['areaPlanned'] = FALSE;
			}

			if($eToolPhyto->empty()) {

				$eTask['unit'] = NULL;
				$eTask['quantity'] = NULL;
				$cTaskWithout[$eTask['id']] = $eTask;

			} else {

				// La dose se lit dans l'intervention seule : celle du produit change, et le carnet rend compte de ce qui a été épandu.
				$dose = $eTask['treatment']['dose'] ?? NULL;
				$eTask['unit'] = $eTask['treatment']['unit'] ?? $eToolPhyto['routineValue']['unit'];

				$eTask['quantity'] = ($eTask['area'] === NULL or $dose === NULL) ? NULL : $eTask['area'] * $dose / 10000;

				if($without) {
					continue;
				}

			}

			$month = self::getMonth($eTask);
			$tasksByMonth[$month] ??= new \Collection();
			$tasksByMonth[$month][$eTask['id']] = $eTask;

		}

		krsort($tasksByMonth);

		return [
			'tasksByMonth' => $tasksByMonth,
			'cTaskWithout' => $cTaskWithout
		];

	}

	/**
	 * Un traitement noté à la semaine se range dans le mois de son jeudi, qui est toujours dans l'année
	 * ISO de la semaine : le lundi de la semaine 1 tombe parfois en décembre, et le traitement
	 * s'afficherait alors sous un mois de l'année d'avant, dans l'onglet de la suivante.
	 */
	private static function getMonth(\series\Task $eTask): string {
		return substr($eTask['doneDate'] ?? week_date_day($eTask['doneWeek'], 4), 0, 7);
	}

	/**
	 * Le registre en PDF, rangé par mois du plus ancien au plus récent. Il porte aussi les traitements
	 * notés à la semaine, à la place de leur semaine, même si un registre demande le jour (décision de
	 * du 28/09). `getTasks()` rend du plus récent au plus ancien, par semaine puis par jour :
	 * l'ordre se renverse sans nouveau tri, et un traitement sans jour passe avant les jours de sa
	 * semaine, là où l'écran le range après.
	 */
	public static function getRegister(\Collection $cTask): array {

		$tasksByMonth = [];

		foreach($cTask->reverse(preserveKeys: TRUE) as $eTask) {

			$month = self::getMonth($eTask);
			$tasksByMonth[$month] ??= new \Collection();
			$tasksByMonth[$month][$eTask['id']] = $eTask;

		}

		return $tasksByMonth;

	}

	/**
	 * Le carnet en CSV : une ligne par traitement de l'année, avec ou sans jour, du plus ancien au
	 * plus récent. La surface et la quantité d'une série pas encore placée ne sont qu'un ordre de
	 * grandeur, que l'écran atténue : un tableur ne le montrerait pas, elles restent donc vides.
	 */
	public static function getExport(\Collection $cTask): array {

		$output = [];

		foreach($cTask->reverse(preserveKeys: TRUE) as $eTask) {

			$eToolPhyto = $eTask['eToolPhyto'];
			$water = $eTask['treatment']['water'] ?? NULL;

			if($eTask['series']->notEmpty()) {
				$crop = $eTask['series']['name'];
			} else if($eTask['plant']->notEmpty()) {
				$crop = $eTask['plant']['name'];
			} else {
				$crop = '';
			}

			$output[] = [
				$eTask['doneDate'] ?? '',
				$eTask['doneWeek'],
				$crop,
				$eTask['series']->empty() ? '' : self::getPlacesText($eTask['series']),
				$eTask['areaPlanned'] ? NULL : \util\TextUi::csvNumber($eTask['area']),
				$eToolPhyto->empty() ? '' : $eToolPhyto['name'],
				$eToolPhyto->empty() ? '' : ($eToolPhyto['routineValue']['amm'] ?? ''),
				$eToolPhyto->empty() ? NULL : \util\TextUi::csvNumber($eTask['treatment']['dose'] ?? NULL),
				$eTask['unit'] ?? '',
				($eToolPhyto->empty() or $eToolPhyto['routineValue']['readyToUse']) ? NULL : \util\TextUi::csvNumber($water),
				$eTask['areaPlanned'] ? NULL : \util\TextUi::csvNumber($eTask['quantity'], 3),
				implode(', ', $eTask['cTool']->getColumn('name')),
				$eTask['description'] ?? '',
			];

		}

		return $output;

	}

	/**
	 * Les emplacements d'une série en texte, comme l'écran les écrit (`CultivationUi::displayPlaces()`) :
	 * « Jardin > P1, P2 + Serre ».
	 */
	protected static function getPlacesText(\series\Series $eSeries): string {

		$list = [];

		foreach($eSeries['cccPlace'] as $ccPlace) {

			foreach($ccPlace as $cPlace) {

				$ePlaceFirst = $cPlace->first();
				$text = $ePlaceFirst['plot']['zoneFill'] ? $ePlaceFirst['zone']['name'] : $ePlaceFirst['plot']['name'];

				if($eSeries['use'] === \series\Series::BED) {

					$beds = $cPlace
						->find(fn($ePlace) => $ePlace['bed']['plotFill'] === FALSE)
						->makeArray(fn($ePlace) => $ePlace['bed']['name']);

					if($beds) {
						$text .= ' > '.implode(', ', $beds);
					}

				}

				$list[] = $text;

			}

		}

		return implode(' + ', $list);

	}

	/**
	 * Le matériel à proposer comme produit de traitement (carte de reprise, planche pf-l4-v3) : celui
	 * qui revient le plus souvent sur les traitements de l'année sans produit. Il est lié à l'action
	 * de traitement, sans type de matériel, actif, et la question n'y a pas déjà reçu « Non »
	 * (`work\Tool.phytoRefused`). Le nombre de traitements qui le portent est posé en `tasks`.
	 */
	public static function getToolReprise(\structure\Structure $eStructure, \work\Action $eAction, \Collection $cTaskWithout): \work\Tool {

		$tasks = [];

		foreach($cTaskWithout as $eTask) {
			foreach($eTask['tools'] as $tool) {
				$tasks[$tool] = ($tasks[$tool] ?? 0) + 1;
			}
		}

		if($tasks === []) {
			return new \work\Tool();
		}

		$cTool = \work\Tool::model()
			->select(['id', 'name'])
			->whereStructure($eStructure)
			->whereId('IN', array_keys($tasks))
			->whereAction($eAction)
			->whereRoutineName(NULL)
			->wherePhytoRefused(FALSE)
			->whereStatus(\work\Tool::ACTIVE)
			->getCollection();

		if($cTool->empty()) {
			return new \work\Tool();
		}

		foreach($cTool as $eTool) {
			$eTool['tasks'] = $tasks[$eTool['id']];
		}

		return $cTool
			->sort(['tasks' => SORT_DESC, 'name' => SORT_ASC])
			->first();

	}

}
?>
