<?php

if($this->user_has_right('mt_tool:phplog')){

	$this->set_hook('phplog:server[]', (object)[
		"ID"		=> "local",
		"remote"	=> null,
		"name"		=> "Lokal",
		], 100);
	}
