<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Xtream PHP API Console
|--------------------------------------------------------------------------
| Neon Lime UI + Modal Output + Multi Output Modes
|
| Operations:
| login
| category_channels
| channels
| category_vods
| vods
| category_series
| series
| serie
| vod
| epg_simples
| epg
| epgfull
| lists
|
|--------------------------------------------------------------------------
*/

// Xtream Codes Login Config
define("XTREAM_SERVER_HOST", "");
define("XTREAM_USERNAME", "");
define("XTREAM_PASSWORD", "");

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function request_value(string $key, string $default = ""): string
{
    return isset($_REQUEST[$key]) ? trim((string)$_REQUEST[$key]) : $default;
}

function get_data(string $url, int $timeout = 30): array
{
    $ch = curl_init();

    if ($ch === false) {
        return [
            "success" => false,
            "error" => "Unable to initialize cURL."
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => "TRC4-Xtream-PHP-API-Console/3.0",
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => "",
        CURLOPT_HTTPHEADER => [
            "Accept: */*"
        ]
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    curl_close($ch);

    if ($body === false || $errno !== 0) {
        return [
            "success" => false,
            "error" => "cURL error: " . ($error ?: "Unknown cURL error"),
            "http_code" => $httpCode
        ];
    }

    return [
        "success" => true,
        "body" => (string)$body,
        "http_code" => $httpCode,
        "content_type" => $contentType
    ];
}

function build_xtream_url(string $base, string $path, array $params = []): string
{
    $base = rtrim($base, "/");

    $query = http_build_query(
        $params,
        "",
        "&",
        PHP_QUERY_RFC3986
    );

    return $base . "/" . ltrim($path, "/") . ($query !== "" ? "?" . $query : "");
}

function pretty_json(string $data): ?string
{
    $decoded = json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    $encoded = json_encode(
        $decoded,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    return $encoded !== false ? $encoded : null;
}

function parse_m3u(string $data): array
{
    $lines = preg_split("/\r\n|\r|\n/", $data);
    $results = [];
    $current = null;

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === "") {
            continue;
        }

        if (stripos($line, "#EXTINF:") === 0) {
            $current = [
                "name" => "",
                "group" => "",
                "logo" => "",
                "tvg_id" => "",
                "tvg_name" => "",
                "url" => ""
            ];

            if (preg_match('/tvg-id="([^"]*)"/i', $line, $m)) {
                $current['tvg_id'] = $m[1];
            }

            if (preg_match('/tvg-name="([^"]*)"/i', $line, $m)) {
                $current['tvg_name'] = $m[1];
            }

            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $m)) {
                $current['logo'] = $m[1];
            }

            if (preg_match('/group-title="([^"]*)"/i', $line, $m)) {
                $current['group'] = $m[1];
            }

            $parts = explode(',', $line, 2);

            if (isset($parts[1])) {
                $current['name'] = trim($parts[1]);
            }

            continue;
        }

        if ($current !== null && preg_match('~^https?://~i', $line)) {
            $current['url'] = $line;
            $results[] = $current;
            $current = null;
        }
    }

    return $results;
}

