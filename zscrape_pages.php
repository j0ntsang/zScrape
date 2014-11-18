<?php
    // PHP Simple HTML DOM Parser
    require_once('includes/simple_html_dom.php');

    // If there is a [space] character in the city name, replace with '-' [hyphen] for URL
    if( strpos($link['city']," "))
    {
        $link['city'] = str_replace(" ","-",$link['city']);
    }

    // Target URL to determine pagination
    $url = 'http://www.zillow.com/homes/' . $link['leadType'] . '/' . $link['city'] . '-' . $link['state'] . '/list/' . $link['sortBy'] . '/0_mmm/';
    //echo "<br />" . $url . "<br />";
    // Create a DOM object from Target URL
    $html = file_get_html($url);

    // Find pagination limit
    // Grabs all links from pagination list
    $page_total = array();
    foreach($html->find('.pagination-2012 a') as $anchor) {
        // gets only the text, i.e. '2', 'next page'
        $a = trim($anchor->plaintext);
        // stores values in array
        array_push($page_total,$a);
    }
    // Set pointer to end of pagination array
    $page_limit = end($page_total);
    // Grabs second last value from from pagination array and set string value
    $page_limit = prev($page_total);
    //echo $page_limit . " is page limit<br />";

    // Clear & unset $html variable
    $html->clear();
    unset($html);

    // Pagination start
    $page = 1;

    $page_results = array();

    // Collect FSBO listings
    for($j = 0; $j < $page_limit; $j++) {
        // Target page
        $target_page = 'http://www.zillow.com/homes/' . $link['leadType'] . '/' . $link['city'] . '-' . $link['state'] . '/list/' . $link['sortBy'] . '/' . $page . '_p/0_mmm/';

        //echo "<br />" . $target_page . "<br />";

        // Create a DOM object from Target URL
        $html = file_get_html($target_page);

        // Get property listing link
        foreach($html->find('.property-address a') as $address) {
            // store link in page_results array
            $page_results[] = trim($address->href);
        }

        // Increase counter for next page
        $page++;

        // Clear memory for next test
        $html->clear();
        unset($html);
        $target_page = null;
    }

    // If there is a '-' [hyphen] in the city, replace it with a [space] for next query
    if( strpos($link['city'],"-"))
    {
        $link['city'] = str_replace("-"," ",$link['city']);
    }
    $details['city_name'] = $link['city'];
    $details['leadType'] = $link['leadType'];

    // Scrape FSBO listings
    if($page_results != null)
    {
        foreach($page_results as $linkpart) {

            // Target listing
            $target_listing = "http://www.zillow.com" . $linkpart;

            // Remove trailing '/' from url
            $zpid = trim($target_listing, '_zpid/');
            // Get remainder after last '/'
            $zpid = substr($zpid, strrpos($zpid, '/')+1);
            // Store in listing details
            $details['zpid'] = trim($zpid);

            // Create a DOM object from Target URL
            $listing_page = file_get_html($target_listing);

            $updateLead = new leads;
            // Check to see if lead id exists
            $sql_is_exist = "SELECT * from z_leads WHERE lead_zpid=" . $details['zpid'];
            $updateLead->query($sql_is_exist);
            if($updateLead->resultsCount == 0)
            {
                if (method_exists($listing_page,'find')) {

                    // Contact Number
                    if ($listing_page->find('.phone')) {
                        foreach($listing_page->find('.phone') as $phone_number) {
                            $string = trim($phone_number->plaintext);
                            if(strpos($string,'866-324-4005') === false)
                            {
                                $details['phone'] = preg_replace('/(Call: )/','',$string);
                            }
                            else {
                                $details['phone'] = 'Zillow Sales Referred: 866-324-4005';
                            }
                        }
                    }
                    else {
                        $details['phone'] = 'Phone number not provided';
                    }

                    // Price & Z Estimate,
                    // accounts for 'div' child offset if 'price cut' is present
                    if($listing_page->find('.price-cut-row')){
                        // Price
                        if ($listing_page->find('.estimates div','1')->find('span')) {
                            foreach($listing_page->find('.estimates div','1')->find('span') as $price_estimate) {
                                $details['price'] = trim($price_estimate->plaintext);
                            }
                        }
                        // Z Estimate
                        if($listing_page->find('.estimates div','3'))
                        {
                            if ($listing_page->find('.estimates div','3')->find('span')) {
                                foreach($listing_page->find('.estimates div','3')->find('span') as $z_estimate) {
                                    $details['zestimate'] = trim($z_estimate->plaintext);
                                }
                            }
                        }
                        else
                        {
                            $details['zestimate'] = "N/A";
                        }
                    }
                    else {
                        // Price
                        if ($listing_page->find('.estimates div','1')->find('span')) {
                            foreach($listing_page->find('.estimates div','1')->find('span') as $price_estimate) {
                                $details['price'] = trim($price_estimate->plaintext);
                            }
                        }
                        // Z Estimate
                        if($listing_page->find('.estimates div','2'))
                        {
                            if ($listing_page->find('.estimates div','2')->find('span')) {
                                foreach($listing_page->find('.estimates div','2')->find('span') as $z_estimate) {
                                    $details['zestimate'] = trim($z_estimate->plaintext);
                                }
                            }
                        }
                        else
                        {
                            $details['zestimate'] = "N/A";
                        }
                    }

                    // Address
                    if ($listing_page->find('.prop-addr')) {
                        foreach($listing_page->find('.prop-addr') as $address) {

                            $string = trim($address->plaintext);
                            $string = trim(preg_replace('/\, \s+/',"\r\n",$string));

                            if(strpos($string,'undisclosed') !== false)
                            {
                                $string = str_replace($string,'This address is undisclosed',$string);
                            }

                            $details['address'] = $string;
                        }
                    }

                    // Amenities
                    if ($listing_page->find('.prop-summary')) {
                        foreach($listing_page->find('.prop-summary') as $amenities) {
                            $string = trim($amenities->innertext);
                            $string = preg_replace('/<h1[^>]*>([\s\S]*?)<\/h1[^>]*>/','',$string);
                            $string = trim(preg_replace('/, \s+/',' ',$string));
                            $details['amenities'] = $string;
                        }
                    }
                    else
                    {
                        $details['amenities'] = "N/A";
                    }

                    // Description
                    if ($listing_page->find('#link-footer p')) {
                        foreach($listing_page->find('#link-footer p') as $description) {
                            $string = trim($description->innertext);
                            $string = strip_tags($string);
                            $string = trim(preg_replace('~\\s{2,}~',' ',$string));
                            $details['description'] = $string;
                        }
                    }
                }

                // Get city_id for listing/city relation
                $sql = 'SELECT `city_id` FROM `z_cities` WHERE `city_name`="' . $details['city_name'] . '"';
                // Instantiate 'leads' class
                $leadsSave = new leads;
                $result = $leadsSave->query($sql);
                // Bind captured data to class variables
                if($result = mysqli_query($leadsSave->dblink, $sql))
                {
                    $row = mysqli_fetch_assoc($result);
                    $leadsSave->lead_city = $row['city_id'];
                    $leadsSave->lead_type = $details['leadType'];
                    $leadsSave->lead_zpid = $details['zpid'];
                    $leadsSave->lead_phone = htmlspecialchars($details['phone']);
                    $leadsSave->lead_price = htmlspecialchars($details['price']);
                    $leadsSave->lead_zestimate = htmlspecialchars($details['zestimate']);
                    $leadsSave->lead_address = htmlspecialchars($details['address']);
                    $leadsSave->lead_amenities = htmlspecialchars($details['amenities']);
                    $leadsSave->lead_description = htmlspecialchars($details['description']);

                    // Save leads
                    $leadsSave->addLead();
                    unset($result);
                }

                // Clean Memory
                $listing_page->clear();
                unset($listing_page);
                $target_listing = null;
            }
            else
            {
                $sql_existing_leads = "SELECT * from z_leads WHERE lead_zpid=" . $details['zpid'] . " AND lead_datestamp < NOW() - INTERVAL 30 MINUTE";
                // Looks for any rows that match Zillow ID where the datestamp is older than 30 minutes from current script run-time
                $updateLead->query($sql_existing_leads);
                // If results are found, continue to update
                if($updateLead->resultsCount = 1)
                {
                    if (method_exists($listing_page,'find')) {
                        // Price & Z Estimate,
                        // accounts for 'div' child offset if 'price cut' is present
                        if($listing_page->find('.price-cut-row')){
                            // Price
                            if ($listing_page->find('.estimates div','1')->find('span')) {
                                foreach($listing_page->find('.estimates div','1')->find('span') as $price_estimate) {
                                    $details['price'] = trim($price_estimate->plaintext);
                                }
                            }
                            // Z Estimate
                            if($listing_page->find('.estimates div','3'))
                            {
                                if ($listing_page->find('.estimates div','3')->find('span')) {
                                    foreach($listing_page->find('.estimates div','3')->find('span') as $z_estimate) {
                                        $details['zestimate'] = trim($z_estimate->plaintext);
                                    }
                                }
                            }
                            else
                            {
                                $details['zestimate'] = "N/A";
                            }
                        }
                        else {
                            // Price
                            if ($listing_page->find('.estimates div','1')->find('span')) {
                                foreach($listing_page->find('.estimates div','1')->find('span') as $price_estimate) {
                                    $details['price'] = trim($price_estimate->plaintext);
                                }
                            }
                            // Z Estimate
                            if($listing_page->find('.estimates div','2'))
                            {
                                if ($listing_page->find('.estimates div','2')->find('span')) {
                                    foreach($listing_page->find('.estimates div','2')->find('span') as $z_estimate) {
                                        $details['zestimate'] = trim($z_estimate->plaintext);
                                    }
                                }
                            }
                            else
                            {
                                $details['zestimate'] = "N/A";
                            }
                        }
                    }

                    $sql_update_lead = "UPDATE z_leads SET lead_price='" .$details['price']. "', lead_zestimate='" .$details['zestimate']. "' WHERE lead_zpid=" .$details['zpid'];

                    // If result, update
                    if($updateLead->query($sql_update_lead))
                    {
                        echo json_encode(array("result"=>true, "msg"=>"Lead: " .$details['zpid']. " was updated"));
                    }

                    // Clean Memory
                    $listing_page->clear();
                    unset($listing_page);
                    $target_listing = null;

                }
                else
                {
                    echo json_encode(array("result"=>false, "msg"=>"Lead " .$details['zpid']. " skipped"));
                }
            }
        } // end of foreach

        // Clean memory of page_results
        unset($details);
        $page_results = null;

    } // end of scrape