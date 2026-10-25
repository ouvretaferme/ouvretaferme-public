<?php
namespace series;

/**
 * Le découpage des semaines de récolte en campagnes, sur la frise des espèces d'une ferme face au
 * réseau (`CompareAiLib::getCampaigns()`).
 *
 * La règle est celle du plan de culture : un trou de plus de 45 jours ouvre une campagne. Les
 * semaines étant des multiples de 7 jours, la borne se lit entre 42 jours (même campagne) et 49
 * (campagne suivante), et les deux côtés sont éprouvés. Le passage d'année l'est aussi : les
 * semaines ISO débordent sur l'année civile suivante.
 *
 * La méthode est privée et n'écrit rien : elle est appelée par réflexion, sans décor.
 *
 * Lancement : dev/test package=series file=CompareCampaignAi
 */
class CompareCampaignAiTest extends \Test {

	private function getCampaigns(array $weeks): array {
		return new \ReflectionMethod(CompareAiLib::class, 'getCampaigns')->invoke(NULL, $weeks);
	}

	public function testGapBoundary(): void {

		$this->assertEquals([['2026-W12', '2026-W18']], $this->getCampaigns(['2026-W12', '2026-W18']), '42 jours d\'écart restent dans la même campagne');
		$this->assertEquals([['2026-W12', '2026-W12'], ['2026-W19', '2026-W19']], $this->getCampaigns(['2026-W12', '2026-W19']), '49 jours d\'écart ouvrent une campagne');

	}

	public function testUnorderedAndRepeatedWeeks(): void {

		$this->assertEquals(
			[['2026-W10', '2026-W13'], ['2026-W30', '2026-W31']],
			$this->getCampaigns(['2026-W31', '2026-W10', '2026-W13', '2026-W30', '2026-W10', '2026-W12']),
			'les semaines de plusieurs cultures se trient et se dédoublonnent avant le découpage'
		);

	}

	public function testYearOverflow(): void {

		$this->assertEquals([['2026-W51', '2027-W02']], $this->getCampaigns(['2027-W02', '2026-W51', '2026-W53', '2027-W01']), 'une campagne traverse le changement d\'année');

	}

	public function testEmpty(): void {
		$this->assertEquals([], $this->getCampaigns([]), 'aucune semaine, aucune campagne');
	}

}
