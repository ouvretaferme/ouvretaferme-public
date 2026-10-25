<?php
namespace main;

/**
 * L'archive des bases du décor des tests, que `company/configure/backup` pose chaque nuit à côté de
 * celle du parc pour que le rechargement de la dev réinstalle les bases `structure_<id>` du décor.
 *
 * Seule la commande s'éprouve : en dev, `BackupLib::exec()` l'imprime au lieu de la lancer. Elle ne
 * doit archiver que les bases reçues, par leur nom relatif au répertoire des dumps, chacun échappé,
 * et écrire sous un nom temporaire renommé ensuite, pour qu'un scp pendant la compression ne
 * récupère jamais une archive tronquée.
 *
 * Lancement : dev/test package=main file=BackupDecorAi
 */
class BackupDecorAiTest extends \Test {

	const ARCHIVE = BackupLib::LOCAL_BACKUP_DIR.BackupLib::LOCAL_DECOR_BACKUP_FILE;
	const DUMPS = BackupLib::LOCAL_BACKUP_DIR.BackupLib::LOCAL_STRUCTURES_DUMP_DIR.'backup/';

	/**
	 * Les fichiers que la commande donne à tar, lus entre le répertoire des dumps et l'enchaînement
	 * vers le renommage. Le découpage suit le shell pour un nom sans apostrophe : escapeshellarg()
	 * en écrit une '\'', que str_getcsv() ne relit pas comme le shell. Les noms éprouvés n'en portent pas.
	 */
	private function getFiles(string $command): array {

		$start = strpos($command, ' -C '.self::DUMPS.' ');
		$end = strpos($command, ' && ');

		$this->assertTrue($start !== FALSE and $end !== FALSE, 'la commande porte le répertoire des dumps puis le renommage : '.$command);

		$arguments = substr($command, $start + strlen(' -C '.self::DUMPS.' '), $end - $start - strlen(' -C '.self::DUMPS.' '));

		return str_getcsv($arguments, ' ', '\'', '');

	}

	public function testArchivesOnlyTheBasesReceived(): void {

		$command = BackupLib::getDecorCommand(['structure_4', 'structure_164']);

		$this->assertEquals(['structure_4.sql', 'structure_164.sql'], $this->getFiles($command), 'une entrée par base reçue, aucune autre');

		foreach($this->getFiles($command) as $file) {
			$this->assertFalse(str_contains($file, '/'), 'le fichier '.$file.' est relatif au répertoire des dumps');
		}

	}

	public function testWritesUnderATemporaryNameThenRenames(): void {

		$command = BackupLib::getDecorCommand(['structure_4']);

		$this->assertTrue(str_starts_with($command, 'tar czf '.self::ARCHIVE.'.tmp -C '), 'tar écrit l\'archive sous son nom temporaire : '.$command);
		$this->assertTrue(str_ends_with($command, ' && mv '.self::ARCHIVE.'.tmp '.self::ARCHIVE), 'le renommage vers le nom final suit la compression, et seulement si elle réussit : '.$command);

	}

	public function testEscapesEachName(): void {

		$command = BackupLib::getDecorCommand(['structure_4; rm -rf /tmp/x', 'structure_$(id)']);

		$this->assertEquals(['structure_4; rm -rf /tmp/x.sql', 'structure_$(id).sql'], $this->getFiles($command), 'chaque nom reste un seul argument, tel quel');
		$this->assertTrue(str_contains($command, ' \'structure_4; rm -rf /tmp/x.sql\' '), 'le point-virgule reste entre apostrophes : '.$command);
		$this->assertTrue(str_contains($command, ' \'structure_$(id).sql\' '), 'la substitution reste entre apostrophes : '.$command);

	}

	public function testNothingForNoBase(): void {

		$this->assertEquals('', BackupLib::getDecorCommand([]), 'aucune commande sans base');

		ob_start();
		$failures = BackupLib::backupDecor([]);
		$printed = ob_get_clean();

		$this->assertEquals('', $printed, 'aucune archive n\'est écrite sans base, même vide');
		$this->assertEquals([], $failures, 'rien à archiver n\'est pas un échec');

	}

	public function testBackupRunsTheCommand(): void {

		ob_start();
		$failures = BackupLib::backupDecor(['structure_4', 'structure_164']);
		$printed = ob_get_clean();

		$this->assertEquals(BackupLib::getDecorCommand(['structure_4', 'structure_164'])."\n", $printed, 'backupDecor() lance la commande de getDecorCommand(), en dev par un echo');
		$this->assertEquals([], $failures, 'en dev, la commande imprimée compte pour réussie : la sauvegarde de dev ne sort pas en 1');

	}

}
?>
