<?php
/*****
 * Version 		1.0.2013-12-12
 *
**/
namespace dotdev\app;

use \tools\error as e;
use \tools\helper as h;

class example {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		// Diese Funktion gibt immer ein Array mit zwei Werten zurück. Der erste ist der Verbindungs-Name, der zweite ein Assoziatives Array an Queries.

		// Technisch muss der zweite Parameter nicht befüllt werden. Es können auch Queries direkt übergeben werden.
		// Da bestimmte Querys durchaus an mehreren Stellen genutzt werden könnten, lassen diese sich hier so separat definieren.

		// Der erste Parameter ist jedoch wichtig. Dieser benennt die Verbindung, die im /config/php/pdo/ Verzeichnis vorgegeben ist. Die ermöglicht,
		// das jede dieser Klassen nichts davon weiß, wo der Datenbank-Server liegt, oder wie die Verbindung aufgebaut wird. Des weiteren kann dies
		// so auf verschiedene Datenbankserver aufgeilt werden, halt je nach Konfiguration.
		// Der erste Parameter kann auch mit einem Doppelpunkt im Namen versehen werden. Normalerweise wird eine Verbindung zur Laufzeit benutzt. D.h.
		// ein PDO-Object mit der Verbindung 'app_example'. Die Queries werden dorthin gecacht (bzw. die Statements). Dies kann zu Konflikten führen
		// wenn verschiedene Klasse 'app_example' benutzen. Daher reicht es 'app_example:foo' hier, 'app_example:bar' in der andere u.s.w. anzugeben.
		// Dadurch hat jede ihre getrennte Verbindung, inkl. getrennten Statement-Cache.

		// Es gibt noch einige andere Möglichkeiten für den zweiten Parameter, die hier jedoch erstmal nicht relevant sind.

