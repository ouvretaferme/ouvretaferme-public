<?php
namespace security;

class AccessReportUi {

	public function __construct() {
		\Asset::css('security', 'access.css');
	}

	/**
	 * La date du rapport et le geste pour l'ouvrir seul, quand il existe
	 */
	public function getInformation(?string $generatedAt, array $logs): string {

		if($generatedAt === NULL) {
			return '';
		}

		$h = '<div class="util-title">';
			$h .= '<span class="color-muted">';
				$date = \util\DateUi::numeric($generatedAt, \util\DateUi::DATE_HOUR_MINUTE);
				if($logs === []) {
					$h .= s("Produit le {date}", ['date' => $date]);
				} else {
					$h .= s("Produit le {date}, sur {logs}", [
						'date' => $date,
						// Le socle rend <code> en bloc (reboot.css) : les noms des journaux restent du texte
						'logs' => implode(' '.s("et").' ', array_map(fn($log) => encode(basename($log)), $logs))
					]);
				}
			$h .= '</span>';
			$h .= '<a href="/security/admin/access:report" target="_blank" class="btn btn-outline-primary">'.s("Ouvrir dans un onglet").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le rapport GoAccess dans son cadre, ou la raison de son absence
	 */
	public function getReport(bool $configured, ?string $generatedAt): string {

		if($configured === FALSE) {
			return '<div class="util-empty">'.s("Le rapport des accès n'est pas configuré sur cette installation.").'</div>';
		}

		if($generatedAt === NULL) {
			return '<div class="util-empty">'.s("Le rapport n'a pas encore été produit.").'</div>';
		}

		return '<iframe class="security-access-report" src="/security/admin/access:report" title="'.s("Rapport GoAccess").'"></iframe>';

	}

}
?>
