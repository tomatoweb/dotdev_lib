<?php
return (object)[
	'instance' 	=> ['tcp://127.0.0.1', '6379'],
	'db'		=> 1,
	'options' 	=> [
		\Redis::OPT_PREFIX => 'ext_tim:',
		\Redis::OPT_SERIALIZER => \Redis::SERIALIZER_IGBINARY,
		],
	];
