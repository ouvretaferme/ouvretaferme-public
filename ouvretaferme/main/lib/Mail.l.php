<?php
namespace main;

/**
 * L'envoi d'un e-mail, synchrone et sans trace en base.
 *
 * C'est l'envoi de la version publique, où base/mail n'est pas installé. Avec lui, getInstance()
 * rend \mail\SendLib, qui hérite de cette classe et enregistre chaque e-mail avant de le confier à
 * un prestataire. Le code publié passe donc par getInstance() et s'en tient aux méthodes déclarées
 * ici.
 */
class MailLib {

	use \Notifiable;

	protected \structure\Structure|null $eStructure = NULL;
	protected ?string $fromEmail = NULL;
	protected ?string $fromName = NULL;
	protected ?string $to = NULL;
	protected ?string $bcc = NULL;
	protected ?string $replyTo = NULL;
	protected ?string $bodyText = NULL;
	protected ?string $bodyHtml = NULL;
	protected ?string $subject = NULL;

	protected array $attachments = [];

	/**
	 * Ce qui est parti depuis le début du processus, en dev seulement, pour qu'un test sache si un
	 * envoi a eu lieu sans dépendre de la table de base/mail.
	 */
	private static array $sent = [];

	public function __construct() {
	}

	public static function getInstance(): MailLib {

		if(\structure\StructureSetting::hasMailPackage()) {
			return self::notify('getSendLib')[0];
		} else {
			return new MailLib();
		}

	}

	/**
	 * @return list<array{to: string, subject: string}>
	 */
	public static function getSent(): array {
		return self::$sent;
	}

	/*
	 * Les réglages de l'envoi. \mail\SendLib les redéfinit pour lire les siens : une installation qui
	 * porte base/mail écrit ses réglages d'envoi dans MailSetting depuis secret.c.php, et n'a pas à
	 * les recopier dans MainSetting.
	 */

	protected static function getEmailName(): string {
		return MainSetting::$emailName;
	}

	protected static function getEmailFrom(): string {
		return MainSetting::EMAIL_FROM;
	}

	protected static function getDevSendOnly(): array {
		return MainSetting::$emailDevSendOnly;
	}

	public function setStructure(\structure\Structure $eStructure): static {
		$this->eStructure = $eStructure;
		return $this;
	}

	public function setFromEmail(?string $fromEmail): static {
		$this->fromEmail = $fromEmail;
		return $this;
	}

	public function setFromName(?string $fromName): static {
		$this->fromName = $fromName;
		return $this;
	}

	public function setReplyTo(?string $email): static {
		$this->replyTo = $email;
		return $this;
	}

	public function addAttachment(string $content, string $name, string $type): static {
		$this->attachments[] = [
			'content' => $content,
			'name' => $name,
			'type' => $type
		];
		return $this;
	}

	public function setTo(string $to): static {
		$this->to = $to;
		return $this;
	}

	public function setBcc(string $email): static {
		$this->bcc = $email;
		return $this;
	}

	public function setContent(string $subject, ?string $bodyText = NULL, ?string $bodyHtml = NULL): static {

		$this->subject = $subject;
		$this->bodyText = $bodyText;
		$this->bodyHtml = $bodyHtml;

		return $this;

	}

	public function setBodyText(string $bodyText): static {
		$this->bodyText = $bodyText;
		return $this;
	}

	public function setBodyHtml(string $bodyHtml): static {
		$this->bodyHtml = $bodyHtml;
		return $this;
	}

	public function setSubject(string $subject): static {
		$this->subject = $subject;
		return $this;
	}

	/**
	 * Reset mail parameters.
	 */
	public function reset() {
		$this->eStructure = NULL;
		$this->fromEmail = NULL;
		$this->fromName = NULL;
		$this->to = NULL;
		$this->bcc = NULL;
		$this->replyTo = NULL;
		$this->bodyText = '';
		$this->bodyHtml = '';
		$this->subject = '';
		$this->attachments = [];
	}

