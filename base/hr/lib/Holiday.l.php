<?php
namespace hr;

/**
 * Les jours fériés de métropole, que la durée d'une absence ne compte pas. Ceux d'Alsace-Moselle (Vendredi
 * saint, 26 décembre) et des outre-mer ne sont pas tenus : rien ne dit aujourd'hui où travaille une personne.
 */
class HolidayLib {

	/**
	 * @return string[] les onze jours fériés de l'année `$year`, en `Y-m-d`
	 */
	public static function getByYear(int $year): array {

		$easter = self::getEaster($year);

		$holidays = [
			$year.'-01-01',
			date('Y-m-d', strtotime($easter.' + 1 DAY')),
			$year.'-05-01',
			$year.'-05-08',
			date('Y-m-d', strtotime($easter.' + 39 DAY')),
			date('Y-m-d', strtotime($easter.' + 50 DAY')),
			$year.'-07-14',
			$year.'-08-15',
			$year.'-11-01',
			$year.'-11-11',
			$year.'-12-25',
		];

		sort($holidays);

		return $holidays;

	}

	public static function isHoliday(string $date): bool {
		return in_array($date, self::getByYear((int)substr($date, 0, 4)), TRUE);
	}

	/**
	 * Le dimanche de Pâques du calendrier grégorien (algorithme de Meeus, Jones et Butcher). `easter_date()`
	 * n'est pas employé : l'extension `calendar` qui la porte n'est pas garantie hors de la ligne de commande.
	 */
	public static function getEaster(int $year): string {

		$a = $year % 19;
		$b = intdiv($year, 100);
		$c = $year % 100;
		$d = intdiv($b, 4);
		$e = $b % 4;
		$f = intdiv($b + 8, 25);
		$g = intdiv($b - $f + 1, 3);
		$h = (19 * $a + $b - $d - $g + 15) % 30;
		$i = intdiv($c, 4);
		$k = $c % 4;
		$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
		$m = intdiv($a + 11 * $h + 22 * $l, 451);
		$month = intdiv($h + $l - 7 * $m + 114, 31);
		$day = (($h + $l - 7 * $m + 114) % 31) + 1;

		return sprintf('%04d-%02d-%02d', $year, $month, $day);

	}

}
?>
