<?php
$selfFileName = substr($_SERVER['PHP_SELF'], strrpos($_SERVER['PHP_SELF'], '/')+1);
$selfBaseUrl = substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == $selfBaseUrl.'/ping') die('pong'); //for testing .htaccess functionality

define('LOADING_GIF', "data:image/gif;base64,R0lGODlhEAAQALMNAD8/P7+/vyoqKlVVVX9/fxUVFUBAQGBgYMDAwC8vL5CQkP///wAAAP///wAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQJAAANACw".
                      "AAAAAEAAQAAAEPbDJSau9OOvNew0AEHDA8wCkiW6g6AXHMU4LgizUYRgEZdsUggFAQCgUP+AERggcFYHaDaMgEBQchBNhiQAAIfkECQAADQAsAAAAABAAEAAABDuwyUmrvTYAEDAFzwN4".
                      "EyiSksaRyHF06GEYBNoQ82EHBwHbCIUCYRMKiwSCYoFALDCIwLDZBFJtTKclAgAh+QQJAAANACwAAAAAEAAQAAAEPrDJGQAIM2vwHtAUcVTdBzaHYRCKip2EepxacBAvjSgKQmc83m+iI".
                      "LCGEkSgh5wsEIhFEwqdUpvPaHPLnUQAACH5BAkAAA0ALAAAAAAQABAAAAQ+sMkZyAkz62MM0ROiKAjRXQCATeOIHEQAPA+QKQShZHOdIQFSRqaSLBCIBQiERC41TcQzc0xOr9isdsvtPi".
                      "MAIfkECQAADQAsAAAAABAAEAAABD2wyYmUQjNra/VcCLIoBKEExBFkYRtcBGAQbJsdhnFkoMimGI8wAACshBnA4wFAJpdNp4RolFqv2Kx2q4kAACH5BAkAAA0ALAAAAAAQABAAAAQ9sMm".
                      "5EFoza2u1b5ylKMjXVFdAjGamrEo7IWMpz8QR3A0BGATewWA48BA5mykAAOxugMcDwItOeUwnb9uKAAAh+QQJAAANACwAAAAAEAAQAAAEO7DJSau92C6EVp4c90khMjZbd5KKYo4B0Z4K".
                      "IZ9I4H7IQQSng8FwwAQAgJgBQMAAHo+kD3h5Rk/HpCUCACH5BAkAAA0ALAAAAAAQABAAAAQ8sMlJq7046827nwuCLJwoliYXjlIAAAGFKApCAc8DULQSTzgd4kCYEQgKigt2MBgOC5rtQ".
                      "nAeOAHilBIBADs=");

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    if (error_reporting() == 0) return; // error supressed with @foo()
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
error_reporting(E_ALL);
set_error_handler("exception_error_handler");
set_time_limit(120);

if (php_sapi_name() == 'cli') {
    throw new Exception("Please open Downloader Script in browser");
}

if (isset($_REQUEST['httpBackend'])) {
    //httpBackend can be passed (which is actually just a cache) as firewall blocks timeout which is very slow
    $httpBackend = $_REQUEST['httpBackend'];
} else {
    $httpBackend = 'none';
    exec('wget --version', $out, $ret);
    if (!$ret) {
        //wget exists
        if (trim(shell_exec('wget -O - '.escapeshellarg('https://api.github.com/')))) {
            //wget works
            $httpBackend = 'wget';
        }
    }
    if ($httpBackend == 'none') {
        $context = stream_context_create(array('http' => array('timeout' => 5)));
        if (trim(@file_get_contents('https://api.github.com/', false, $context))) {
            //file_get_contents works
            $httpBackend = 'php';
        }
    }
    //TODO probably implement curl (from cli), curl (php module), fsockopen and others
}
define('HTTP_BACKEND', $httpBackend);


$steps = array(
    'welcome' => 'StepWelcome',
    'check' => 'StepCheck',
    'downloadApp' => 'StepDownloadApp',
    'extractApp' => 'StepExtractApp',
    'downloadKwf' => 'StepDownloadKwf',
    'downloadLibrary' => 'StepDownloadLibrary',
    'extractKwf' => 'StepExtractKwf',
    'extractLibrary' => 'StepExtractLibrary',
    'moveApp' => 'StepMoveApp',
);

