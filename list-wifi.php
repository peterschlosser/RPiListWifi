<html lang="en">
<head>
<meta charset="utf-8">
<title>WiFi Access Points</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
<style type="text/css">
<!--
[class*="col-"] {
    padding-top: 1rem;
    padding-bottom: 1rem;
    background-color: rgba(86, 61, 124, .15);
    border: 1px solid rgba(86, 61, 124, .2);
}	
.data {
    background-color: #fff;
}	
-->
</style>
</head>
<body>
<div class="container body-content">
  <h2><i class="fa fa-info-circle"></i> WiFi Access Points</h2>
</div>
<?php
	error_reporting(E_ALL);
	ini_set('display_errors', true);
	define('IF_WIFI', 'wlan0');
	
	// returns raw iwlist scanning content
	function system_list_wifi_data()
	{
		$interface = constant('IF_WIFI');
		$command = sprintf('/sbin/iwlist %s scanning 2>&1', $interface);
		$lastline = exec($command, $output, $retval);
		return $output;
	}
	
	// returns iwlist scan content grouped by cell
	function list_wifi_cells()
	{
		// all lines content
		$content = (array)system_list_wifi_data();
		if (count($content) == 0)
			return false;

		// first line of cell list
		list($interface, $message) = preg_split("/\s/", $content[0], 2, PREG_SPLIT_NO_EMPTY);
		if (strcmp($interface, constant('IF_WIFI')))
		{
			printf("Warning: WiFi interface(%s) mismatch: <code>%s</code>\n<br>", constant('IF_WIFI'), $content[0]);
		}
		array_shift($content);	// discard first line
		
		// cell list
		$result = array();
		$cell_data = array();
		$cell_name = false;
		foreach ($content as $line)
		{
			// when $line begins a new access point cell
			if (preg_match("/\s+(Cell [0-9]+) - (.+)/", $line, $match))
			{
				if ($cell_name != false)
				{
					// terminate existing cell rows of data
					$result[$cell_name] = $cell_data;
				}
				// begin new list of cell rows
				$cell_name = $match[1];
				$cell_data = array($match[2]);
				continue;
			}
			
			// trim leading 20 spaces each row
			$trimmed = preg_replace("/^\s{20}/", "", $line);
			
			array_push($cell_data, $trimmed);
		}
		if ($cell_name)
		{
			// terminate final cell rows of data
			$result[$cell_name] = $cell_data;
		}

		return $result;
	}
	
	// returns list of wifi access points
	function get_waps()
	{
		$result = array();
		$cells = list_wifi_cells();
		$new_cell = array(
			"SSID" => "",
			"Quality" => "",
			"Channel" => "",
			"Encryption" => "",
			"Address" => "",
			"Signal Level" => "",
			"Bit Rates" => ""
		);

		foreach ($cells as $ckey => $cell)
		{
			$pairs = $new_cell;
			
			$key = false;
			$value = false;
			foreach ($cell as $lineno => $line)
			{
				// handle fields with multi-line content
				switch($key)
				{
					case 'Bit Rates':
						if (preg_match("/^\s?/", $line))
						{
							// line has leading spaces, append value content.
							$value .= '; ';
							$value .= trim($line);
							continue;
						}
						break;
					default:
						// do nothing
						break;
				}
				
				// save last key content (may contain multiple lines)
				switch ($key)
				{
					case 'Encryption key':
						if ($value == "off")
						{
							// Open network when off
							$pairs['Encryption'] = 'Open';
							break;
						}
						if (empty($pairs['Encryption']))
						{
							// default to WEP unless set by IE
							$pairs['Encryption'] = 'WEP';
						}
						break;
						
					case 'IE':
						if (preg_match("/^(WPA Version .+)/", $value, $match))
						{
							// set WPA encryption vesion
							$pairs['Encryption'] = $match[1];
						}
						break;
						
					case 'ESSID':
						$scrubbed = trim($value);
						$scrubbed = trim($scrubbed, '"');
						$pairs['SSID'] = $scrubbed;
						break;
					case 'Quality':
						list($quality, $other) = preg_split("/\s+/", $value, 2, PREG_SPLIT_NO_EMPTY);
						$pairs['Quality'] = $quality;
						if (preg_match("/^([^=]+)=(.+)/", $other, $match))
						{
							$pairs['Signal Level'] = $match[2];
						}
						break;
					case 'Address':
					case 'Bit Rates':
					case 'Channel':
						$pairs[$key] = $value;
						break;
						
					default:
						// otherwise ignore
						break;
				}
				$key = $value = false;
				
				// split new key/value pair
				if (preg_match("/^([^=:]+)[=:\s]+(.+)/", $line, $match))
				{
					$key = $match[1];			
					$value = $match[2];
				}
			}
			
			array_push($result, $pairs);
		}
		return $result;
	}

	$wifidata = get_waps();
?>
<div class="container">
  <?php foreach ($wifidata as $num => $ap): ?>
  <div class="row">
    <div class="col-sm-12">
      <?= sprintf('WAP %02d', $num + 1) ?>
    </div>
  </div>
  <?php foreach ($ap as $key => $value): ?>
  <div class="row">
    <div class="col-sm-2">
      <?= $key ?>
    </div>
    <div class="col-sm-10 data">
      <?= $value ?>
    </div>
  </div>
  <?php endforeach; /* key/value */ ?>
  <?php endforeach; /* ap */ ?>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>
