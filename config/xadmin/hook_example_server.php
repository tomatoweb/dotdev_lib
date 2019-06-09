<?php


if($this->user_has_right('xa_debug')){
	$this->set_hook('xa_debug:example_server[]', (object)[
		"ID"		=> "local",
		"remote"	=> null,
		"name"		=> "Lokal",
		], 100);
	$this->set_hook('xa_debug:example_server[]', (object)[
		"ID"		=> "locala",
		"remote"	=> null,
		"name"		=> "Lokal again",
		], 200);
	}
