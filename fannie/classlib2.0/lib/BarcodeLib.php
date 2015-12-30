<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

    This file is part of CORE-POS.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/**
  @class BarcodeLib
  Barcode related functions
*/
class BarcodeLib
{
    public static $CODES = array(
    'A'=>array(
        '0'=>'0001101','1'=>'0011001','2'=>'0010011','3'=>'0111101','4'=>'0100011',
        '5'=>'0110001','6'=>'0101111','7'=>'0111011','8'=>'0110111','9'=>'0001011'),
    'B'=>array(
        '0'=>'0100111','1'=>'0110011','2'=>'0011011','3'=>'0100001','4'=>'0011101',
        '5'=>'0111001','6'=>'0000101','7'=>'0010001','8'=>'0001001','9'=>'0010111'),
    'C'=>array(
        '0'=>'1110010','1'=>'1100110','2'=>'1101100','3'=>'1000010','4'=>'1011100',
        '5'=>'1001110','6'=>'1010000','7'=>'1000100','8'=>'1001000','9'=>'1110100')
    );

    public static $PARITIES = array(
        '0'=>array('A','A','A','A','A','A'),
        '1'=>array('A','A','B','A','B','B'),
        '2'=>array('A','A','B','B','A','B'),
        '3'=>array('A','A','B','B','B','A'),
        '4'=>array('A','B','A','A','B','B'),
        '5'=>array('A','B','B','A','A','B'),
        '6'=>array('A','B','B','B','A','A'),
        '7'=>array('A','B','A','B','A','B'),
        '8'=>array('A','B','A','B','B','A'),
        '9'=>array('A','B','B','A','B','A')
    );

    /**
      Zero-padd a UPC to standard length
      @param $upc string upc
      @return standard length upc
    */
    static public function padUPC($upc)
    {
        return str_pad(trim($upc), 13, '0', STR_PAD_LEFT);
    }

    static public function trimCheckDigit($upc)
    {
        if (strlen($upc) < 13) {
            $upc = self::padUPC($upc);
        }

        if (strlen(ltrim($upc, '0')) == 13 && self::getCheckDigit(substr($upc,0,12)) == $upc[12]) {
            // 13 digit value without leading zeroes
            // Could be EAN-13 w/ check or GTIN-14 w/o check
            // last digit is check digit
            // EAN-13 is far more common so trim
            return '0' . substr($upc, 0, 12);
        } else if (strlen(ltrim($upc, '0')) == 12) {
            // 12 digit value without leading zeroes
            // Could be UPC-A w/ check, EAN-13 w/ or w/o check
            $upc_check = self::getCheckDigit(substr($upc, 1, 11));
            $ean_check = self::getCheckDigit(substr($upc, 0, 12));
            if ($upc_check == $upc[12] && $ean_check == $upc[12]) {
                // almost definitely UPC-A w/ check
                // EAN-13 is a superset so its check should match
                return '0' . substr($upc, 0, 12);
            } else if ($ean_check == $upc[12] && $upc_check != $upc[12]) {
                // not sure what this means
                // EAN-13 with two-digit numbering code
                // between 01 and 09 & has check?
                // or should code 01 to 09 correspond to
                // UPC-A codes 1 to 9
                return $upc;
            } else if ($ean_check != $upc[12] && $upc_check == $upc[12]) {
                // I think this shouldn't happen
                // since EAN-13 is a superset
                return $upc;
            } else {
                return $upc;
            }
        }

        return $upc;
    }

    /**
      Calculate standard check digit for UPCs, EANs, etc
      @param $upc string upc
      @return int check digit
    */
    static public function getCheckDigit($upc)
    {
        // GTIN standard provides weights for 17 digits
        // values must be right aligned, so left pad with 0s
        $upc = str_pad($upc, 17, '0', STR_PAD_LEFT);
        return self::calculateVariableCheckDigit($upc);
    }

    static private function calculateVariableCheckDigit($upc)
    {
        $sum = 0;
        for ($i=0; $i<strlen($upc); $i++) {
            if ($i % 2 == 0) {
                $sum += 3 * ((int)$upc[$i]);
            } else {
                $sum += (int)$upc[$i];
            }
        }

        $mod = $sum % 10;
        if ($mod == 0) {
            return 0;
        } else {
            return 10 - $mod;
        }
    }

    static public function verifyCheckDigit($upc)
    {
        $current_check = substr($upc, -1);
        $without_check = substr($upc, 0, strlen($upc)-1);
        if ($current_check == self::getCheckDigit($without_check)) {
            return true;
        } else {
            return false;
        }
    }

    static public function EAN13CheckDigit($str)
    {
        $ean = str_pad($str,12,'0',STR_PAD_LEFT);
        return $ean . self::getCheckDigit($ean);
    }

    public static function UPCACheckDigit($str)
    {
        $upc = str_pad($str,11,'0',STR_PAD_LEFT);
        return $upc . self::getCheckDigit($upc);
    }

    public static function normalize13($str)
    {
        $str = ltrim($str,'0');
        if (strlen($str) <= 11) {
            return '0' . self::UPCACheckDigit($str);
        } else {
            return self::EAN13CheckDigit($str);
        }
    }
}

