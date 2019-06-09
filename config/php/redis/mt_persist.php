<?php
return (object)[
	'instance' 	=> ['127.0.0.1', '6379'],
	'db'		=> 2,
	'options' 	=> [
		\Redis::OPT_PREFIX => 'mt:persist:',
		\Redis::OPT_SERIALIZER => \Redis::SERIALIZER_IGBINARY,
		],
	];
