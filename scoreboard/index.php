<?php
$page_start_time = microtime(true);

require('config.php');
require('cookie_session.php');

ob_start();
session_name('session_guard');
session_set_cookie_params(60*60*24*7, '/', $_SERVER['HTTP_HOST'], true, true);
session_set_save_handler(new CookieSession(SECRET, 'session', 60*60*24*3), true);
session_start();

$msg = null;
$error = false;
$db = null;
$name = $_SESSION['name'];

// $notices in config.php

function h($s)
{
    return htmlentities($s, ENT_QUOTES | ENT_HTML5);
}

function valid_username($name)
{
    return (
        strlen($name) > 0 &&
        strlen($name) <= 32 &&
        preg_match('/^[A-Za-z0-9_]+$/', $name) === 1 &&
        stristr($name, 'flag') === false
    );
}

function pathto($path='')
{
    return SCOREBOARD_PATH . '/' . $path;
}

function redirect($path='')
{
    header(sprintf('Location: %s', pathto($path)));
    exit;
}

function get_ip()
{
    // reverse proxy
    return $_SERVER['HTTP_X_REAL_IP'] ?: $_SERVER['REMOTE_ADDR'];
}

function write_log($log_data) {
    file_put_contents(LOG_FILE, json_encode([time(), get_ip(), $log_data])."\n", FILE_APPEND);
}

function notify($type, $message) {
    global $msg;
    $msg = sprintf('<div class="alert alert-%s">%s</div>', $type, $message);
}

function query_task_by_flag($flag)
{
    global $db;

    if(preg_match('/^FLAG{.+}$/', $flag) === 1) {
        $stmt = $db->prepare('SELECT * FROM tasks WHERE flag = :flag');
        $stmt->execute(['flag' => $flag]);
        return $stmt->fetchObject();
    } else {
        return null;
    }
}

function query_record_by_task_id_and_username($task_id, $name)
{
    global $db;
    $stmt = $db->prepare('SELECT * FROM records WHERE task_id = :task_id AND name = :who');
    $stmt->execute(['task_id' => $task_id, 'who' => $name]);
    return $stmt->fetchObject();
}

function solve_by_task_id_and_username($task_id, $name, $ip)
{
    global $db;
    $stmt = $db->prepare('INSERT INTO records
        (id, task_id, name, time, ip)
        VALUES (NULL, :task_id, :who, NOW(), :ip)');
    $stmt->execute([ 'task_id' => $task_id, 'who' => $name, 'ip' => $ip ]);
}

function telegram_solve_notify_by_task_name_and_user($task_name, $name)
{
    $data = [
        'chat_id' => TG_CHAT_ID,
        'text' => sprintf('`%s` solved `%s`', $name, $task_name),
        'parse_mode' => 'markdown'
    ];

    $curl = curl_init();
    $url = sprintf("https://api.telegram.org/bot%s/sendMessage", TG_API_KEY);
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
    ]);
    curl_exec($curl);
}

// process user name

if($_SESSION['admin']) {
    $name = $_REQUEST['name'] ?: $name;
}

// connect to database

$connection_string = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
$db = new PDO($connection_string, DB_USER, DB_PASS);

// process actions

if($_GET['logout'] === 'me') {
    write_log(['logout', $name]);
    session_destroy();
    redirect();
}

if($_GET['declare'] === 'my_name') {
    $name = $_REQUEST['name'];
    if(!valid_username($name)) {
        $error = true;
        $msg = sprintf(
            '<div class="alert alert-danger">Invalid username: %s</div><script>setTimeout(function() { location.href = "%s"; }, 3000);</script>',
            h($name),
            pathto()
        );
        $name = null;
    } elseif($name === ADMIN_USER) {
        if($_REQUEST['password'] === ADMIN_PASS) {
            $_SESSION['name'] = $name;
            $_SESSION['admin'] = true;
            write_log(['admin-login', $name]);
            redirect();
        } else {
            $msg = sprintf(
                '<div class="alert alert-danger">Invalid username: %s</div><script>setTimeout(function() { location.href = "%s"; }, 3000);</script>',
                h($name),
                pathto()
            );
        }
    } else {
        $_SESSION['name'] = $name;
        write_log(['set-name', $name]);
        redirect();
    }
}

