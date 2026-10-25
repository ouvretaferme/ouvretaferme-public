<?php
namespace main;

/**
 * Le contrôle de l'espace disque du serveur de sauvegarde (chantier sécurité, S8) : ce que le
 * serveur peut encore recevoir se mesure en jours, et Discord est prévenu sous cinq jours ou quand
 * la mesure échoue. Le ssh n'est pas exercé : tout se joue sur la sortie de df et de du, telle que
 * le serveur la rend.
 *
 * Ce que la prévision divise est le piège de ce contrôle. Rapporter l'espace libre à ce que la nuit
 * a écrit annonce la fin du disque chaque matin, puisque le nettoyage de la même nuit libère la
 * sauvegarde qui sort de la fenêtre : la consommation réelle est la différence entre les deux, soit
 * la seule croissance des données. Mesuré le 2026-10-02 en production, 40 Go libres et 22,5 Go
 * écrits dans la nuit ont donné « il reste 1 jour » alors que l'occupation était stable.
 *
 * Le second contrat est la fenêtre de conservation, que rien ne dépasse. La politique de
 * confidentialité promet qu'une donnée supprimée disparaît des sauvegardes au plus tard quinze
 * jours après sa suppression, et la conservation du 1er et du 15 pendant sept ans ne tenait ni
 * cette promesse ni le disque, une archive de storage pesant 22 Go pour 40 Go libres.
 *
 * Lancement : dev/test package=main file=BackupDiskAi
 */
class BackupDiskAiTest extends \Test {

	const DF = [
		'Filesystem     1024-blocks      Used Available Capacity Mounted on',
		'/dev/root         81106868  68104526  13002342      84% /',
	];

	/** L'espace libre que porte DF, et ce que le décor de du écrit dans la nuit */
	const FREE = 13002342;
	const WRITTEN = 4299161;

	/** Ce qui reste à la croissance, la sauvegarde de la nuit suivante étant gardée en réserve */
	const AVAILABLE = self::FREE - self::WRITTEN;

	protected function du(int $database, int $structures, int $storage): array {

		return [
			$database.'	/home/ubuntu/otf-backup/mysql/2026-09-23.tar.gz',
			$structures.'	/home/ubuntu/otf-backup/mysql/2026-09-23-structure.tar.gz',
			$storage.'	/home/ubuntu/otf-backup/storage/2026-09-23.tar.gz',
		];

	}

	/**
	 * Un relevé complet, et l'espace libre d'avant la nuit qui va avec : la croissance demandée se
	 * retranche de ce que DF porte.
	 */
	protected function night(int $growth): array {

		return [[...self::DF, ...$this->du(3000000, 1000000, 299161)], self::FREE + $growth];

	}

	public function testAvailableSpaceIsTheFourthColumn(): void {

		$this->assertEquals(13002342, BackupLib::getAvailableSpace(self::DF));
		$this->assertEquals(NULL, BackupLib::getAvailableSpace([]));
		$this->assertEquals(NULL, BackupLib::getAvailableSpace(['ssh: connect to host backup port 22: Connection timed out']));
		$this->assertEquals(NULL, BackupLib::getAvailableSpace([self::DF[0]]), 'l\'en-tête seul ne porte aucun nombre');

	}

	public function testWrittenSpaceSumsTheFilesOfTheDay(): void {

		$this->assertEquals(0, BackupLib::getWrittenSpace([]));
		$this->assertEquals(0, BackupLib::getWrittenSpace(self::DF), 'la ligne de df commence par un chemin, pas par une taille');
		$this->assertEquals(4299161, BackupLib::getWrittenSpace($this->du(3000000, 1000000, 299161)));
		$this->assertEquals(4299161, BackupLib::getWrittenSpace([...self::DF, ...$this->du(3000000, 1000000, 299161)]));

	}

	public function testRemainingDaysIsAvailableOverGrowth(): void {

		$this->assertEquals(3, BackupLib::getRemainingDays(13002342, 4299161));
		$this->assertEquals(0, BackupLib::getRemainingDays(1000, 4299161));
		$this->assertEquals(100, BackupLib::getRemainingDays(100 * 4299161, 4299161));

	}

	public function testFormatSpace(): void {

		$this->assertEquals('12,4 Go', BackupLib::formatSpace(13002342));
		$this->assertEquals('4,1 Go', BackupLib::formatSpace(4299161));
		$this->assertEquals('0,3 Go', BackupLib::formatSpace(299161));
		$this->assertEquals('1 024,0 Go', BackupLib::formatSpace(1024 * 1024 * 1024));

	}

