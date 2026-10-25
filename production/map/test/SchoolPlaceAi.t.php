<?php
namespace map;

/**
 * Le coin d'une planche (`Draw.corner`, un `point`) suit le reste du plan quand la ferme école est recentrée
 * (`SchoolAiLib::place()`) : il garde, au millimètre, son écart au contour de son jardin (`Plot.coordinates`, un `polygon`).
 * Les deux se lisent en WKT, que `place()` retourne en (longitude, latitude) chacun par sa propre lecture : une inversion
 * d'un seul côté déplacerait chaque planche de la ferme école loin de son jardin sans qu'aucun autre test le voie.
 *
 * Le décor s'écrit sur un numéro au-dessus de toutes les structures, que rien ne peut porter, dans la transaction de la méthode :
 * `place()` recentre tout le plan de la structure qu'on lui donne, parcelles et jardins compris.
 *
 * Lancement : dev/test package=map file=SchoolPlaceAi
 */
class SchoolPlaceAiTest extends \dev\TransactionTest {

	const array CONTOUR = [[3.0, 45.0], [3.2, 45.4], [3.4, 45.4], [3.0, 45.0]];

	const array CORNER = [3.1, 45.6];

	protected function getTransactionModel(): \ModuleModel {
		return new DrawModel();
	}

	public function testCornerKeepsItsPlaceInThePlan(): void {

		$structure = (int)\structure\Structure::model()->getValue(new \Sql('MAX(id)')) + 1;

		$eStructure = new \structure\Structure(['id' => $structure]);

		// Le jardin et la planche qu'il crée prennent leur auteur dans la connexion
		\user\ConnectionLib::setOnline(new \user\User(['id' => 1]));
		$eZone = new Zone(['id' => 1, 'structure' => $eStructure]);

		$ePlot = new Plot([
			'zone' => $eZone,
			'name' => 'Jardin recentré',
			'mode' => Plot::OPEN_FIELD,
			'area' => 100,
			'coordinates' => self::CONTOUR
		]);

		// Sans build(), qui refuserait une parcelle d'une autre structure (`zone.owned`) : le décor ne porte pas de parcelle réelle, place() ne lit que la géométrie
		PlotLib::create($ePlot);

		$eDrawBed = new Draw([
			'structure' => $eStructure,
			'zone' => $eZone,
			'plot' => $ePlot,
			'season' => 2027,
			'bed' => new Bed(['id' => 1]),
			'corner' => self::CORNER,
			'angle' => 0.5
		]);

		// Sans build(), qui vérifierait la saison du jardin, pour la même raison
		DrawLib::create($eDrawBed);

		$result = \structure\SchoolAiLib::place($structure);

		$this->assertEquals(5, $result['points'], 'les quatre points du contour et le coin de la planche sont recentrés');

		$contour = Plot::model()->whereId($ePlot['id'])->getValue('coordinates');
		$corner = Draw::model()->whereId($eDrawBed['id'])->getValue('corner');

		$this->assertTrue(MapLib::getDistance($contour[0], self::CONTOUR[0]) > 1000, 'le plan a bien quitté sa position d\'origine');

		$this->assertTrue(abs(($corner[0] - $contour[0][0]) - (self::CORNER[0] - self::CONTOUR[0][0])) < 0.00000001, 'le coin garde son écart en longitude au contour');
		$this->assertTrue(abs(($corner[1] - $contour[0][1]) - (self::CORNER[1] - self::CONTOUR[0][1])) < 0.00000001, 'et en latitude');

	}

}
?>
