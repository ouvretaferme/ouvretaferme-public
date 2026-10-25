<?php
namespace structure;

/**
 * L'empreinte des liens publics d'une structure (`Structure::getChecksum()`, `checkChecksum()`).
 *
 * Elle protège l'inscription à la lettre d'une structure (`/optIn/{structure}/{checksum}`) et le
 * désabonnement des rappels de relevés de prix. Un CRC32 de l'identifiant suivi du secret se
 * calculait d'une structure à l'autre sans le secret : l'empreinte est maintenant un HMAC, propre à
 * chaque structure, et l'ancienne reste acceptée jusqu'à `Structure::CHECKSUM_LEGACY_UNTIL`, pour les
 * liens que les structures ont copiés hors du logiciel.
 *
 * Aucune écriture : des éléments construits à la main suffisent.
 *
 * Lancement : dev/test package=structure file=StructureChecksumAi
 */
class StructureChecksumAiTest extends \Test {

	public function testTheChecksumIsSpecificToEachStructure(): void {

		$eStructure = new Structure(['id' => 1234]);
		$eStructureOther = new Structure(['id' => 5678]);

		$this->assertTrue($eStructure->checkChecksum($eStructure->getChecksum()), 'une structure accepte son empreinte');
		$this->assertFalse($eStructureOther->checkChecksum($eStructure->getChecksum()), 'une structure refuse l\'empreinte d\'une autre');
		$this->assertFalse($eStructure->checkChecksum(''), 'une empreinte vide est refusée');

	}

	public function testTheLegacyChecksumIsAcceptedUntilItsLastDay(): void {

		$eStructure = new Structure(['id' => 1234]);
		$legacy = (string)abs(crc32($eStructure['id'].\main\MainSetting::$crypt['structure']));

		$this->setClock(Structure::CHECKSUM_LEGACY_UNTIL.' 23:59:00');
		$this->assertTrue($eStructure->checkChecksum($legacy), 'l\'ancienne empreinte est acceptée le dernier jour');

		$this->setClock(date('Y-m-d', strtotime(Structure::CHECKSUM_LEGACY_UNTIL.' +1 day')).' 00:01:00');
		$this->assertFalse($eStructure->checkChecksum($legacy), 'l\'ancienne empreinte est refusée le lendemain');
		$this->assertTrue($eStructure->checkChecksum($eStructure->getChecksum()), 'la nouvelle reste acceptée');

	}

}
?>
