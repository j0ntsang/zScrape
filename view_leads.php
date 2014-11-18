<?php
    require_once("includes/database.php");
    $leads_table = new leads;
    $leads = $leads_table->sanitize($_GET['leads']);
    $leads_table->getLeads($leads);
    $i = 0;
    foreach($leads_table->results as $row)
    {
        $table[$i]['lead_id'] = $row['lead_id'];
        $table[$i]['lead_type'] = $row['lead_type'];
        $table[$i]['lead_zpid'] = $row['lead_zpid'];
        $table[$i]['lead_phone'] = htmlspecialchars_decode($row['lead_phone']);
        $table[$i]['lead_price'] = htmlspecialchars_decode($row['lead_price']);
        $table[$i]['lead_zestimate'] = htmlspecialchars_decode($row['lead_zestimate']);
        $table[$i]['lead_address'] = htmlspecialchars_decode($row['lead_address']);
        $table[$i]['lead_amenities'] = htmlspecialchars_decode($row['lead_amenities']);
        $leads_table->lead_description = htmlspecialchars_decode($row['lead_description']);
        $leads_table->lead_description = htmlspecialchars_decode($leads_table->lead_description);
        $leads_table->lead_description = preg_split('/(?<=[.?!:])\s+/', $leads_table->lead_description, -1, PREG_SPLIT_NO_EMPTY);
        foreach($leads_table->lead_description as $var=>$val)
        {
            // callback recurses, running each item through the 'else'
            if(preg_match('/\bZillow\b/',$val) == 1)
            {
                $val = '';
            }
            $leads_table->lead_description[$var] = $val;
        }
        $table[$i]['lead_description'] = implode(" ",$leads_table->lead_description);
        $table[$i]['lead_datestamp'] = date('Y-m-d H:i:s',strtotime($row['lead_datestamp']));
        $i++;
    }

    echo json_encode($table);
?>