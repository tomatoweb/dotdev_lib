<?php
/*****
 * Version	 	1.0.2015-07-21
**/
namespace dotdev\app\competition;
use \tools\error as e;
use \tools\helper as h;
use \tools\pdo_trait;
use \tools\libcom_trait;
use \dotdev\app\competition;

class quiz {
	use pdo_trait, libcom_trait;

	protected static function pdo_config(){
		return ['app_competition:quiz', [
			'l_pool'					=> "SELECT p.*, COUNT(q.ID) as `questions` FROM `quiz_pool` p LEFT JOIN `quiz_question` q ON q.poolID = p.ID GROUP BY p.ID",
			's_pool'					=> "SELECT p.*, COUNT(q.ID) as `questions` FROM `quiz_pool` p LEFT JOIN `quiz_question` q ON q.poolID = p.ID WHERE p.ID = ? GROUP BY p.ID LIMIT 1",
			'i_pool'					=> "INSERT INTO `quiz_pool` (`name`) VALUES (?)",

			'l_question'				=> "SELECT q.*, COUNT(a.ID) as `answers` FROM `quiz_question` q LEFT JOIN `quiz_answer` a ON a.questionID = q.ID WHERE q.poolID = ? GROUP BY q.ID",
			's_question'				=> "SELECT q.*, COUNT(a.ID) as `answers` FROM `quiz_question` q LEFT JOIN `quiz_answer` a ON a.questionID = q.ID WHERE q.ID = ? GROUP BY q.ID LIMIT 1",
			'i_question'				=> "INSERT INTO `quiz_question` (`poolID`,`question`) VALUES (?,?)",
			'u_question_answerID'		=> "UPDATE `quiz_question` SET `answerID` = ? WHERE `ID` = ?",

			'l_answer'					=> "SELECT * FROM `quiz_answer` WHERE `questionID` = ?",
			's_answer'					=> "SELECT * FROM `quiz_answer` WHERE `ID` = ? LIMIT 1",
			'i_answer'					=> "INSERT INTO `quiz_answer` (`questionID`,`answer`) VALUES (?,?)",

			's_round'					=> "SELECT * FROM `quiz_round` WHERE `ID` = ? LIMIT 1",
			'i_round'					=> "INSERT INTO `quiz_round` (`competitionID`,`poolID`) VALUES (?,?)",
			'u_round_start'				=> "UPDATE `quiz_round` SET `startMtime` = ? WHERE `ID` = ?",
			'u_round_finish'			=> "UPDATE `quiz_round` SET `finishMtime` = ? WHERE `ID` = ?",

			'i_choice'					=> "INSERT INTO `quiz_choice` (`questionID`,`answerID`,`needed`) VALUES (?,?,?)",
			]];
		}

	// Pool
	public static function add_pool($req){
		$mand = h::eX($req, ['name'=>'~^.{1,120}$'], $e1); // mandatory
		if($e1) return self::response(400, $e1);

		$insID = self::pdo('i_pool', $mand['name']);
		if(!$insID) return self::response(560);

		return self::response(201, (object)['ID'=>$insID]);
		}

	public static function get_pool($req = []){
		$opt = h::eX($req, ['poolID'=>'~1,65535/i'], $e1, true); // optional
		if($e1) return self::response(400, $e1);

		if(isset($opt['poolID'])){
			$pool = self::pdo('s_pool', $opt['poolID']);
			if(!$pool) return self::response($pool === false ? 560 : 404);

			return self::response(200, $pool);
			}
		else{
			$list = self::pdo('l_pool');
			if($list === false) return self::response(560);
			return self::response(200, $list);
			}
		}


	// Pool - Question
	public static function add_question($req){
		$mand = h::eX($req, ['poolID'=>'~1,65535/i', 'question'=>'~^.{1,255}$'], $e1); // mandatory
		if($e1) return self::response(400, $e1);

		$insID = self::pdo('i_question', [$mand['poolID'], $mand['question']]);
		if(!$insID) return self::response(560);

		return self::response(201, (object)['ID'=>$insID]);
		}

