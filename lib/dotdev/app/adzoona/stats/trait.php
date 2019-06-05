<?php
/*****
 * Version 1.0.2017-09-22
**/

namespace dotdev\app\adzoona;

use \tools\helper as h;
use \tools\error as e;
use \tools\http;
use \dotdev\nexus\levelconfig;

trait stats_trait {

	/*
	* called from get_stats hour or day
	* merge stats
	*/
	protected function merge_stats($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'result'		=> '~/l'
			], $error);

		$result = $mand['result'];

		// create a default temp object
		$tmp = (object) [
			'time'				=> $result[0]->data->from,
			'result_session'	=> [],
			'result_click'		=> [],
			'result_event'		=> [],
			'result_callback'	=> [],
			];

		// foreach entry in result write in temp object to reduce result
		foreach($result as $entry) {

			foreach($entry->data->result_session as $session) {
				$tmp->result_session[] = $session;
				}

			foreach($entry->data->result_click as $click) {
				$tmp->result_click[] = $click;
				}

			foreach($entry->data->result_event as $event) {
				$tmp->result_event[] = $event;
				}

			foreach($entry->data->result_callback as $callback) {
				$tmp->result_callback[] = $callback;
				}

			}

		$tmp2 = [];

		// if there is no data, return empty array
		if(empty($tmp->result_session) and empty($tmp->result_click) and empty($tmp->result_event) and empty($tmp->result_callback)) return $tmp2;

		// merge result_session
		foreach($tmp->result_session as $session) {
			$hash = $session->publisherID.'-'.$session->domainID.'-'.$session->pageID;

			$tmp2[$hash] = (object) [
				'time'				=> $tmp->time,
				'publisherID'		=> $session->publisherID,
				'domainID'			=> $session->domainID,
				'pageID'			=> $session->pageID,
				];

			$tmp2[$hash]->sum_session = 0;
			}

		foreach($tmp->result_session as $session) {
			$hash = $session->publisherID.'-'.$session->domainID.'-'.$session->pageID;

			$tmp2[$hash]->sum_session += $session->sum;
			}

		// merge result_click
		foreach($tmp->result_click as $click) {
			$hash = $click->publisherID.'-'.$click->domainID.'-'.$click->pageID;

			if(empty($tmp2[$hash])) {
				$tmp2[$hash] = (object) [
					'time'				=> $tmp->time,
					'publisherID'		=> $click->publisherID,
					'domainID'			=> $click->domainID,
					'pageID'			=> $click->pageID,
					];
				}

			$tmp2[$hash]->sum_click = 0;

			}

		foreach($tmp->result_click as $click) {
			$hash = $click->publisherID.'-'.$click->domainID.'-'.$click->pageID;

			$tmp2[$hash]->sum_click += $click->sum;
			}

		// merge result_event
		foreach($tmp->result_event as $event) {
			$hash = $event->publisherID.'-'.$event->domainID.'-'.$event->pageID;

			if(empty($tmp2[$hash])) {
				$tmp2[$hash] = (object) [
					'time'				=> $tmp->time,
					'publisherID'		=> $event->publisherID,
					'domainID'			=> $event->domainID,
					'pageID'			=> $event->pageID,
					];
				}

			$tmp2[$hash]->{'sum_'.$event->type} = 0;
			$tmp2[$hash]->sum_income = 0;
			$tmp2[$hash]->sum_cost = 0;

			}

		foreach($tmp->result_event as $event) {
			$hash = $event->publisherID.'-'.$event->domainID.'-'.$event->pageID;

			$tmp2[$hash]->{'sum_'.$event->type} += $event->sum;
			$tmp2[$hash]->sum_income += $event->income;
			$tmp2[$hash]->sum_cost += $event->cost;
			}

		// merge result_callback
		foreach($tmp->result_callback as $callback) {
			$hash = $callback->publisherID.'-'.$callback->domainID.'-'.$callback->pageID;

			if(empty($tmp2[$hash])) {
				$tmp2[$hash] = (object) [
					'time'				=> $tmp->time,
					'publisherID'		=> $callback->publisherID,
					'domainID'			=> $callback->domainID,
					'pageID'			=> $callback->pageID,
					];
				}

			$tmp2[$hash]->sum_callback = 0;

			}

		foreach($tmp->result_callback as $callback) {
			$hash = $callback->publisherID.'-'.$callback->domainID.'-'.$callback->pageID;

			$tmp2[$hash]->sum_callback += $callback->sum;
			}

		return $tmp2;
		}


	/*
	* called from controller to merge stats
	*/
	protected function merge_stats_controller($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'stats_day'				=> '~/l',
			'stats_hour'			=> '~/l',
			'stats_current_hour'	=> '~/l',
			], $error);

		$opt = h::eX($req, [
			'partner_access'		=> '~/l'
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		$tmp = [];

		$publisher_access = [];
		$domain_access = [];

		// look for status of publisher and domain and only get ID's with status == online
		if(isset($opt['partner_access'])) {
			foreach($opt['partner_access'] as $partner_access) {
				if($partner_access->publisherID != 0 and $partner_access->domainID == 0 and $partner_access->pageID == 0 and $partner_access->status == 'online') $publisher_access[] = $partner_access->publisherID;

				// combination of publisherID and domainID
				if($partner_access->publisherID != 0 and $partner_access->domainID != 0 and $partner_access->pageID == 0 and $partner_access->status == 'online') $domain_access[] = $partner_access->publisherID.'-'.$partner_access->domainID;
				}
			}

		if(!empty($mand['stats_hour'])) {
			foreach($mand['stats_hour'] as $entry) {
				if($entry->status == 'online' and in_array($entry->publisherID, $publisher_access) and in_array($entry->publisherID.'-'.$entry->domainID, $domain_access)) {
					$entry->stats_type = 'hour';
					$tmp[] = $entry;
					}
				}
			}

		if(!empty($mand['stats_day'])) {
			foreach($mand['stats_day'] as $entry) {
				if($entry->status == 'online' and in_array($entry->publisherID, $publisher_access) and in_array($entry->publisherID.'-'.$entry->domainID, $domain_access)) {
					$entry->stats_type = 'day';
					$tmp[] = $entry;
					}
				}
			}

		foreach($mand['stats_current_hour'] as $hour) {
			if(!empty($hour->data)) {
				foreach($hour->data as $entry) {
					if($entry->status == 'online') {
						$entry->stats_type = 'current_hour';
						$tmp[] = $entry;
						}
					}
				}
			}

		return $tmp;
		}


	/*
	* called from service.php
	*/
	protected function merge_callback($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'result'		=> '~/l'
			], $error);

		$result = $mand['result'];
		$tmp = [];

		foreach($result as $result_per_server) {

			// abo - create tmp object
			foreach($result_per_server->data->result_event_abo as $abo) {
				$hash = $abo->publisherID. '-' .$abo->pageID;

				if(array_key_exists($hash, $tmp)) continue;

				$tmp[$hash] = (object) [
					'publisherID'	=> $abo->publisherID,
					'pageID'		=> $abo->pageID,
					'sum_smspay'	=> 0,
					'sum_abo'		=> 0,
					'sum_mo'		=> 0,
					'sum_income'	=> 0,			// == revenue
					'sum_charges'	=> 0
					];
				}

			// abo - fill tmp object
			foreach($result_per_server->data->result_event_abo as $abo) {
				$hash = $abo->publisherID. '-' .$abo->pageID;

				$tmp[$hash]->sum_abo	+= $abo->sum;
				$tmp[$hash]->sum_income	+= $abo->income;
				$tmp[$hash]->sum_charges+= $abo->charges;

				}

			// smspay - create tmp object
			foreach($result_per_server->data->result_event_smspay as $smspay) {
				$hash = $smspay->publisherID. '-' .$smspay->pageID;

				if(array_key_exists($hash, $tmp)) continue;

				$tmp[$hash] = (object) [
					'publisherID'	=> $smspay->publisherID,
					'pageID'		=> $smspay->pageID,
					'sum_smspay'	=> 0,
					'sum_abo'		=> 0,
					'sum_mo'		=> 0,
					'sum_income'	=> 0,			// == revenue
					'sum_charges'	=> 0
					];
				}

			// smspay - fill tmp object
			foreach($result_per_server->data->result_event_smspay as $smspay) {
				$hash = $smspay->publisherID. '-' .$smspay->pageID;

				$tmp[$hash]->sum_smspay	+= $smspay->sum;
				$tmp[$hash]->sum_mo		+= $smspay->mo;
				$tmp[$hash]->sum_income	+= $smspay->income;

				}

			}

		return array_values($tmp);
		}

	}