<?php
/**
 * Backup
 *
 */
(new Page())
	->cron('index', function($data) {

		$free = \main\BackupLib::getFreeSpace();

		$messages = [
			...\main\BackupLib::backupDatabase(),
			...\main\BackupLib::backupStorage(),
		];

		\main\BackupLib::cleanDatabase();
		\main\BackupLib::cleanStorage();

		\main\BackupLib::checkDiskSpace($free);

		// Un envoi raté lève une exception, comme les autres crons, une fois le nettoyage et le contrôle
		// de l'espace disque joués : ni l'un ni l'autre ne dépend de l'envoi de la nuit, et l'exception
		// porte l'échec jusqu'au journal des erreurs, là où une sauvegarde manquée se voit.
		if($messages !== []) {
			throw new \Exception('main/cron/backup: '.implode(' ', $messages));
		}

	}, interval: '15 4 * * *');
?>
