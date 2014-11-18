<?php
require("config.php");

class mydb
{ 
	public $dblink, $dbname, $dbhost, $dbuser, $dbpass, $error, $results = array(), $resultsCount;
	
	public function __construct()
	{
		$this->dbhost = DBHOST;
		$this->dbname = DBNAME;
		$this->dbpass = DBPASS;
		$this->dbuser = DBUSER;
		
		$this->connect();
	}
	
	public function connect()
	{
		if($this->dblink = mysqli_connect($this->dbhost,$this->dbuser,$this->dbpass,$this->dbname))
		{
            return true;
		}
		else
		{
			$this->error = "DB ERROR : ".mysqli_error($this->dblink);
			return false;
		}
	}
	
	public function query($sql)
	{
		# this function will return TRUE (with a number) or FALSE.
        # if there is a result set it will populate results property
		if($result = mysqli_query($this->dblink, $sql))
		{
			if(is_object($result))
			{
                while($row = mysqli_fetch_assoc($result))
                {
                    array_push($this->results, $row);
                }
                $this->resultsCount = mysqli_num_rows($result);
            }
            return $result;
		}
		else
		{
			$this->error = "query error :".mysqli_error($this->dblink);
			return false;
		}
	}

    // cleanInput:
    // will prevent browser-rendered code from being passed
    public function cleanInput($input) {

        $search = array(
            '@<script[^>]*?>.*?</script>@si',   // strip out Javascript
            '@<[\/\!]*?[^<>]*?>@si',            // strip out HTML
            '@<style[^>]*?>.*?</style>@siU',    // strip style tags properly
            '@<![\s\S]*?--[ \t\n\r]*>@'         // strip multi-line comments
        );

        // if any of the regular expressions are found in input, erase it with empty string
        $output = preg_replace($search, '', $input);
        // returns string
        return $output;
    }

    // sanitize:
    // will prevent sql injection
    public function sanitize($input) {
        if (is_array($input))
        {
            // array indexies individually selected
            foreach($input as $var=>$val)
            {
                // callback recurses, running each item through the 'else'
                $output[$var] = $this->sanitize($val);
            }
        }
        else {
            $input = $this->cleanInput($input);
            $output = mysqli_real_escape_string($this->dblink, $input);
        }
        return $output;
    }
	
}// end of class mydb

class leads extends mydb
{
    public $lead_exists, $lead_old, $lead_type, $lead_zpid, $lead_address, $lead_amenities, $lead_city, $lead_description, $lead_phone, $lead_price, $lead_zestimate, $lead_timestamp;

    public function updateLead()
    {
        $flag = false;

        // Looks for any rows that match Zillow ID where the datestamp is older than 30 minutes from current script run-time
        $this->lead_old = $this->query("select * from z_leads WHERE lead_zpid=" . $this->lead_zpid . " AND lead_datestamp < NOW() - INTERVAL 30 MINUTE");

        // If results are found, update current results
        if($this->lead_old)
        {
            $sql = "UPDATE z_leads SET lead_price='" .$this->lead_price. "', lead_zestimate='" .$this->lead_zestimate. "' WHERE lead_zpid=" .$this->lead_zpid;
            if($this->query($sql))
            {
                $flag = true;
            }
        }

        if($flag === true)
        {
            echo json_encode(array("result"=>true, "msg"=>"Lead: " .$this->lead_zpid. " was updated"));
            return true;
        }
        else
        {
            echo json_encode(array("result"=>false, "msg"=>"Lead is already up-to-date"));
            return false;
        }
    }

    public function addLead()
    {
        $flag = false;

        $sql = "INSERT INTO z_leads (`lead_type`,
                                     `lead_zpid`,
                                     `lead_address`,
                                     `lead_amenities`,
                                     `lead_city_id`,
                                     `lead_description`,
                                     `lead_phone`,
                                     `lead_price`,
                                     `lead_zestimate`)
                            VALUES ('".$this->lead_type."',
                                     ".$this->lead_zpid.",
                                    '".addslashes($this->lead_address)."',
                                    '".addslashes($this->lead_amenities)."',
                                    '".$this->lead_city."',
                                    '".addslashes($this->lead_description)."',
                                    '".addslashes($this->lead_phone)."',
                                    '".addslashes($this->lead_price)."',
                                    '".addslashes($this->lead_zestimate)."')";
        if($this->query($sql))
        {
            $flag = true;
        }

        if($flag === true)
        {
            echo json_encode(array("result"=>true, "msg"=>"Lead: " .$this->lead_zpid. " was saved"));
        }
        else
        {
            echo json_encode(array("result"=>false, "msg"=>$this->error));
        }
    }

    public function getLead($option,$value=NULL) // this function can get 'all' posts or post by id etc..
    {
        $sql = "SELECT * FROM z_leads";

        switch($option)
        {
            case 'all':
                break;
            case 'id':
                $sql .= " WHERE lead_id=" . $value;
                break;
        }

        if($result = $this->query($sql)) // in this case all results will be fetched into $this->results
        {
            $this->resultsCount = mysqli_num_rows($result);
            return true;
        }
        else
        {
            return false;
        }
    }

    public function getLeads()
    {
        return $this->getLead('all');
    }
} // end of class leads