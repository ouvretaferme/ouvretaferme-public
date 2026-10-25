<?php
namespace editor;

/**
 * Convert html got from a form to valid xml
 */
class XmlLib {

	/**
	 * Current DomDocument handled
	 *
	 * @var DomDocument
	 */
	protected $dom;

	/**
	 * Conversion options
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Convert output of the editor to a XML string
	 *
	 * @param string $value
	 * @param array $options
	 * @return string
	 */
	public function fromHtml(?string $value, array $options = []): ?string {

		if($value === NULL) {
			return NULL;
		}

		$this->options = $options + [
			'acceptFigure' => FALSE,
			'figureOnlyImage' => FALSE,
			'figureCaption' => FALSE,
			'draft' => FALSE
		];

		libxml_use_internal_errors(TRUE);

		$dom = new \DOMDocument();
		$dom->strictErrorChecking = FALSE;

		if(
			$value === '<p></p>' or
			$value === '<p><br></p>'
		) {
			$value = '';
		}

		// La valeur stockée porte toujours cette enveloppe, posée ici quand build() convertit un champ
		// editor : une entrée déjà enveloppée lève une notice de réparation, et une valeur écrite en base
		// sans passer par ici s'affiche tronquée ou vide, FormatterUi prenant son premier nœud pour racine.
		$dom->loadHtml(
			'<?xml version="1.0" encoding="UTF-8"?>'.'<main>'.$value.'</main>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOXMLDECL | LIBXML_NOBLANKS
		);

		$this->dom = $dom;

		if($dom->childNodes->length === 1) {
			return NULL;
		}

		libxml_clear_errors();

		$main = $dom->childNodes[1];

		if($main->childNodes === NULL) {
			return NULL;
		}

		// First, repair XML
		// Avoir <p>xxx</p>vvv<p>yyy</p> -> <p>xxx</p><p>vvv</p><p>yyy</p>
		if($this->options['draft'] === FALSE) {
			$this->repair($main);
		}

		\util\DomLib::noWhitespaceNode($main);

		for($i = 0; $i < $main->childNodes->length; $i++) {

			$node = $main->childNodes[$i];

			$i += $this->cleanNode($node);

		}

		if($main->childNodes->length === 0) {
			return NULL;
		}

		// Workaround to avoid XML declaration
		$value = $dom->saveXml($main);

		// Final cleanup
		$value = preg_replace('/<li>\s*(.*?)\s*<\/li>/si', '<li>\\1</li>', $value);
		$value = preg_replace('/<p>\s*(.*?)\s*<\/p>/si', '<p>\\1</p>', $value);

		return $value;
	}

	protected function cleanNode(\DOMNode $node): int {

		switch($node->nodeName) {

			case 'p' :
				return $this->cleanParagraphNode($node);

			case 'h2' :
			case 'h3' :
			case 'h4' :
				return $this->cleanHeaderNode($node);

			case 'ol' :
			case 'ul' :
				return $this->cleanListNode($node);

			case ($this->options['acceptFigure'] ? 'figure' : NULL) :
				return $this->cleanFigureNode($node);

			// Forbidden root node
			default :
				$node->parentNode->removeChild($node);
				return -1;

		}

	}

