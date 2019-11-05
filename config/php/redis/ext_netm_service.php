<?php
return (object)[
	'instance' 	=> ['127.0.0.1', '6379'],
	'db'		=> 1,
	'options' 	=> [
		\Redis::OPT_PREFIX => 'netm:cache:pg:',
		\Redis::OPT_SERIALIZER => \Redis::SERIALIZER_IGBINARY,
		],
	];