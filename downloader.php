<?php
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == '/ping') die('pong'); //for testing .htaccess functionality

define('LOADING_GIF', "data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAkKAAAALAAAAAAQABAAAAMyCLrc/jDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAAA==");

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
error_reporting(E_ALL);
set_error_handler("exception_error_handler");
set_time_limit(120);

if (php_sapi_name() == 'cli') {
    throw new Exception("Start downloader.php in browser");
}

$steps = array(
    'welcome' => 'StepWelcome',
    'check' => 'StepCheck',
    'downloadApp' => 'StepDownloadApp',
    'downloadKwf' => 'StepDownloadKwf',
    'downloadLibrary' => 'StepDownloadLibrary',
    'extractKwf' => 'StepExtractKwf',
    'extractLibrary' => 'StepExtractLibrary',
    'extractApp' => 'StepExtractApp',
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
    echo "<p><a href=\"downloader.php?step=".$nextStep."\" onclick=\"$onClick\" style=\"background-repeat: no-repeat;\">Next Step: ".$s->name."</a></p>";
}
echo "</div>\n";

//interesting way to do a http request, but file_get_contents might be blocked or something
function httpRequestGet($url)
{
    return shell_exec('wget -O - '.escapeshellarg($url));
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
    public function execute()
    {
        //test required executables
        exec('ls', $out, $ret);
        if ($ret) {
            throw new Exception("can't execute system commands");
        }
        exec('wget --version', $out, $ret);
        if ($ret) {
            throw new Exception("can't find wget executable");
        }
        exec('tar --version', $out, $ret);
        if ($ret) {
            throw new Exception("can't find tar executable");
        }

        //test permissions
        if (!is_writeable('.')) {
            throw new Exception("downloader.php script needs write permissions to current folder");
        }

        //test web runs in document_root
        if (substr($_SERVER['REQUEST_URI'], 0, 15) != '/downloader.php') {
            throw new Exception("Only installation in document root is supported, don't use subfolder");
        }

        //test .htaccess functionality
        $htAccessTestContents = "RewriteEngine on
        RewriteCond %{REQUEST_URI} !^/*(downloader.php)/?
        RewriteRule ^(.*)$ /downloader.php [L]
        ";
        if (file_exists('.htaccess') && file_get_contents('.htaccess') != $htAccessTestContents) {
            throw new Exception("There exists already a .htaccess in the current folder");
        }
        file_put_contents('.htaccess', $htAccessTestContents);
        
        $pingResponse = trim(httpRequestGet('http://'.$_SERVER['HTTP_HOST'].'/ping'));
        unlink('.htaccess');

        if ($pingResponse != 'pong') {
            throw new Exception(".htaccess broken");
        }
        
        echo "<p>All checks required for the downloader passed.</p>\n";
        echo "<p style=\"font-style:italic;\">Note: this doesn't include all requirements needed by kwf.</p>\n";
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
            exec("wget -O ".escapeshellarg($this->_target)." ".escapeshellarg($url), $out, $ret);
            if ($ret) {
                echo "<p>Download failed</p>";
            }
            echo "<p>Successfully Downloaded: $this->_target</p>";
        }
        if (!file_exists($this->_target)) {
            echo "<form action=\"downloader.php?step=".$_GET['step']."\" method=\"POST\">\n";
            echo "<p></p>\n";
            echo "<label for=\"appUrl\">Archive to download:</label><br />\n";
            echo "<select id=\"predefUrls\" onchange=\"document.getElementById('downloadUrl').value=this.value;\">\n";
            $predefinedUrls = $this->_getDownloadUrls();
            foreach ($predefinedUrls as $k=>$r) {
                echo "<option value=\"".htmlspecialchars($k)."\">$r</option>\n";
            }
            echo "<option value=\"\">own archive (url)</option>\n";
            echo "</select><br />\n";
            $urls = array_keys($predefinedUrls);
            echo "<input style=\"width: 600px;\" type=\"text\" name=\"downloadUrl\" id=\"downloadUrl\" value=\"".$urls[0]."\"><br />\n";
            $onClick = "this.parentNode.style.backgroundImage = 'url(".LOADING_GIF.")'; this.style.visibility = 'hidden';";
            echo "<p style=\"background-repeat: no-repeat;\">";
            echo "<input type=\"submit\" value=\"Download\" onclick=\"$onClick\" />\n";
            echo "</p>";
            echo "</form>\n";
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
    protected $_keepDir = true;

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
            if ($this->_keepDir) {
                rename("$dirs[0]",  $this->_targetDir);
            } else {
                exec("mv $dirs[0]/* $this->_targetDir", $out, $ret);
                if ($ret) {
                    throw new Exception("Extraction failed");
                }
                exec("mv $dirs[0]/.* $this->_targetDir", $out, $ret);
                rmdir("$dirs[0]");
            }
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
        echo "<p>Plase choose the application you want to install. This can be either
                 a kwf demo application or your own. If the application code is hosted on github you can
                 use the github archive functionality.<br />
                 You can also upload $this->_target manually.</p>\n";
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
        echo "<p>Plase choose the Koala Framework Version you want to install.<br />
                 You can also upload $this->_target manually.</p>\n";
        parent::execute();
    }

    protected function _getDownloadUrls()
    {
        $ret = array();
        $kwfBranches = json_decode(httpRequestGet('https://api.github.com/repos/vivid-planet/koala-framework/branches'));
        foreach ($kwfBranches as $b) {
            $ret["https://github.com/vivid-planet/koala-framework/archive/$b->name.tar.gz"] = $b->name;
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
        echo "<p>Plase choose the library you want to install, usually default should suffice.<br />
                 You can also upload $this->_target manually.</p>\n";
        parent::execute();
    }

    protected function _getDownloadUrls()
    {
        $ret = array(
            'https://github.com/vivid-planet/library/archive/master.tar.gz' => 'default'
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
    protected $_targetDir = '.';
    protected $_keepDir = false;
    protected function _alreadyExtracted()
    {
        return file_exists($this->_targetDir.'/bootstrap.php');
    }
    public function execute()
    {
        parent::execute();

        //if the apache configuration doesn't allo setting php_flag remove it
        $response = shell_exec('wget -O /dev/null -S '.escapeshellarg($url).' 2>&1');
        if (preg_match("  HTTP/1\.? (\d{3}) .*", $response, $m)) {
            if ($m == 500) { //internal server error
                $htAccess = file_get_contents('.htaccess');
                $htAccess = str_replace('php_flag magic_quotes_gpc off', '', $htAccess);
                file_put_contents('.htaccess', $htAccess);
            }
        }

        echo "<p style=\"font-weight: bold;\">Congratulations, downloader finished!</p>";
        echo "<p><a href=\"/kwf/maintenance/setup\">start setup</a></p>";
        unlink("downloader.php");
    }
}

