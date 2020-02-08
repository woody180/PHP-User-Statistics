<?php
class Analytics {
    private $today_date;
    private $today_visits;

    // User information
    private $wb;
    private $loc;
    
    // Connect
    private $conn;
    private $host;
    private $dbname;
    private $user;
    private $pass;

    // Visited url
    private $page_link;

    public function __construct($db, $timezone = NULL) {
        require 'vendor/autoload.php';
        require 'geolocation/geoplugin.class.php';

        // Set page link
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $actual_link = filter_var($actual_link, FILTER_SANITIZE_STRING);
        $actual_link = filter_var($actual_link, FILTER_VALIDATE_URL);
        $actual_link = filter_var($actual_link, FILTER_SANITIZE_URL);
        $this->page_link = $actual_link;
    
        // Browser parser
        $this->wb = new WhichBrowser\Parser(getallheaders(), [ 'detectBots' => true ]);

        // Locations
        $this->loc = new geoPlugin();

        $this->host = $db['host'];
        $this->dbname = $db['dbname'];
        $this->user = $db['user'];
        $this->pass = $db['password'];

        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        if ($timezone) {
            // Set timezone
            date_default_timezone_set($timezone);
        }
        
        $this->today_date = date("j");
    }


    private function deb($x = '') {
        echo '<pre>';
        print_r($x);
        echo '</pre>';
    }


    private function toJSON($fileArray) {
        $json = json_encode($fileArray, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        return $json;
    }


    // Getting client IP
    private function get_client_ip() {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';

        return $ipaddress;
        // return rand();
    }

    // Create database dependencies
    private function create_deps() {
        $sql_users = "CREATE TABLE analytics_users (
            id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_ip varchar(255),
            session_date_day int NOT NULL,
            session_date_month int NOT NULL,
            browser varchar(255),
            geo varchar(255),
            page_views varchar(255),
            os varchar(100),
            device varchar(100),
            engine varchar(255),
            visited_pages TEXT
        )";

        $sql_users_today = "CREATE TABLE analytics_users_today (
            id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
            today_date int,
            today int NOT NULL,
            yesterday int,
            monthly int
        )";

        $this->conn->query($sql_users_today);
        $this->conn->query($sql_users);

        $this->conn->query("INSERT INTO analytics_users_today (today_date, today, yesterday, monthly) VALUES ($this->today_date, 1, 0, 1)");

        $time = time();
        $this->conn->query("INSERT INTO analytics_users (
            user_ip,
            session_date_day,
            session_date_month,
            browser,
            geo,
            page_views,
            os,
            device,
            engine,
            visited_pages
        ) VALUES (
            '".$this->get_client_ip()."',
            '".$this->today_date."',
            '".date('n')."',
            '".addslashes($this->wb->browser->name)." - ".addslashes($this->wb->browser->version->value)."',
            '".addslashes($this->loc->regionName)." - ".addslashes($this->loc->countryName)."',
            1,
            '".addslashes($this->wb->os->name)." - ".addslashes($this->wb->os->version->value)."',
            '".addslashes($this->wb->device->type)."',
            '".addslashes($this->wb->engine->name)."',
            '".addslashes($this->toJSON([$time => $this->page_link]))."'
        )");

        // Create month counter file
        file_put_contents(__DIR__.'/month.txt', date('n'));
    }

    // Initialize analytics
    public function init() {
        $this->loc->locate();
        
        // $this->deb($this->loc->regionName);
        // Check if not a bot
        if (!empty($this->wb->device->type) && strtolower($this->wb->device->type) != 'bot') {

            // Getting analytics data
            $query = $this->conn->query('SELECT * FROM analytics_users');

            // Check if database is setted up and ready to use
            if (empty($query)) {

                // Create database dependencies
                $this->create_deps();

                $this->today_visits = 1;

            } else {
                // If database already created

                // Today visitors
                $query = $this->conn->query('SELECT * FROM analytics_users_today');
                $res = $query->fetch_object();
                $this->today_visits = $res->today;

                // On next day 
                // This IF statement will fire every day
                if ($res->today_date != date('j')) {

                    // After one month
                    $month_file = (int)file_get_contents(__DIR__.'/month.txt');
                    if ($month_file != date('n')) {
                        $this->conn->query("UPDATE analytics_users_today SET monthly = 1 WHERE id = 1");
                        
                        // FreeUp datebase from last month data
                        $this->conn->query("DELETE FROM analytics_users WHERE session_date_month = $month_file");
                        
                        // Create month counter file
                        file_put_contents(__DIR__.'/month.txt', date('n'));

                        $res->monthly = 0;
                    }

                    // Update yesterday visitors
                    $this->conn->query("UPDATE analytics_users_today SET today = 1, yesterday = '".$this->today_visits."', today_date = '".date('j')."'");

                    // Set today visits to zero
                    $this->today_visits = 0;
                    
                }


                // Get user by IP Address
                $queryUser = $this->conn->query("SELECT * FROM analytics_users WHERE user_ip = '".$this->get_client_ip()."' AND session_date_day = '".$this->today_date."'");
                $user = $queryUser->fetch_object();

                // Check if visitor is unique
                // Update visits and user base
                // If user IP is not exists inside the database
                if (empty($user)) {
                    $updated_user_count = $this->today_visits += 1;  // Getting today users count and increment by one
                    $monthly_users = $res->monthly += 1;             // Getting monthly users count and increment by one

                    $time = time();
                    $this->conn->query("UPDATE analytics_users_today SET today = '".$updated_user_count."', monthly = '".$monthly_users."'");
                    $this->conn->query("INSERT INTO analytics_users (
                        user_ip,
                        session_date_day,
                        session_date_month,
                        browser,
                        geo,
                        page_views,
                        os,
                        device,
                        engine,
                        visited_pages
                    ) VALUES (
                        '".$this->get_client_ip()."',
                        '".$this->today_date."',
                        '".date('n')."',
                        '".addslashes($this->wb->browser->name)." - ".addslashes($this->wb->browser->version->value)."',
                        '".addslashes($this->loc->regionName)." - ".addslashes($this->loc->countryName)."',
                        1,
                        '".addslashes($this->wb->os->name)." - ".addslashes($this->wb->os->version->value)."',
                        '".addslashes($this->wb->device->type)."',
                        '".addslashes($this->wb->engine->name)."',
                        '".addslashes($this->toJSON([$time => $this->page_link]))."'
                    )");
                } else {
                    // Update page views
                    $page_views = $user->page_views += 1;
                    $user_id = $user->id;
                    $this->conn->query("UPDATE analytics_users SET page_views = '".$page_views."' WHERE id = '".$user_id."'");

                    // Update visited pages
                    $res = $this->conn->query("SELECT * FROM analytics_users WHERE user_ip = '".$this->get_client_ip()."' AND session_date_day = '".$this->today_date."'");
                    $visited_pages = $res->fetch_object()->visited_pages;
                    $visited_pages = json_decode($visited_pages, true);

                    if (!in_array($this->page_link, $visited_pages)) {
                        $visited_pages[time()] = $this->page_link;
                        $json = $this->toJSON($visited_pages);
                        $ip = $this->get_client_ip();

                        $this->conn->query("UPDATE analytics_users SET visited_pages = '".$json."' WHERE user_ip = '".$ip."' AND session_date_day = '".$this->today_date."'");
                    }

                }


                $this->deb($visited_pages);


            }
        }

        $this->conn->close();
    }
}