	/**
	 * Clean a <figure> node
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanFigureNode(\DOMNode $node) : int {

		// Clean attributes from figure
		$figureAttributes = [
			'data-interactive' => 'interactive',
			'data-size?' => 'size',
			'data-after?' => 'after',
			'data-before?' => 'before'
		];

		if($this->cleanNodeAttributes($node, $figureAttributes) === -1) {
			return -1;
		}

		$this->safeRenameNodeAttributes($node, $figureAttributes);

		\util\DomLib::noWhitespaceNode($node);

		// Remove hack span
		if(
			$node->lastChild and
			$node->lastChild->nodeName === 'span'
		) {
			$node->removeChild($node->lastChild);
		}

		$medias = 0;

		for($i = 0; $i < $node->childNodes->length; $i++) {

			$childNode = $node->childNodes[$i];

			if($this->cleanMediaNode($childNode) === -1) {
				$i--;
				continue;
			}

			if($childNode->nodeName === 'div') {
				$medias++;
			}

		}

		if(
			// Nothing in the figure
			$node->childNodes->length === 0 or
			// Only a caption in the figure
			($node->childNodes->length === 1 and $node->firstChild->nodeName === 'figcaption')
		) {
			$node->parentNode->removeChild($node);
			return -1;
		}

		if($node->getAttribute('before') === 'true') {

			if(
				$node->previousSibling === NULL or
				$node->previousSibling->nodeName !== 'p'
			) {
				$node->removeAttribute('before');
			}

		} else {
			$node->removeAttribute('before');
		}

		if($node->getAttribute('after') === 'true') {

			if(
				$node->nextSibling === NULL or
				$node->nextSibling->nodeName !== 'p'
			) {
				$node->removeAttribute('after');
			}

		} else {
			$node->removeAttribute('after');
		}

		if($node->getAttribute('interactive') === 'true') {

			if($node->hasAttribute('size')) {

				switch($node->getAttribute('size')) {

					// Left/right size are very special
					case 'left' :
					case 'right' :

						if(
							$medias > 1 or // Only 1 media
							$node->firstChild->nodeName === 'hr'
						) {
							$node->setAttribute('size', 'compressed');
						} else {

							// No captions for left size
							if($node->lastChild->nodeName === 'figcaption') {
								$node->removeChild($node->lastChild);
							}

						}

						break;

					case 'compressed' :
						break;

					default :
						$node->removeAttribute('size');
						break;


				}

			}

		} else {

			$node->removeAttribute('size');

		}

		return 0;

	}

	protected function cleanMediaNode(\DOMNode $node): int {

		\util\DomLib::noWhitespaceNode($node);

		switch($node->nodeName) {

			case 'figcaption' :
				if($this->options['figureOnlyImage'] and $this->options['figureCaption'] !== TRUE) {
					$node->parentNode->removeChild($node);
					return -1;
				} else {
					return $this->cleanFigcaptionNode($node);
				}

			case 'div' :

				$type = $node->getAttribute('data-type');

				if($this->options['figureOnlyImage']) {

					if($type === 'image') {
						return $this->cleanImageNode($node);
					} else {
						$node->parentNode->removeChild($node);
						return -1;
					}

				} else {

					switch($type) {

						case 'image' :
							return $this->cleanImageNode($node);

						case 'video' :
							return $this->cleanVideoNode($node);

						case 'hr' :
							return $this->cleanHrNode($node);

						case 'quote' :
							return $this->cleanQuoteNode($node);

						case 'grid' :
							return $this->cleanGridNode($node);

						default:
							$node->parentNode->removeChild($node);
							return -1;

					}

				}

			default :
				$node->parentNode->removeChild($node);
				return -1;
		}
	}

	protected function cleanFigcaptionNode(\DOMNode $node): int {

		if(
			$node->nextSibling or // Caption is not the last child of the figure
			$node->previousSibling === NULL or // Caption is the only child of the figure
			$node->previousSibling->nodeName === 'hr' // No caption for hrs
		) {

			$node->parentNode->removeChild($node);
			return -1;

		} else {

			// Clean caption
			$i = 0;

			while($i < $node->childNodes->length) {

				$childNode = $node->childNodes[$i];

				// Text nodes are OK
				if($childNode->nodeType === XML_TEXT_NODE) {

					$trimmedValue = str_replace(chr(0xC2).chr(0xA0), ' ', $childNode->nodeValue); // Convert nbsp
					$trimmedValue = trim($trimmedValue);

					if($trimmedValue === '') {
						$node->removeChild($childNode);
						$i--;
					}

				} else {

					if($this->cleanFigcaptionSubnode($childNode) === -1) {
						$i--;
					}

				}

				$i++;

			}

			if($node->childNodes->length > 0) {

				return $this->cleanNodeAttributes($node);

			} else {

				$node->parentNode->removeChild($node);
				return -1;

			}

		}


	}

	/* Clean figcaption subnodes : only allowed subnodes are kept */
	protected function cleanFigcaptionSubnode(\DOMNode $node): int {

		$node->parentNode->removeChild($node);
		return -1;

	}