function execute_operation(string $action, string $server, string $username, string $password, string $category, string $id): array
{
    $params = [
        'username' => $username,
        'password' => $password
    ];

    switch ($action) {
        case 'login':
            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'category_channels':
            $params['action'] = 'get_live_categories';
            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'channels':
            $params['action'] = 'get_live_streams';

            if ($category !== '' && (int)$category > 0) {
                $params['category_id'] = (int)$category;
            }

            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'category_vods':
            $params['action'] = 'get_vod_categories';
            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'vods':
            $params['action'] = 'get_vod_streams';

            if ($category !== '' && (int)$category > 0) {
                $params['category_id'] = (int)$category;
            }

            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'category_series':
            $params['action'] = 'get_series_categories';
            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'series':
            $params['action'] = 'get_series';

            if ($category !== '' && (int)$category > 0) {
                $params['category_id'] = (int)$category;
            }

            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'serie':
            if ($id === '') {
                return [
                    'success' => false,
                    'error' => 'Series ID is Required.'
                ];
            }

            $params['action'] = 'get_series_info';
            $params['series_id'] = $id;

            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'vod':
            if ($id === '') {
                return [
                    'success' => false,
                    'error' => 'VOD ID is Required.'
                ];
            }

            $params['action'] = 'get_vod_info';
            $params['vod_id'] = $id;

            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'epg_simples':
            if ($id === '') {
                return [
                    'success' => false,
                    'error' => 'Stream ID is Required.'
                ];
            }

            $params['action'] = 'get_short_epg';
            $params['stream_id'] = $id;

            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'epg':
            if ($id === '') {
                return [
                    'success' => false,
                    'error' => 'Stream ID is Required.'
                ];
            }

            $params['action'] = 'get_simple_data_table';
            $params['stream_id'] = $id;

            $url = build_xtream_url($server, 'player_api.php', $params);
            break;

        case 'epgfull':
            $url = build_xtream_url($server, 'xmltv.php', $params);
            break;

        case 'lists':
            $params['type'] = 'm3u_plus';
            $params['output'] = 'm3u8';

            $url = build_xtream_url($server, 'get.php', $params);
            break;

        default:
            return [
                'success' => false,
                'error' => 'Unknown operation.'
            ];
    }

    $result = get_data($url);

    if (!$result['success']) {
        return $result;
    }

    $body = $result['body'];

    if ($action === 'lists') {
        $parsed = parse_m3u($body);

        return [
            'success' => true,
            'body' => $body,
            'data' => $parsed,
            'http_code' => $result['http_code'],
            'content_type' => $result['content_type'],
            'type' => 'm3u'
        ];
    }

    $decoded = json_decode($body, true);

    return [
        'success' => true,
        'body' => $body,
        'data' => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
        'http_code' => $result['http_code'],
        'content_type' => $result['content_type'],
        'type' => 'json'
    ];
}

