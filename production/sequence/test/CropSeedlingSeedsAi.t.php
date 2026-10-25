<?php
namespace sequence;

/**
 * Le nombre de graines d'une production selon son implantation.
 *
 * Un plant acheté n'a pas de graines : le formulaire cache le champ, qui arrive vide. La règle le
 * convertissait pourtant en entier comme pour un semis, et le 0 obtenu tombait sous la borne du
 * schéma (int8(1, null)) : « Modifier la production » refusait toute production en plant acheté
 * dont la base portait NULL, sur un champ invisible, donc sans message (Aneth, production 11106 de
 * la structure 1848, 197 productions du parc au 27/09/2026).
 *
 * Aucune base : seul build() est exercé, sur un élément construit en mémoire.
 *
 * Lancement : dev/test package=sequence file=CropSeedlingSeedsAi
 */
class CropSeedlingSeedsAiTest extends \Test {

	private function build(string $seedling, ?string $seeds): array {

		$eCrop = new Crop([
			'sequence' => new Sequence(['use' => Sequence::BED]),
		]);

		$fw = new \FailWatch();

		$eCrop->build(['seedling', 'seedlingSeeds'], [
			'seedling' => $seedling,
			'seedlingSeeds' => $seeds,
		]);

		return [$eCrop, $fw->ok()];

	}

	public function testBoughtYoungPlantHasNoSeeds(): void {

		[$eCrop, $ok] = $this->build(Crop::YOUNG_PLANT_BOUGHT, '');

		$this->assertTrue($ok, 'le champ caché arrive vide et ne doit rien refuser');
		$this->assertEquals(NULL, $eCrop['seedlingSeeds']);

		[$eCrop, $ok] = $this->build(Crop::YOUNG_PLANT_BOUGHT, '3');

		$this->assertTrue($ok);
		$this->assertEquals(NULL, $eCrop['seedlingSeeds'], 'une valeur restée dans le champ caché ne s\'enregistre pas');

	}

	public function testSownOrRaisedCropKeepsItsSeeds(): void {

		[$eCrop, $ok] = $this->build(Crop::YOUNG_PLANT, '2');

		$this->assertTrue($ok);
		$this->assertEquals(2, $eCrop['seedlingSeeds']);

		[$eCrop, $ok] = $this->build(Crop::SOWING, '0');

		$this->assertFalse($ok, 'un semis garde la borne du schéma');

	}

}
?>
