<?php
	require 'vendor/autoload.php';

	// phpinfo();
	
	$host = getenv('HOST');
	$username = getenv('USERNAME');
	$password = getenv('PASSWORD');
	$db_name = getenv('DB_NAME');

	//Establishes the connection
	$conn = mysqli_init();
	mysqli_real_connect($conn, $host, $username, $password, $db_name, 3306);
	if (mysqli_connect_errno($conn)) {
		die('Failed to connect to MySQL: '.mysqli_connect_error());
	}

	Flight::set('conn', $conn);
	Flight::set('READ_API', getenv("READ_API"));
	Flight::set('CHANNEL_ID', getenv("CHANNEL_ID"));

	Flight::route('GET /', function() {
		echo 'Welcome to Bus Tracking Project!!!';
	});


	function getCoordinates($bus_id) {
		$readApi = Flight::get('READ_API');
		$channelID = Flight::get('CHANNEL_ID');		
		$json = json_decode(file_get_contents('https://api.thingspeak.com/channels/' . $channelID . '/feed.json?api_key='. $readApi));		
		$feeds = $json->feeds;
		$max_date;
		$result = "";
		foreach ($feeds as $key => $value) {
			if(!isset($max_date)) {
				$max_date = date($value->created_at);
			} else {
				$d = date($value->created_at);
				if($max_date < $d && $value->field3 == $bus_id) {
					$max_date = $d;
					$result = $value;
				}
			}
		}
	    return json_encode($result);
	}

	Flight::route('GET /get_coords/@bus_id', function($bus_id){
		echo getCoordinates($bus_id);
	});

	Flight::route('GET /routes', function() {
		$connection = Flight::get('conn');
		$query = "select source, dest, bus_ids from routes";
		$res = $connection->query($query);
		$jsonResponse = [];
		while($row = $res->fetch_assoc()) {
			$jsonArr = array("source" => $row["source"], "dest" => $row["dest"], "bus_ids" => $row["bus_ids"]);
			$myObj = json_encode($jsonArr);
			array_push($jsonResponse, $myObj);
		}
		Flight::json($jsonResponse);
	});

	Flight::route('POST /add_route', function() {
		$connection = Flight::get('conn');
		$requestData = Flight::request()->data;
		$source = $requestData->source;
		$dest = $requestData->dest;
		$bus_ids = $requestData->bus_ids;
		$query = "insert into routes values(\"". $source . "\",\"". $dest . "\",\"". $bus_ids ."\")";		
		if ($connection->query($query) === TRUE) {
		    echo "New route created successfully. Query /routes to check.";
		} else {
		    echo "Error: " . $query . "<br>" . $connection->error;
		}
	});

	Flight::route('POST /edit_route', function() {
		$connection = Flight::get('conn');
		$requestData = Flight::request()->data;
		$old_source = $requestData->old_source;
		$old_dest = $requestData->old_dest;
		$source = $requestData->source;
		$dest = $requestData->dest;
		$bus_ids = $requestData->bus_ids;
		$query = "update routes set  source = \"". $source . "\", dest = \"". $dest . "\", bus_ids = \"". $bus_ids ."\" where source = \"". $old_source . "\" and dest = \"". $old_dest . "\"";
		if ($connection->query($query) === TRUE) {
		    echo "Route updated successfully. Query /routes to check.";
		} else {
		    echo "Error: " . $query . "<br>" . $connection->error;
		}
	});

	Flight::route('POST /delete_route', function() {
		$connection = Flight::get('conn');
		$requestData = Flight::request()->data;
		$source = $requestData->source;
		$dest = $requestData->dest;
		$query = "delete from routes where source = \"". $source . "\" and dest = \"". $dest . "\"";
		if ($connection->query($query) === TRUE) {
		    echo "Route deleted successfully. Query /routes to check.";
		} else {
		    echo "Error: " . $query . "<br>" . $connection->error;
		}
	});


	Flight::route('GET /all_bus_loc', function() {
		$connection = Flight::get('conn');
		$query = "select bus_id from bus";
		$res = $connection->query($query);
		$jsonResponse = [];
		while($row = $res->fetch_assoc()) {
			$thingsSpeakResponse = (getCoordinates($row["bus_id"]));						
			$jsonArr = array("bus_loc" => $thingsSpeakResponse);
			$myObj = json_encode($jsonArr);
			array_push($jsonResponse, $myObj);
		}		
		Flight::json($jsonResponse);
	});

	Flight::route('GET /get_bus_loc/@source/@dest', function($source, $dest) {		
		$connection = Flight::get('conn');
		$query = "select bus_ids from routes where source = \"".$source."\" and dest = \"".$dest."\"";		
		$bus_ids = "";
		$res = $connection->query($query);
		while($row = $res->fetch_assoc()) {
			$bus_ids = $row["bus_ids"];
		}		
		$bus_loc_query = "select bus_id from bus where bus_id in (".$bus_ids.")";
		echo $bus_loc_query;
		$res = $connection->query($bus_loc_query);
		$jsonResponse = [];
		while($row = $res->fetch_assoc()) {
			$thingsSpeakResponse = (getCoordinates($row["bus_id"]));						
			$jsonArr = array("bus_loc" => $thingsSpeakResponse);
			$myObj = json_encode($jsonArr);
			array_push($jsonResponse, $myObj);
		}
		Flight::json($jsonResponse);
	});

	Flight::route('GET /all_bus_ids', function() {
		$connection = Flight::get('conn');
		$query = "select bus_id from bus";
		$res = $connection->query($query);
		$jsonResponse = [];
		while($row = $res->fetch_assoc()) {			
			$jsonArr = array("bus_ids" => $row["bus_id"]);
			$myObj = json_encode($jsonArr);
			array_push($jsonResponse, $myObj);
		}
		Flight::json($jsonResponse);
	});

	Flight::route('POST /add_bus', function() {
		$connection = Flight::get('conn');
		$bus_id = Flight::request()->data->bus_id;
		$query = "insert into bus values(".$bus_id.")";
		if ($connection->query($query) === TRUE) {
		    echo "Added bus successfully. Query /all_bus_ids to check.";
		} else {
		    echo "Error: " . $query . "<br>" . $connection->error;
		}
	});

	Flight::start();
?>