$step = isset($_GET['step']) ? $_GET['step'] : 'welcome';
if (!isset($steps[$step])) throw new Exception("Invalid step");
$stepClass = $steps[$step];
$stepNum = array_search($step, array_keys($steps));
$step = new $stepClass();
echo "<div style=\"width: 800px; margin: 0 auto;\">\n";
echo "<h1>Koala Framework Downloader</h1>";
echo "<h2>$step->name [".($stepNum+1).'/'.count($steps)."]</h2>\n";
$step->execute();

$stepKeys = array_keys($steps);
if ($step->getShowNextStep() && isset($stepKeys[$stepNum+1])) {
    $nextStep = $stepKeys[$stepNum+1];
    $s = new $steps[$nextStep];
    $onClick = "this.innerHTML = '&nbsp;&nbsp;&nbsp;&nbsp;'; this.style.backgroundImage = 'url(".LOADING_GIF.")';";
    $url = $_SERVER['PHP_SELF']."?step=".$nextStep."&httpBackend=".$httpBackend;
    echo "<p><a href=\"$url\" onclick=\"$onClick\" style=\"background-repeat: no-repeat;\">Next Step: ".$s->name."</a></p>";
}
echo "</div>\n";



//interesting way to do a http request, but file_get_contents might be blocked or something
function httpRequestGet($url)
{
    if (HTTP_BACKEND == 'wget') {
        return shell_exec('wget -O - '.escapeshellarg($url));
    } else if (HTTP_BACKEND == 'php') {
        return file_get_contents($url);
    } else {
        throw new Exception("unknown backend");
    }
}

abstract class Step
{
    abstract public function execute();
    public function getShowNextStep() { return true; }
}

class StepWelcome extends Step
{
    public $name = 'Welcome';
    public function execute()
    {
        echo "<p>Welcome to the Koala Framework Downloader</p>";
        echo "<p>This script will download a koala framework application plus the required libraries to run.</p>";
        echo "<p>The recommended way is to use git on the server, but if you don't have shell access or git is not installed this downloader can be helpful.</p>";
    }
}
class StepCheck extends Step
{
    public $name = 'Check Server Configuration';

    private $_foundError = false;
    private function _printError($msg)
    {
        echo "<p style=\"color: red;\">".htmlspecialchars($msg)."</p>";
        $this->_foundError = true;
    }

    public function getShowNextStep()
    {
        return !$this->_foundError;
    }

