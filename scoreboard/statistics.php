<?php
$page_start_time = microtime(true);

require('config.php');

// connect to database

$connection_string = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
$db = new PDO($connection_string, DB_USER, DB_PASS);

function pathto($path='')
{
    return SCOREBOARD_PATH . '/' . $path;
}

function sqlexec($sql)
{
    global $db;
    return $db->query($sql);
}

function sqlselect($sql)
{
    $res = sqlexec($sql);
    return $res ? $res->fetch()[0] : false;
}

function sqlcount($sql)
{
    $res = sqlexec($sql);
    return $res ? $res->rowCount() : false;
}

function timeto($time)
{
    $sec = time() - $time;

    $min = (int)($sec / 60);
    $sec = $sec % 60;

    $hour = (int)($min / 60);
    $min = $min % 60;

    $day = (int)($hour / 24);
    $hour = $hour % 24;

    if($day > 0) return sprintf("%d days %d hours", $day, $hour);
    elseif($hour > 0) return sprintf("%d hours %d min ago", $hour, $min);
    elseif($min > 0) return sprintf("%d min %d sec ago", $min, $sec);
    elseif($sec > 30) return sprintf("%d sec ago", $sec);
    else return "a few seconds ago";
}

function closure($f)
{
    return $f();
}

$GLOBALS['AKPts'] = sqlselect('SELECT SUM(`points`) FROM `tasks`') * POINTS_MULTIPLY;

$statistics = [
    [
        'Task Count',
        sqlcount('SELECT `id` FROM `tasks`')
    ],
    [
        'Success Submit Count',
        sqlcount('SELECT `id` FROM `records`')
    ],
    [
        'Unique User Count',
        sqlcount('SELECT DISTINCT `name` FROM `records`')
    ],
    [
        'Unique Submit IP Count',
        sqlcount('SELECT DISTINCT `ip` FROM `records`')
    ],
    [
        'Last Success Submit Time',
        timeto(strtotime(sqlselect('SELECT MAX(`time`) FROM `records`')))
    ],
    [
        'All Kill Points',
        $AKPts
    ],
    [
        'Top Player',
        closure(function () use ($AKPts) {
            $player = sqlselect('SELECT `name` FROM `rank_view`');
            $pts = sqlselect('SELECT MAX(`points`) FROM `rank_view`') * POINTS_MULTIPLY;
            $last_time = sqlselect('SELECT `last_time` FROM `rank_view`');
            return sprintf("<code>%s</code> %dpts (%d%%), Last success submit time: %s", $player, $pts, $pts * 100 / $AKPts, $last_time);
        })
    ],
    [
        'Top Pwn-er',
        sqlselect('
            SELECT GROUP_CONCAT(CONCAT("<code>", `name`, " (", `points`, "pts, ", `count` , ")</code>") SEPARATOR ", ")
            FROM (
                SELECT
                    `records`.`name` AS `name`,
                    MAX(`records`.`time`) AS `last_time`,
                    SUM(`tasks`.`points`) AS `points`,
                    COUNT(`records`.`name`) AS `count`
                FROM `records`
                LEFT JOIN `tasks`
                    ON `tasks`.`id` = `records`.`task_id`
                WHERE `tasks`.`type` = "Pwn"
                GROUP BY `records`.`name`
                ORDER BY
                    `points` DESC,
                    `last_time`
                LIMIT 5
            ) AS `tmp`
        ')
    ],
    [
        'Top Web-er',
        sqlselect('
            SELECT GROUP_CONCAT(CONCAT("<code>", `name`, " (", `points`, "pts, ", `count` , ")</code>") SEPARATOR ", ")
            FROM (
                SELECT
                    `records`.`name` AS `name`,
                    MAX(`records`.`time`) AS `last_time`,
                    SUM(`tasks`.`points`) AS `points`,
                    COUNT(`records`.`name`) AS `count`
                FROM `records`
                LEFT JOIN `tasks`
                    ON `tasks`.`id` = `records`.`task_id`
                WHERE `tasks`.`type` = "Web"
                GROUP BY `records`.`name`
                ORDER BY
                    `points` DESC,
                    `last_time`
                LIMIT 5
            ) AS `tmp`
        ')
    ],
    [
        'Top Reverser',
        sqlselect('
            SELECT GROUP_CONCAT(CONCAT("<code>", `name`, " (", `points`, "pts, ", `count` , ")</code>") SEPARATOR ", ")
            FROM (
                SELECT
                    `records`.`name` AS `name`,
                    MAX(`records`.`time`) AS `last_time`,
                    SUM(`tasks`.`points`) AS `points`,
                    COUNT(`records`.`name`) AS `count`
                FROM `records`
                LEFT JOIN `tasks`
                    ON `tasks`.`id` = `records`.`task_id`
                WHERE `tasks`.`type` = "Reversing"
                GROUP BY `records`.`name`
                ORDER BY
                    `points` DESC,
                    `last_time`
                LIMIT 5
            ) AS `tmp`
        ')
    ],
    [
        'Least Success Submit Tasks',
        sqlselect('SELECT GROUP_CONCAT(CONCAT("<code>", `challenge`, "</code>") SEPARATOR ", ") FROM (
                       SELECT
                       `challenge`, COUNT(`challenge`) as `count`
                       FROM `records_view`
                       GROUP BY `challenge`
                       ORDER BY `count` ASC, MAX(`time`) DESC
                       LIMIT 5
                   ) AS `tmptable`')
    ],
    [
        'No Submussion',
        sqlselect('SELECT GROUP_CONCAT(CONCAT("<code>", `challenge`, "</code>") SEPARATOR ", ") FROM (
            SELECT `name` as `challenge`, `solved_count` FROM `tasks_view` WHERE `solved_count` = 0
        ) AS `tmptable`')
    ]
];
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scoreboard Statistics</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link rel="shortcut icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="./bootstrap/css/bootstrap.min.css" media="all">
    <style>
        .navbar-form {
            margin: 0;
        }

        .footer {
            height: 60px;
            background-color: #f5f5f5;
        }

        .footer span {
            line-height: 60px;
        }
    </style>
</head>
<body>
    <div class="jumbotron">
        <div class="container">
            <h1>Scoreboard</h1>
        </div>
    </div>

    <div class="container">
        <div class="navbar">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" href="/">Please Hack Me</a>
                </div>
                <ul class="nav navbar-nav">
                    <li>
                        <a href="<?=pathto()?>">Scoreboard</a>
                    </li>
                    <li>
                        <a href="https://telegram.me/joinchat/A4vRIj-Ij-OMbpaLitQKCg">Offical Chat Room</a>
                    </li>
                    <li>
                        <a href="https://www.inndy.tw/" target="_blank">Author</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container">
        <h2>Statistics</h2>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach($statistics as $i => $value): ?>
                    <tr>
                        <td><?=1+$i?></td>
                        <td><?=$value[0]?></td>
                        <td><?=$value[1]?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <span class="text-muted">
                Copyright &copy; 2017 Inndy Lin, MIT licesned.
                Source code available on <a href="https://github.com/inndy/scoreboard">GitHub</a>.
                Rendered in <?=sprintf("%3.4fms", (microtime(true) - $page_start_time) * 1000)?>.
            </span>
        </div>
    </footer>

    <script>
        Array.prototype.slice.call(
            document.querySelectorAll('.no-action, a[href=""], a[href="#"]')
        ).map(function (e) {
            e.onclick = function () { return false; };
        });
    </script>
</body>
</html>
