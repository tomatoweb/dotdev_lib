<?php

if($this->user_has_right('mt_tool:ccbtool')){
	$this->set_hook('ccbtool:server[]', (object)[
		"ID"		=> "local",
		"dsn"		=> null,
		"remote"	=> null,
		"name"		=> "Lokal",
		], 100);
	}