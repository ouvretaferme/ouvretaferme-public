<?php
namespace entry;

/**
 * Le décor des tests du champ : celui du package `series` (espèce, séries, cultures, interventions,
 * dans la transaction de la méthode), plus une espèce au nom fait de lettres seules, et la phase
 * d'une culture posée à la main.
 *
 * L'espèce du décor `series` porte un `uniqid()` dans son nom, que `EntryLib::split()` couperait
 * entre chiffres et lettres (« 66f7 » devient « 66 f7 ») : le champ lirait un morceau du nom comme
 * une quantité.
 *
 * Abstraite, donc ignorée par `TestLib::run()`.
 */
abstract class EntryFixtureTest extends \series\SeriesFixtureAiTest {

	/**
	 * Le membre mis en ligne est un membre qui a le planning entier : l'écriture valide
	 * `canCreate` et `canWrite` sur l'intervention, que le premier membre par nom du décor `series`
	 * (un observateur sur la structure 7) n'a pas.
	 */
	protected function getMember(): \user\User {

		return $this->once('member', function() {

			foreach(\structure\MemberLib::getByStructure($this->eStructure, index: NULL) as $eMember) {

				if($eMember['role']->notEmpty() and $eMember->isRole('planning', \structure\Role::FULL)) {
					return $eMember['user'];
				}

			}

			// Sans lui, chaque cas tomberait sur un refus de droit loin de sa cause : le décor le nomme.
			$this->assertFail('la structure '.static::STRUCTURE.' a un membre avec le planning complet');

			return new \user\User();

		});

	}

	/**
	 * Forme de `AmapFixtureTest::executePageAction()` : la page est exécutée par le routeur, avec
	 * son constructeur commun, et l'action qu'elle lève est rendue. En `GET`, les paramètres de la
	 * route viennent du chemin, le reste de `$input`. L'utilisateur en ligne est posé sur `$data` comme
	 * `main\PageLib::common()` le pose en HTTP, que le lanceur de tests court-circuite.
	 */
	protected function executePageAction(string $path, array $input, string $method = 'POST'): ?\Action {

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->hasAvatar = \map\SceneLib::isOpenByStructures(\structure\StructureLib::getOnline());

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		// Chaque route tourne dans une requête neuve : un état statique qu'une page garnit pour sa vue
		// ne doit pas rester d'un appel à l'autre, sinon la route suivante le lit sans l'avoir posé.
		// Cas vu : `WorkSetting::$mainActions`, garni par le planning, que `doWrite` lisait sans le
		// poser, vert ici et en erreur dans le navigateur.
		\work\WorkSetting::$mainActions = NULL;

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		$_REQUEST = $input;

		if($method === 'GET') {
			$_GET = $input;
		} else {
			$_POST = $input;
		}

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	protected function createPlant(string $name = 'Entrée'): \plant\Plant {

		$ePlant = new \plant\Plant([
			'name' => $name.' '.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10),
			'structure' => $this->eStructure,
			'cycle' => \plant\Plant::ANNUAL,
		]);

		// Sans `build()` : il vérifierait le nom et le cycle postés depuis le formulaire d'espèce, alors que
		// le décor les fixe lui-même, un nom de lettres seules que le champ ne coupe pas.
		\plant\PlantLib::create($ePlant);

		return $ePlant;

	}

	/**
	 * L'horloge d'un test qui dit un jour passé, à la mi-juillet de la saison du décor : jusqu'à
	 * `EntrySetting::PAST_DAYS` jours plus tôt, le jour dit reste dans la saison des séries
	 * du décor, qu'une date de janvier ferait passer dans la saison d'avant.
	 */
	protected function setClockInSeason(): void {

		$this->setClock($this->getSeason().'-07-15 10:00:00');

	}