	protected function cleanImageNode(\DOMNode $node): int {

		// L'adresse d'une image part en src, son lien en href : un schéma exécutable y devient du script
		if($this->isSafeSourceUrl($node->getAttribute('data-url')) === FALSE) {
			$node->parentNode->removeChild($node);
			return -1;
		}

		if($this->isSafeUrl($node->getAttribute('data-link')) === FALSE) {
			$node->removeAttribute('data-link');
		}

		$title = $node->getAttribute('data-title');

		if(
			$title and
			mb_strlen($title) > EditorSetting::MEDIA_TITLE_LIMIT
		) {
			$node->setAttribute('data-title', mb_substr($title, 0, EditorSetting::MEDIA_TITLE_LIMIT));
		}

		$attributes = [
			'data-url' => 'url',
			'data-color' => 'color',
			'data-w' => 'width',
			'data-h' => 'height',
			'data-title?' => 'title',
			'data-link?' => 'link',
		];

		if($this->isXyzNode($node)) {

			$attributes += [
				'data-xyz' => 'xyz',
				'data-xyz-w' => 'xyz-width',
				'data-xyz-h' => 'xyz-height',
				'data-xyz-version' => 'xyz-version',
			];

		}

		return $this->createMediaNode($node, 'image', $attributes);

	}

	protected function isXyzNode(\DOMNode $node) {
		return $node->hasAttribute('data-xyz');
	}

	protected function cleanVideoNode(\DOMNode $node): int {

		// L'adresse part en src d'une <iframe> sur les sites publics : seul un hébergeur de la liste la fournit,
		// et l'adresse enregistrée est celle que le serveur recompose, jamais celle qui a été postée
		$url = EditorSetting::getVideoUrl($node->getAttribute('data-url'), $node->getAttribute('data-source'));

		if($url === NULL) {
			$node->parentNode->removeChild($node);
			return -1;
		}

		$node->setAttribute('data-url', $url);

		return $this->createMediaNode($node, 'video', [
			'data-source' => 'source',
			'data-url' => 'url',
			'data-w' => 'width',
			'data-h' => 'height',
		]);
	}

	protected function cleanHrNode(\DOMNode $node): int {

		// Figure must have only 1 child
		if($node->parentNode->childNodes->length !== 1) {
			$node->parentNode->removeChild($node);
			return -1;
		}

		return $this->createMediaNode($node, 'hr');

	}

