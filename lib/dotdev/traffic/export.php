<?php
/*****
 * Version 1.0.2018-02-16
**/
namespace dotdev\traffic;

use \tools\error as e;
use \tools\helper as h;

class export {

	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_traffic:export', [

			'l_callback'				=> "SELECT e.eventID, e.clickID, s.publisherID, s.pageID, s.domainID, e.persistID, s.operatorID, ec.createTime as `cbTime`, c.createTime as `clickTime`, e.createTime as `eventTime`, e.callbacks, cd.request as `clickData`, ec.request, ec.httpcode, ec.response, ec.comlogID
											FROM `event_comlog` ec
											INNER JOIN `event` e ON ec.eventID = e.eventID
											INNER JOIN `session` s ON s.persistID = e.persistID
											INNER JOIN `click` c ON c.clickID = e.clickID
											INNER JOIN `click_pubdata` cd ON cd.clickID = e.clickID
											WHERE ec.createTime BETWEEN ? AND ? AND e.callbacks >= 1 AND 1
											",
			'l_callback_by_pageID'		=> ['l_callback', ['AND 1' => 'AND s.pageID = ?']],
			'l_callback_by_dom_pub_ID'	=> ['l_callback', ['AND 1' => 'AND s.domainID = ? AND s.publisherID = ?']],
			'l_callback_by_domainID'	=> ['l_callback', ['AND 1' => 'AND s.domainID = ?']],
			'l_callback_by_publisherID'	=> ['l_callback', ['AND 1' => 'AND s.publisherID = ?']],


			'l_lmaboevt'				=> "SELECT e.eventID, s.publisherID, s.pageID, s.domainID, e.persistID, s.operatorID, a.paidafter, a.terminateTime, a.charges, a.charges_max, a.charges_refunded, c.createTime as `clickTime`, e.createTime as `eventTime`, cd.request as `clickData`
											FROM `event` e
											INNER JOIN `event_abo` a ON a.eventID = e.eventID
											INNER JOIN `session` s ON s.persistID = e.persistID
											INNER JOIN `click` c ON c.clickID = e.clickID
											INNER JOIN `click_pubdata` cd ON cd.clickID = e.clickID
											WHERE e.createTime BETWEEN ? AND ? AND e.type = 'abo' AND e.callbacks = 0 AND 1
											",
			'l_lmaboevt_by_pageID'		=> ['l_lmaboevt', ['AND 1' => 'AND s.pageID = ?']],
			'l_lmaboevt_by_dom_pub_ID'	=> ['l_lmaboevt', ['AND 1' => 'AND s.domainID = ? AND s.publisherID = ?']],
			'l_lmaboevt_by_domainID'	=> ['l_lmaboevt', ['AND 1' => 'AND s.domainID = ?']],
			'l_lmaboevt_by_publisherID'	=> ['l_lmaboevt', ['AND 1' => 'AND s.publisherID = ?']],

			]];
		}


	/* Export */
	public static function get_callback_export($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'publisherID'	=> '~1,65535/i',
			'domainID'		=> '~1,65535/i',
			'pageID'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: pageID
		if(isset($opt['pageID'])){

			// search in DB
			$list = self::pdo('l_callback_by_pageID', [$mand['from'], $mand['to'], $opt['pageID']]);
			}

		// param order 2: domainID + publisherID
		elseif(isset($opt['domainID']) and isset($opt['publisherID'])){

			// search in DB
			$list = self::pdo('l_callback_by_dom_pub_ID', [$mand['from'], $mand['to'], $opt['domainID'], $opt['publisherID']]);
			}

		// param order 3: domainID
		elseif(isset($opt['domainID'])){

			// search in DB
			$list = self::pdo('l_callback_by_domainID', [$mand['from'], $mand['to'], $opt['domainID']]);
			}

		// param order 4: publisherID
		elseif(isset($opt['publisherID'])){

			// search in DB
			$list = self::pdo('l_callback_by_publisherID', [$mand['from'], $mand['to'], $opt['publisherID']]);
			}

		// param order 5: no param
		elseif(empty($opt)){

			// search in DB
			$list = self::pdo('l_callback', [$mand['from'], $mand['to']]);
			}

		// on error
		if($list === false) return self::response(560);

		// for each entry
		foreach($list as $entry){

			// replace newline and tabulator chars in callback response
			$entry->response = h::replace_in_str($entry->response, ["\r"=>"","\n"=>"","\t"=>""]);
			}

		// return result
		return self::response(200, $list);
		}

	public static function get_lmaboevt_export($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'publisherID'	=> '~1,65535/i',
			'domainID'		=> '~1,65535/i',
			'pageID'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: pageID
		if(isset($opt['pageID'])){

			// search in DB
			$list = self::pdo('l_lmaboevt_by_pageID', [$mand['from'], $mand['to'], $opt['pageID']]);
			}

		// param order 2: domainID + publisherID
		elseif(isset($opt['domainID']) and isset($opt['publisherID'])){

			// search in DB
			$list = self::pdo('l_lmaboevt_by_dom_pub_ID', [$mand['from'], $mand['to'], $opt['domainID'], $opt['publisherID']]);
			}

		// param order 3: domainID
		elseif(isset($opt['domainID'])){

			// search in DB
			$list = self::pdo('l_lmaboevt_by_domainID', [$mand['from'], $mand['to'], $opt['domainID']]);
			}

		// param order 4: publisherID
		elseif(isset($opt['publisherID'])){

			// search in DB
			$list = self::pdo('l_lmaboevt_by_publisherID', [$mand['from'], $mand['to'], $opt['publisherID']]);
			}

		// param order 5: no param
		elseif(empty($opt)){

			// search in DB
			$list = self::pdo('l_lmaboevt', [$mand['from'], $mand['to']]);
			}

		// on error
		if($list === false) return self::response(560);

		// return result
		return self::response(200, $list);
		}

	}