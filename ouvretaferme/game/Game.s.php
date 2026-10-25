<?php
namespace game;

class GameSetting extends \Settings {

	const END = '2026-12-24';
	const END_TIME = '20:00';

	public static function isFinished() {

		return (
			\Clock::date() > self::END or
			(\Clock::date() === self::END and \Clock::timeOfDay() >= self::END_TIME.':00')
		);
	}

	public static function isPlaying() {

		return (
			self::isFinished() === FALSE
		);
	}

	/**
	 * Le jour du calendrier de l'Avent que la cour montre, du 1er au 24 décembre, NULL hors de cette période.
	 */
	public static function getAdventDay(): ?int {

		$date = \Clock::date();
		$day = (int)substr($date, 8, 2);

		if(substr($date, 5, 2) !== '12' or $day > 24) {
			return NULL;
		}

		return $day;

	}

	const PROMOTION_LIMIT = '2025-12-04';

	const BOARDS = 4;

	// Les essais de code ami d'un joueur, par fenêtre d'une heure : au-delà, le code se devinerait par
	// envois répétés (`PlayerLib::addFriend()`)
	const LIMIT_FRIEND = 10;
	const WINDOW_FRIEND = 3600;

	// Le code ami : huit signes d'un alphabet sans lettre ni chiffre qu'on confond à la lecture (ni I, ni
	// O, ni 0, ni 1), soit 32^8 = 2^40 codes. Il tient dans `Player.code`, en `?text8`
	const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	const CODE_LENGTH = 8;

	const ADMIN = ['VingtCent'];

	const BOARDS_OPENING = [
		1 => NULL,
		2 => '2025-11-30',
		3 => '2025-12-04',
		4 => '2025-12-10',
	];

	const EMOJI_SEEDLING = '🌱';
	const EMOJI_WATERING = '🚿';
	const EMOJI_WEED = '✂️';
	const EMOJI_HARVEST = '🧺';

	const TIME_DAY = 8;
	const TIME_DAY_PREMIUM = 11;

	const TIME_HARVESTING = 4;
	const TIME_WATERING = 1;
	const TIME_WEED = 6;
	const TIME_SEEDLING = 1.5;

	const BONUS_MOTIVATION = 1;
	const BONUS_WEED = 2;
	const BONUS_SOUP = 2;
	const BONUS_PIVOINE = 30;
	const BONUS_LUZERNE = 5;

	const ADJACENT = [
		1 => [2, 5, 6],
		2 => [1, 3, 5, 6, 7],
		3 => [2, 4, 6, 7, 8],
		4 => [3, 7, 8],
		5 => [1, 2, 6, 9, 10],
		6 => [1, 2, 3, 5, 7, 9, 10, 11],
		7 => [2, 3, 4, 6, 8, 10, 11, 12],
		8 => [3, 4, 7, 11, 12],
		9 => [5, 6, 10, 13, 14],
		10 => [5, 6, 7, 9, 11, 13, 14, 15],
		11 => [6, 7, 8, 10, 12, 14, 15, 16],
		12 => [7, 8, 11, 15, 16],
		13 => [9, 10, 14],
		14 => [9, 10, 11, 13, 15],
		15 => [10, 11, 12, 14, 16],
		16 => [11, 12, 15],
	];

}
?>
