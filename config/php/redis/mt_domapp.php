<?php
return (object)[
	'instance' 	=> ['tcp://127.0.0.1', '6379'],
	'db'		=> 2,
	'options' 	=> [
		\Redis::OPT_PREFIX => 'mt:domapp:',
		\Redis::OPT_SERIALIZER => \Redis::SERIALIZER_IGBINARY,
		],
	];