	/**
	 * Clean a <blockquote> node
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanQuoteNode(\DOMNode $node): int {

		$nodeBlockquote = $this->getQuoteNode($node);

		if($nodeBlockquote === NULL) {
			return -1;
		}

		$newNode = $this->createQuote($nodeBlockquote);

		if($newNode->childNodes->length === 0) {

			$node->parentNode->removeChild($node);
			return -1;

		} else {

			$about = $node->getAttribute('data-about');

			if(array_key_exists($about, FormatterUi::getQuoteIcons())) {
				$newNode->setAttribute('about', $about);
			}


			$border = $node->getAttribute('data-border');

			if(in_array($border, EditorSetting::QUOTE_BORDER)) {
				$newNode->setAttribute('border', $border);
			}

			$node->parentNode->replaceChild($newNode, $node);
			return 0;

		}

	}

	protected function createQuote(\DOMNode $node) : \DOMNode {

		$newNode = $this->dom->createElement('quote');

		// Only <p> as children for blockquote
		while($node->childNodes->length > 0) {

			if($this->cleanNode($node->firstChild) === 0) {
				$newNode->appendChild($node->firstChild);
			}

		}

		return $newNode;

	}

	protected function getQuoteNode(\DOMNode $node) {

		// Figure must have only 1 child
		if($node->parentNode->childNodes->length !== 1) {
			$node->parentNode->removeChild($node);
			return NULL;
		}

		// Node must have a div child
		// <div class="editor-quote">
		$nodeDiv = NULL;

		for($i = 0; $i < $node->childNodes->length; $i++) {

			$childNode = $node->childNodes[$i];

			if(
				$childNode->nodeName === 'div' and
				$childNode->getAttribute('class') === 'editor-quote'
			) {
				$nodeDiv = $childNode;
				break;
			}

		}

		if($nodeDiv === NULL) {
			$node->parentNode->removeChild($node);
			return NULL;
		}

		\util\DomLib::noWhitespaceNode($nodeDiv);

		// Div must have a blockquote child
		// <blockquote>
		if(
			$nodeDiv->childNodes->length !== 2 or
			$nodeDiv->lastChild->nodeName !== 'blockquote'
		) {
			$node->parentNode->removeChild($node);
			return NULL;
		}

		$nodeBlockquote = $nodeDiv->lastChild;

		return $nodeBlockquote;
	}

	/**
	 * Clean a grid node
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanGridNode(\DOMNode $node): int {

		$cells = $this->getGridCells($node);

		if($cells === NULL) {
			return -1;
		}

		$newNode = $this->dom->createElement('grid');


		$border = $node->getAttribute('data-border');

		if(in_array($border, EditorSetting::QUOTE_BORDER)) {
			$newNode->setAttribute('border', $border);
		}

		// Only <p> as children for blockquote
		foreach($cells as $cell) {

			$newCell = $this->dom->createElement('cell');

			while($cell->childNodes->length > 0) {

				if($this->cleanNode($cell->firstChild) === 0) {
					$newCell->appendChild($cell->firstChild);
				}

			}

			$newNode->appendChild($newCell);

		}

		$columns = $node->firstChild->getAttribute('data-columns');

		if($columns < 1 or $columns > 4) {
			$columns = 1;
		}

		$newNode->setAttribute('columns', $columns);

		$node->parentNode->replaceChild($newNode, $node);

		return 0;


	}

	protected function getGridCells(\DOMNode $node): ?array {

		// Figure must have only 1 child
		if($node->parentNode->childNodes->length !== 1) {
			$node->parentNode->removeChild($node);
			return NULL;
		}

		// Node must have on child of class="editor-grid"
		if(
			$node->childNodes->length !== 1 or
			$node->firstChild->getAttribute('class') !== 'editor-grid'
		) {
			$node->parentNode->removeChild($node);
			return NULL;
		}

		$nodeGrid = $node->firstChild;
		\util\DomLib::noWhitespaceNode($nodeGrid);

		$cells = [];

		// Get cells
		for($i = 0; $i < $nodeGrid->childNodes->length; $i++) {

			$nodeCell = $nodeGrid->childNodes[$i];

			if(
				$nodeCell->nodeName === 'div' and
				$nodeCell->getAttribute('contenteditable') === 'true'
			) {
				$cells[] = $nodeCell;
			}

		}

		if($cells === []) {
			$node->parentNode->removeChild($node);
			return NULL;
		}

		return $cells;
	}

	/**
	 * Create a media node for a figure
	 *
	 * @param \DOMNode $node
	 * @param string $tagName video, hr, image
	 * @param array $attributes Attributes matching
	 */
	protected function createMediaNode(\DOMNode $node, string $tagName, array $attributes = []): int {

		// Attributes must be there
		if($this->cleanNodeAttributes($node, $attributes) === -1) {
			return -1;
		}

		$newNode = $this->dom->createElement($tagName);

		foreach($attributes as $from => $to) {

			$optional = str_ends_with($from, '?');

			if($optional) {
				$from = substr($from, 0, -1);
			}

			if($optional) {

				if($node->hasAttribute($from) and $node->getAttribute($from) !== '') {
					$newNode->setAttribute($to, $node->getAttribute($from));
				}

			} else {
				$newNode->setAttribute($to, $node->getAttribute($from));
			}

		}

		$node->parentNode->replaceChild($newNode, $node);
		return 0;
	}

	/**
	 * Clean a <p> node
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanParagraphNode(\DOMNode $node) {

		\util\DomLib::trimNode($node);

		$attributes = [
			'style?' => NULL,
		];

		$this->cleanStyleNode($node, ['text-align']);

		return $this->createParagraphElement($node, $attributes);

	}

	protected function createParagraphElement(\DOMNode $node, array $attributes): int {

		\util\DomLib::trimNode($node);
		$this->cleanChildTextNodes($node);

		$this->cleanNodeAttributes($node, $attributes);
		$this->safeRenameNodeAttributes($node, $attributes);

		return 0;

	}

	/**
	 * Clean a <ol> and <ul> node
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanListNode(\DOMNode $node, int $depth = 0): int {

		\util\DomLib::trimNode($node);

		for($i = 0; $i < $node->childNodes->length; $i++) {

			$childNode = $node->childNodes[$i];

			if($childNode->nodeName !== 'li') {
				$node->removeChild($childNode);
				$i--;
			} else {
				\util\DomLib::trimNode($childNode);
				$this->cleanListItemNode($childNode, $depth);
			}

		}

		if($node->childNodes->length === 0) {
			$node->parentNode->removeChild($node);
			return -1;
		} else {
			$this->cleanNodeAttributes($node);
			return 0;
		}

	}

	/**
	 * Clean a <li> node : du texte, plus une éventuelle sous-liste
	 *
	 * @param \DOMNode $node
	 * @param int $depth Profondeur du <ul>/<ol> qui porte cet item, 0 pour une liste de premier niveau
	 */
	protected function cleanListItemNode(\DOMNode $node, int $depth) {

		$this->cleanNodeAttributes($node);

		for($i = 0; $i < $node->childNodes->length; $i++) {

			$childNode = $node->childNodes[$i];

			if(in_array($childNode->nodeName, ['ul', 'ol'])) {

				// Au-delà de la profondeur autorisée, les items de la sous-liste
				// remontent dans la liste parente plutôt que d'être perdus
				if($depth + 1 >= EditorSetting::LIST_DEPTH_LIMIT) {
					$i += $this->unwrapListNode($node, $childNode);
				} else {
					$i += $this->cleanListNode($childNode, $depth + 1);
				}

			} else {
				$i += $this->cleanTextNode($childNode);
			}

		}

	}

