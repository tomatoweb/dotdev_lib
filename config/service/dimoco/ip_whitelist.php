<?php
$_dimoco_ip_whitelist = [];
for($i=1; $i<=64; $i++){ // Dimoco IP Range
	$_dimoco_ip_whitelist[] = '91.198.93.'.$i;
	}
return $_dimoco_ip_whitelist;