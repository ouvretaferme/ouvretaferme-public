<?php
namespace work;

/**
 * Inventaire des cultures du catalogue E-Phy (`work\PhytoUsage.crop`) face à `PhytoUsageLib::CROPS`
 * et `PhytoUsageLib::CROPS_OUTSIDE`.
 *
 * Un usage ne paraît sur la fiche d'une espèce que par les ids que sa culture lui a donnés à l'import
 * (`workPhytoUsage.plants`). Une culture absente des deux tables garde `[]` et disparaît de toutes les
 * fiches sans rien lever : l'import du mercredi la signale, mais seulement dans la sortie d'un cron.
 * Ce test la signale à celui qui lance les sentinelles, avec le nom de la culture.
 *
 * Il tient, dans les deux sens :
 * 1. toute culture en base est déclarée, reliée à des espèces (`CROPS`) ou écartée avec sa raison
 *    (`CROPS_OUTSIDE`), et aucune déclaration ne vise une culture que la base ne porte plus ;
 * 2. une culture n'est déclarée qu'une fois, sous sa clé normalisée (`getCropKey()`), sans quoi
 *    elle ne serait jamais lue ;
 * 3. tout fqn de `CROPS` désigne une espèce du référentiel partagé : un fqn faux ne lève rien, il
 *    retire l'espèce de la liste en silence.
 *
 * Lecture seule : les cultures et les espèces partagées sont lues, rien n'est écrit.
 *
 * Lancement : dev/test package=work file=PhytoCropSentinelAi
 */
class PhytoCropSentinelAiTest extends \Test {

	public function testEveryCropIsDeclared(): void {

		$keys = [];

		foreach(PhytoUsage::model()->select('crop')->group('crop')->getColumn('crop') as $crop) {
			$keys[PhytoUsageLib::getCropKey($crop)] = TRUE;
		}

		$this->assertDiffer([], $keys, 'le catalogue E-Phy est vide, le contrôle ne vérifie rien');

		$declared = array_merge(array_keys(PhytoUsageLib::CROPS), array_keys(PhytoUsageLib::CROPS_OUTSIDE));

		$undeclared = array_diff(array_keys($keys), $declared);
		$this->assertEquals([], array_values($undeclared), 'cultures en base absentes de PhytoUsageLib::CROPS et CROPS_OUTSIDE : '.implode(', ', $undeclared));

		$stale = array_diff($declared, array_keys($keys));
		$this->assertEquals([], array_values($stale), 'cultures déclarées que la base ne porte plus : '.implode(', ', $stale));

	}

	public function testDeclarationsAreUniqueAndNormalized(): void {

		$both = array_keys(array_intersect_key(PhytoUsageLib::CROPS, PhytoUsageLib::CROPS_OUTSIDE));
		$this->assertEquals([], $both, 'cultures à la fois reliées et écartées : '.implode(', ', $both));

		$declared = array_merge(array_keys(PhytoUsageLib::CROPS), array_keys(PhytoUsageLib::CROPS_OUTSIDE));
		$unnormalized = array_filter($declared, fn($key) => PhytoUsageLib::getCropKey($key) !== $key);
		$this->assertEquals([], array_values($unnormalized), 'clés que getCropKey() ne rend pas telles quelles, donc jamais lues : '.implode(', ', $unnormalized));

		$reasonless = array_keys(array_filter(PhytoUsageLib::CROPS_OUTSIDE, fn($reason) => $reason === ''));
		$this->assertEquals([], $reasonless, 'cultures écartées sans raison : '.implode(', ', $reasonless));

	}

	public function testEveryFqnExists(): void {

		$fqns = \plant\Plant::model()
			->select('fqn')
			->whereStructure(NULL)
			->getColumn('fqn');

		$unknown = [];

		foreach(PhytoUsageLib::CROPS as $key => $plants) {

			if($plants === 'all') {
				continue;
			}

			$this->assertDiffer([], $plants, $key.' est reliée à une liste vide, elle se déclare dans CROPS_OUTSIDE');

			foreach(array_diff($plants, $fqns) as $fqn) {
				$unknown[] = $key.' → '.$fqn;
			}

		}

		$this->assertEquals([], $unknown, 'fqn absents du référentiel partagé : '.implode(', ', $unknown));

	}

}
?>
