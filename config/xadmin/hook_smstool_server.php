<?php

if($this->user_has_right('mt_tool:smstool')){
	$this->set_hook('smstool:server[]', (object)[
		"ID"		=> "local",
		"dsn"		=> null,
		"remote"	=> null,
		"name"		=> "Lokal",
		], 100);
	}
