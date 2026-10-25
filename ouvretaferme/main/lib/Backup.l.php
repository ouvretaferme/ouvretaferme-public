<?php
namespace main;

class BackupLib {

	const LOCAL_BACKUP_DIR = '/var/www/';
	const LOCAL_DATABASE_BACKUP_FILE = 'backup.sql';
	const LOCAL_STRUCTURES_BACKUP_FILE = 'structures-mysql.tar.gz';
	const LOCAL_STRUCTURES_DUMP_DIR = 'mysql-backup/';
	const LOCAL_DECOR_BACKUP_FILE = 'structures-decor-mysql.tar.gz';
	const LOCAL_STORAGE_BACKUP_FILE = 'storage.tar.gz';
	const SERVER_BACKUP_DIR = '/home/ubuntu/otf-backup/';
	const DATABASE_FOLDER = 'mysql';
	const STORAGE_FOLDER = 'storage';

	const DURATION_BACKUP_IN_DAYS = 15;

	const DISK_SPACE_ALERT_IN_DAYS = 5;

	/**
	 * Les étapes de sauvegarde rendent chacune ce qui a échoué, une phrase par étape avec son code,
	 * et jamais la commande, qui nomme le compte et l'hôte du serveur de sauvegarde.
	 */
	public static function backupStructures(): array {

		$day = \Clock::date();
		[$serverUser, $serverHostname] = self::getHostData();

		$command = 'scp '.self::LOCAL_BACKUP_DIR.self::LOCAL_STRUCTURES_BACKUP_FILE.' '.$serverUser.'@'.$serverHostname.':'.self::SERVER_BACKUP_DIR.self::DATABASE_FOLDER.'/'.$day.'-structure.tar.gz';
		self::exec($command, $code);

		if($code !== 0) {
			return ['L\'envoi de l\'archive des structures au serveur de sauvegarde a échoué (scp, code '.$code.').'];
		}

		return [];

	}

	public static function backupDecor(array $bases): array {

		$command = self::getDecorCommand($bases);

		if($command === '') {
			return [];
		}

		self::exec($command, $code);

		if($code !== 0) {
			return ['L\'archive des bases du décor a échoué (tar ou mv, code '.$code.').'];
		}

		return [];

	}

	public static function backupDatabase(): array {

		$day = \Clock::date();
		[$serverUser, $serverHostname] = self::getHostData();

		$failures = [];

		exec('tar czf '.self::LOCAL_BACKUP_DIR.self::LOCAL_DATABASE_BACKUP_FILE.'.tar.gz -C '.self::LOCAL_BACKUP_DIR.' '.self::LOCAL_DATABASE_BACKUP_FILE, $output, $code);

		if($code !== 0) {
			$failures[] = 'L\'archive de la base commune a échoué (tar, code '.$code.').';
		}

		$command = 'scp '.self::LOCAL_BACKUP_DIR.self::LOCAL_DATABASE_BACKUP_FILE.'.tar.gz '.$serverUser.'@'.$serverHostname.':'.self::SERVER_BACKUP_DIR.self::DATABASE_FOLDER.'/'.$day.'.tar.gz';
		self::exec($command, $code);

		if($code !== 0) {
			$failures[] = 'L\'envoi de l\'archive de la base commune au serveur de sauvegarde a échoué (scp, code '.$code.').';
		}

		return $failures;

	}

	public static function backupStorage(): array {

		[$serverUser, $serverHostname] = self::getHostData();
		$day = \Clock::date();

		$command = 'scp '.self::LOCAL_BACKUP_DIR.self::LOCAL_STORAGE_BACKUP_FILE.' '.$serverUser.'@'.$serverHostname.':'.self::SERVER_BACKUP_DIR.self::STORAGE_FOLDER.'/'.$day.'.tar.gz';
		self::exec($command, $code);

		if($code !== 0) {
			return ['L\'envoi de l\'archive du stockage au serveur de sauvegarde a échoué (scp, code '.$code.').'];
		}

		return [];

	}

