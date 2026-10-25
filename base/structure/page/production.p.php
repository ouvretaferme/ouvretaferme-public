<?php
new \structure\ProductionPage()
	->doDelete(fn() => throw new ReloadAction());
?>