	/**
	 * Send the mail.
	 */
	public function send(): static {

		if(
			$this->to === NULL or
			(empty($this->bodyText) and empty($this->bodyHtml)) or
			empty($this->subject)
		) {

			throw new \Exception('Internal error: it needs at least to set: a to address ('.var_export($this->to, true).'), a subject ('.var_export($this->subject, true).'), a body (text or html) to send a mail.');

		} else {

			$this->to = mb_strtolower($this->to);

			// /!\ Do not touch
			if(LIME_ENV === 'dev') {

				if(in_array($this->to, static::getDevSendOnly()) === FALSE) {
					return $this;
				}

				// La copie cachée ne doit pas faire sortir de la machine ce que la règle retient pour `to`
				if(
					$this->bcc !== NULL and
					in_array(mb_strtolower($this->bcc), static::getDevSendOnly()) === FALSE
				) {
					$this->bcc = NULL;
				}

				$this->subject = '['.LIME_ENV.'] '.$this->subject;

			}

			if(LIME_ENV === 'dev') {
				self::$sent[] = [
					'to' => $this->to,
					'subject' => $this->subject
				];
			}

			$this->deliver();

		}

		$this->reset();

		return $this;

	}

	/**
	 * Remet l'e-mail au serveur de messagerie de la machine, par la fonction mail() de PHP.
	 */
	protected function deliver(): void {

		[$contentType, $body] = $this->getMessage();

		$headers = [
			'From' => $this->getFrom(),
			'MIME-Version' => '1.0',
			'Content-Type' => $contentType
		];

		if($this->replyTo !== NULL) {
			$headers['Reply-To'] = $this->replyTo;
		}

		if($this->bcc !== NULL) {
			$headers['Bcc'] = $this->bcc;
		}

		if(mail($this->to, mb_encode_mimeheader($this->subject), $body, $headers) === FALSE) {
			trigger_error('mail() refused the email to '.$this->to);
		}

	}

	/**
	 * L'en-tête From. Un nom en ASCII passe entre guillemets : laissé nu, une virgule ou un `<…>`
	 * qu'il contient ferait lire plusieurs adresses. Un nom qui sort de l'ASCII est encodé, et un
	 * mot encodé ne peut pas se placer entre guillemets.
	 */
	protected function getFrom(): string {

		$name = $this->fromName ?? static::getEmailName();

		if(mb_check_encoding($name, 'ASCII')) {
			$name = '"'.addcslashes($name, '"\\').'"';
		} else {
			$name = mb_encode_mimeheader($name);
		}

		return $name.' <'.($this->fromEmail ?? static::getEmailFrom()).'>';

	}

	/**
	 * Le message MIME : le texte et le HTML en alternative, les pièces jointes à côté.
	 *
	 * @return array{0: string, 1: string} la valeur de l'en-tête Content-Type, puis le corps
	 */
	protected function getMessage(): array {

		$parts = [];

		if(empty($this->bodyText) === FALSE) {
			$parts[] = self::getPart('text/plain; charset=UTF-8', $this->bodyText);
		}

		if(empty($this->bodyHtml) === FALSE) {
			$parts[] = self::getPart('text/html; charset=UTF-8', $this->bodyHtml);
		}

		[$contentType, $body] = self::getMultipart('alternative', $parts);

		if($this->attachments === []) {
			return [$contentType, $body];
		}

		$parts = ['Content-Type: '.$contentType."\r\n\r\n".$body];

		foreach($this->attachments as $attachment) {
			$parts[] = self::getPart($attachment['type'], $attachment['content'], $attachment['name']);
		}

		return self::getMultipart('mixed', $parts);

	}

	private static function getPart(string $contentType, string $content, ?string $filename = NULL): string {

		$headers = 'Content-Type: '.$contentType."\r\n";
		$headers .= 'Content-Transfer-Encoding: base64'."\r\n";

		if($filename !== NULL) {
			$headers .= 'Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($filename)."\r\n";
		}

		return $headers."\r\n".chunk_split(base64_encode($content));

	}

	private static function getMultipart(string $subtype, array $parts): array {

		$boundary = bin2hex(random_bytes(16));

		$body = '';

		foreach($parts as $part) {
			$body .= '--'.$boundary."\r\n".$part."\r\n";
		}

		$body .= '--'.$boundary.'--';

		return ['multipart/'.$subtype.'; boundary="'.$boundary.'"', $body];

	}

}
?>
