<?php
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == '/ping') die('pong'); //for testing .htaccess functionality

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
error_reporting(E_ALL);
set_error_handler("exception_error_handler");

if (php_sapi_name() == 'cli') {
    throw new Exception("Start downloader.php in browser");
}

$steps = array(
    'welcome' => 'StepWelcome',
    'check' => 'StepCheck',
    'downloadKwf' => 'StepDownloadKwf',
    'downloadLibrary' => 'StepDownloadLibrary',
    'downloadApp' => 'StepDownloadApp',
    'extractKwf' => 'StepExtractKwf',
    'extractLibrary' => 'StepExtractLibrary',
    'extractApp' => 'StepExtractApp',
);

// TODO add configure form to select web repository
// TODO select kwf branch?

$step = isset($_GET['step']) ? $_GET['step'] : 'welcome';
if (!isset($steps[$step])) throw new Exception("Invalid step");
$stepClass = $steps[$step];
$stepNum = array_search($step, array_keys($steps));
$step = new $stepClass();
echo "<h1>Koala Framework Downloader</h1>";
echo "<h2>$step->name [".($stepNum+1).'/'.count($steps)."]</h2>\n";
$step->execute();

$stepKeys = array_keys($steps);
if (isset($stepKeys[$stepNum+1])) {
    $nextStep = $stepKeys[$stepNum+1];
    $s = new $steps[$nextStep];
    $loadingGif = "data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAkKAAAALAAAAAAQABAAAAMyCLrc/jDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAAA==";
    $onClick = "this.innerHTML = '&nbsp;&nbsp;&nbsp;&nbsp;'; this.style.backgroundImage = 'url($loadingGif)';";
    echo "<p><a href=\"downloader.php?step=".$nextStep."\" onclick=\"$onClick\" style=\"background-repeat: no-repeat;\">Next Step: ".$s->name."</a></p>";
}

//interesting way to do a http request, but file_get_contents might be blocked or something
function httpRequestGet($url)
{
    return shell_exec('wget -O - '.escapeshellarg($url));
}

abstract class Step
{
    abstract function execute();
}

class StepWelcome extends Step
{
    public $name = 'Welcome';
    public function execute()
    {
        echo "<p>Welcome to the Koala Framework Downloader</p>";
        echo "<p>This script will download a koala framework application plus the required libraries to run.</p>";
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
        
        echo "<p>All checks required for the downloader passed.</p>";
    }
}
abstract class StepDownload extends Step
{
    protected $_url;
    protected $_target;
    public function execute()
    {
        if (!file_exists($this->_target)) {
            exec("wget -O $this->_target $this->_url", $out, $ret);
            if ($ret) {
                throw new Exception("Download failed");
            }
            echo "<p>Successfully Downloaded: $this->_target</p>";
        } else {
            echo "<p>Already Downloaded: $this->_target</p>";
        }
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
            echo "<p>Successfully Extracted: $this->_targetDir</p>";
        } else {
            echo "<p>Already Extracted: $this->_targetDir</p>";
        }
    }
}
class StepDownloadKwf extends StepDownload
{
    public $name = 'Download Kwf';
    protected $_url = 'https://github.com/vivid-planet/koala-framework/archive/3.3.tar.gz';
    protected $_target = 'kwf.tar.gz';
}
class StepDownloadLibrary extends StepDownload
{
    public $name = 'Download Library';
    protected $_url = 'https://github.com/vivid-planet/library/archive/master.tar.gz';
    protected $_target = 'library.tar.gz';
}
class StepDownloadApp extends StepDownload
{
    public $name = 'Download App';
    protected $_url = 'https://github.com/vivid-planet/kwf-cms-demo/archive/master.tar.gz';
    protected $_target = 'app.tar.gz';
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
        //TODO enable automatic deletion?
        //unlink("downloader.php");
        echo "<p>download finished</p>";
        echo "<p><a href=\"/kwf/maintenance/setup\">start setup</a></p>";
    }
}