	/**
	 * Le cas de la production du 2026-10-02 : la nuit écrit une sauvegarde entière et le nettoyage en
	 * libère presque autant, donc rien ne se dit. C'est ce cas que l'ancienne division annonçait à un
	 * jour de la fin.
	 */
	public function testNothingToSayWhenTheNightFreesWhatItWrote(): void {

		[$lines, $freeBefore] = $this->night(100000);

		$this->assertEquals(87, BackupLib::getRemainingDays(self::AVAILABLE, 100000));
		$this->assertEquals(NULL, BackupLib::getDiskSpaceMessage($lines, $freeBefore));

		$this->assertTrue(
			BackupLib::getRemainingDays(self::FREE, self::WRITTEN) < BackupLib::DISK_SPACE_ALERT_IN_DAYS,
			'sous l\'ancienne division, l\'espace libre rapporté à la sauvegarde entière passait sous le seuil et alertait sur ce même relevé'
		);

	}

	/**
	 * Une nuit qui libère autant ou plus qu'elle écrit ne consomme rien, et une prévision n'a alors
	 * aucun sens : la division par zéro n'est pas évitée par un plancher, elle est écartée.
	 */
	public function testNothingToSayWhenNothingWasConsumed(): void {

		[$lines, $freeBefore] = $this->night(0);
		$this->assertEquals(NULL, BackupLib::getDiskSpaceMessage($lines, $freeBefore), 'autant libéré qu\'écrit');

		[$lines, $freeBefore] = $this->night(-2000000);
		$this->assertEquals(NULL, BackupLib::getDiskSpaceMessage($lines, $freeBefore), 'plus libéré qu\'écrit, le disque se vide');

	}

	/**
	 * Sans l'espace libre d'avant la nuit, la croissance est inconnue : seule la place de la nuit
	 * suivante reste contrôlable, et c'est ce qui se dit.
	 */
	public function testWithoutTheNightBeforeOnlyTheRoomIsChecked(): void {

		$this->assertEquals(NULL, BackupLib::getDiskSpaceMessage([...self::DF, ...$this->du(3000000, 1000000, 299161)]));

		$this->assertEquals(
			'💾 La sauvegarde de la prochaine nuit ne tiendra pas sur le serveur de sauvegarde (12,4 Go libres, 14,3 Go écrits chaque nuit).',
			BackupLib::getDiskSpaceMessage([...self::DF, ...$this->du(15000000, 0, 0)])
		);

	}

	/**
	 * La réserve d'une nuit entière n'est pas une marge de confort : le cron envoie la sauvegarde
	 * avant que le nettoyage ne libère la plus ancienne, donc un disque qui ne peut plus l'accueillir
	 * ne reçoit plus rien, quelle que soit la croissance.
	 */
	public function testTheNextNightMustFitBeforeAnythingIsFreed(): void {

		[$lines, $freeBefore] = $this->night(100000);

		$this->assertEquals(NULL, BackupLib::getDiskSpaceMessage($lines, $freeBefore), 'la nuit tient, et la croissance laisse du temps');

		$short = [...self::DF, ...$this->du(13002343, 0, 0)];

		$this->assertEquals(
			'💾 La sauvegarde de la prochaine nuit ne tiendra pas sur le serveur de sauvegarde (12,4 Go libres, 12,4 Go écrits chaque nuit).',
			BackupLib::getDiskSpaceMessage($short, self::FREE + 100000),
			'un Kio de plus que l\'espace libre, et la sauvegarde ne passe plus'
		);

	}

	public function testThresholdIsFiveDays(): void {

		$growth = intdiv(self::AVAILABLE, BackupLib::DISK_SPACE_ALERT_IN_DAYS);

		[$lines, $freeBefore] = $this->night($growth);
		$this->assertEquals(NULL, BackupLib::getDiskSpaceMessage($lines, $freeBefore), 'cinq jours ne déclenchent rien');

		[$lines, $freeBefore] = $this->night($growth + 1);
		$this->assertTrue(BackupLib::getDiskSpaceMessage($lines, $freeBefore) !== NULL, 'quatre jours déclenchent');

	}