	public static function get_question($req){
		$alt1 = h::eX($req, ['questionID'=>'~1,65535/i'], $e1, true); // mandatory alt 1
		$alt2 = h::eX($req, ['poolID'=>'~1,65535/i'], $e2, true); // mandatory alt 2
		if($e1 or $e2) return self::response(400, array_merge($e1, $e2));
		elseif(!$alt1 and !$alt2) return self::response(400, 'Need at least questionID or poolID');

		if(isset($alt1['questionID'])){
			$question = self::pdo('s_question', $alt1['questionID']);
			if(!$question) return self::response($question === false ? 560 : 404);

			return self::response(200, $question);
			}
		else{
			$list = self::pdo('l_question', $alt2['poolID']);
			if($list === false) return self::response(560);

			return self::response(200, $list);
			}
		}


	// Pool - Question - Answer
	public static function add_answer($req){
		$mand = h::eX($req, ['questionID'=>'~1,65535/i', 'answer'=>'~^.{1,255}$'], $e1); // mandatory
		$opt = h::eX($req, ['right_answer'=>true], $e2, true);
		if($e1 or $e2) return self::response(400, array_merge($e1, $e2));

		$insID = self::pdo('i_answer', [$mand['questionID'], $mand['answer']]);
		if(!$insID) return self::response(560);

		if(!empty($opt['right_answer'])){
			$upd = self::pdo('u_question_answerID', [$insID, $mand['questionID']]);
			if(!$upd) return self::response(560);
			}

		return self::response(201, (object)['ID'=>$insID]);
		}

	public static function get_answer($req){
		$alt1 = h::eX($req, ['answerID'=>'~1,65535/i'], $e1, true); // mandatory alt 1
		$alt2 = h::eX($req, ['questionID'=>'~1,65535/i'], $e2, true); // mandatory alt 2
		if($e1 or $e2) return self::response(400, array_merge($e1, $e2));

		if(isset($alt1['answerID'])){
			$answer = self::pdo('s_answer', $alt1['answerID']);
			if(!$answer) return self::response($answer === false ? 560 : 404);

			return self::response(200, $answer);
			}
		else{
			$list = self::pdo('l_answer', $alt2['questionID']);
			if($list === false) return self::response(560);

			return self::response(200, $list);
			}
		}


	// Round
	public static function add_round($req){
		$mand = h::eX($req, ['competitionID'=>'~1,16777215/i', 'poolID'=>'~1,65535/i'], $e1); // mandatory
		$opt = h::eX($req, ['startMtime'=>'~U.u/d'], $e2, true); // optional
		if($e1 or $e2) return self::response(400, array_merge($e1, $e2));

		$insID = self::pdo('i_round', [$mand['competitionID'], $mand['poolID']]);
		if(!$insID) return self::response(560);

		if(isset($opt['startMtime'])){
			$res = self::start_round(['ID'=>$insID, 'startMtime'=>$opt['startMtime']]);
			if($res->status != 204) return self::response(570, $res);
			}

		return self::response(201, (object)['ID'=>$insID]);
		}

	public static function start_round($req){
		$mand = h::eX($req, ['roundID'=>'~1,16777215/i', 'startMtime'=>'~U.u/d'], $e1); // mandatory
		if($e1) return self::response(400, $e1);

		$upd = self::pdo('u_round_start', [$mand['startMtime'], $mand['roundID']]);
		if(!$upd) return self::response($upd === false ? 560 : 404);
		return self::response(204);
		}

	public static function finish_round($req){
		$mand = h::eX($req, ['roundID'=>'~1,16777215/i', 'finishMtime'=>'~U.u/d'], $e1); // mandatory
		if($e1) return self::response(400, $e1);

		$upd = self::pdo('u_round_finish', [$mand['finishMtime'], $mand['roundID']]);
		if(!$upd) return self::response($upd === false ? 560 : 404);
		return self::response(204);
		}

	public static function get_round($req){
		$mand = h::eX($req, ['roundID'=>'~1,16777215/i'], $e1); // mandatory
		if($e1) return self::response(400, $e1);

		$round = self::pdo('s_round', $mand['roundID']);
		if(!$round) return self::response($round === false ? 560 : 404);

		return self::response(200, $round);
		}


	// Round - Choice
	public static function add_choice($req){
		$mand = h::eX($req, ['questionID'=>'~1,65535/i', 'answerID'=>'~1,65535/i', 'needed'=>'~0,9999/f'], $e1); // mandatory
		if($e1) return self::response(400, $e1);

		$insID = self::pdo('i_choice', [$mand['questionID'], $mand['answerID'], $mand['needed']]);
		if(!$insID) return self::response(560);

		return self::response(201, (object)['ID'=>$insID]);
		}

	}