function format_output(array $result, string $mode, string $action): string
{
    if (!$result['success']) {
        return json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $body = (string)($result['body'] ?? '');

    if ($mode === 'raw') {
        return $body;
    }

    if ($mode === 'compact') {
        if (isset($result['data'])) {
            $json = json_encode(
                $result['data'],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
				JSON_PRETTY_PRINT
            );

            if ($json !== false) {
                return $json;
            }
        }

        return $body;
    }

    if ($mode === 'pretty') {
        if ($action === 'lists' && isset($result['data'])) {
            $json = json_encode(
                $result['data'],
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            return $json !== false ? $json : $body;
        }

        $pretty = pretty_json($body);

        return $pretty !== null ? $pretty : $body;
    }

    if ($mode === 'auto') {
        if ($action === 'epgfull') {
            return $body;
        }

        if ($action === 'lists' && isset($result['data'])) {
            $json = json_encode(
                $result['data'],
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            return $json !== false ? $json : $body;
        }

        $pretty = pretty_json($body);

        return $pretty !== null ? $pretty : $body;
    }

    return $body;
}

$isApiRequest = isset($_REQUEST['action']) && request_value('action') !== '';

if ($isApiRequest) {
    header('Content-Type: application/json; charset=utf-8');

    $action = request_value('action');
    $username = request_value('username');
    $password = request_value('password');
    $category = request_value('category');
    $id = request_value('id');
    $server = request_value('server', XTREAM_SERVER_HOST);
    $mode = request_value('output', 'pretty');

    if ($username === '' || $password === '') {
        echo json_encode([
            'success' => false,
            'error' => 'Username and password are required.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    if (!filter_var($server, FILTER_VALIDATE_URL)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid server URL.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    $result = execute_operation(
        $action,
        $server,
        $username,
        $password,
        $category,
        $id
    );

    $output = format_output($result, $mode, $action);

    if ($mode === 'raw' && $action === 'epgfull') {
        header('Content-Type: application/xml; charset=utf-8');
    }

    echo $output;
    exit;
}

$operations = [
    'login' => 'Login / Account Info',
    'category_channels' => 'Live Categories',
    'channels' => 'Live Channels',
    'category_vods' => 'VOD Categories',
    'vods' => 'VOD Streams',
    'category_series' => 'Series Categories',
    'series' => 'Series',
    'serie' => 'Series Info',
    'vod' => 'VOD Info',
    'epg_simples' => 'Short EPG',
    'epg' => 'EPG Data Table',
    'epgfull' => 'Full XMLTV EPG',
    'lists' => 'M3U → JSON'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Xtream API Console</title>
<link rel="shortcut icon" href="https://kodi.al/favicon.ico"/>
<style>
:root{
    --bg:#050805;
    --panel:#091009;
    --panel2:#0c140d;
    --lime:#b7ff00;
    --lime2:#76ff03;
    --text:#eaffd1;
    --muted:#7e9275;
    --border:rgba(183,255,0,.20);
    --danger:#ff405d;
    --warning:#ffd447;
    --shadow:0 0 30px rgba(183,255,0,.08);
}

*{box-sizing:border-box}

html,body{
    margin:0;
    min-height:100%;
    background:
        radial-gradient(circle at 50% -20%,rgba(183,255,0,.10),transparent 40%),
        linear-gradient(135deg,#030503,#071007 50%,#030503);
    color:var(--text);
    font-family:Inter,Segoe UI,Arial,sans-serif;
}

body{
    padding:30px 15px;
}

.container{
    width:min(1100px,100%);
    margin:auto;
}

.header{
    border:1px solid var(--border);
    background:linear-gradient(145deg,rgba(12,20,13,.96),rgba(4,9,5,.96));
    border-radius:24px;
    padding:26px;
    box-shadow:var(--shadow);
    position:relative;
    overflow:hidden;
    margin-bottom:20px;
}

.header:before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(90deg,transparent,rgba(183,255,0,.06),transparent);
    transform:translateX(-100%);
    animation:scan 5s linear infinite;
}

@keyframes scan{
    to{transform:translateX(100%)}
}

.brand{
    position:relative;
    z-index:1;
}

.brand h1{
    margin:0;
    color:var(--lime);
    font-size:clamp(25px,5vw,42px);
    letter-spacing:-1px;
    text-shadow:0 0 18px rgba(183,255,0,.28);
}

.brand p{
    margin:8px 0 0;
    color:var(--muted);
    font-size:14px;
}

.badges{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:18px;
}

.badge{
    padding:7px 11px;
    border:1px solid var(--border);
    border-radius:999px;
    color:var(--lime);
    background:rgba(183,255,0,.04);
    font-size:11px;
    font-weight:700;
    letter-spacing:.8px;
}

.panel{
    background:rgba(7,13,8,.95);
    border:1px solid var(--border);
    border-radius:22px;
    padding:22px;
    box-shadow:var(--shadow);
	color:var(--lime2);
}

.grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:15px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:7px;
	color:var(--lime2);
}

.field.full{
    grid-column:1/-1;
}

label{
    /* color:#a8bc9d; */
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:1px;
	color:var(--lime2);
}

input,select{
    font-size:11px;
    font-weight:800;
    width:100%;
    height:48px;
    border:1px solid rgba(183,255,0,.18);
    border-radius:13px;
    outline:none;
    background:
        linear-gradient(rgba(183,255,0,.018) 1px,transparent 1px),
        linear-gradient(90deg,rgba(183,255,0,.018) 1px,transparent 1px),
        #030603;
    /* background:#050905; */
    /* color:var(--text); */
	color:var(--lime);
    padding:0 14px;
    transition:.2s;
}

input:focus,select:focus{
    border-color:var(--lime);
    box-shadow:0 0 0 3px rgba(183,255,0,.07),0 0 20px rgba(183,255,0,.08);
    background:
        linear-gradient(rgba(183,255,0,.018) 1px,transparent 1px),
        linear-gradient(90deg,rgba(183,255,0,.018) 1px,transparent 1px),
        #030603;
}

select option{
    background:#071007;
}

.password-wrap{
    position:relative;
    background:
        linear-gradient(rgba(183,255,0,.018) 1px,transparent 1px),
        linear-gradient(90deg,rgba(183,255,0,.018) 1px,transparent 1px),
        #030603;
}

.password-wrap input{
    padding-right:48px;
}

.toggle-pass{
    position:absolute;
    right:8px;
    top:8px;
    width:32px;
    height:32px;
    border:0;
    border-radius:9px;
    background:rgba(183,255,0,.07);
    color:var(--lime);
    cursor:pointer;
}

.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:18px;
}

button{
    border:0;
    cursor:pointer;
    transition:.2s;
}

.btn{
    min-height:48px;
    border-radius:13px;
    padding:0 18px;
    font-weight:900;
    letter-spacing:.5px;
}

.btn-primary{
    color:#071000;
    background:linear-gradient(135deg,#d7ff4a,#8cff00);
    box-shadow:0 0 22px rgba(183,255,0,.16);
}

.btn-primary:hover{
    transform:translateY(-1px);
    box-shadow:0 0 30px rgba(183,255,0,.28);
}

.btn-secondary{
    color:var(--lime);
    background:rgba(183,255,0,.05);
    border:1px solid var(--border);
}

.btn-secondary:hover{
    background:rgba(183,255,0,.10);
}

.operation-info{
    margin-top:15px;
    border:1px solid rgba(183,255,0,.12);
    background:rgba(183,255,0,.025);
    border-radius:13px;
    padding:12px 14px;
    color:var(--muted);
    font-size:12px;
}

.operation-info strong{
    color:var(--lime);
}

.status{
    display:none;
    margin-top:18px;
    padding:13px 15px;
    border-radius:13px;
    border:1px solid var(--border);
    background:rgba(183,255,0,.04);
    color:var(--lime);
    font-size:12px;
}

.status.show{
    display:block;
}

.status.error{
    color:#ff7184;
    border-color:rgba(255,64,93,.3);
    background:rgba(255,64,93,.05);
}

.modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:18px;
    background:rgba(0,0,0,.82);
    backdrop-filter:blur(12px);
}

.modal.show{
    display:flex;
}

.modal-box{
    width:min(1100px,100%);
    max-height:90vh;
    display:flex;
    flex-direction:column;
    border:1px solid rgba(183,255,0,.30);
    border-radius:22px;
    overflow:hidden;
    background:#050905;
    box-shadow:0 0 70px rgba(183,255,0,.13);
}

.modal-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:15px 18px;
    border-bottom:1px solid rgba(183,255,0,.13);
}

.modal-title{
    color:var(--lime);
    font-size:14px;
    font-weight:900;
    letter-spacing:1px;
}

.modal-actions{
    display:flex;
    gap:7px;
}

.icon-btn{
    width:36px;
    height:36px;
    border:1px solid var(--border);
    border-radius:10px;
    background:rgba(183,255,0,.04);
    color:var(--lime);
    font-weight:900;
}

.icon-btn:hover{
    background:rgba(183,255,0,.10);
}

.output-meta{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    padding:12px 18px;
    border-bottom:1px solid rgba(183,255,0,.08);
}

.meta{
    font-size:10px;
    color:var(--muted);
    border:1px solid rgba(183,255,0,.12);
    border-radius:999px;
    padding:6px 9px;
}

.meta b{
    color:var(--lime);
}

.output{
    margin:0;
    padding:20px;
    overflow:auto;
    flex:1;
    min-height:300px;
    max-height:65vh;
    color:#d9ffba;
    background:
        linear-gradient(rgba(183,255,0,.018) 1px,transparent 1px),
        linear-gradient(90deg,rgba(183,255,0,.018) 1px,transparent 1px),
        #030603;
    background-size:20px 20px;
    font:12px/1.65 Consolas,Monaco,monospace;
    white-space:pre;
    tab-size:4;
}

.footer{
    text-align:center;
    color:#53624e;
    font-size:10px;
    margin-top:15px;
}

@media(max-width:700px){
    body{padding:15px 10px}
    .grid{grid-template-columns:1fr}
    .field.full{grid-column:auto}
    .panel,.header{padding:17px}
}
</style>
</head>
<body>

<div class="container">

    <div class="header">
        <div class="brand">
            <h1>Xtream Codes API Interface</h1>
            <p>Advanced Xtream Codes API Explorer • Interface</p>

            <div class="badges">
                <span class="badge">Xtream Codes API</span>
                <span class="badge">cURL</span>
                <span class="badge">Multi Output</span>
                <span class="badge">JSON Formatter</span>
            </div>
        </div>
    </div>

    <div class="panel">
        <form id="apiForm">
            <div class="grid">

                <div class="field full">
                    <label>Panel Server Host</label>
                   <input type="url" name="server" id="server" value="<?= h(XTREAM_SERVER_HOST) ?>" placeholder="http://server:port" autocomplete="off" required>
                </div>

                <div class="field">
                    <label>Username</label>
                    <input type="text" name="username" id="username" placeholder="Enter Username" autocomplete="off" value="<?= h(XTREAM_USERNAME) ?>" required>
                </div>

                <div class="field">
                    <label>Password</label>
                    <div class="password-wrap">
                        <input type="text" name="password" id="password" placeholder="Enter Password" autocomplete="off" value="<?= h(XTREAM_PASSWORD) ?>" required>
                        <button type="button" class="toggle-pass" id="togglePass">◉</button>
                    </div>
                </div>

                <div class="field">
                    <label>Operation</label>
                    <select name="action" id="action">
                        <?php foreach ($operations as $value => $label): ?>
                            <option value="<?= h($value) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Output Mode</label>
                    <select name="output" id="output">
                        <option value="pretty" selected>Pretty JSON</option>
                        <option value="compact">Compact JSON</option>
                        <option value="raw">RAW Response</option>
                        <option value="auto">AUTO</option>
                    </select>
                </div>

                <div class="field">
                    <label>Category ID</label>
                    <input type="text" name="category" id="category" placeholder="Optional" autocomplete="off">
                </div>

                <div class="field">
                    <label>ID / Stream ID</label>
                    <input type="text" name="id" id="id" placeholder="Optional" autocomplete="off">
                </div>

            </div>

            <div class="operation-info" id="operationInfo">
                Select an operation to see its parameters.
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary" id="runBtn">
                    ▶ RUN API
                </button>
                <button type="button" class="btn btn-secondary" id="clearBtn">
                    CLEAR
                </button>
            </div>
            <div class="status" id="status"></div>
        </form>
    </div>

    <div class="footer">
        XTREAM PHP API CONSOLE • MULTI MODE OUTPUT
    </div>

</div>

<div class="modal" id="outputModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title" id="modalTitle">
                API RESPONSE
            </div>

            <div class="modal-actions">
                <button class="icon-btn" type="button" id="copyBtn" title="Copy">⧉</button>
                <button class="icon-btn" type="button" id="closeBtn" title="Close">×</button>
            </div>

        </div>

        <div class="output-meta">
            <div class="meta">Action: <b id="metaOp">-</b></div>
            <div class="meta">HTTP: <b id="metaHttp">-</b></div>
            <div class="meta">MODE: <b id="metaMode">-</b></div>
            <div class="meta">SIZE: <b id="metaSize">-</b></div>
        </div>
        <pre class="output" id="outputBox"></pre>
    </div>
</div>

<script>
const form = document.getElementById('apiForm');
const runBtn = document.getElementById('runBtn');
const clearBtn = document.getElementById('clearBtn');
const statusBox = document.getElementById('status');
const modal = document.getElementById('outputModal');
const outputBox = document.getElementById('outputBox');
const copyBtn = document.getElementById('copyBtn');
const closeBtn = document.getElementById('closeBtn');
const togglePass = document.getElementById('togglePass');
const password = document.getElementById('password');
const action = document.getElementById('action');
const category = document.getElementById('category');
const id = document.getElementById('id');
const operationInfo = document.getElementById('operationInfo');

const operationHints = {
    login: 'No Extra ID Required, Returns Xtream Account/Server information',
    category_channels: 'No ID Required, Returns All Live TV Categories',
    channels: 'Category ID is Optional, Leave Empty or Use 0 For All Channels',
    category_vods: 'No ID Required, Returns All VOD Categories',
    vods: 'Category ID is Optional, Leave Empty or Use 0 For All VOD Streams',
    category_series: 'No ID Required, Returns All Series Categories',
    series: 'Category ID is Optional, Leave Empty or Use 0 For All Series',
    serie: 'Series ID is Required',
    vod: 'VOD ID is Required',
    epg_simples: 'Stream ID is Required. Returns Short EPG',
    epg: 'Stream ID is Required. Returns Simple EPG Data Table',
    epgfull: 'No ID Required. Returns Complete XMLTV Data',
    lists: 'No ID Required. Downloads/Parses the M3U Plus Playlist into JSON'
};

function updateHint() {
    const selected = action.value;
    operationInfo.innerHTML = '<strong>' + selected + '</strong> • ' + (operationHints[selected] || 'Ready.');
    category.disabled = !['channels', 'vods', 'series'].includes(selected);
    id.disabled = !['serie', 'vod', 'epg_simples', 'epg'].includes(selected);

    if (category.disabled) category.value = '';
    if (id.disabled) id.value = '';
}

action.addEventListener('change', updateHint);
updateHint();

togglePass.addEventListener('click', () => {
    password.type = password.type === 'password' ? 'text' : 'password';
    togglePass.textContent = password.type === 'password' ? '◉' : '●';
});

function setStatus(message, error = false) {
    statusBox.textContent = message;
    statusBox.classList.add('show');
    statusBox.classList.toggle('error', error);
}

function openModal(title, data, httpCode, mode, opName) {
    outputBox.textContent = data;
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('metaOp').textContent = opName;
    document.getElementById('metaHttp').textContent = httpCode || '-';
    document.getElementById('metaMode').textContent = mode;
    document.getElementById('metaSize').textContent = new Blob([data]).size + ' bytes';
    modal.classList.add('show');
}

function closeModal() {
    modal.classList.remove('show');
}

closeBtn.addEventListener('click', closeModal);

modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

copyBtn.addEventListener('click', async () => {
    const text = outputBox.textContent;

    try {
        await navigator.clipboard.writeText(text);
        copyBtn.textContent = '✓';

        setTimeout(() => {
            copyBtn.textContent = '⧉';
        }, 1200);

    } catch (error) {
        copyBtn.textContent = '!';
    }
});

clearBtn.addEventListener('click', () => {
    document.getElementById('username').value = '';
    password.value = '';
    category.value = '';
    id.value = '';
    outputBox.textContent = '';
    statusBox.className = 'status';
    closeModal();
});

form.addEventListener('submit', async e => {
    e.preventDefault();

    if (runBtn.disabled) return;

    const formData = new FormData(form);
    const selectedOp = action.value;
    const selectedMode = document.getElementById('output').value;

    runBtn.disabled = true;
    runBtn.textContent = '⟳ EXECUTING...';
    setStatus('Connecting to Xtream Server...');

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            cache: 'no-store'
        });

        const text = await response.text();

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        setStatus('Request completed successfully.');

        openModal(
            'API RESPONSE • ' + selectedOp.toUpperCase(),
            text,
            response.status,
            selectedMode,
            selectedOp
        );

    } catch (error) {

        setStatus(
            'REQUEST ERROR • ' + (error.message || 'Unknown error'),
            true
        );

        openModal(
            'API ERROR',
            JSON.stringify({
                success: false,
                error: error.message || 'Unknown error'
            }, null, 4),
            '-',
            selectedMode,
            selectedOp
        );

    } finally {
        runBtn.disabled = false;
        runBtn.textContent = '▶ RUN API';
    }
});
</script>
</body>
</html>