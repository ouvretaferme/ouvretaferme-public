<?php
namespace map;

class DrawLib extends DrawCrud {

	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		return Draw::model()
			->select(Draw::getSelection())
			->whereStructure($eStructure)
			->getCollection(index: ['plot', NULL]);

	}

	/**
	 * Le coin et l'angle d'une planche sous leur seule écriture (`map.yml`, `Draw`) : un angle de `$angle` degrés ramené à [0°, 180°[,
	 * rendu en radians. Au-delà de 180°, la planche part du coin opposé, au bout de sa largeur et de sa longueur.
	 */
	public static function getCanonical(array $corner, float $angle, Bed $eBed): array {

		$eBed->expects(['length', 'width']);

		$angle = fmod(fmod($angle, 360) + 360, 360);

		if($angle >= 180) {
			$corner = MapLib::getDestination(MapLib::getDestination($corner, $eBed['width'] / 100, $angle), $eBed['length'], $angle + 90);
			$angle -= 180;
		}

		return [
			'corner' => $corner,
			'angle' => deg2rad($angle)
		];

	}

	/**
	 * Le rectangle d'une ligne écrit en trait de trois points, comme le tracé d'un rang d'une seule planche : le coin, le bout de la
	 * largeur au cap, puis la longueur à cap + 90°.
	 */
	public static function getLine(Draw $eDraw, Bed $eBed): array {

		$eDraw->expects(['corner', 'angle']);
		$eBed->expects(['length', 'width']);

		$angle = rad2deg($eDraw['angle']);
		$across = MapLib::getDestination($eDraw['corner'], $eBed['width'] / 100, $angle);

		return [
			$eDraw['corner'],
			$across,
			MapLib::getDestination($across, $eBed['length'], $angle + 90)
		];

	}

	/**
	 * Le trait d'où part le rang suivant d'un jardin, tiré des deux dernières planches posées (`$cDraw`, dans l'ordre de pose, chaque
	 * ligne portant la longueur et la largeur de sa planche). La forme canonique ramène le cap dans [0°, 180°[ et perd le sens du
	 * rang : le travers s'oriente donc de l'avant-dernière planche vers la dernière, et une planche seule garde son cap.
	 *
	 * Une planche qu'on vient de déplacer est la dernière posée, puisque le geste réécrit sa ligne : le rang suivant part du dernier
	 * geste, là où se trouve le regard. Il ne tombe pas pour autant sur les planches posées, que MapDrawingLine.getDefault() écarte.
	 */
	public static function getLineNext(\Collection $cDraw): ?array {

		if($cDraw->empty()) {
			return NULL;
		}

		$eDrawLast = $cDraw->last();
		$line = self::getLine($eDrawLast, $eDrawLast['bed']);

		if($cDraw->count() === 1) {
			return $line;
		}

		$eDrawBefore = $cDraw->slice($cDraw->count() - 2, 1)->first();

		$bearing = MapLib::getBearing($line[0], $line[1]);
		$bearingFromBefore = MapLib::getBearing($eDrawBefore['corner'], $line[0]);

		if(cos(deg2rad($bearingFromBefore - $bearing)) >= 0) {
			return $line;
		}

		// La planche d'avant est du côté où part la largeur : le même rectangle se lit du bout de la largeur vers le coin
		return [
			$line[1],
			$line[0],
			MapLib::getDestination($line[0], MapLib::getDistance($line[1], $line[2]), MapLib::getBearing($line[1], $line[2]))
		];

	}

	/**
	 * Les lignes que la saison montre pour ces planches, indexées par planche : les siennes, ou celles de la dernière saison tracée
	 * avant elle, dont elle hérite.
	 */
	public static function getBySeason(Plot $ePlot, int $season, \Collection $cBed): \Collection {

		$seasonDrawn = Draw::model()
			->wherePlot($ePlot)
			->whereSeason('<=', $season)
			->getValue(new \Sql('MAX(season)', 'int'));

		if($seasonDrawn === NULL) {
			return new \Collection();
		}

		return Draw::model()
			->select([
				'bed' => ['name', 'length', 'width', 'alleyWidth'],
				'corner', 'angle'
			])
			->wherePlot($ePlot)
			->whereSeason($seasonDrawn)
			->whereBed('IN', $cBed)
			->getCollection(index: 'bed');

	}

	/**
	 * Le rang droit que « Recommencer le dessin » repose : la première planche dans l'ordre de la Carte (naturel, par nom) garde son
	 * coin et son angle, et les suivantes prennent son angle et se posent à la suite, chacune séparée de la précédente par son
	 * passe-pied. Une planche sans passe-pied prend l'écart médian que laissent devant elles les planches sans passe-pied de tout le
	 * jardin (`$cDrawPlot`), avant le geste : celles qu'on coche sont souvent celles qu'on a dérangées, et les autres gardent
	 * l'écart d'origine. Un second geste retrouve le même écart, celles qui ont un passe-pied n'y entrant pas. Un écart négatif est
	 * un chevauchement, que le geste est là pour défaire : les planches se touchent alors.
	 *
	 * Le rang part du côté où se trouvait la deuxième planche : la forme canonique d'un angle (getCanonical()) ne dit pas dans quel
	 * sens il avançait. Toutes les planches gardant l'angle de la première, leur coin reste celui du début de leur largeur.
	 */
	public static function getRespaced(\Collection $cDraw, \Collection $cDrawPlot): \Collection {

		$cDraw->sort(['bed' => ['name' => SORT_ASC]], natural: TRUE);

		$draws = $cDraw->getArrayCopy();

		$eDrawFirst = $draws[array_key_first($draws)];
		$bearing = rad2deg($eDrawFirst['angle']);

		// La place des coins le long de la largeur de la première planche, dans le sens où le rang avance
		$eDrawSecond = array_values($draws)[1];
		$forward = (cos(deg2rad(MapLib::getBearing($eDrawFirst['corner'], $eDrawSecond['corner']) - $bearing)) >= 0);

		$offset = function(array $corner) use($eDrawFirst, $bearing, $forward): float {
			$distance = MapLib::getDistance($eDrawFirst['corner'], $corner) * cos(deg2rad(MapLib::getBearing($eDrawFirst['corner'], $corner) - $bearing));
			return $forward ? $distance : -$distance;
		};

		$cDrawPlot->sort(['bed' => ['name' => SORT_ASC]], natural: TRUE);

		$gaps = [];
		$eDrawBefore = NULL;

		foreach($cDrawPlot as $eDraw) {

			if($eDrawBefore !== NULL and $eDraw['bed']['alleyWidth'] === NULL) {
				// Vers l'avant, une planche commence au coin de sa largeur ; vers l'arrière, elle y finit
				$gaps[] = $forward ?
					$offset($eDraw['corner']) - $offset($eDrawBefore['corner']) - $eDrawBefore['bed']['width'] / 100 :
					$offset($eDraw['corner']) - $offset($eDrawBefore['corner']) - $eDraw['bed']['width'] / 100;
			}

			$eDrawBefore = $eDraw;

		}

		$gapMedian = max(self::getMedian($gaps), 0.0);

		$cDrawRespaced = new \Collection();
		$position = 0.0;
		$eDrawBefore = NULL;

		foreach($draws as $bedId => $eDraw) {

			$eBed = $eDraw['bed'];

			if($eDrawBefore !== NULL) {

				$gap = ($eBed['alleyWidth'] !== NULL) ? $eBed['alleyWidth'] / 100 : $gapMedian;

				$position += $forward ?
					$eDrawBefore['bed']['width'] / 100 + $gap :
					$eBed['width'] / 100 + $gap;

			}

			$cDrawRespaced[$bedId] = new Draw([
				'bed' => $eBed,
				'corner' => MapLib::getDestination($eDrawFirst['corner'], $position, $forward ? $bearing : $bearing + 180),
				'angle' => $eDrawFirst['angle']
			]);

			$eDrawBefore = $eDraw;

		}

		return $cDrawRespaced;

	}

	// Pour un nombre pair, entre les deux valeurs du milieu : la plus grande seule écarterait le rang, la plus petite le resserrerait
	private static function getMedian(array $values): float {

		if($values === []) {
			return 0.0;
		}

		sort($values);
		$middle = intdiv(count($values), 2);

		return (count($values) % 2 === 1) ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;

	}

	/* Les planches du jardin, de toutes ses saisons, suivent le mouvement de son contour (MapLib::getMotion()) */
	public static function moveByPlot(Plot $ePlot, array $motion): void {

		$ePlot->expects(['structure']);

		self::move(
			Draw::model()
				->whereStructure($ePlot['structure'])
				->wherePlot($ePlot),
			$motion
		);

	}

	/* Les planches de la parcelle, de tous ses jardins et de toutes ses saisons, suivent le mouvement de son contour */
	public static function moveByZone(Zone $eZone, array $motion): void {

		$eZone->expects(['id', 'structure']);

		self::move(
			Draw::model()
				->whereStructure($eZone['structure'])
				->whereZone($eZone),
			$motion
		);

	}

	/* Le coin tourne et se décale comme le contour, et l'angle tourne d'autant, ramené comme toute écriture (getCanonical()) */
	private static function move(DrawModel $mDraw, array $motion): void {

		Draw::model()->beginTransaction();

		$cDraw = $mDraw
			->select([
				'id', 'corner', 'angle',
				'bed' => ['length', 'width']
			])
			// Une marque de saison effacée n'a pas de coin à déplacer
			->whereBed('!=', NULL)
			// Chaque trait est réécrit depuis celui qu'on lit : un trait modifié entre-temps serait remplacé par l'ancien, déplacé
			->option('for-update')
			->getCollection();

		foreach($cDraw as $eDraw) {

			[$corner] = MapLib::getTransformed([$eDraw['corner']], $motion['origin'], $motion['shift'], $motion['angle']);

			$eDraw->merge(self::getCanonical($corner, rad2deg($eDraw['angle']) + $motion['angle'], $eDraw['bed']));

			Draw::model()
				->select(['corner', 'angle'])
				->update($eDraw);

		}

		Draw::model()->commit();

	}

	/**
	 * Les planches d'un jardin, de toutes ses saisons, recopiées sur sa copie : chacune tournée et décalée comme le contour de la copie
	 * (`placement`, Plot::properties()), sous la copie de sa planche (`$cBedBySource`, indexée par l'id d'origine). Une marque de
	 * saison effacée se recopie telle quelle ; une planche sans copie ne se recopie pas.
	 */
	public static function duplicateByPlot(Plot $ePlotSource, Plot $ePlot, \Collection $cBedBySource): void {

		$ePlot->expects(['structure', 'zone', 'placement']);

		$placement = $ePlot['placement'];

		$cDrawSource = Draw::model()
			->select(['season', 'bed', 'corner', 'angle'])
			->whereStructure($ePlotSource['structure'])
			->wherePlot($ePlotSource)
			->getCollection();

		foreach($cDrawSource as $eDrawSource) {

			$eDraw = new Draw([
				'structure' => $ePlot['structure'],
				'zone' => $ePlot['zone'],
				'plot' => $ePlot,
				'season' => $eDrawSource['season']
			]);

			if($eDrawSource['bed']->notEmpty()) {

				if($cBedBySource->offsetExists($eDrawSource['bed']['id']) === FALSE) {
					continue;
				}

				$eBed = $cBedBySource[$eDrawSource['bed']['id']];

				[$corner] = MapLib::getTransformed([$eDrawSource['corner']], $placement['origin'], $placement['shift'], $placement['angle']);

				$eDraw['bed'] = $eBed;
				$eDraw->merge(self::getCanonical($corner, rad2deg($eDrawSource['angle']) + $placement['angle'], $eBed));

			}

			parent::create($eDraw);

		}

	}

	/**
	 * Pose des planches du jardin dans la saison de `$e`, une ligne par planche avec son coin et son angle (`$cDraw`). Une planche
	 * déjà posée dans la saison change de place, et la saison perd sa marque si elle en avait une.
	 */
	public static function createByBeds(Draw $e, \Collection $cDraw): void {

		$e->expects([
			'season',
			'plot' => ['structure', 'zone']
		]);

		// Une planche sans longueur ou sans largeur n'a pas de rectangle à poser
		foreach($cDraw as $eDraw) {

			$eDraw->expects(['bed' => ['length', 'width'], 'corner', 'angle']);

			if($eDraw['bed']['length'] === NULL or $eDraw['bed']['width'] === NULL) {
				Draw::fail('bed.sized');
				return;
			}

		}

		$cBed = $cDraw->getColumnCollection('bed');

		self::startSeason($e);

		self::deleteBySeason($e, $cBed);

		foreach($cDraw as $eDraw) {

			$eDraw->merge([
				'structure' => $e['plot']['structure'],
				'zone' => $e['plot']['zone'],
				'plot' => $e['plot'],
				'season' => $e['season']
			]);

			parent::create($eDraw);

		}

		Draw::model()->commit();

	}

	/**
	 * Recommence le dessin des planches dessinées parmi `$cBed`, dans la saison de `$e` : un rang droit (getRespaced()), écrit comme
	 * un geste de rang. Il en faut deux pour faire un rang, et les planches non dessinées ne bougent pas.
	 */
	public static function respaceByBeds(Draw $e, \Collection $cBed): void {

		$e->expects(['season', 'plot']);

		// Le rang se lit derrière le verrou : une planche déplacée pendant le geste reposerait sinon à son ancienne place
		self::lockPlot($e);

		$cDraw = self::getBySeason($e['plot'], $e['season'], $cBed);

		if($cDraw->count() < 2) {
			Draw::fail('bed.spaced');
		} else {
			$cDrawPlot = self::getBySeason($e['plot'], $e['season'], BedLib::getByPlot($e['plot'], $e['season']));
			self::createByBeds($e, self::getRespaced($cDraw, $cDrawPlot));
		}

		Draw::model()->commit();

	}

	public static function deleteByBeds(Draw $e, \Collection $cBed): void {

		self::startSeason($e);

		self::deleteBySeason($e, $cBed);

		self::putMark($e['plot'], $e['season']);

		Draw::model()->commit();

	}

	/**
	 * Ouvre l'écriture d'une saison du jardin, dans une transaction que l'appelant valide, après y avoir recopié le tracé dont elle
	 * hérite.
	 */
	private static function startSeason(Draw $e): void {

		self::lockPlot($e);

		self::duplicateForSeason($e);

	}

	private static function lockPlot(Draw $e): void {

		$e->expects(['plot', 'season']);

		Draw::model()->beginTransaction();

		// Deux envois du même geste recopieraient chacun le tracé hérité : le second attend ici la fin du premier,
		// et lit derrière le verrou une saison déjà tracée. Première lecture de la transaction, sans quoi la suivante
		// reste d'avant l'attente
		Plot::model()
			->whereId($e['plot'])
			->getForUpdate();

	}

	/* Retire de la saison les lignes des planches, et sa marque : la saison qu'on écrit a désormais ses propres lignes, ou la reçoit de
	   nouveau si elle se vide (deleteByBeds()) */
	private static function deleteBySeason(Draw $e, \Collection $cBed): void {

		$cDraw = Draw::model()
			->select('id')
			->wherePlot($e['plot'])
			->whereSeason($e['season'])
			->or(
				fn() => $this->whereBed('IN', $cBed),
				fn() => $this->whereBed(NULL)
			)
			->getCollection();

		foreach($cDraw as $eDraw) {
			self::delete($eDraw);
		}

	}

	/**
	 * Une saison qui se vide alors qu'elle hérite de planches garde une marque : sans ligne, elle relirait le tracé de la saison
	 * antérieure qu'on vient d'y effacer. Elle n'en a pas besoin quand la saison dont elle hérite ne montre déjà rien.
	 */
	private static function putMark(Plot $ePlot, int $season): void {

		$ePlot->expects(['structure', 'zone']);

		if(
			Draw::model()
				->wherePlot($ePlot)
				->whereSeason($season)
				->exists()
		) {
			return;
		}

		$seasonInherited = Draw::model()
			->wherePlot($ePlot)
			->whereSeason('<', $season)
			->getValue(new \Sql('MAX(season)', 'int'));

		if(
			$seasonInherited === NULL or
			Draw::model()
				->wherePlot($ePlot)
				->whereSeason($seasonInherited)
				->whereBed('!=', NULL)
				->exists() === FALSE
		) {
			return;
		}

		parent::create(new Draw([
			'structure' => $ePlot['structure'],
			'zone' => $ePlot['zone'],
			'plot' => $ePlot,
			'season' => $season
		]));

	}

	/**
	 * Un jardin montre, pour une saison, le tracé de la dernière saison tracée jusqu'à elle. Écrire dans une saison
	 * qui hérite ainsi d'une saison antérieure y ferait disparaitre toutes les planches que le geste ne porte pas :
	 * leurs lignes y sont d'abord recopiées, et la saison d'origine garde les siennes.
	 *
	 * Une saison qui porte déjà ses lignes ne reçoit rien, et une saison postérieure déjà tracée continue de se lire
	 * sur ses propres lignes.
	 */
	private static function duplicateForSeason(Draw $e): void {

		$season = Draw::model()
			->wherePlot($e['plot'])
			->whereSeason('<=', $e['season'])
			->getValue(new \Sql('MAX(season)', 'int'));

		if($season === NULL or $season === $e['season']) {
			return;
		}

		$cDraw = Draw::model()
			->select(['structure', 'zone', 'plot', 'bed', 'corner', 'angle'])
			->wherePlot($e['plot'])
			->whereSeason($season)
			// Une marque de saison effacée n'a rien à transmettre à la suivante
			->whereBed('!=', NULL)
			->getCollection();

		foreach($cDraw as $eDraw) {

			parent::create(new Draw([
				'season' => $e['season']
			] + $eDraw->extracts(['structure', 'zone', 'plot', 'bed', 'corner', 'angle'])));

		}

	}

	/**
	 * Une planche qui change de jardin quitte celui qu'elle laisse, dans toutes ses saisons. Une saison qui s'en vide garde une
	 * marque aux conditions d'un effacement (putMark()).
	 */
	public static function deleteByBedsOutsidePlot(\Collection $cBed, Plot $ePlot): void {

		$ePlot->expects(['structure']);

		self::deleteEverySeason(
			Draw::model()
				->whereStructure($ePlot['structure'])
				->whereBed('IN', $cBed)
				->wherePlot('!=', $ePlot)
		);

	}

	/* Une planche supprimée pour de bon quitte toutes les saisons de son jardin, aux mêmes conditions qu'un changement de jardin */
	public static function deleteByBed(Bed $eBed): void {

		self::deleteEverySeason(
			Draw::model()->whereBed($eBed)
		);

	}

	private static function deleteEverySeason(DrawModel $mDraw): void {

		Draw::model()->beginTransaction();

		$cDraw = $mDraw
			->select(['id', 'plot' => ['structure', 'zone'], 'season'])
			->getCollection();

		foreach($cDraw as $eDraw) {
			self::delete($eDraw);
		}

		$seasons = [];

		foreach($cDraw as $eDraw) {
			$seasons[$eDraw['plot']['id'].'-'.$eDraw['season']] = $eDraw;
		}

		// Toutes les lignes sont parties avant la première marque : une saison antérieure vidée par le même geste ne transmet plus rien
		foreach($seasons as $eDraw) {
			self::putMark($eDraw['plot'], $eDraw['season']);
		}

		Draw::model()->commit();

	}

	public static function deleteByPlot(Plot $ePlot): void {

		$ePlot->expects(['id', 'structure']);

		$cDraw = Draw::model()
			->select('id')
			->whereStructure($ePlot['structure'])
			->wherePlot($ePlot)
			->getCollection();

		foreach($cDraw as $eDraw) {
			self::delete($eDraw);
		}

	}

	public static function deleteByZone(Zone $eZone): void {

		$eZone->expects(['id', 'structure']);

		$cDraw = Draw::model()
			->select('id')
			->whereStructure($eZone['structure'])
			->whereZone($eZone)
			->getCollection();

		foreach($cDraw as $eDraw) {
			self::delete($eDraw);
		}

	}

}
?>
