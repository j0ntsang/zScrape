<?php
    // Place this at the very top of script
    $start = microtime(TRUE);

    require_once("includes/database.php");

    // Instantiate 'leads' class
    $cityListings = new leads;

    // Get cities & state abbreviations
    $sql = "SELECT `city_name`,`state_abbr` FROM `z_cities` JOIN `z_states` ON z_cities.city_state=z_states.state_name ORDER BY `city_name`";

    if($cityListings->query($sql))
    {
        // Loop through each city
        for($i = 0; $i < count($cityListings->results); $i++)
        {
            $link = array("city" => $cityListings->results[$i]['city_name'],"state" => $cityListings->results[$i]['state_abbr'],"leadType" => "fsbo", "sortBy" => "days_sort");
            include("zscrape_pages.php");
        }
    }

    // Place this at the very bottom of script
    $finish = microtime(TRUE);

    // Subtract the start time from the end time to get our difference in seconds
    $totaltime = $finish - $start;

    echo "This script took " . $totaltime . " seconds to run";
?>