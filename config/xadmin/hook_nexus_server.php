<?php

if($this->user_has_right('mt_admin:nexus')){
	$this->set_hook('nexus:server[]', (object)[
		"ID"		=> "local",
		"remote"	=> null,
		"name"		=> "Lokal",
		"cache_server" => [(object)[
			"ID"		=> "local",
			"remote"	=> null,
			"name"		=> "Lokal",
			]],
		], 100);
	}
