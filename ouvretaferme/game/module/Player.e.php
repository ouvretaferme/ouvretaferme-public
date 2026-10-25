<?php
namespace game;

class Player extends PlayerElement {

	// Indexé par utilisateur : la vue d'un ami lit un second joueur dans la même requête.
	private static array $premium = [];

	public function getBoards(): int {

		$boards = 0;
		$currentDate = \Clock::date();

		foreach(GameSetting::BOARDS_OPENING as $date) {

			if($date === NULL or $currentDate >= $date) {
				$boards++;
			}

		}

		return $boards;
	}

	public function isOnline(): bool {
		return (
			$this->empty() === FALSE and
			$this['user']->is(\user\ConnectionLib::getOnline())
		);
	}

	public function isPremium(): bool {

		return self::$premium[$this['user']['id']] ??= (
			\structure\StructureLib::getByUser($this['user'], type: \structure\Structure::PRODUCER)->contains(fn($eStructure) => $eStructure['membership']) or
			PlayerLib::hasDonate($this['user'])
		);

	}

	public function getDailyTime(): int {
		return $this->isPremium() ? GameSetting::TIME_DAY_PREMIUM : GameSetting::TIME_DAY;
	}

	public function getRemainingTime(): float {
		return $this->getDailyTime() - $this['time'];
	}

	// Les crans allumés de la jauge du temps, un par heure entière qui reste ; une soupe ou un ami peuvent faire dépasser
	// la journée, que la jauge ne montre pas au-delà de ses crans
	public function getGauge(): int {
		return min($this->getDailyTime(), max(0, (int)floor($this->getRemainingTime())));
	}

	/**
	 * L'initiale du pseudonyme, que montre le jeu à la place d'un portrait quand un joueur en regarde un autre : le
	 * pseudonyme est tout ce que le jeu dit d'une personne aux autres joueurs. Les signes qui précèdent la première
	 * lettre ou le premier chiffre ne comptent pas, et un pseudonyme qui n'en porte aucun se montre par son premier signe.
	 */
	public function getInitial(): string {

		$this->expects(['name']);

		if(preg_match('/[\p{L}\p{N}]/u', $this['name'], $matches) === 1) {
			return mb_strtoupper($matches[0]);
		}

		return mb_substr($this['name'], 0, 1);

	}

	public function getHarvestTime(\Collection $cTile): float {

		$bonus = $cTile->find(fn($eTile) => $eTile['growing']->notEmpty() and $eTile['growing']['fqn'] === 'pivoine')->count() * GameSetting::BONUS_PIVOINE / 60;

		return GameSetting::TIME_HARVESTING - $bonus;

	}

	// Un joueur se lit de tous : c'est `game\FriendLib::are()`, appelé par la page, qui ferme ce
	// qu'un joueur voit d'un autre.
	public function canRead(): bool {

		return TRUE;

	}

	// Ouvert à tous en lecture, un joueur ne s'écrit que par son propriétaire.
	public function canWrite(): bool {

		return $this->isOnline();

	}

	public function acceptTime(float $time): bool {

		return $this->getRemainingTime() >= $time;

	}

	public function acceptEat(): bool {

		return $this->isPremium();

	}

	public function acceptMotivate(Player $ePlayerFriend): bool {

		$this->expects(['name', 'giftSentAt']);
		$ePlayerFriend->expects(['giftReceivedAt']);

		return (
			($this['giftSentAt'] !== \Clock::date() or in_array($this['name'], GameSetting::ADMIN)) and
			$ePlayerFriend['giftReceivedAt'] !== \Clock::date()
		);

	}

	public function assertMotivate(Player $ePlayerFriend): bool {

		return $this->canWrite() and $this->acceptMotivate($ePlayerFriend);

	}

}
?>
