<?php
namespace securing;

class Signature extends SignatureElement {

	/** Ce que rend SignatureControlLib::controlReference(). */
	const INTACT = 'intact';
	const ALTERED = 'altered';
	const UNSIGNED = 'unsigned';

	public function canRead(): bool {
		return TRUE;
	}

}
?>