    public function execute()
    {
        global $selfFileName, $selfBaseUrl;

        //test required executables
        exec('ls', $out, $ret);
        if ($ret) {
            $this->_printError("can't execute system commands");
        }

        exec('tar --version', $out, $ret);
        if ($ret) {
            $this->_printError("can't find tar executable");
        }

        //test permissions
        if (!is_writeable('.')) {
            $this->_printError("Downloader script needs write permissions to current folder");
        }

        //test .htaccess functionality
        $htAccessTestContents = "RewriteEngine on
        RewriteCond %{REQUEST_URI} !^/*($selfFileName)/?
        RewriteRule ^(.*)$ $selfFileName [L]
        ";
        if (file_exists('.htaccess') && file_get_contents('.htaccess') != $htAccessTestContents) {
            $this->_printError("There exists already a .htaccess in the current folder");
        }
        file_put_contents('.htaccess', $htAccessTestContents);

        $url = 'http://'.$_SERVER['HTTP_HOST'].$selfBaseUrl.'/ping';
        if (HTTP_BACKEND == 'none') {
            //with 'none' httpBackend we still might can access ourselves (as no firewall blocks)
            //this eventually still fails because of allow_url_fopen=Off
            //TODO try other alternatives, fsockopen and friends
            $pingResponse = trim(file_get_contents($url));
        } else {
            $pingResponse = trim(httpRequestGet($url));
        }
        unlink('.htaccess');

        if ($pingResponse != 'pong') {
            $this->_printError(".htaccess broken");
        }

        if (HTTP_BACKEND == 'none') {
            echo "<p><strong>WARNING</strong> The downloader script can't download files as requests are not allowed/get blocked. You can still use this tool by manually uploading the required files onto your server using eg. ftp</p>\n";
        }
        if (!$this->_foundError) {
            echo "<p>All checks required for the downloader passed.</p>\n";
            echo "<p style=\"font-style:italic;\">Note: this doesn't include all requirements needed by kwf.</p>\n";
        }
    }
}

abstract class StepDownload extends Step
{
    protected $_url;
    protected $_target;
    public function execute()
    {
        if (isset($_REQUEST['downloadUrl'])) {
            $url = $_REQUEST['downloadUrl'];
            if (HTTP_BACKEND == 'wget') {
                exec("wget -O ".escapeshellarg($this->_target)." ".escapeshellarg($url), $out, $ret);
                if ($ret) {
                    echo "<p>Download failed</p>";
                }
            } else if (HTTP_BACKEND == 'php') {
                $fpr = fopen($url, 'r');
                $fpw = fopen($this->_target, 'w');
                while(!feof($fpr)) {
                    fwrite($fpw, fread($fpr, 1024));
                }
                fclose($fpr);
                fclose($fpw);
            } else {
            }
            echo "<p>Successfully Downloaded: $this->_target</p>";
        }
        if (!file_exists($this->_target)) {
            if (HTTP_BACKEND == 'none') {
                echo "<p>Please upload $this->_target and refresh this page</p>\n";
            } else {
                //that's a bit ugly - probably this should be a function. But it works :D
                $updateUrlJs  = "if (document.getElementById('predefUrls').value=='github') { ";
                $updateUrlJs .= "  if (document.getElementById('ghUser').value && document.getElementById('ghRepo').value && document.getElementById('ghBranch').value) { ";
                $updateUrlJs .= "    document.getElementById('downloadUrl').value = 'https://github.com/'+document.getElementById('ghUser').value+'/'+document.getElementById('ghRepo').value+'/archive/'+document.getElementById('ghBranch').value+'.tar.gz'; ";
                $updateUrlJs .= "  } else { document.getElementById('downloadUrl').value = ''; }";
                $updateUrlJs .= "} else { ";
                $updateUrlJs .= "  document.getElementById('downloadUrl').value=document.getElementById('predefUrls').value; ";
                $updateUrlJs .= "}";
                echo "<form action=\"".$_SERVER['PHP_SELF']."?step=".$_GET['step']."&httpBackend=".HTTP_BACKEND."\" method=\"POST\">\n";
                echo "<p></p>\n";
                echo "<label for=\"appUrl\">Archive to download:</label><br />\n";
                echo "<select id=\"predefUrls\" onchange=\"if (this.value=='github') { document.getElementById('githubRepo').style.display='block'; } else { document.getElementById('githubRepo').style.display='none'; } $updateUrlJs\">\n";
                $predefinedUrls = $this->_getDownloadUrls();
                foreach ($predefinedUrls as $k=>$r) {
                    echo "<option value=\"".htmlspecialchars($k)."\">$r</option>\n";
                }
                echo "<option value=\"github\">own github repository</option>\n";
                echo "<option value=\"\">own archive (url)</option>\n";
                echo "</select><br />\n";

                echo "<div id=\"githubRepo\" style=\"display:none\">\n";
                echo "  <label for=\"ghUser\" style=\"width: 100px; display: block; float: left;\">User:</label>\n";
                echo "  <input onkeyup=\"$updateUrlJs\" id=\"ghUser\" /><br />\n";
                echo "  <label for=\"ghRepo\" style=\"width: 100px; display: block; float: left;\">Repository:</label>\n";
                echo "  <input onkeyup=\"$updateUrlJs\" id=\"ghRepo\" /><br />\n";
                echo "  <label for=\"ghBranch\" style=\"width: 100px; display: block; float: left;\">Branch:</label>\n";
                echo "  <input onkeyup=\"$updateUrlJs\" id=\"ghBranch\" value=\"master\" />\n";
                echo "</div>\n";

                $urls = array_keys($predefinedUrls);
                echo "<br />Download Url:<br />\n";
                echo "<input style=\"width: 600px;\" type=\"text\" name=\"downloadUrl\" id=\"downloadUrl\" value=\"".$urls[0]."\"><br />\n";
                $onClick = "this.parentNode.style.backgroundImage = 'url(".LOADING_GIF.")'; this.style.visibility = 'hidden';";
                echo "<p style=\"background-repeat: no-repeat;\">";
                echo "<input type=\"submit\" value=\"Download\" onclick=\"$onClick\" />\n";
                echo "</p>";
                echo "</form>\n";
            }
        } else {
            echo "<p>Using: $this->_target</p>";
        }
    }
    abstract protected function _getDownloadUrls();

    public function getShowNextStep()
    {
        return file_exists($this->_target);
    }
}
abstract class StepExtract extends Step
{
    protected $_file;
    protected $_targetDir;

    protected function _alreadyExtracted()
    {
        return file_exists($this->_targetDir);
    }

    public function execute()
    {
        if (!$this->_alreadyExtracted()) {
            $dir = tempnam('.', 'downloader');
            unlink($dir);
            mkdir($dir);
            exec("tar xfz $this->_file -C $dir", $out, $ret);
            if ($ret) {
                throw new Exception("Extraction failed");
            }
            $dirs = glob("$dir/*");
            if (count($dirs) != 1) {
                throw new Exception("more than one directory extracted");
            }
            if (!is_dir($dirs[0])) {
                throw new Exception("no directory extracted");
            }
            rename("$dirs[0]",  $this->_targetDir);
            rmdir($dir);
            unlink($this->_file);
            echo "<p>Successfully Extracted: $this->_targetDir</p>";
        } else {
            echo "<p>Already Extracted: $this->_targetDir</p>";
        }
    }
}
class StepDownloadApp extends StepDownload
{
    public $name = 'Download App';
    protected $_target = 'app.tar.gz';

    public function execute()
    {
        if (HTTP_BACKEND == 'none') {
            echo "<p>Plase upload the application you want to install. This can be either
                    a kwf demo application or your own. If the application code is hosted on github you can
                    use the github archive functionality.<br />
                    <a href=\"https://github.com/vivid-planet/kwf-cms-demo/archive/master.tar.gz\">Download Example: kwf-cms-demo</a> </p>\n";
        } else {
            echo "<p>Plase choose the application you want to install. This can be either
                    a kwf demo application or your own. If the application code is hosted on github you can
                    use the github archive functionality.<br />
                    You can also upload $this->_target manually.</p>\n";
        }
        parent::execute();
    }

    protected function _getDownloadUrls()
    {
        $demoRepos = array();
        $repos = json_decode(httpRequestGet('https://api.github.com/users/vivid-planet/repos'));
        foreach ($repos as $repo) {
            if (substr($repo->name, -5) == '-demo') {
                $demoRepos['https://github.com/'.$repo->full_name.'/archive/master.tar.gz'] = $repo->name;
            }
        }
        return $demoRepos;
    }
}
class StepDownloadKwf extends StepDownload
{
    public $name = 'Download Kwf';
    protected $_target = 'kwf.tar.gz';

    public function execute()
    {
        if (HTTP_BACKEND == 'none') {
            echo "<p>Plase upload the Koala Framework Version you want to install. (it has to match the required version for your application)<br />
                    <a href=\"https://github.com/vivid-planet/koala-framework/archive/master.tar.gz\">Download Example: master branch</a>
                    </p>\n";
        } else {
            echo "<p>Plase choose the Koala Framework Version you want to install. (it has to match the required version for your application)<br />
                    You can also upload $this->_target manually.</p>\n";
        }
        parent::execute();
    }

    protected function _getDownloadUrls()
    {
        $ret = array();
        $ini = parse_ini_file('app-temp/config.ini', true);
        $ghUser = $ghRepo = null;
        if (isset($ini['production']['updateDownloader']['kwf']['github']['repository'])) {
            $ghUser = $ini['production']['updateDownloader']['kwf']['github']['user'];
            $ghRepo = $ini['production']['updateDownloader']['kwf']['github']['repository'];
        }
        if (!$ghUser) $ghUser = 'vivid-planet';
        if (!$ghRepo) $ghRepo = 'koala-framework';
        $ghBranch = trim(file_get_contents('app-temp/kwf_branch'));

        $ret["https://github.com/$ghUser/$ghRepo/archive/$ghBranch.tar.gz"] = "$ghUser/$ghRepo $ghBranch (Recommended)";

        $kwfBranches = json_decode(httpRequestGet('https://api.github.com/repos/vivid-planet/koala-framework/branches'));
        foreach ($kwfBranches as $b) {
            if ($b->name == '3.2' || $b->name == '3.3') continue; //don't support setup
            $url = "https://github.com/vivid-planet/koala-framework/archive/$b->name.tar.gz";
            if (!isset($ret[$url])) {
                $ret[$url] = 'vivid-planet/koala-framework '.$b->name;
            }
        }
        return $ret;
    }
}
class StepDownloadLibrary extends StepDownload
{
    public $name = 'Download Library';
    protected $_target = 'library.tar.gz';

    public function execute()
    {
        $checkExistingDirs = array('../library', '../../library');
        foreach ($checkExistingDirs as $dir) {
            if (is_dir($dir) && is_dir($dir.'/zend')) {
                if (isset($_REQUEST['useExisting'])) {
                    system("ln -s $dir library");
                }
            }
        }

        if (is_dir('library')) {
            echo "Using existing library folder";
        } else {
            if (HTTP_BACKEND == 'none') {
                echo "<p>Plase upload the library you want to install.<br />
                        <a href=\"https://github.com/vivid-planet/library/archive/master.tar.gz\">Download Default</a></p>\n";
            } else {
                echo "<p>Plase choose the library you want to install, usually default should suffice.<br />
                        You can also upload $this->_target manually.</p>\n";
            }
            parent::execute();

            foreach ($checkExistingDirs as $dir) {
                if (is_dir($dir) && is_dir($dir.'/zend')) {
                    echo "<br />Alternatively you can also use the existing library:<br />";
                    echo "<pre><a href=\"".$_SERVER['PHP_SELF']."?step=".$_GET['step']."&httpBackend=".HTTP_BACKEND."&useExisting\">".realpath($dir)."</a></pre>";
                    echo "(you won't have to download the 50MB)";
                    break;
                }
            }
        }
    }

    public function getShowNextStep()
    {
        if (parent::getShowNextStep()) return true;
        return is_dir('library');
    }

    protected function _getDownloadUrls()
    {
        $ret = array(
            'https://github.com/vivid-planet/library/archive/master.tar.gz' => 'Default (Recommended)'
        );
        return $ret;
    }
}
class StepExtractKwf extends StepExtract
{
    public $name = 'Extract Kwf';
    protected $_file = 'kwf.tar.gz';
    protected $_targetDir = 'kwf-lib';
    public function execute()
    {
        parent::execute();
        file_put_contents($this->_targetDir.'/include_path', getcwd().'/library/zend/%version%');
    }
}
class StepExtractLibrary extends StepExtract
{
    public $name = 'Extract Library';
    protected $_file = 'library.tar.gz';
    protected $_targetDir = 'library';
}

class StepExtractApp extends StepExtract
{
    public $name = 'Extract App';
    protected $_file = 'app.tar.gz';
    protected $_targetDir = 'app-temp';
}

class StepMoveApp extends Step
{
    public $name = 'Move App';
    public function execute()
    {
        global $selfFileName, $selfBaseUrl;
        exec("mv app-temp/* .", $out, $ret);
        if ($ret) {
            throw new Exception("Moving app failed");
        }
        exec("mv app-temp/.* .", $out, $ret);

        rmdir("app-temp");

        $cfg  = "[production]\n";
        $cfg .= "server.domain = ".$_SERVER['HTTP_HOST']."\n";
        $cfg .= "server.baseUrl = \"".$selfBaseUrl."\"\n";
        $cfg .= "setupFinished = false\n";
        file_put_contents('config.local.ini', $cfg);

        //if the apache configuration doesn't allow setting php_flag remove it
        if (HTTP_BACKEND == 'wget') {
            $response = shell_exec('wget -O /dev/null -S '.escapeshellarg('http://'.$_SERVER['HTTP_HOST'].$selfBaseUrl).' 2>&1');
        } else {
            @file_get_contents('http://'.$_SERVER['HTTP_HOST'].$selfBaseUrl);
            $response = implode("\n", $http_response_header);
        }
        if (preg_match("#HTTP/1\.\d\s+(\d{3})\s+#", $response, $m)) {
            if ($m[1] == 500) { //internal server error
                $htAccess = file_get_contents('.htaccess');
                $htAccess = str_replace('php_flag magic_quotes_gpc off', '', $htAccess);
                file_put_contents('.htaccess', $htAccess);
            }
        }

        echo "<p style=\"font-weight: bold;\">Congratulations, downloader finished!</p>";
        echo "<p><a href=\"$selfBaseUrl/kwf/maintenance/setup\">start setup</a></p>";

        unlink($selfFileName); //our job is done, now commit suicide
    }
}

