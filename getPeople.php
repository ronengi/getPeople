<?php

/**
 return a list of users located in required distances from required cities
 */


error_reporting(E_ALL | E_ERROR | E_PARSE);
ini_set('display_errors', true);
ini_set('html_errors', true);


/* constants for DB connectivity & cache */
define("SERVER", "localhost");
define("DATABASE", "getPeople");
define("USERNAME", "<user-name>");
define("PASSWORD", "<password>");
define("PORT", 3306);
define("EXPIRE", 3600);  /* time of cache data expiration in seconds */

function strip_($str) {
    /**
     * replaces '_' with spaces
     * @param string $str the string to replace
     * @return string
     */
    return str_replace('_', ' ', $str);
}

function add_($str) {
    /**
     * replaces spaces with '_'
     * @param string $str the string to replace
     * @return string
     */
    return str_replace(' ', '_', $str);
}

function get_cities() {
    /**
     * Get a list of all cities in the database. Fetch from cache or from DB. Update cache.
     * @return array of all cities: if city name has spaces - convert to '_'
     */
    $m = new Memcached();
    $m->addServer('localhost', 11211);
    $cities = $m->get("cities"); /* try to get cities from cache */
    if (!$cities) {
        /* get cities from DB */
        $cities = array();
        $mysqli = new mysqli(SERVER, USERNAME, PASSWORD, DATABASE, PORT);
        if ($mysqli->connect_errno)    die( "Failed to connect to MySQL: " . $mysqli->connect_error . "<br>\n" );
        $res = $mysqli->query("select name from cities");
        for ($i = 0; $i < $res->num_rows; ++$i) {
            $row = $res->fetch_assoc();
            $cities[] = add_($row["name"]);
        }
        $m->set("cities", $cities, EXPIRE);
    }
    /* var_dump($cities); */
    return $cities; /* Engine automatically optimizes to return reference */
}

function get_distances($fromCity, $radius) {
    /**
     * Calculate and get a list of all distances from required city to all other cities in the database.
     * Fetch from cache or from DB. Update cache.
     * Because the list is big (about 10^6 items * (20 chars + integer)),
     *  it is cached in small keys, with unique key names: (from-city-name + '$' + to-city-name)
     * @param $fromCity required city name
     * @param $radius required distance in km from $fromCity
     * @return array of all distances smaller or equal to $radius (key==from-city $ to-city, value==distance)
     */

    global $cities;
    $distances = array();
    $spacedFromCity = strip_($fromCity);
    $m = new Memcached();
    $m->addServer('localhost', 11211);

    /* Check if distances are cached */
    $distance = $m->get( $fromCity . "$$" );
    if (!$distance) {
        /* get distances from DB */
        /* printf("DB:<br>\n"); */
        $m->set( $fromCity . "$$", true, EXPIRE );
        $mysqli = new mysqli(SERVER, USERNAME, PASSWORD, DATABASE, PORT);
        if ($mysqli->connect_errno)    die( "Failed to connect to MySQL: " . $mysqli->connect_error . "<br>\n" );
        $res = $mysqli->query("select cFrom.name as fromName, cities.name as toName, round(sqrt(pow((cFrom.x - cities.x), 2) + pow((cFrom.y - cities.y), 2))) as distance from cities inner join (select name, x, y from cities where name = \"{$spacedFromCity}\" limit 1) as cFrom");
        for ($i = 0; $i < $res->num_rows; ++$i) {
            $row = $res->fetch_assoc();
            $key = add_($row["fromName"]) . "$" . add_($row["toName"]);
            $val = $row["distance"];
            $m->set( $key, $val, EXPIRE );
            /* printf("set( %s , %s )<br>\n", $key, $val); */
            if ($val <= $radius)
                $distances[$key] = $val;
        }
    }
    else {
        /* get distances from cache */
        /* printf("Cache:<br>\n"); */
        foreach ($cities as $toCity) {
            $key = $fromCity . "$" . $toCity;
            $val = $m->get( $key );
            if ($val <= $radius)
                $distances[$key] = $val;
        }
    }

    /* var_dump($distances); */
    return $distances;
}


$cities = get_cities();
$wanted_cities = "";

/* for each search parameter */
foreach ($_POST as $city => $radius) {
    $distances = get_distances($city, $radius);

    /* collect wanted cities */
    foreach ($distances as $fromTo => $distance) {
        $pieces = explode("$", $fromTo);
        $wanted_cities .= " \"" . $pieces[1] . "\",";
    }

    /* print_r($distances); */
    /* printf("<br>\n"); */

}

/* prepare list of destinations to be inserted to DB query */
$wanted_cities = rtrim($wanted_cities, ",");
$wanted_cities = strip_($wanted_cities);
/* print_r($wanted_cities); */
/* printf("<br>\n"); */


/* display the results in a table */
printf("<table id=\"users\" class=\"centralized\" border=\"0\">\n");
$mysqli = new mysqli(SERVER, USERNAME, PASSWORD, DATABASE, PORT);
if ($mysqli->connect_errno)    die( "Failed to connect to MySQL: " . $mysqli->connect_error . "<br>\n" );
$res = $mysqli->query("select users.name as userName, wanted.name as cityName from users inner join ( select id, name from cities where name in ( {$wanted_cities} ) ) as wanted on users.cityId = wanted.id order by wanted.name, users.name");
if (isset($res->num_rows)) {
    for ($i = 0; $i < $res->num_rows; ++$i) {
        $row = $res->fetch_assoc();
        printf("<tr><td>%d</td><td>%s</td><td>%s</td></tr>\n", $i + 1, $row["userName"], $row["cityName"]);
        /* var_dump($row); */
    }
}
printf("</table>\n");


?>
