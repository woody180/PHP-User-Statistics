## PHP User Statistics. Shows user ip, browser, location, today, yesterday and monthly users, device and engine.


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