	public static function getDecorCommand(array $bases): string {

		if($bases === []) {
			return '';
		}

		$archive = self::LOCAL_BACKUP_DIR.self::LOCAL_DECOR_BACKUP_FILE;

		$files = '';

		foreach($bases as $base) {
			$files .= ' '.escapeshellarg($base.'.sql');
		}

		// Écrite sous un autre nom puis renommée : un scp lancé pendant la compression prendrait sinon une archive tronquée.
		return 'tar czf '.$archive.'.tmp -C '.self::LOCAL_BACKUP_DIR.self::LOCAL_STRUCTURES_DUMP_DIR.'backup/'.$files.' && mv '.$archive.'.tmp '.$archive;

	}

	public static function cleanDatabase(): void {

		self::doClean(self::SERVER_BACKUP_DIR.self::DATABASE_FOLDER);

	}

	public static function cleanStorage(): void {

		// On ne garde que 10j de storage
		self::doClean(self::SERVER_BACKUP_DIR.self::STORAGE_FOLDER, duration: 10);

	}

	/**
	 * L'espace libre du serveur de sauvegarde, en Kio, ou NULL s'il ne répond pas. Se prend avant la
	 * sauvegarde de la nuit, pour que `checkDiskSpace()` en déduise ce que la nuit a consommé.
	 */
	public static function getFreeSpace(): ?int {

		[$serverUser, $serverHostname] = self::getHostData();

		return self::getAvailableSpace(self::exec('ssh '.$serverUser.'@'.$serverHostname.' "df -Pk '.self::SERVER_BACKUP_DIR.'"'));

	}

	/**
	 * Ce que le serveur de sauvegarde peut encore recevoir, en jours. Discord sous
	 * DISK_SPACE_ALERT_IN_DAYS, et quand le serveur ne répond pas au contrôle, ce qui dit qu'il n'a
	 * probablement pas reçu la sauvegarde.
	 */
	public static function checkDiskSpace(?int $freeBefore = NULL): void {

		[$serverUser, $serverHostname] = self::getHostData();

		$day = \Clock::date();

		$command = 'ssh '.$serverUser.'@'.$serverHostname.' "df -Pk '.self::SERVER_BACKUP_DIR.' ; du -sk '.self::SERVER_BACKUP_DIR.self::DATABASE_FOLDER.'/'.$day.'* '.self::SERVER_BACKUP_DIR.self::STORAGE_FOLDER.'/'.$day.'*"';

		$lines = self::exec($command);

		if(LIME_ENV !== 'prod') {
			return;
		}

		$message = self::getDiskSpaceMessage($lines, $freeBefore);

		if($message !== NULL) {
			\communication\DiscordWebhookLib::send($message, [], \communication\DiscordWebhookLib::SECURITY);
		}

	}

	/**
	 * `$freeBefore` est l'espace libre d'avant la sauvegarde : sans lui, ce que la nuit a consommé
	 * est inconnu, et seule la place de la nuit suivante se contrôle.
	 */
	public static function getDiskSpaceMessage(array $lines, ?int $freeBefore = NULL): ?string {

		$free = self::getAvailableSpace($lines);

		if($free === NULL) {
			return '💾 Le serveur de sauvegarde n\'a pas répondu au contrôle de l\'espace disque.';
		}

		$written = self::getWrittenSpace($lines);

		if($written === 0) {
			return '💾 Aucune sauvegarde du jour sur le serveur de sauvegarde ('.self::formatSpace($free).' libres).';
		}

		// Le cron envoie la sauvegarde de la nuit avant que le nettoyage ne libère la plus ancienne :
		// une journée entière doit donc tenir sur le disque en plus de ce qu'il porte déjà.
		if($free < $written) {
			return '💾 La sauvegarde de la prochaine nuit ne tiendra pas sur le serveur de sauvegarde ('.self::formatSpace($free).' libres, '.self::formatSpace($written).' écrits chaque nuit).';
		}

		if($freeBefore === NULL) {
			return NULL;
		}

		$growth = $freeBefore - $free;

		if($growth <= 0) {
			return NULL;
		}

		$days = self::getRemainingDays($free - $written, $growth);

		if($days >= self::DISK_SPACE_ALERT_IN_DAYS) {
			return NULL;
		}

		if($days === 0) {
			$remaining = 'moins d\'un jour';
		} else if($days === 1) {
			$remaining = '1 jour';
		} else {
			$remaining = $days.' jours';
		}

		return '💾 Il reste '.$remaining.' d\'espace disque sur le serveur de sauvegarde ('.self::formatSpace($free).' libres, dont '.self::formatSpace($written).' à garder pour la sauvegarde de chaque nuit, et '.self::formatSpace($growth).' consommés cette nuit).';

	}

