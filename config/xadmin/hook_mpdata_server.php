<?php

if($this->user_logged()){
	$this->set_hook('mpdata:server[]', (object)[
		"ID"		=> "local",
		"dsn"		=> null,
		"remote"	=> null,
		"name"		=> "Lokal",
		], 100);
	}
