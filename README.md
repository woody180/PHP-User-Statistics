## PHP User Statistics.

Shows user ip, browser, location, today, yesterday and monthly users, device and engine.

Provide location name for time zone inside configuration as it shown in example below.
See how to provide your location time zone correctly here - https://www.php.net/manual/en/timezones.php


## Configure and initialize
Automatically create two database tables.

-- analytics_users
-- analytics_users_today

```
require_once 'analytics/Analytics.php';
$analytics = new Analytics([
    'host' => 'localhost',
    'dbname' => 'analytics',
    'user' => 'root',
    'password' => '',
],
"Asia/Tbilisi");


$analytics->init();
```