	/**
	 * La phase d'une culture, par sa semaine de départ et sa récolte prévue : en phase, un départ
	 * deux semaines plus tôt et une récolte prévue cette semaine ; hors phase, une fenêtre close
	 * dans une autre moitié de l'année.
	 */
	protected function setPhase(\series\Cultivation $eCultivation, bool $inPhase): void {

		$week = week_number(\Clock::week());
		$season = $this->getSeason();

		if($inPhase) {
			$eCultivation['startWeek'] = max(1, $week - 2);
			$eCultivation['harvestWeeksExpected'] = [\Clock::week()];
		} else if($week > 20) {
			$eCultivation['startWeek'] = 1;
			$eCultivation['harvestWeeksExpected'] = [$season.'-W05'];
		} else {
			$eCultivation['startWeek'] = 40;
			$eCultivation['harvestWeeksExpected'] = [$season.'-W45'];
		}

		// Le produit ne pose jamais une semaine de départ sans son geste (0 culture sur 158 735 en
		// dev), et la pastille de départ refuse ce cas.
		$eCultivation['startAction'] = \series\Cultivation::PLANTING;

		\series\Cultivation::model()
			->select(['startWeek', 'startAction', 'harvestWeeksExpected'])
			->update($eCultivation);

	}

	protected function createCultivationInPhase(bool $inPhase, string $seriesName = 'Série du décor'): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeries($seriesName), $this->getPlant());

		$this->setPhase($eCultivation, $inPhase);

		return $eCultivation;

	}

	/**
	 * Une culture sur une série de la saison donnée, qui en porte la saison.
	 */
	protected function createCultivationOfSeason(string $seriesName, int $season): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeries($seriesName, $season), $this->getPlant());

		// Sans `build()` : la saison d'une culture suit celle de sa série et ne se saisit pas, alors que le
		// décor de `series` la pose toujours à la saison du décor.
		$eCultivation['season'] = $season;

		\series\Cultivation::model()
			->select(['season'])
			->update($eCultivation);

		return $eCultivation;

	}

	/**
	 * Une culture de la saison d'avant, hors phase, sur une série de cette saison-là.
	 */
	protected function createCultivationPreviousSeason(string $seriesName): \series\Cultivation {

		$eCultivation = $this->createCultivationOfSeason($seriesName, $this->getSeason() - 1);

		$this->setPhase($eCultivation, FALSE);

		return $eCultivation;

	}

	/**
	 * Une culture de la saison suivante, en phase, sur une série de cette saison-là : semée deux semaines
	 * plus tôt, donc l'année d'avant sa saison, comme une fève d'automne. Une semaine de départ de l'année
	 * d'avant s'écrit moins cent (`\series\Cultivation::getStartWeek()`).
	 */
	protected function createCultivationNextSeasonInPhase(string $seriesName): \series\Cultivation {

		$eCultivation = $this->createCultivationOfSeason($seriesName, $this->getSeason() + 1);

		$this->setPhase($eCultivation, TRUE);

		// Sans `build()` : la semaine de départ en phase, que `setPhase()` pose dans la saison du décor, passe
		// à l'année d'avant celle de la série, ce qu'un formulaire écrit par sa liste de semaines.
		$eCultivation['startWeek'] -= 100;

		\series\Cultivation::model()
			->select(['startWeek'])
			->update($eCultivation);

		return $eCultivation;

	}

	protected function read(string $text, array $answers = []): array {

		return EntryLib::read($this->eStructure, $text, $answers);

	}

	/**
	 * Annule une saisie comme sa pastille : par l'intention et le jeton que l'écriture a posés
	 * (`reading['undo']`).
	 */
	protected function cancelUndo(array $undo): array {

		$cancellation = EntryLib::prepareCancel($this->eStructure, $undo['intention'], $undo['ticket']);

		EntryLib::cancel($this->eStructure, $undo['intention'], $cancellation);

		return $cancellation;

	}

	protected function getText(string $rest = ''): string {

		return 'récolte '.$this->getPlant()['name'].' '.$rest;

	}

	/**
	 * Une récolte à faire, planifiée au jour décalé de `$shift` par rapport à aujourd'hui, au kilo sur la
	 * culture entière sauf ce que `$properties` pose.
	 */
	protected function createTaskTodo(\series\Cultivation $eCultivation, string $shift, array $properties = []): \series\Task {

		$date = date('Y-m-d', \Clock::shift($shift));

		$eTask = new \series\Task($properties + [
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_RECOLTE),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => toWeek($date),
			'plannedDate' => $date,
			'harvestUnit' => $eCultivation['mainUnit'],
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
			'repeatMaster' => new \series\Repeat(),
		]);

		\series\TaskLib::create($eTask);

		return $eTask;

	}

	protected function getHarvest(\series\Cultivation $eCultivation): \Collection {

		return \series\Task::model()
			->select(['id', 'status', 'doneDate', 'harvest', 'harvestUnit'])
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->getCollection();

	}

}
?>