	/**
	 * Remonte les items d'une sous-liste dans la liste parente, puis la supprime
	 *
	 * @param \DOMNode $nodeItem Le <li> qui porte la sous-liste
	 * @param \DOMNode $nodeList La sous-liste à dissoudre
	 */
	protected function unwrapListNode(\DOMNode $nodeItem, \DOMNode $nodeList): int {

		$nodeParent = $nodeItem->parentNode;
		$nodeReference = $nodeItem;

		while($nodeList->childNodes->length > 0) {

			$childNode = $nodeList->firstChild;

			if($childNode->nodeName === 'li') {

				// insertBefore(…, NULL) ajoute en fin de liste
				$nodeParent->insertBefore($childNode, $nodeReference->nextSibling);
				$nodeReference = $childNode;

			} else {
				$nodeList->removeChild($childNode);
			}

		}

		$nodeItem->removeChild($nodeList);

		return -1;

	}

	/**
	 * Keep only authorized tags in a text (<b>, <i>, <u>, <s>, <a>, <smiley>)
	 *
	 * @param string $node
	 */
	protected function cleanTextNode(\DOMNode $node) : int {

		switch($node->nodeType) {

			case XML_ELEMENT_NODE :

				$this->cleanChildTextNodes($node);
				$this->cleanStyleNode($node, ['color']);

				switch($node->nodeName) {

					// Clean attributes
					case 'b' :
					case 'i' :
					case 'u' :
					case 's' :
						return $this->cleanNodeAttributes($node, ['style?' => NULL]);

					case 'a' :
						return $this->cleanLinkNode($node);

					case 'span' :

						// Les navigateurs laissent des <span> vides derrière eux à chaque
						// édition : sans élagage ils s'empilent d'une sauvegarde à l'autre
						if($node->childNodes->length === 0) {
							return \util\DomLib::removeNode($node);
						}

						// Seuls le style, déjà filtré par cleanStyleNode() juste au-dessus, et les deux
						// attributs fermés sont conservés : sinon un onclick collé traverse jusqu'au site public
						$attributes = [
							'style?' => NULL,
							'data-size?' => 'size',
							'data-highlight?' => 'highlight'
						];

						$this->cleanNodeAttributes($node, $attributes);
						$this->cleanSpanValues($node);
						$this->safeRenameNodeAttributes($node, $attributes);

						return 0;

					case 'br' :
						return $this->cleanNodeAttributes($node);

					default :
						return \util\DomLib::removeNode($node);

				}

			case XML_TEXT_NODE :

				$newValue = str_replace(["\n", "\t", chr(194).chr(160), "\r"], [' ', ' ', ' ', ''], $node->nodeValue);
				$newValue = preg_replace('/[ 	]{2,}/', ' ', $newValue); // Replace double spaces and tabs
				$newValue = preg_replace('/ ([\?\!\.\:\;])/', chr(194).chr(160).'\\1', $newValue);

				$node->nodeValue = $newValue;

				return 0;

			default : // CDATA ...
				return \util\DomLib::removeNode($node);

		}


	}

	protected function cleanSpanValues(\DOMElement $node): void {

		if(
			$node->hasAttribute('data-size') and
			(
				in_array($node->getAttribute('data-size'), EditorSetting::TEXT_SIZE, TRUE) === FALSE or
				$this->isInHeader($node)
			)
		) {
			$node->removeAttribute('data-size');
		}

		if(
			$node->hasAttribute('data-highlight') and
			$node->getAttribute('data-highlight') !== 'true'
		) {
			$node->removeAttribute('data-highlight');
		}

	}

