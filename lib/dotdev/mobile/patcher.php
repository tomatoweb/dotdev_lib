<?php
/*****
 * Version 1.0.2018-11-12
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\nexus\service as nexus_service;
use \dotdev\traffic\event as traffic_event;

class patcher {

	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile:patcher', [

			// recaluclate event income
			'l_abo_stat_intime'		=> 'SELECT
											c.aboID,
											a.productID, a.persistID,
											m.operatorID,
											COUNT(c.ID) AS `charges`,
											CAST(SUM(c.paidTime != \'0000-00-00 00:00:00\') AS UNSIGNED) AS `charges_paid`
										FROM `abo_charge` c
										INNER JOIN `abo` a ON c.aboID = a.ID
										INNER JOIN `mobile` m ON m.ID = a.mobileID
										WHERE a.confirmTime BETWEEN ? AND ?
										GROUP BY c.aboID
										',
			'l_otp_stat_intime'		=> 'SELECT
											o.ID as `otpID`, o.productID, o.persistID,
											m.operatorID
										FROM `otp` o
										INNER JOIN `mobile` m ON m.ID = o.mobileID
										WHERE o.paidTime BETWEEN ? AND ?
										',
			'l_smspay_stat_intime'	=> 'SELECT
											s.smspayID, s.productID, s.persistID,
											m.operatorID,
											(SELECT COUNT(*) FROM `smspay_mo` WHERE `smspayID` = s.smspayID) as `mo`,
											(SELECT COUNT(*) FROM `smspay_mt` WHERE `smspayID` = s.smspayID) as `mt`,
											(SELECT COUNT(*) FROM `smspay_mt` WHERE `smspayID` = s.smspayID AND `paidTime` != \'0000-00-00 00:00:00\') as `mt_paid`
										FROM `smspay` s
										INNER JOIN `mobile` m ON m.ID = s.mobileID
										WHERE s.createTime BETWEEN ? AND ?
										',
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_traffic');
		}


	/* recaluclate event income */
	public static function recalc_event_income($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			'step'		=> '~^\+[0-9]{1,2} (?:month|week|day|hour|min)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'abo'		=> '~/b',
			'otp'		=> '~/b',
			'smspay'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($opt['abo']) and empty($opt['otp']) and empty($opt['smspay'])) return self::response(400, 'Need at least one of param abo, otp, smspay set to true');


		// step range
		$step_range = $mand['step'];

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to']);

		// result array
		$stat = (object)[
			'interval'	=> 0,
			'entries'	=> 0,
			'skipped'	=> 0,
			'nopayout'	=> 0,
			'noupdate'	=> 0,
			'updated'	=> 0,
			'error'		=> [],
			];

		// do for each step
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// increment interval
			$stat->interval++;

			// for each type
			foreach(['abo','otp','smspay'] as $type){

				// skip if event type should not be calculated
				if(empty($opt[$type])) continue;

				// load list
				$list = self::pdo('l_'.$type.'_stat_intime', [$from, $to]);

				// on error
				if($list === false) return self::response(560);

				// for each entry
				foreach($list as $entry){

					// count
					$stat->entries++;

					// skip entries without persistID
					if(!$entry->persistID){

						// count and continue
						$stat->skipped++;
						continue;
						}

					// load payout
					$res = nexus_service::get_service_payout([
						'operatorID'			=> $entry->operatorID,
						'productID'				=> $entry->productID,
						'product_type'			=> $type,
						'fallback_operatorID'	=> 0,
						]);

					// if not found
					if($res->status == 400){

						// count and continue
						$stat->nopayout++;
						continue;
						}

					// on unexpected error
					if($res->status != 200){

						// count and continue
						if(!isset($stat->error['get_service_payout_'.$res->status])) $stat->error['get_service_payout_'.$res->status] = 0;
						$stat->error['get_service_payout_'.$res->status]++;
						continue;
						}

					// define payout and update
					$payout = $res->data->payout;
					$update = [];

					// for type abo
					if($type == 'abo'){

						// define update
						$update = [
							'aboID'		=> $entry->aboID,
							'type'		=> $type,
							'charges'	=> $entry->charges_paid,
							'charges_max'=> $entry->charges,
							'income'	=> round($entry->charges_paid * $payout, 2),
							];
						}

					// for type otp
					if($type == 'otp'){

						// define update
						$update = [
							'otpID'		=> $entry->otpID,
							'type'		=> $type,
							'income'	=> round($payout, 2),
							];
						}

					// for type smspay
					if($type == 'smspay'){

						// load product
						$res = nexus_service::get_product([
							'type'		=> 'smspay',
							'productID'	=> $entry->productID,
							]);

						// on error
						if($res->status != 200){

							// count and continue
							if(!isset($stat->error['get_product_'.$res->status])) $stat->error['get_product_'.$res->status] = 0;
							$stat->error['get_product_'.$res->status]++;
							continue;
							}

						// take product
						$product = $res->data;

						// define billing type
						$billing_type = in_array($entry->operatorID, h::gX($product->param, 'mt_billing_operator') ?: []) ? 'mt' : 'mo';

						// define update
						$update = [
							'smspayID'	=> $entry->smspayID,
							'type'		=> $type,
							'mo'		=> $entry->mo,
							'mt'		=> $entry->mt,
							'billing'	=> ($billing_type == 'mt') ? $entry->mt_paid : $entry->mo,
							'income'	=> round((($billing_type == 'mt') ? $entry->mt_paid : $entry->mo) * $payout, 2),
							];
						}

					// if nothing to update
					if(!$update){

						// count and continue
						$stat->noupdate++;
						continue;
						}

					// update event
					$res = traffic_event::update_event($update);

					// on unexpected error
					if($res->status != 204){

						// count and continue
						if(!isset($stat->error['update_event_'.$res->status])) $stat->error['update_event_'.$res->status] = 0;
						$stat->error['update_event_'.$res->status]++;
						continue;
						}

					// count and continue
					$stat->updated++;
					}

				}

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			} while (h::date($to) < $end_time);

		// return result
		return self::response(200, (object)['request' => $mand, 'stats' => $stat]);
		}

	}