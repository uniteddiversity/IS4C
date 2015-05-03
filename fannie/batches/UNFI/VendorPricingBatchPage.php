<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class VendorPricingBatchPage extends FanniePage 
{
    protected $title = "Fannie - Create Price Change Batch";
    protected $header = "Create Price Change Batch";

    public $description = '[Vendor Price Change] creates a price change batch for a given
    vendor and edits it based on catalog cost information.';
    public $themed = true;

    private $mode = 'start';

    function preprocess()
    {
        global $FANNIE_URL;

        if (FormLib::get_form_value('vid') !== ''){
            $this->mode = 'edit';
            $this->add_script($FANNIE_URL.'src/javascript/jquery.js');
        }

        return True;
    }

    function body_content(){
        if ($this->mode == 'start')
            return $this->start_content();
        elseif ($this->mode == 'edit')
            return $this->edit_content();
    }

    function css_content(){
        return '
        tr.green td.sub {
            background:#ccffcc;
        }
        tr.red td.sub {
            background:#ff6677;
        }
        tr.white td.sub {
            background:#ffffff;
        }
        tr.selection td.sub {
            background:#add8e6;
        }
        td.srp {
            text-decoration: underline;
        }';
    }

    function javascript_content(){
        ob_start();
        ?>
var vid = null;
var bid = null;
var sid = null;
var qid = null;
$(document).ready(function(){
    vid = $('#vendorID').val();
    bid = $('#batchID').val();
    sid = $('#superID').val();
    qid = $('#queueID').val();
});

function toggleB(upc){
    var elem = $('#row'+upc).find('.addrem');
    
    var dstr = "upc="+upc+"&vendorID="+vid+"&queueID="+qid+"&batchID="+bid;
    if (elem.html() == "Add"){
        elem.html('Del');
        var price = $('#row'+upc).find('.srp').html();
        $.ajax({
            url: 'batchAjax.php',
            data: dstr + '&action=batchAdd&price='+price,
            success: function(data){
                $('#row'+upc).attr('class','selection');
            }
        });
    }
    else {
        elem.html('Add');
        $.ajax({
            url: 'batchAjax.php',
            data: dstr + '&action=batchDel',
            success: function(data){
                if ($('tr#row'+upc+' input.varp:checked').length > 0)
                    $('#row'+upc).attr('class','white');
                else if ($('tr#row'+upc+' td.price').html() < $('tr#row'+upc+' td.srp').html())
                    $('#row'+upc).attr('class','red');
                else
                    $('#row'+upc).attr('class','green');
            }
        });
    }
}
function toggleV(upc){
    var val = $('#row'+upc).find('.varp').attr('checked');
    if (val){
        $('#row'+upc).attr('class','white');
        $.ajax({
            url: 'batchAjax.php',
            data: 'action=addVarPricing&upc='+upc,
            success: function(data){

            }
        });
    }
    else {
        var m1 = $('#row'+upc).find('.cmargin').html();
        var m2 = $('#row'+upc).find('.dmargin').html();
        if (m1 >= m2)
            $('#row'+upc).attr('class','green');
        else
            $('#row'+upc).attr('class','red');
        $.ajax({
            url: 'batchAjax.php',
            data: 'action=delVarPricing&upc='+upc,
            success: function(data){

            }
        });
    }
}

function reprice(upc){
    if ($('#newprice'+upc).length > 0) return;

    var elem = $('#row'+upc).find('.srp');
    var srp = elem.html();

    var content = "<div class=\"form-inline input-group\"><span class=\"input-group-addon\">$</span>";
    content += "<input type=\"text\" id=\"newprice"+upc+"\" value=\""+srp+"\" class=\"form-control\" size=4 /></div>";
    var content2 = "<button type=\"button\" onclick=\"saveprice('"+upc+"');\" class=\"btn btn-default\">Save</button>";
    elem.html(content);
    $('#row'+upc).find('.dmargin').html(content2);
    $('#newprice'+upc).focus().select();
}

function saveprice(upc){
    var srp = parseFloat($('#newprice'+upc).val());
    var cost = parseFloat($('#row'+upc).find('.cost').html());
    var shipping = parseFloat($('#row'+upc).find('.shipping').html()) / 100.00;
    var newmargin = 1 - (cost * ((1+shipping)/srp));
    newmargin *= 100;
    newmargin = Math.round(newmargin*100)/100;

    $('#row'+upc).find('.srp').html(srp);
    $('#row'+upc).find('.dmargin').html(newmargin+'%');

    var dstr = "upc="+upc+"&vendorID="+vid+"&queueID="+qid+"&batchID="+bid;
    $.ajax({
        url: 'batchAjax.php',
        data: dstr+'&action=newPrice&price='+srp,
        cache: false,
        success: function(data){}
    });
}
        <?php
        return ob_get_clean();
    }

    function edit_content()
    {
        global $FANNIE_OP_DB, $FANNIE_URL;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $vendorID = FormLib::get_form_value('vid',0);
        $superID = FormLib::get_form_value('super',99);
        $queueID = FormLib::get('queueID');
        $filter = FormLib::get_form_value('filter') == 'Yes' ? True : False;

        /* lookup vendor and superdept names to build a batch name */
        $sn = "All";
        if ($superID != 99){
            $p = $dbc->prepare_statement("SELECT super_name FROM superDeptNames WHERE superID=?");
            $r = $dbc->exec_statement($p,array($superID));
            $sn = array_pop($dbc->fetch_row($r));
        }
        $vendor = new VendorsModel($dbc);
        $vendor->vendorID($vendorID);
        $vendor->load();

        $batchName = $sn." ".$vendor->vendorName()." PC ".date('m/d/y');

        /* find a price change batch type */
        $typeP = $dbc->prepare_statement('SELECT MIN(batchTypeID) FROM batchType WHERE discType=0');
        $typeR = $dbc->exec_statement($typeP);
        $bType = 0;
        if ($dbc->num_rows($typeR) > 0) {
            $typeW = $dbc->fetch_row($typeR);
            $bType = $typeW[0];
        }

        /* get the ID of the current batch. Create it if needed. */
        $bidQ = $dbc->prepare_statement("SELECT batchID FROM batches WHERE batchName=? AND batchType=? AND discounttype=0
            ORDER BY batchID DESC");
        $bidR = $dbc->exec_statement($bidQ,array($batchName,$bType));
        $batchID = 0;
        if ($dbc->num_rows($bidR) == 0) {
            $insQ = $dbc->prepare_statement("INSERT INTO batches (batchName,startDate,endDate,batchType,discounttype,priority) VALUES
                (?,'1900-01-01','1900-01-01',?,0,0)");
            $insR = $dbc->exec_statement($insQ,array($batchName,$bType));
            $batchID = $dbc->insert_id();
        } else  {
            $batchID = array_pop($dbc->fetch_row($bidR));
        }

        $ret = sprintf('<b>Batch</b>: 
                    <a href="%sbatches/newbatch/BatchManagementTool.php?startAt=%d">%s</a>',
                    $FANNIE_URL,
                    $batchID,
                    $batchName);
        $ret .= sprintf("<input type=hidden id=vendorID value=%d />
            <input type=hidden id=batchID value=%d />
            <input type=hidden id=queueID value=%d />
            <input type=hidden id=superID value=%d />",
            $vendorID,$batchID,$queueID,$superID);

        $batchUPCs = array();
        $bq = $dbc->prepare_statement("SELECT upc FROM batchList WHERE batchID=?");
        $br = $dbc->exec_statement($bq,array($batchID));
        while($bw = $dbc->fetch_row($br)) $batchUPCs[$bw[0]] = True;

        $query = "SELECT p.upc,
            p.description,
            v.cost,
            b.shippingMarkup,
            p.normal_price,
            1 - (v.cost * ((1+b.shippingMarkup)/p.normal_price)) AS current_margin,
            s.srp,
            1 - (v.cost * ((1+b.shippingMarkup)/s.srp)) AS desired_margin,
            v.vendorDept,
            x.variable_pricing
            FROM products AS p 
                INNER JOIN vendorItems AS v ON p.upc=v.upc AND v.vendorID=?
                INNER JOIN vendorSRPs AS s ON v.upc=s.upc AND v.vendorID=s.vendorID
                INNER JOIN vendors as b ON v.vendorID=b.vendorID
                LEFT JOIN prodExtra AS x on p.upc=x.upc ";
        $args = array($vendorID);
        if ($superID != 99){
            $query .= " LEFT JOIN MasterSuperDepts AS m
                ON p.department=m.dept_ID ";
        }
        $query .= "WHERE v.cost > 0 ";
        if ($superID != 99) {
            $query .= " AND m.superID=? ";
            $args[] = $superID;
        }
        if ($filter === false) {
            $query .= " AND p.normal_price <> s.srp ";
        }

        $query .= " ORDER BY p.upc";

        $prep = $dbc->prepare_statement($query);
        $result = $dbc->exec_statement($prep,$args);

        $ret .= "<table class=\"table table-bordered\">";
        $ret .= "<tr><td colspan=3>&nbsp;</td><th colspan=3>Current</th>
            <th colspan=2>Vendor</th></tr>";
        $ret .= "<tr><th>UPC</th><th>Our Description</th><th>Cost</th>
            <th>Shipping</th>
            <th>Price</th><th>Margin</th><th>SRP</th>
            <th>Margin</th><th>Cat</th><th>Var</th>
            <th>Batch</th></tr>";
        while($row = $dbc->fetch_row($result)){
            $bg = "white";
            if (isset($batchUPCs[$row['upc']]))
                $bg = 'selection';
            elseif ($row['variable_pricing'] != 1)
                $bg = ($row['normal_price']<$row['srp'])?'red':'green';
            $ret .= sprintf("<tr id=row%s class=%s>
                <td class=\"sub\">%s</td>
                <td class=\"sub\">%s</td>
                <td class=\"sub cost\">%.3f</td>
                <td class=\"sub shipping\">%.2f%%</td>
                <td class=\"sub price\">%.2f</td>
                <td class=\"sub cmargin\">%.2f%%</td>
                <td onclick=\"reprice('%s');\" class=\"sub srp\">%.2f</td>
                <td class=\"sub dmargin\">%.2f%%</td>
                <td class=\"sub\">%d</td>
                <td><input class=varp type=checkbox onclick=\"toggleV('%s');\" %s /></td>
                <td class=white><a class=addrem href=\"\" onclick=\"toggleB('%s');return false;\">%s</a></td>
                </tr>",
                $row['upc'],
                $bg,
                $row['upc'],
                $row['description'],
                $row['cost'],
                $row['shippingMarkup']*100,
                $row['normal_price'],
                100*$row['current_margin'],
                $row['upc'],
                $row['srp'],
                100*$row['desired_margin'],
                $row['vendorDept'],
                $row['upc'],
                ($row['variable_pricing']==1?'checked':''),
                $row['upc'],
                (isset($batchUPCs[$row['upc']])?'Del':'Add')
            );
        }
        $ret .= "</table>";

        return $ret;
    }

    function start_content()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $p = $dbc->prepare_statement("select superID,super_name from MasterSuperDepts
            WHERE superID > 0
            group by superID,super_name");
        $res = $dbc->exec_statement($p);
        $opts = "<option value=99 selected>All</option>";
        while($row = $dbc->fetch_row($res))
            $opts .= "<option value=$row[0]>$row[1]</option>";

        $p = $dbc->prepare_statement("SELECT vendorID,vendorName FROM vendors ORDER BY vendorName");
        $res = $dbc->exec_statement($p);
        $vopts = "";
        while($w = $dbc->fetch_row($res))
            $vopts .= "<option value=$w[0]>$w[1]</option>";

        $queues = new ShelfTagQueuesModel($dbc);
        $qopts = $queues->toOptions();

        ob_start();
        ?>
        <form action=VendorPricingBatchPage.php method="get">
        <label>Select a Vendor</label>
        <select name=vid class="form-control">
        <?php echo $vopts; ?>
        </select>
        <label>and a Super Department</label>
        <select name=super class="form-control">
        <?php echo $opts; ?>
        </select>
        <label>Show all items</label>
        <select name=filter class="form-control">
        <option>No</option>
        <option>Yes</option>
        </select>
        <label>Shelf Tag Queue</label>
        <select name="queueID" class="form-control">
        <?php echo $qopts; ?>
        </select>
        <br />
        <p>
        <button type=submit class="btn btn-default">Continue</button>
        </p>
        </form>
        <?php

        return ob_get_clean();
    }

    public function helpContent()
    {
        return '<p>Review products from the vendor with current vendor cost,
            retail price, and margin information. The tool creates a price
            change batch in the background. It will add items to this batch
            and automatically create shelf tags.</p>
            <p>The default <em>Show all items</em> setting, No, omits items
            whose current retail price is identical to the margin-based
            suggested retail price.</p>
            ';
    }


}

FannieDispatch::conditionalExec(false);

?>