if($_GET['capture'] === 'the_flag' && strlen($name) > 0) {
    $flag = $_POST['flag'];

    if(strlen($flag) == 0) {
        redirect();
    }

    $task = query_task_by_flag($flag);

    if($task) {
        if(query_record_by_task_id_and_username($task->id, $name)) {
            $msg = sprintf('<div class="alert alert-warning">Duplicated flag (%s)</div>', $task->name);
            $submit_status = 'duplicated';
        } else {
            solve_by_task_id_and_username($task->id, $name, get_ip());
            $msg = sprintf('<div class="alert alert-success">You solved %s!</div>', $task->name);
            telegram_solve_notify_by_task_name_and_user($task->name, $name);
            $submit_status = 'accepted';
        }
    } else {
        $msg = '<div class="alert alert-danger">Not a flag</div>';
        $submit_status = 'not-flag';
    }
    write_log(['submit-flag', $name, $submit_status, $flag]);
}

// query data for tasks

if(strlen($name) > 0) {
    $stmt = $db->prepare('
        SELECT
            tasks.*,
            records.name as solver,
            COUNT(records_sol.id) AS sol_count
        FROM tasks
        LEFT JOIN records ON records.task_id = tasks.id AND records.name = :who
        LEFT JOIN records AS records_sol ON records_sol.task_id = tasks.id
        GROUP BY tasks.id
        ORDER BY tasks.ordering, tasks.id
    ');

    $stmt->execute(['who' => $name]);
    $tasks = $stmt->fetchAll(PDO::FETCH_CLASS);

    $stmt = $db->prepare('
        SELECT
            SUM(tasks.points) as points
        FROM records
        LEFT JOIN tasks
            ON tasks.id = records.task_id
        WHERE records.name = :who
    ');
    $stmt->execute(['who' => $name]);
    $points = $stmt->fetchObject()->points * POINTS_MULTIPLY;
} else {
    $tasks = $db->query('
        SELECT
            tasks.*,
            COUNT(records.name) AS sol_count
        FROM tasks
        LEFT JOIN records ON tasks.id = records.task_id
        GROUP BY tasks.id
        ORDER BY tasks.ordering, tasks.id
    ', PDO::FETCH_CLASS, 'stdClass');
}

// query data for scoreboard

$rank_limits = 15;
if($_SESSION['admin']) $rank_limits = 100;
$ranks = $db->query(sprintf('
    SELECT
        records.name as name,
        MAX(records.time) as time,
        SUM(tasks.points) as points,
        COUNT(records.name) as count
    FROM records
    LEFT JOIN tasks
        ON tasks.id = records.task_id
    GROUP BY records.name
    ORDER BY points DESC, MAX(time) ASC
    LIMIT %d
', $rank_limits), PDO::FETCH_CLASS, 'stdClass');

$event_limits = 15;
if($_SESSION['admin']) $event_limits = 100;
$events = $db->query(sprintf('
    SELECT
        records.name as user,
        tasks.name as task,
        records.time as time,
        records.ip as ip
    FROM records
    LEFT JOIN tasks
        ON tasks.id = records.task_id
    ORDER BY records.id DESC
    LIMIT 0, %d
', $event_limits), PDO::FETCH_CLASS, 'stdClass');

session_write_close();
ob_end_flush();
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scoreboard</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link rel="shortcut icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="./bootstrap/css/bootstrap.min.css" media="all">
    <style>
        .navbar-form {
            margin: 0;
        }

        p.administrator {
            color: #555;
            font-weight: bold;
        }

        .container > form > .input-group {
            margin-top: 32px;
        }

        .footer {
            height: 60px;
            background-color: #f5f5f5;
        }

        .footer span {
            line-height: 60px;
        }
    </style>
    <!--<script src="/jquery-2.2.4.min.js"></script>
    <script src="/bootstrap/js/bootstrap.min.js"></script>-->
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
                        <a href="https://www.inndy.tw/" target="_blank">Author</a>
                    </li>
<?php if(strlen($name) > 0): ?>
                    <li>
                        <form action="<?=pathto()?>" method="GET">
                            <input type="hidden" name="logout" value="me">
                            <button class="navbar-btn btn btn-danger">Logout</button>
                        </form>
                    </li>

                    <p class="navbar-text">Hi, <?=h($name)?> (<?=$points?:'0'?> pts)</p>
<?php if($_SESSION['admin']): ?>
                    <p class="navbar-text administrator">Administrator</p>
<?php endif; ?>
<?php else: ?>
                    <li>
                        <form action="<?=pathto()?>" method="GET" class="navbar-form navbar-left">
                            <input type="hidden" name="declare" value="my_name">
                            <div class="form-group">
                                <input class="form-control" type="text" name="name" placeholder="Your name ...">
                                <button class="navbar-btn btn btn-info">Login</button>
                            </div>
                        </form>
                    </li>
<?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

<?php if($msg): ?>
    <div class="container">
        <h2>Message</h2>

        <?=$msg?>
    </div>
<?php endif; ?>

    <div class="container">
        <h2>Announcement</h2>

        <ul>
<?php foreach($notices as $text): ?>
            <li><p><?=$text?></p></li>
<?php endforeach; ?>
        </ul>
    </div>

<?php if(strlen($name) > 0): ?>
    <div class="container">
        <h2>Tasks</h2>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Points</th>
                        <th>Solved</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>

<?php           $i = 1;
                foreach($tasks as $task): ?>
                    <tr>
                        <td><?=$i++?></td>
                        <td><?=$task->type?></td>
<?php               if($task->solver): ?>
                        <td>[Solved] <?=$task->name?></td>
<?php               elseif(strlen($task->link)): ?>
                        <td><a href="<?=$task->link?>" target="_blank"><?=$task->name?></a></td>
<?php               else: ?>
                        <td><a href="#" class="no-action"><?=$task->name?></a></td>
<?php               endif; ?>
                        <td><?=$task->points * POINTS_MULTIPLY?></td>
                        <td><?=$task->sol_count?></td>
                        <td><?=$task->text?></td>
                    </tr>
<?php           endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="container">
        <h2>Login for Tasks</h2>
    </div>
<?php endif; ?>

<?php if(strlen($name) > 0): ?>
    <div class="container">
        <h2>Submit Flag</h2>

        <form method="POST" class="form-horizontal" action="<?=pathto('?capture=the_flag')?>" role="form">
            <input type="hidden" name="name" value="<?=h($name)?>">
            <div class="input-group">
                <input class="form-control" type="text" name="flag" placeholder="FLAG{Here is your flag}">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </span>
            </div>
        </form>
    </div>
<?php elseif(0): ?>
    <div class="container">
        <h2>Login</h2>

        <form method="POST" class="form-horizontal" action="<?=pathto('?declare=my_name')?>" role="form">
            <div class="input-group">
                <input class="form-control" type="text" name="name" placeholder="You name">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-primary">Login</button>
                </span>
            </div>
        </form>
    </div>
<?php endif; ?>

    <div class="container">
        <h2>Rank</h2>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Solved</th>
                        <th>Points</th>
                        <th>Last</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; foreach($ranks as $man): $i++; ?><tr>
                        <td><?=$i?></td>
<?php if($_SESSION['admin']): ?>
                        <td><a href="?name=<?=$man->name?>"><?=$man->name?></a></td>
<?php else: ?>
                        <td><?=$man->name?></td>
<?php endif; ?>
                        <td><?=$man->count?></td>
                        <td><?=$man->points * POINTS_MULTIPLY?></td>
                        <td><?=$man->time?></td>
                    </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="container">
        <h2>Events</h2>

        <ul>
        <?php
            foreach($events as $event) {
                if($_SESSION['admin']) {
                    printf(
                        "<li><code>%s</code> solved <code>%s</code> at <code>%s</code> from <code>%s</code></li>\n",
                        $event->user, $event->task, $event->time, $event->ip
                    );
                } else {
                    printf(
                        "<li><code>%s</code> solved <code>%s</code> at <code>%s</code></li>\n",
                        $event->user, $event->task, $event->time
                    );
                }
            }
        ?>
        </ul>
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
