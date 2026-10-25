<?php
namespace series;

/**
 * Le bac de l'assolement n'offre pas de série clôturée, mais il n'efface pas celle qu'on
 * lui demande explicitement.
 *
 * Les deux conditions se cumulaient en AND : place:panel?series=<clôturée> demandait une série
 * que le filtre venait d'exclure, et le bac revenait vide. La série demandée est désormais sortie
 * de la liste des exclues avant que le filtre ne s'applique.
 *
 * Le NOT IN reste, contre l'anti-join que demande CLAUDE.md : joindre series rend « area »
 * ambiguë dans une sélection de cette largeur. La liste est bornée par la saison — 397
 * identifiants au maximum mesuré, 34,9 en moyenne. Écart assumé, la raison est dans le Lib.
 *
 * Ce test touche la base : structure 7, saison 2026.
 *
 * Lancement : dev/test package=series file=SelectorFilterAi
 */
class SelectorFilterAiTest extends \Test {

	private function structure(): \structure\Structure {

		return \structure\StructureLib::getById(7);

	}

	private function count(\Collection $ccCultivation): int {

		$n = 0;

		foreach($ccCultivation as $cCultivation) {
			$n += $cCultivation->count();
		}

		return $n;

	}

	public function testClosedSeriesAreOutOfTheTray(): void {

		$eStructure = $this->structure();

		$ccCultivation = CultivationLib::getForSelector($eStructure, 2026);

		$closed = 0;

		foreach($ccCultivation as $cCultivation) {
			foreach($cCultivation as $eCultivation) {
				if($eCultivation['series']['status'] === Series::CLOSED) {
					$closed++;
				}
			}
		}

		echo "\n  bac structure 7, saison 2026 : ".$this->count($ccCultivation)." cartes, dont ".$closed." clôturées\n";

		$this->assertEquals(0, $closed, 'aucune carte clôturée dans le bac');
		$this->assertTrue($this->count($ccCultivation) > 0, 'et le bac n\'est pas vide, sinon le contrôle ne prouve rien');

	}

	public function testAnExplicitlyAskedClosedSeriesSurvives(): void {

		$eStructure = $this->structure();

		$eSeries = Series::model()
			->select(['id', 'status'])
			->whereStructure($eStructure)
			->whereSeason(2026)
			->whereStatus(Series::CLOSED)
			->get();

		$this->assertTrue($eSeries->notEmpty(), 'la structure de dev porte bien une série clôturée');

		$ccCultivation = CultivationLib::getForSelector($eStructure, 2026, $eSeries);

		echo "  série clôturée ".$eSeries['id']." demandée explicitement : ".$this->count($ccCultivation)." carte(s)\n";

		$this->assertTrue($this->count($ccCultivation) > 0, 'la série demandée n\'est pas effacée par le filtre');

	}

	public function testAnOpenSeriesIsStillFound(): void {

		$eStructure = $this->structure();

		$eSeries = Series::model()
			->select(['id'])
			->whereStructure($eStructure)
			->whereSeason(2026)
			->whereStatus('!=', Series::CLOSED)
			->get();

		$ccCultivation = CultivationLib::getForSelector($eStructure, 2026, $eSeries);

		echo "  série ouverte ".$eSeries['id']." demandée explicitement : ".$this->count($ccCultivation)." carte(s)\n";

		$this->assertTrue($this->count($ccCultivation) > 0, 'une série ouverte demandée reste trouvable');

	}

}
?>