		return ['app_example', [

			// SELECT mit LIMIT 1
			's_profile'					=> "SELECT *
											FROM `profile`
											WHERE `ID` = ?
											LIMIT 1
											",
			// SELECT mit unbekannter Menge
			'l_profile'					=> "SELECT * FROM `profile` ",

			// SELECT mit LIMIT 1
			'c_profile_name'			=> "SELECT `ID` FROM `profile` WHERE `name` = ? LIMIT 1",

			// INSERT
			'i_profile'					=> "INSERT INTO `profile` (`name`,`age`) VALUES (?,?)",

			// UPDATE
			'u_profile_age'				=> "UPDATE `profile` SET `age` = ? WHERE `ID` = ?",

			// DELETE
			'd_profile'					=> "DELETE FROM `profile` WHERE `ID` = ?"
			]];
		}


	/*
	Vorweg etwas zu den hier verwendeten h::eX() Funktionen. Diese ist recht kniffelig zu beschreiben, jedoch sehr mächtig ist. Ich hoffe ich kann es hiermit ein
	wenig zu umschreiben. Im Grunde kannst du aber auch darauf verzichten, da letztlich nur die korrekte Rückgabe zählt:

	Die h::eX() Funktion ist eine recht komplex gewordene Funktion zur Prüfung und Extraktion von Werten. Sie benutzt intern die Funktionen
	h::cX() und h::gX(). Das X steht sinnbildlich für eine assoziatives Array oder (standard) Object. Wärend das 'c' in cX() für check
	steht, steht das 'g' für get.

	Ein cX(['foo'=>'bar'], 'foo') prüft, ob der Key 'foo' im übergeben Array/Object vorkommt und gibt dies als
	boolean-Wert zurück.

	Ein gX() mit gleichen Parameter gibt hingegen den Wert 'bar' zurück, oder NULL, wenn key nicht vorhanden wäre.

	h::eX() ist die Kombination daraus. Der erste Parameter übergibt das zu prüfende Array/Object, der zweitere Parameter ist ein Array, welches
	die zu prüfenden Keys beschreibt. Die Rückgabe ist immer ein Array. Bei dem Prüfungsparamter kann ein normaler Wert (['key1','key2']), oder
	ein assoziativer Wert angegeben werden ['key1'=>'checkvalue', 'key2'=>'checkvalue']. Das kann auch gemischt werden ['key1', 'key2'=>'checkvalue'].

	Beispiel:
	  - h::eX(['foo'=>1, 'bar'=>2], ['foo'])
	  		-> return ['foo'=>1]
	  - h::eX(['foo'=>'foovalue', 'bar'=>'barvalue'], ['bar'=>'barvalue'])
	  		-> return ['bar'=>'barvalue']

	Wenn ein Key nicht gefunden wurde, oder dessen Wert nicht korrekt ist, wird das im dritten Paramter gespeichert
	  - h::eX(['foo'=>'foovalue', 'bar'=>'wrong'], ['bar'=>'barvalue'], $error)
	  		-> return []
	  		-> $error = ['bar']

	Im vierten Parameter kann mit true angeben werden, das die Parameter optional sind. Das bedeutet aber nur, das bei Nicht-Existenz kein Fehler geworfen wird:
	 - h::eX(['bar'=>'wrong'], ['foo', bar'=>'barvalue'], $error, true)
	  		-> return []
	  		-> $error = ['bar']
	  		-> // Key 'foo' kam nicht vor -> kein Fehler. Key 'bar' schon, war aber falsch -> Fehler.


	Zu guter letzt kann der Checkwert ein wenig dynamischer sein als nur ein === Test. Dabei sind folgende Szenarien möglich
		- Ist der Checkwert (STRING) und fängt mit '~' an, so gibt es drei Möglichkeiten:
			-> Am Ende des Strings steht /i, was bedeutet:
				- $value ist (INT) oder (STRING), der exakt in ein (INT) umgewandelt werden könnte
				- und folgendes ist true = ($value >= 18 and $value <=65)
			-> Am Ende des Strings steht /f, was gleichbedeuten wie /i nur für (FLOAT) wäre
			-> Ansonsten ist von einer RegularExpression auszugehen, die in '/'.$expression.'/' gekappselt getestet wird
		- Ist der Checkwert eine Funktion, dann wird diese darauf angewandt
		- Ist der Checkwert nicht NULL, TRUE (default) oder (array), dann wird ein $check === $value angewandt
		- Ist der Checkwert TRUE (default), dann wird ein (isset($value) and $value !== '') angewandt
		- Ist der Checkwert (array), dann wird damit in_array() angewandt

	Diese Umschreibung ist ein bischen grob und schnell gemacht. An den folgenden Beispielen wird es ersichtlicher. Wie gesagt, eine Prüfung kann auch komplett
	ohne gestaltet werden. Für Details musst Du Dir die /dloewel/amboss/helper Klasse ansehen.
	*/

	public static function add_profile($req){
		// Mandatory Parameter
		$mand = h::eX($req, ['name'=>'~^[a-zA-Z0-9\-\_]{6,64}$'], $error);

		// Optionale Parameter (Nur ein Fehler, wenn Age angeben ist und nicht zwischen 18 bis 65 liegt)
		$opt = h::eX($req, ['age'=>'~18,65/i'], $error, true);

		// Wenn Error vorhanden, Error 400 Bad Request zurückgegeben. Dabei wird als zweiter Parameter ein Array mit den fehlerhaften Parameternamen erwartet.
		// Die resultierende Rückgabe sieht so aus, wenn 'name' falsch war: (object)['status'=>400, 'error'=>['name']]
		if($error) return self::response(400, $error);

		// Prüfen, ob Name bereits benutzt wird
		// Der erste Parameter ist der Key des Queries, der oben in pdo_config() definiert wurde. Jedoch kann auch direkt ein Query hier angegeben werden.
		// Jeder Query ist als geschlossene Transaction zu betrachten. (Wenn es notwendig wird, mehrere Queries in einer Transaction durchzuführen, muss dies nochmal separat besprochen werden.)
		// Der zweite Parameter ist ein Wert (Int, Float, String) oder ein Array an diesen Werten, jenachdem wie viele Platzhalter vorgesehen sind. Es geht hier als auch, $mand['name'] in ein Array zu kappseln.
		// Der zweite Parameter ist optional. Wenn kein Parameter notwendig ist, braucht auch nix angegeben zu werden.
		// (Ein dritter optionaler Parameter ist möglich, der mit dem Statement-Object verknüpft wird (siehe \tools\pdo_trait::pdo()))
		$check = self::pdo('c_profile_name', $mand['name']);
		// Ein SELECT gibt false beim (Query)-Fehler, oder das Ergebnis zurück. Da dies ein SELECT mit LIMIT 1 ist, kann folglich entweder kein Eintrag oder 1 Eintrag gefunden werden.
		// Daher ist das Ergebniss entweder null, oder der Eintrag als Object.
		// Im übrigen ist PDO::FETCH_OBJ voreingestellt

		// Wenn Check false ist, liegt ein Query-Fehler vor
		// self::response() kommt aus dem libcom-Trait. Fehler 560 bedeutet, das ein MySQL-Fehler vorliegen muss. Dieser wird geloggt und es wird (object)['status'=>500] zurückgegeben
		if($check === false) return self::response(560);

		// Wenn ein Eintrag gefunden wurde, liegt ein Konflikt vor. Daher Status 409 Conflict.
		// Das zurückgegebene Object sieht schlicht so aus: (object)['status'=>409]
		if($check) return self::response(409);
		// Ansonsten existiert noch kein Profil mit dem Namen

		// Wenn Age nicht angegeben war, das Alter auf 0 setzen (Ist nur exemplarisch gemeint. Vom Gedanken her irgendwie sinnlos.)
		$age = !empty($opt['age']) ? $opt['age'] : 0;

		// Profile einfügen
		$insertID = self::pdo('i_profile', [$mand['name'], $age]);
		// Ein Insert gibt entweder false beim (Query)-Fehler, oder den Primary Key bei Erfolg zurück
		// Wenn kein Primary-Key, oder mehrere existieren, wird bei Erfolg null zurückgeben (siehe PDO)
		// Da die Abfrage aber `ID` als Primary-Key hat, muss folglich eine ID zurückkommen
		if(!$insertID) return self::response(560);

		// Status 201 steht für Created, als zweiter Parameter wird die ID im Object übergeben.
		return self::response(201, (object)['ID'=>$videoID]);
		// Das letztlich zurückgegebene Object sieht dann wie folgt aus:
		// (object)['status'=>201, 'data'=>(object)['ID'=>$videoID]]
		}

	public static function update_profile_age($req){
		// Mandatory Parameter
		$mand = h::eX($req, ['profileID'=>'~1,16777215/i', 'age'=>'~18,65/i'], $error);
		if($error) return self::response(400, $error);

		// Profil suchen
		$profile = self::pdo('s_profile', $mand['profileID']);
		// Query-Fehler
		if($profile === false) return self::response(560);
		// Profil nicht gefunden: 404 NOT FOUND
		// 404-Fehler werden nicht geloggt, da nicht hier eindeutig entschiedenen werden kann, ob dies als Fehler zu betrachten ist.
		// Daher muss der Ort, von wo diese Funktion aufgerufen wird entsprechend auf den Returnwert (object)['status'=>404] reagieren.
		if(!$profile) return self::response(404);

		// Alter aktualisieren
		$upd = self::pdo('u_profile', [$mand['age'], $profile->ID]);

		// Da dies ein UPDATE-Query ist, wird nun entweder beim Query-Fehler false, ansonsten die Anzahl der betroffenen Reihen zurückgegeben.
		if($upd === false) return self::response(560);

		// Im Grunde ist es möglich, das ewaitige andere Datenbanktypen nicht angeben können, wie viele Reihen aktualisiert wurden. Dies müsste gesondert evaluiert werden.
		// Wenn also hier keine Reihe aktualisier wurde, ist das verdächtig (Das Profil wurde ja gefunden)
		// Ein Error 500 (Internal Server Error) sollte dies umschreiben. Dabei wird der zweite Parameter automatisch geloggt. Die Rückgabe wäre: (object)['status'=>500, 'error'=>'Profil ko...'];
		if(!$upd) return self::response(500, 'Profil konnte nicht aktualisert werden');

		// Ansonsten ist das Update erfolgreich: Status 204 (No Content)
		return self::response(204);
		}

	public static function delete_profile($req){
		// Mandatory Parameter
		$mand = h::eX($req, ['profileID'=>'~1,16777215/i'], $error);
		if($error) return self::response(400, $error);

		// Profil suchen
		$profile = self::pdo('s_profile', $mand['profileID']);
		if(!$profile) return self::response($profile === false ? 560 : 404);

		// Profil löschen
		$del = self::pdo('d_profile', $profile->ID);
		// Ein DELETE funktioniert wie eine UPDATE, daher kommt entweder false beim Query-Error, ansonsten die Anzahl der betroffenen Reihen.
		if($upd === false) return self::response(560);
		if(!$upd) return self::response(500, 'Profil konnte nicht gelöscht werden');

		// Profil erfolgreich gelöscht
		return self::response(204);
		}


	public static function list_profile(){
		// Keine Parameter nötig

		// Profile laden
		$profile_list = self::pdo('l_profile');
		// 'l_profile' ist ein SELECT ohne LIMIT 1, daher ist ein Array an Einträgen/Objecten zu erwarten
		if($profile_list === false) return self::response(560);

		// Wenn also kein Query-Fehler vorliegt, gibt es mindestens ein leeres Array, oder halt ein gefülltes
		return self::response(200, $profile_list);

		}

	public static function get_profile($req){
		// Mandatory Parameter
		$mand = h::eX($req, ['profileID'=>'~1,16777215/i'], $error);
		if($error) return self::response(400, $error);

		// Profil suchen
		$profile = self::pdo('s_profile', $mand['profileID']);
		if($profile === false) return self::response(560);
		if(!$profile) return self::response(404);

		// Profil zurückgeben
		return self::response(200, $profile);

		}

	public static function update_profile_age_var2($req){
		// Hier noch ein Aspekt, wodurch sich die Funktionen ergänzen können. Diese Funktion macht das gleiche wie update_profile_age()

		$res = self::get_profile($req);
		// Wenn ein Fehler vorliegt, so reicht es (meistens) hier einfach $res zurückzugeben. Ewaitige Fehler wurden bereits geloggt. Mögliche Fehler sind laut get_profile() 400, 404 und 500 (560)
		if($res->status != 200) return $res;

		// Ansonsten liegt das Profile vor
		$profile = $res->data;

		// Mandatory Parameter
		$mand = h::eX($req, ['age'=>'~18,65/i'], $error);
		if($error) return self::response(400, $error);

		// Alter aktualisieren
		$upd = self::pdo('u_profile', [$mand['age'], $profile->ID]);
		if($upd === false) return self::response(560);
		if(!$upd) return self::response(500, 'Profil konnte nicht aktualisert werden');

		// Update erfolgreich: Status 204 (No Content)
		return self::response(204);
		}



	/*
	Fehlerhandling

	Die \tools\error Klasse ist oben auch angegeben. Im Grunde ist daraus nur eine Funktion relevant: e::logtrigger('Error xyz bla bla'). Diese triggert zwar den Error so wie ein echter
	Fehler, jedoch ohne das Script abzubrechen. Dies ist für Debuggingzwecken nützlich.

	*/

	}
