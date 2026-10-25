<?php
namespace dev;

/**
 * Ce que remove() envoie au navigateur. ajax.js annonce les nœuds marqués vanish par l'événement
 * ajaxVanish avant la première instruction de la réponse.
 *
 * Lancement : dev/test package=dev file=InstructionRemove
 */
class InstructionRemoveTest extends \Test {

	public function testRemove(): void {

		$this->assertEquals(
			['qs', '#a', [['remove', [[]]]]],
			new \QuerySelectorInstruction('#a')->remove()->export()
		);

		$this->assertEquals(
			['qs', '#a', [['remove', [['mode' => 'fadeOut']]]]],
			new \QuerySelectorInstruction('#a')->remove(['mode' => 'fadeOut'])->export()
		);

	}

	public function testRemoveVanish(): void {

		$this->assertEquals(
			['qs', '#a', [['remove', [['vanish' => TRUE]]]]],
			new \QuerySelectorInstruction('#a')->remove(vanish: TRUE)->export()
		);

	}

	public function testRemoveVanishRefusesModeAndDelay(): void {

		foreach([['mode' => 'fadeOut'], ['delay' => 500]] as $options) {

			try {
				new \QuerySelectorInstruction('#a')->remove($options, vanish: TRUE);
				$this->assertFail('vanish ne se combine pas avec '.array_key_first($options));
			} catch(\Exception $e) {
				$this->assertException($e, 'Options \'mode\' and \'delay\' can not be used with vanish');
			}

		}

	}

}
?>
