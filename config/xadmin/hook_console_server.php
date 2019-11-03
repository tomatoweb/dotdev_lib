<?php

if($this->user_has_right('console')){

	$this->set_hook('console:server[]', (object)[
		"ID"		=> "local",
		"remote"	=> null,
		"name"		=> "Lokal",
		], 100);
	}
