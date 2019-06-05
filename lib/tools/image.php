<?php
/*****
 * Version 1.0.2014-02-03 (untested)
**/
namespace tools;

class image {

	public static function resize($typ, $source, $sW, $sH, $target = null){
		if($target === null) $target = $source;
		if(is_file($source) and list($oW, $oH, $sourceTyp) = @getimagesize($source)){
			if($sW+$sH > 0 and $oW+$oH > 1 and ($oW != $sW or $oH != $sH)){
				/* Voreinstellung: kompletten Bild verwenden */
				$mLeft = 0;
				$mTop = 0;
				$mW = $oW;
				$mH = $oH;

				/* Resize Typ berechnen */
				if($typ === "proportialCrop"){
					$mW = ($oW/$sW > $oH/$sH) ? round($oH/$sH*$sW) : $oW; // ggf. Breite reduzieren
					$mH = ($oW/$sW < $oH/$sH) ? round($oW/$sW*$sH) : $oH; // ggf. Höhe reduzieren
					$mLeft = ($mW < $oW) ? round(($oW-$mW-0.5)/2) : 0; // ggf. zentrierte Breite
					$mTop = ($mH < $oH) ? round(($oH-$mH-0.5)/2) : 0; // ggf. zentrierte Höhe
					}
				elseif($typ === "proportialToOuter"){
					if(!$sH and $sW) $sH = round($sW/$oW*$oH);
					if(!$sW and $sH) $sW = round($sH/$oH*$oW);
					}
				elseif($typ === "proportialToInner"){
					$sW = round($oH/$sH*$sW);
					$sH = round($oW/$sW*$sH);
					}

				/* Bild laden */
				if($sourceTyp === 1) $sourceImage = imagecreatefromgif($source);
				elseif($sourceTyp === 2) $sourceImage = imagecreatefromjpeg($source);
				elseif($sourceTyp === 3) $sourceImage = imagecreatefrompng($source);

				/* Neues Bild generieren */
				if(isset($sourceImage)){
					$targetImage = imagecreatetruecolor($sW, $sH);
					imagecopyresampled($targetImage, $sourceImage, 0, 0, $mLeft, $mTop, $sW, $sH, $mW, $mH);
					}

				/* Bild schreiben */
				$re = false;
				if($sourceTyp === 1) $re = imagegif($targetImage, $target);
				elseif($sourceTyp === 2) $re = imagejpeg($targetImage, $target, 98);
				elseif($sourceTyp === 3) $re = imagepng($targetImage, $target);

				/* Bilder aus Speicher löschen */
				imagedestroy($sourceImage);
				imagedestroy($targetImage);
				return $re;
				}
			elseif($source !== $target){
				return copy($source, $target);
				}
			}
		else return false;
		}

	}
