<?php
namespace planning;

/**
 * Le bandeau de l'ancien planning annonce le nouveau et mène à la même période (R-Retours-0810, AE).
 *
 * Il se pose sous le sélecteur de période, en tête du contenu, dans les trois vues : Quotidien mène au Jour,
 * Hebdomadaire à la Semaine, Annuel à l'Année. L'Annuel affiche un mois et le nouveau planning attend une
 * semaine : la semaine en cours pour le mois en cours, sinon celle du 4 du mois, qui appartient toujours à
 * l'année affichée.
 *
 * Lancement : dev/test package=planning file=OldPlanningAnnounce
 */
class OldPlanningAnnounceTest extends HarvestFixtureTest {

	public function testDailyAndWeekly(): void {

		$week = $this->getSeason().'-W36';

		foreach([
			\structure\Member::DAILY => TaskDayUi::url($this->eStructure, $week),
			\structure\Member::WEEKLY => TaskWeekUi::url($this->eStructure, $week),
		] as $view => $expected) {
			$this->checkAnnounce('ferme/'.$this->eStructure['id'].'/planning/'.$view.'/'.$week, $view, $expected);
		}

	}

	public function testYearly(): void {

		$season = $this->getSeason();

		$this->checkAnnounce('ferme/'.$this->eStructure['id'].'/planning/yearly/'.$season.'/'.(int)\Clock::format('n'), 'mois en cours', TaskYearUi::url($this->eStructure, \Clock::week()));
		$this->checkAnnounce('ferme/'.$this->eStructure['id'].'/planning/yearly/'.$season.'/1', 'janvier', TaskYearUi::url($this->eStructure, $season.'-W01'));
		$this->checkAnnounce('ferme/'.$this->eStructure['id'].'/planning/yearly/'.$season.'/3', 'mars', TaskYearUi::url($this->eStructure, toWeek($season.'-03-04')));

	}

	/**
	 * Le bouton du bandeau mène à l'adresse attendue.
	 */
	private function checkAnnounce(string $path, string $case, string $expected): void {

		$action = $this->executePage($path, [], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, $case.' : l\'ancien planning ne s\'ouvre pas') === FALSE) {
			return;
		}

		[, $template, $html] = $this->runView('planning', $action, 'index', 'series');
		$html .= $template->main;

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
		$blocks = $document->querySelectorAll('.util-block-info');

		$this->assertEquals($expected, $blocks->item(0)?->querySelector('.util-block-info-actions > a.btn')?->getAttribute('href'), $case.' : le bandeau ne mène pas à la même période du nouveau planning');

	}

}
?>
