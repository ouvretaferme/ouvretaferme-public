<?php
new \structure\StructurePage()
	->read(['/ferme/{id}/plan-3d', '/ferme/{id}/plan-3d/{season}'], function($data) {

		\map\MapLib::load($data, $data->e);

		// Les avatars disent qui est présent chaque jour de la saison, et le relief ne se lit que pour le plan : le bourg reçoit la
		// même scène, sans l'un ni l'autre
		$data->scene = \map\SceneLib::getForPlan($data->eStructure, $data->season);
		$data->bake = \scene\BakeLib::getCatalogAndVersions();

		// Une zone sans contour n'a rien à cadrer dans la scène
		$data->zones = [];

		foreach($data->scene['zones'] as $zone) {
			if($zone['coordinates'] !== NULL) {
				$data->zones[$zone['id']] = $zone['name'];
			}
		}

		// Le même ordre que la liste des zones du plan, que `ZoneLib` trie par nom naturel selon la langue
		uasort($data->zones, fn(string $name1, string $name2) => \L::getCollator(TRUE)->compare($name1, $name2));

		// Une saison passée ou à venir s'ouvre au même moment de l'année que celle en cours. Les jours de fin décembre
		// ou de début janvier qui appartiennent à une semaine ISO de l'année voisine ouvrent la saison à son bord
		$data->weeks = getWeeksInYear($data->season);

		$isoYear = (int)\Clock::format('o');
		$year = (int)\Clock::format('Y');

		if($isoYear > $year) {
			$data->week = $data->weeks;
		} else if($isoYear < $year) {
			$data->week = 1;
		} else {
			$data->week = min((int)\Clock::format('W'), $data->weeks);
		}

		throw new \ViewAction($data, ':scene');

	})
	// La scène que la bascule du relief remonte, relue pour le décor que la séance a pu changer depuis l'ouverture de la page
	->read('get', function($data) {

		\map\MapLib::load($data, $data->e);

		// L'équipe se met en forme à l'écran (`SceneUi::get()`), et aucun geste de la séance ne la change : la scène remontée garde
		// celle de la page
		$scene = \map\SceneLib::getForPlan($data->eStructure, $data->season);
		unset($scene['team']);

		throw new \JsonAction(['scene' => $scene]);

	});
?>
