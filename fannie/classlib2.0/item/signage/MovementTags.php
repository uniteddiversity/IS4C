<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op, Duluth, MN

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

namespace COREPOS\Fannie\API\item\signage {
use \FannieDB;
use \FannieConfig;
use COREPOS\Fannie\API\lib\Store;

class MovementTags extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 18;
    protected $MED_FONT = 14;
    protected $SMALL_FONT = 10;

    protected $font = 'Arial';
    protected $alt_font = 'Arial';

    protected $width = 52; // tag width in mm
    protected $height = 31; // tag height in mm
    protected $left = 5.5; // left margin
    protected $top = 15; // top margin

    public function drawPDF()
    {
        $pdf = new \FPDF('P', 'mm', 'Letter');

        $dbc = FannieDB::get(FannieConfig::config('OP_DB'));
        $storeID = Store::getIdByIp();
        $mult = $storeID == 1 ? 3 : 7;
        $parP = $dbc->prepare("SELECT auto_par*{$mult} AS weekly FROM products WHERE upc=? AND store_id=?");

        $chkP = $dbc->prepare('SELECT upc FROM MovementTags WHERE upc=? AND storeID=?');
        $insP = $dbc->prepare('INSERT INTO MovementTags (upc, storeID, lastPar, modified) VALUES (?, ?, ?, ?)');
        $upP = $dbc->prepare('UPDATE MovementTags SET lastPar=?, modified=? WHERE upc=? AND storeID=?');
        $now = date('Y-m-d H:i:s');

        $pdf->SetTopMargin($this->top);  //Set top margin of the page
        $pdf->SetLeftMargin($this->left);  //Set left margin of the page
        $pdf->SetRightMargin($this->left);  //Set the right margin of the page
        $pdf->SetAutoPageBreak(False); // manage page breaks yourself

        $data = $this->loadItems();
        $num = 0; // count tags 
        $x = $this->left;
        $y = $this->top;
        $dbc->startTransaction();
        foreach ($data as $item) {

            // extract & format data
            $price = $item['normal_price'];
            $desc = strtoupper(substr($item['posDescription'],0,27));
            $brand = strtoupper(substr($item['brand'],0,13));
            $pak = $item['units'];
            $size = $item['units'] . "-" . $item['size'];
            $sku = $item['sku'];
            $ppu = $item['pricePerUnit'];
            $vendor = substr($item['vendor'],0,7);
            $upc = $item['upc'];
            $weeklySales = $dbc->getValue($parP, array($item['upc'], $storeID));

            if ($num % 32 == 0) {
                $pdf->AddPage();
                $x = $this->left;
                $y = $this->top;
            } else if ($num % 4 == 0) {
                $x = $this->left;
                $y += $this->height;
            }

            $args = array(
                'height' => 7,
                'valign' => 'T',
                'align' => 'L',
                'suffix' => date('  n/j/y'),
                'fontsize' => 8,
                'font' => $this->font,
            );
            $pdf = $this->drawBarcode($upc, $pdf, $x + 3, $y + 4, $args);

            $pdf->SetFont($this->font, '', 8);

            $pdf->SetXY($x+38, $y+4);
            $border = $mult == 7 ? 'TBR' : 'TBL';
            $pdf->Cell(9, 4, sprintf('%.1f', $weeklySales), $border, 1, 'C');
            $mtag = $dbc->getValue($chkP, array($upc, $storeID));
            if ($mtag) {
                $dbc->execute($upP, array($weeklySales/$mult, $now, $upc, $storeID));
            } else {
                $dbc->execute($insP, array($upc, $storeID, $weeklySales/$mult, $now));
            }

            $pdf->SetXY($x,$y+12);
            $pdf->Cell($this->width,4,$desc,0,1,'L');

            $pdf->SetX($x);
            $pdf->Cell($this->width,4,$brand,0,1,'L');

            $pdf->SetX($x);
            $pdf->Cell($this->width,4,$size,0,1,'L');

            $pdf->SetX($x);
            $pdf->Cell($this->width,4,$sku.' '.$vendor,0,0,'L');

            if (strstr($ppu, '/') && $ppu[strlen($ppu)-1] != '/') {
                $pdf->SetX($x);
                $pdf->Cell($this->width-5,4,$ppu,0,0,'R');
            }

            $pdf->SetXY($x, $y+16);
            $pdf->SetFont($this->font,'B',24);  //change font size
            $pdf->Cell($this->width-5,8,$price,0,0,'R');

            // move right by tag width
            $x += $this->width;

            $num++;
        }
        $dbc->commitTransaction();

        $pdf->Output('Tags4x8P.pdf', 'I');
    }
}

}

