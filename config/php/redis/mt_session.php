<?php
return (object)[
	'instance' 	=> ['127.0.0.1', '6379'],
	'db'		=> 4,
	'options' 	=> [
		\Redis::OPT_PREFIX => 'mt:session:',
		\Redis::OPT_SERIALIZER => \Redis::SERIALIZER_IGBINARY,
		],
	];
