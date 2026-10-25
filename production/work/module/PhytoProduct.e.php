<?php
namespace work;

class PhytoProduct extends PhytoProductElement {

	// Le catalogue E-Phy est une donnée publique, que seul le cron de rechargement écrit.
	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return FALSE;
	}

	/**
	 * Le catalogue écrit les noms en capitales (« KOCIDE OPTI ») : le nom proposé au produit de la
	 * structure passe les mots en minuscules, sauf les sigles courts (« HP », « WG »).
	 */
	public function getDisplayName(): string {

		$this->expects(['name']);

		return implode(' ', array_map(
			fn(string $word) => (mb_strlen($word) <= 2) ? $word : mb_convert_case($word, MB_CASE_TITLE),
			explode(' ', $this['name'])
		));

	}

	/**
	 * Les formulations qui ne se diluent pas, relevées sur le catalogue du 22/09/2026 : le produit
	 * n'a alors pas de volume de bouillie, et l'intervention ni bouillie ni cuve. Liste fermée plutôt
	 * qu'une recherche de mots, « Granulé dispersable » et « Granulé soluble dans l'eau » se diluant.
	 * Les « prêt à l'emploi » (appât, piège, gel) le disent dans leur libellé.
	 */
	const READY_TO_USE = [
		'Appât granulé', 'Cire', 'Fumigène', 'Gaz comprimé', 'Granulé', 'Générateur d\'aérosol', 'Microgranulé',
		'Poudre de contact', 'Poudre pour poudrage', 'Produit diffuseur de vapeur', 'Produit générateur de gaz',
		'Suspension concentrée pour application directe', 'pâte',
	];

	public function isReadyToUse(): bool {

		$this->expects(['formulation']);

		return (
			$this['formulation'] !== NULL and (
				in_array($this['formulation'], self::READY_TO_USE) or
				str_contains($this['formulation'], 'prêt à l\'emploi')
			)
		);

	}

}
?>
