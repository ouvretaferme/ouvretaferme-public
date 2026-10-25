<?php
namespace entry;

class DateComplementUi {

	/**
	 * Le jour dit, tel qu'une phrase le reprend : « hier », « avant-hier », « le jeudi 9 octobre »,
	 * `NULL` pour aujourd'hui, que la saisie ne dit pas.
	 */
	public static function getDay(string $date): ?string {

		return match($date) {
			\Clock::date() => NULL,
			date('Y-m-d', \Clock::shift('-1 DAY')) => s("hier"),
			date('Y-m-d', \Clock::shift('-2 DAYS')) => s("avant-hier"),
			default => s("le {value}", self::getDate($date)),
		};

	}

	/**
	 * Les jours d'un même geste, tels qu'une phrase les reprend : un seul comme `getDay()`, plusieurs en liste,
	 * « lundi 20, mercredi 22 et aujourd'hui ». Ils sont d'une même semaine, que le mois n'a pas à redire.
	 */
	public static function getDays(array $dates): ?string {

		if(count($dates) === 1) {
			return self::getDay(reset($dates));
		}

		$days = array_map(fn(string $date) => ($date === \Clock::date()) ?
			s("aujourd'hui") :
			lcfirst(\util\DateUi::getDayName((int)date('N', strtotime($date)))).' '.(int)substr($date, 8, 2), $dates);

		$last = array_pop($days);

		return s("{list} et {last}", ['list' => implode(', ', $days), 'last' => $last]);

	}

	/**
	 * Le libellé de la puce d'un jour, « Lun 20 » : le nom court, comme le calendrier le pose
	 * (`\util\DateUi`), et le numéro du jour.
	 */
	public static function getChip(string $date): string {
		return rtrim(\util\DateUi::getDayNameShort((int)date('N', strtotime($date))), '.').' '.(int)substr($date, 8, 2);
	}

	/**
	 * « jeudi 9 octobre », avec l'année quand ce n'est pas celle d'aujourd'hui.
	 */
	public static function getDate(string $date): string {

		$format = (substr($date, 0, 4) === (string)\Clock::year()) ? \util\DateUi::DAY_MONTH : \util\DateUi::DATE;

		return lcfirst(\util\DateUi::getDayName((int)date('N', strtotime($date)))).' '.\util\DateUi::textual($date, $format);

	}

	/**
	 * Le libellé du jeton de date que le champ garde d'une saisie à l'autre. Le serveur le relit comme
	 * un texte tapé : il prend donc une forme du vocabulaire (« 09/10 »), et une forme absolue, qui
	 * désigne encore le même jour après minuit, quand « hier » changerait de sens. La plus courte : le
	 * jeton partage une ligne avec l'intention et l'espèce, et le jour se relit en entier dans la
	 * confirmation et la pastille.
	 */
	public static function getToken(string $date): string {
		return \util\DateUi::numeric($date, \util\DateUi::DAY_MONTH);
	}

	public static function getInvalid(): string {
		return s("Cette date n'existe pas.");
	}

}
?>