	protected function isInHeader(\DOMNode $node): bool {

		for($parent = $node->parentNode; $parent !== NULL; $parent = $parent->parentNode) {

			if($parent->nodeName === 'header') {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Keep only authorized tags in a header (<smiley>)
	 *
	 * @param string $node
	 */
	protected function cleanHeaderNode(\DOMNode $node) : int {

		if($node->nodeType !== XML_ELEMENT_NODE) {
			return 0;
		}

		switch($node->nodeName) {

			case 'h2' :
				$size = '0';
				break;

			case 'h3' :
				$size = '1';
				break;

			case 'h4' :
				$size = '2';
				break;

		}

		// renameNode() remplace $node dans l'arbre et transfère ses enfants :
		// le nettoyage doit porter sur $newNode, $node étant désormais détaché et vide
		$newNode = \util\DomLib::renameNode($this->dom, $node, 'header');

		$return = $this->cleanParagraphNode($newNode);

		// Après cleanParagraphNode(), qui ne laisse que les attributs autorisés
		$newNode->setAttribute('size', $size);

		return $return;

	}

	/**
	 * Clean a link node
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanLinkNode(\DOMNode $node) : int {

		// Un href dans un schéma exécutable devient du script sur le site public :
		// on déroule le <a> en conservant son texte
		if($this->isSafeUrl($node->getAttribute('href')) === FALSE) {
			return \util\DomLib::removeNode($node);
		}

		$attributesLink = [
			'href' => 'url',
			'style?' => NULL,
		];

		if(\user\ConnectionLib::getOnline()->isAdmin()) {

			$attributesLink += [
				'data-button?' => 'button'
			];

		}

		if($this->cleanNodeAttributes($node, $attributesLink) === -1) {
			return -1;
		}

		$this->safeRenameNodeAttributes($node, $attributesLink);

		return 0;

	}

	/**
	 * Une URL de lien est-elle dans un schéma inoffensif ?
	 *
	 * Liste noire volontaire plutôt que liste blanche : les URL relatives, les ancres,
	 * mailto:, tel:... doivent continuer à passer
	 *
	 * @param string $url
	 */
	protected function isSafeUrl(string $url): bool {

		// Neutralise les tentatives d'obfuscation (espaces, tabulations, \0)
		$normalized = preg_replace('/[\s\x00-\x1F\x7F]/', '', $url);

		// Pas de schéma : URL relative, ancre... rien à filtrer
		if(preg_match('/^([a-z0-9.+-]+):/i', $normalized, $matches) === 0) {
			return TRUE;
		}

		return (in_array(mb_strtolower($matches[1]), ['javascript', 'vbscript', 'data']) === FALSE);

	}

	/**
	 * Une URL chargée par le navigateur, sans geste du lecteur, est-elle dans un schéma inoffensif ?
	 *
	 * Liste blanche, contrairement à isSafeUrl() : une image et une vidéo n'arrivent que du web, et
	 * la valeur part en src d'une <img> ou d'une <iframe>, où aucun autre schéma n'a de sens.
	 *
	 * @param string $url
	 */
	protected function isSafeSourceUrl(string $url): bool {

		// Neutralise les tentatives d'obfuscation (espaces, tabulations, \0)
		$normalized = preg_replace('/[\s\x00-\x1F\x7F]/', '', $url);

		return (preg_match('#^https?://#i', $normalized) === 1);

	}

	/**
	 * Clean a span node
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanStyleNode(\DOMNode $node, array $allowed = []): bool {

		if($node->hasAttribute('style') === FALSE) {
			return FALSE;
		}

		$style = $node->getAttribute('style');
		$matches = [];

		$cleanedStyle = '';

		if(
			in_array('color', $allowed) and
			preg_match('/(^|\s|\;)color\s*:\s*(rgb\([0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\)|[a-z]+|\#[0-9a-f]{6})($|\s|\;)/si', $style, $matches) === 1 and
			$matches[2] !== 'inherit'
		) {
			$cleanedStyle .= 'color: '.$matches[2].';';
		}

		if(
			in_array('text-align', $allowed) and
			preg_match('/(^|\s|\;)text-align\s*:\s*(left|center|right|justify)($|\s|\;)/si', $style, $matches) === 1 and
			$matches[2] !== 'inherit'
		) {
			$cleanedStyle .= 'text-align: '.$matches[2].';';
		}

		if($cleanedStyle === '') {
			$node->removeAttribute('style');
			return FALSE;
		} else {
			$node->setAttribute('style', $cleanedStyle);
			return TRUE;
		}

	}

	/**
	 * Same as cleanNodes() about text
	 *
	 * @param \DOMNode $node
	 */
	protected function cleanChildTextNodes(\DOMNode $node) {

		for($i = 0; $i < $node->childNodes->length; $i++) {

			$childNode = $node->childNodes[$i];

			$i += $this->cleanTextNode($childNode);

		}

	}

	/**
	 * Removed unauthorized attributes from a node
	 *
	 * @param \DOMNode $node
	 * @param array $attributes List of authorized attributes
	 * @return string
	 */
	protected function cleanNodeAttributes(\DOMNode $node, array $attributes = []): int {

		return \util\DomLib::cleanNodeAttributes($node, $attributes);

	}

	/**
	 * Rename attributes of a node
	 *
	 * @param  \DOMNode $node
	 * @param  array    $attributes
	 */
	protected function safeRenameNodeAttributes(\DOMNode $node, array $attributes) {

		return \util\DomLib::safeRenameNodeAttributes($node, $attributes);

	}

	/**
	 * Convert plain text to a XML string
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public function prepareFromText(string $value): string {

		$lines = preg_split('/\n\r?/', $value);

		$h = '';

		foreach($lines as $line) {
			$h .= '<p>'.$line.'</p>';
		}

		return $h;

	}


	public function extractImages(string $value): array {

		libxml_use_internal_errors(TRUE);

		if(
			$value === '<p></p>' or
			$value === '<p><br></p>'
		) {
			return [];
		}
		$dom = new \DOMDocument();
		$dom->strictErrorChecking = FALSE;
		$dom->loadXml($value, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOBLANKS | LIBXML_NOXMLDECL);

		$main = $dom->childNodes[0];

		$tags = [];

		for($i = 0; $i < $main->childNodes->length; $i++) {

			$node = $main->childNodes[$i];

			if($node->nodeName !== 'figure') {
				continue;
			}

			for($j = 0; $j < $node->childNodes->length; $j++) {

				$mediaNode = $node->childNodes[$j];

				if($mediaNode->nodeName !== 'image') {
					continue;
				}

				if($mediaNode->hasAttribute('xyz') === FALSE) {
					continue;
				}

				$url = $mediaNode->getAttribute('url');

				// TODO OVH : ajouter une meilleure vérification ? Quid si on prend plusieurs containers ?
				if(preg_match('/\/photo\/([0-9]+\/)?([a-z0-9]+)\.(jpg|png|gif)/si', $url, $matches) === 0) {
					throw new \Exception('Invalid URL '.$url);
				}

				$hash = $matches[2];
				$extension = $matches[3];

				$tags[] = [
					'url' => $url,
					'hash' => $hash,
					'extension' => $extension,
				];

			}

		}

		return $tags;
	}

	protected function repair(\DOMNode $main) {

		$allowed = ['p', 'h2', 'h3', 'h4', 'ol', 'ul', 'figure'];

		$i = 0;
		$nodeFix = NULL;
		$fixed = NULL;

		while($i < $main->childNodes->length) {

			$node = $main->childNodes[$i];

			if(\util\DomLib::isWhitespace($node) or $node->nodeType !== XML_ELEMENT_NODE) {
				$i++;
				continue;
			}

			if(in_array($node->nodeName, $allowed) === FALSE) {

				if($fixed === NULL) {
					$fixed = $this->dom->saveXml($main);
				}

				if($nodeFix === NULL) {
					$nodeFix = $this->dom->createElement('p');
				}

				$nodeFix->appendChild($node);
				$main->removeChild($node);

				$i--;

			} else {

				if($nodeFix !== NULL) {

					$main->insertBefore($nodeFix, $node);
					$nodeFix = NULL;

				}

			}

			$i++;

		}

		if($nodeFix !== NULL) {
			$main->appendChild($nodeFix);
		}

		if($fixed and LIME_ENV === 'dev') {
			trigger_error('Repair invalid XML ('.$fixed.') to ('.$this->dom->saveXml($main).')', E_USER_NOTICE);
		}

	}

}
?>
