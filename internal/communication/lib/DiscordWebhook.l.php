<?php
namespace communication;

/**
 * Envoi d'un message dans un salon Discord de l'équipe, par webhook.
 *
 * À réserver aux alertes que toute l'équipe doit voir passer — une veille réglementaire, un service
 * tiers qui ne répond plus. Sans webhook configuré, l'envoi ne fait rien. Le salon de sécurité ne
 * reçoit rien hors production, les tests de la dev y déclenchant les alertes ; les autres salons
 * restent ouverts à la dev, d'où part la publication des migrations.
 */
class DiscordWebhookLib {

	const TEAM = 'team';
	const MIGRATION = 'migration';
	const SECURITY = 'security';
	const LAW = 'law';
	const AID = 'aid';

	/**
	 * @param array $embeds Blocs enrichis, au format attendu par Discord (titre, champs, couleur…)
	 * @param string $channel Salon visé, TEAM, MIGRATION, SECURITY, LAW ou AID
	 */
	public static function send(string $content, array $embeds = [], string $channel = self::TEAM): bool {

		$webhookUrl = match($channel) {
			self::TEAM => CommunicationSetting::$discordTeamWebhookUrl,
			self::MIGRATION => CommunicationSetting::$discordMigrationWebhookUrl,
			self::SECURITY => CommunicationSetting::$discordSecurityWebhookUrl,
			self::LAW => CommunicationSetting::$discordLawWebhookUrl,
			self::AID => CommunicationSetting::$discordAidWebhookUrl,
		};

		if(
			empty($webhookUrl) or
			($channel === self::SECURITY and LIME_ENV !== 'prod')
		) {
			return FALSE;
		}

		$curl = new \util\CurlLib();

		$curl->exec($webhookUrl, json_encode([
			'content' => $content,
			'embeds' => $embeds,
		]), 'POST', [
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
		]);

		// Le code de retour est la seule preuve que le message est parti : un webhook révoqué, un salon
		// supprimé ou un contenu refusé rendent 401, 404 ou 400 sans lever, et l'appelant qui se fiait
		// à la valeur de retour n'alertait plus personne. Discord rend 204 sans corps sur un envoi
		// accepté, 200 quand l'URL porte `wait=true`.
		$httpCode = $curl->getLastInfos()['httpCode'];

		return ($httpCode >= 200 and $httpCode < 300);

	}

}
?>
