<?php
namespace learning;

/**
 * Un compte prévenu de l'ouverture d'une formation (F35, lot L4d), une ligne par compte et par
 * formation. C'est la ligne, et non le partage, qui dit qu'un compte a reçu l'annonce : un partage
 * décoché puis recoché, ou un réseau qui passe de « toutes les fermes » à « certaines », crée une
 * ligne de partage neuve, que le cron réclame, sans que le compte déjà prévenu le soit deux fois.
 *
 * Aucune page ne la lit ni ne l'écrit : seul `ShareLib::claimAnnounce()` la pose, par
 * `AnnouncementLib::claim()`, et elle part avec sa formation.
 */
class Announcement extends AnnouncementElement {

	/**
	 * Fermé : aucune page ne la lit, et une page qui y viendrait par erreur ne doit rien en rendre.
	 */
	public function canRead(): bool {
		return FALSE;
	}

}
?>