	/**
	 * Les jours que l'espace disponible laisse à cette croissance. Ce qu'une nuit consomme n'est pas
	 * ce qu'elle écrit : le nettoyage de la même nuit libère la sauvegarde qui sort de la fenêtre, et
	 * ce qui reste est la seule croissance des données.
	 */
	public static function getRemainingDays(int $available, int $growth): int {

		return intdiv($available, $growth);

	}

	/**
	 * La colonne Available de df -Pk, en Kio, ou NULL si aucune ligne ne la porte
	 */
	public static function getAvailableSpace(array $lines): ?int {

		foreach($lines as $line) {

			if(preg_match('~^\S+\s+\d+\s+\d+\s+(\d+)\s+\d+%~', $line, $matches)) {
				return (int)$matches[1];
			}

		}

		return NULL;

	}

	public static function getWrittenSpace(array $lines): int {

		$written = 0;

		foreach($lines as $line) {

			if(preg_match('~^(\d+)\s+/~', $line, $matches)) {
				$written += (int)$matches[1];
			}

		}

		return $written;

	}

	public static function formatSpace(int $kilobytes): string {

		return number_format($kilobytes / 1024 / 1024, 1, ',', ' ').' Go';

	}

	private static function getHostData(): array {

		$serverUser = MainSetting::$backupServer['user'];
		$serverHostname = MainSetting::$backupServer['hostname'];

		if(strlen($serverUser) === 0 or strlen($serverHostname) === 0) {
			throw new \Exception('backupServer must be configured.');
		}

		return [$serverUser, $serverHostname];

	}

	/**
	 * En dev, la commande s'imprime au lieu de partir vers le serveur de sauvegarde, et compte pour
	 * réussie : une sauvegarde de dev qui n'envoie rien n'a pas échoué.
	 */
	private static function exec(string $command, ?int &$code = NULL): mixed {

		if(LIME_ENV === 'prod') {

			exec($command, $result, $code);
			return $result;

		} else {

			echo $command."\n";
			$code = 0;
			return [];

		}

	}

	/**
	 * La politique de confidentialité promet qu'une donnée supprimée disparaît des sauvegardes au
	 * plus tard quinze jours après sa suppression : aucune sauvegarde ne se garde donc plus
	 * longtemps que la fenêtre, et le 1er ni le 15 du mois n'y font exception.
	 */
	public static function isKept(string $file, string $today, int $duration = self::DURATION_BACKUP_IN_DAYS): bool {

		$fileDate = substr($file, 0, 10);

		$days = (int)(\util\DateLib::interval($today, $fileDate) / 60 / 60 / 24);

		return ($days <= $duration);

	}

	private static function doClean(string $folder, int $duration = self::DURATION_BACKUP_IN_DAYS): void {

		[$serverUser, $serverHostname] = self::getHostData();

		$today = \Clock::date();

		$command = 'ssh '.$serverUser.'@'.$serverHostname.' "ls '.$folder.'"';

		$files = self::exec($command);

		foreach($files as $file) {

			if(self::isKept($file, $today, $duration)) {
				continue;
			}

			$command = 'ssh '.$serverUser.'@'.$serverHostname.' "rm '.$folder.'/'.$file.'"';
			self::exec($command);

		}

	}

}
?>