	public function testMessageUnderThreshold(): void {

		[$lines, $freeBefore] = $this->night(2000000);

		$this->assertEquals(
			'💾 Il reste 4 jours d\'espace disque sur le serveur de sauvegarde (12,4 Go libres, dont 4,1 Go à garder pour la sauvegarde de chaque nuit, et 1,9 Go consommés cette nuit).',
			BackupLib::getDiskSpaceMessage($lines, $freeBefore)
		);

		[$lines, $freeBefore] = $this->night(5000000);

		$this->assertEquals(
			'💾 Il reste 1 jour d\'espace disque sur le serveur de sauvegarde (12,4 Go libres, dont 4,1 Go à garder pour la sauvegarde de chaque nuit, et 4,8 Go consommés cette nuit).',
			BackupLib::getDiskSpaceMessage($lines, $freeBefore)
		);

		[$lines, $freeBefore] = $this->night(9000000);

		$this->assertEquals(
			'💾 Il reste moins d\'un jour d\'espace disque sur le serveur de sauvegarde (12,4 Go libres, dont 4,1 Go à garder pour la sauvegarde de chaque nuit, et 8,6 Go consommés cette nuit).',
			BackupLib::getDiskSpaceMessage($lines, $freeBefore)
		);

	}

	public function testMessageWhenTheServerDoesNotAnswer(): void {

		$this->assertEquals(
			'💾 Le serveur de sauvegarde n\'a pas répondu au contrôle de l\'espace disque.',
			BackupLib::getDiskSpaceMessage([], self::FREE)
		);

		$this->assertEquals(
			'💾 Le serveur de sauvegarde n\'a pas répondu au contrôle de l\'espace disque.',
			BackupLib::getDiskSpaceMessage(['ssh: connect to host backup port 22: Connection timed out'], self::FREE)
		);

	}

	public function testMessageWhenNothingWasWrittenToday(): void {

		$this->assertEquals(
			'💾 Aucune sauvegarde du jour sur le serveur de sauvegarde (12,4 Go libres).',
			BackupLib::getDiskSpaceMessage(self::DF, self::FREE),
			'df a répondu mais du n\'a trouvé aucun fichier du jour : la sauvegarde n\'est pas arrivée'
		);

	}

	/**
	 * Rien ne survit à la fenêtre, et le 1er ni le 15 du mois n'y font exception. La conservation
	 * sept ans de ces deux jours promettait un point de restauration que la politique de
	 * confidentialité interdit, et le disque ne pouvait pas la payer : mesuré le 2026-10-02, le
	 * storage du 2026-09-15 pesait 21 Go des 40 Go encore libres, et deux archives par mois
	 * s'ajoutaient sans jamais être libérées.
	 */
	public function testNothingIsKeptBeyondTheWindow(): void {

		$today = '2026-09-23';

		$this->assertTrue(BackupLib::isKept('2026-09-20.tar.gz', $today), 'trois jours');
		$this->assertTrue(BackupLib::isKept('2026-09-08.tar.gz', $today), 'quinze jours, dernier jour gardé');
		$this->assertFalse(BackupLib::isKept('2026-09-07.tar.gz', $today), 'seize jours');
		$this->assertFalse(BackupLib::isKept('2026-09-07-structure.tar.gz', $today), 'le suffixe ne change rien');

		$this->assertFalse(BackupLib::isKept('2026-09-01.tar.gz', $today), 'le 1er, vingt-deux jours');
		$this->assertFalse(BackupLib::isKept('2026-08-15.tar.gz', $today), 'le 15, trente-neuf jours');
		$this->assertFalse(BackupLib::isKept('2020-01-01.tar.gz', $today), 'le 1er, six ans');
		$this->assertTrue(BackupLib::isKept('2026-09-15.tar.gz', $today), 'le 15, huit jours, gardé par la fenêtre et non par son jour');

		$this->assertTrue(BackupLib::isKept('2026-09-14.tar.gz', $today, duration: 10), 'neuf jours pour le storage');
		$this->assertFalse(BackupLib::isKept('2026-09-12.tar.gz', $today, duration: 10), 'onze jours pour le storage');
		$this->assertTrue(BackupLib::isKept('2026-09-15.tar.gz', $today, duration: 10), 'le 15, huit jours, encore dans la fenêtre du storage');
		$this->assertFalse(BackupLib::isKept('2026-08-15.tar.gz', $today, duration: 10), 'le 15 ne survit pas non plus à la fenêtre du storage');

	}

}
?>
