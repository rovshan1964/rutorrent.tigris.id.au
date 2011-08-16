<?php

if(function_exists('ini_set'))
{
	ini_set('display_errors',false);
	ini_set('log_errors',true);
}

if(!isset($_SERVER['REMOTE_USER']))
{
	if(isset($_SERVER['PHP_AUTH_USER']))
		$_SERVER['REMOTE_USER'] = $_SERVER['PHP_AUTH_USER'];
	else
	if(isset($_SERVER['REDIRECT_REMOTE_USER']))
		$_SERVER['REMOTE_USER'] = $_SERVER['REDIRECT_REMOTE_USER'];
}

$rootPath = realpath(dirname(__FILE__)."/..");
require_once( $rootPath.'/conf/config.php' );
$conf = getConfFile('config.php');
if($conf)
	require_once($conf);
require_once( 'lfs.php' );

if(!isset($profileMask))
	$profileMask = 0777;
if(!isset($localhosts) || !count($localhosts))
	$localhosts = array( "127.0.0.1", "localhost" );

function stripSlashesFromArray(&$arr)
{
        if(is_array($arr))
        {
		foreach($arr as $k=>$v)
		{
			if(is_array($v))
			{
				stripSlashesFromArray($v);
				$arr[$k] = $v;
			}
			else
			{
				$arr[$k] = stripslashes($v);
			}
		}
	}
}

function fix_magic_quotes_gpc() 
{
	if(function_exists('ini_set'))
	{
		ini_set('magic_quotes_runtime', 0);
		ini_set('magic_quotes_sybase', 0);
	}
	if(get_magic_quotes_gpc())
	{
		stripSlashesFromArray($_POST);
		stripSlashesFromArray($_GET);
		stripSlashesFromArray($_COOKIE);
		stripSlashesFromArray($_REQUEST);
	}
}

fix_magic_quotes_gpc();
setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8");

function quoteAndDeslashEachItem($item)
{
	return('"'.addcslashes($item,"\\\'\"\n\r\t").'"'); 
}

define('_is_utf8_split',5000);

function isInvalidUTF8($string)
{
	$len = strlen($string);
	if($len > _is_utf8_split) 
	{
		for($i=0,$s=_is_utf8_split,$j=ceil($len/_is_utf8_split); $i < $j; $i++,$s+=_is_utf8_split) 
			if(isInvalidUTF8(substr($string,$s,_is_utf8_split)))
		                return(true);
	        return(false);
    	}
    	else
		return(preg_match('%^(?:'.
			'[\x09\x0A\x0D\x20-\x7E]'.            	// ASCII
			'| [\xC2-\xDF][\x80-\xBF]'.             // non-overlong 2-byte
			'| \xE0[\xA0-\xBF][\x80-\xBF]'.         // excluding overlongs
			'| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'.  // straight 3-byte
			'| \xED[\x80-\x9F][\x80-\xBF]'.         // excluding surrogates
			'| \xF0[\x90-\xBF][\x80-\xBF]{2}'.      // planes 1-3
			'| [\xF1-\xF3][\x80-\xBF]{3}'.          // planes 4-15
			'| \xF4[\x80-\x8F][\x80-\xBF]{2}'.      // plane 16
			')*$%xs', $string)!=1);
}

function win2utf($str) 
{
	$outstr='';
	$recode=array(
	0x0402,0x0403,0x201A,0x0453,0x201E,0x2026,0x2020,0x2021,
	0x20AC,0x2030,0x0409,0x2039,0x040A,0x040C,0x040B,0x040F,
	0x0452,0x2018,0x2019,0x201C,0x201D,0x2022,0x2013,0x2014,
	0x0000,0x2122,0x0459,0x203A,0x045A,0x045C,0x045B,0x045F,
	0x00A0,0x040E,0x045E,0x0408,0x00A4,0x0490,0x00A6,0x00A7,
	0x0401,0x00A9,0x0404,0x00AB,0x00AC,0x00AD,0x00AE,0x0407,
	0x00B0,0x00B1,0x0406,0x0456,0x0491,0x00B5,0x00B6,0x00B7,
	0x0451,0x2116,0x0454,0x00BB,0x0458,0x0405,0x0455,0x0457,
	0x0410,0x0411,0x0412,0x0413,0x0414,0x0415,0x0416,0x0417,
	0x0418,0x0419,0x041A,0x041B,0x041C,0x041D,0x041E,0x041F,
	0x0420,0x0421,0x0422,0x0423,0x0424,0x0425,0x0426,0x0427,
	0x0428,0x0429,0x042A,0x042B,0x042C,0x042D,0x042E,0x042F,
	0x0430,0x0431,0x0432,0x0433,0x0434,0x0435,0x0436,0x0437,
	0x0438,0x0439,0x043A,0x043B,0x043C,0x043D,0x043E,0x043F,
	0x0440,0x0441,0x0442,0x0443,0x0444,0x0445,0x0446,0x0447,
	0x0448,0x0449,0x044A,0x044B,0x044C,0x044D,0x044E,0x044F
	);
	$and=0x3F;
	for ($i=0;$i<strlen($str);$i++) {
	    $octet=array();
	    if (ord($str[$i])<0x80) {
		$strhex=ord($str[$i]);
	    } else {
		$strhex=$recode[ord($str[$i])-128];
	    }
	    if ($strhex<0x0080) {
		$octet[0]=0x0;
	    } elseif ($strhex<0x0800) {
		$octet[0]=0xC0;
		$octet[1]=0x80;
	    } elseif ($strhex<0x10000) {
		$octet[0]=0xE0;
		$octet[1]=0x80;
		$octet[2]=0x80;
	    } elseif ($strhex<0x200000) {
		$octet[0]=0xF0;
		$octet[1]=0x80;
		$octet[2]=0x80;
		$octet[3]=0x80;
	    } elseif ($strhex<0x4000000) {
		$octet[0]=0xF8;
		$octet[1]=0x80;
		$octet[2]=0x80;
		$octet[3]=0x80;
		$octet[4]=0x80;
	    } else {
		$octet[0]=0xFC;
		$octet[1]=0x80;
		$octet[2]=0x80;
		$octet[3]=0x80;
		$octet[4]=0x80;
		$octet[5]=0x80;
	    }
	    for ($j=(count($octet)-1);$j>=1;$j--) {
		$octet[$j]=$octet[$j] + ($strhex & $and);
		$strhex=$strhex>>6;
	    }
	    $octet[0]=$octet[0] + $strhex;
	    for ($j=0;$j<count($octet);$j++) {
		$outstr.=chr($octet[$j]);
	    }
	}
	return($outstr);
}

function toLog( $str )
{
	global $log_file;
	if( $log_file && strlen( $log_file ) > 0 )
	{
		// dmrom: set proper permissions (need if rtorrent user differs from www user)
		if( !is_file( $log_file ) )
		{
			touch( $log_file );
			chmod( $log_file, 0666 );
		}
		$w = fopen( $log_file, "ab+" );
		if( $w )
		{
			fputs( $w, "[".strftime( "%d.%m.%y %H:%M:%S" )."] {$str}\n" );
			fclose( $w );
		}
	}
}

function isLocalMode( $host = null, $port = null )
{
	global $scgi_host;
	global $scgi_port;
	global $localhosts;
	if(is_null($port))
		$port = $scgi_port;
	if(is_null($host))
		$host = $scgi_host;
	return(($port == 0) || in_array($host,$localhosts));
}

function isUserHavePermissionPrim($uid,$gids,$file,$flags)
{
	$ss=LFS::stat($file);
	if($ss)
	{
		$p=$ss['mode'];
		if(($p & $flags) == $flags)
		{
			return(true);
		}
		$flags<<=3;
		foreach( $gids as $ndx=>$gid)
	        	if(($gid==$ss['gid']) &&
				(($p & $flags) == $flags))
				return(true);
		$flags<<=3;
		if(($uid==$ss['uid']) &&
			(($p & $flags) == $flags))
			return(true);
	}
	return(false);
}

function isUserHavePermission($uid,$gids,$file,$flags)
{
	if($uid<=0)
	{
	        if(($flags & 0x0001) && !is_dir($file))
	                return(($ss=LFS::stat($file)) && ($ss['mode'] & 0x49));
	        else
			return(true);
	}
	if(is_link($file))
		$file = readlink($file);
	if(isUserHavePermissionPrim($uid,$gids,$file,$flags))
	{
		if(($flags & 0x0002) && !is_dir($file))
			$flags = 0x0007;
		else
			$flags = 0x0005;
		return(isUserHavePermissionPrim($uid,$gids,dirname($file),$flags));
	}
	return(false);
}

function addslash( $str )
{
	$len = strlen( $str );
	return( (($len == 0) || ($str[$len-1] == '/')) ? $str : $str.'/' );
}

function delslash( $str )
{
	$len = strlen( $str );
	return( (($len == 0) || ($str[$len-1] != '/')) ? $str : substr($str,0,$len-1) );
}

function fullpath($path,$base = '')
{
	$root  = '';
	if($path[0] == '/')
        	$root = '/';
	else
		return(fullpath(addslash($base).$path,getcwd()));
	$path=explode('/', $path);
	$newpath=array();
	foreach($path as $p)
	{
		if ($p === '' || $p === '.') continue;
		if ($p==='..')
			array_pop($newpath);
		else
			array_push($newpath, $p);
	}
	return($root.implode('/', $newpath));
}

function getConfFile($name)
{
	$user = getUser();
	if($user!='')
	{
	       	global $rootPath;
		$conf = $rootPath.'/conf/users/'.$user.'/'.$name;
		if(is_file($conf) && is_readable($conf))
			return($conf);
	}
	return(false);
}

function getPluginConf($plugin)
{
        $ret = '';
	global $rootPath;
	$conf = $rootPath.'/plugins/'.$plugin.'/conf.php';
	if(is_file($conf) && is_readable($conf))
		$ret.='require("'.$conf.'");';
	$user = getUser();
	if($user!='')
	{
		$conf = $rootPath.'/conf/users/'.$user.'/plugins/'.$plugin.'/conf.php';
		if(is_file($conf) && is_readable($conf))
			$ret.='require("'.$conf.'");';
	}
	return($ret);
}

function getUser()
{
        global $forbidUserSettings;
	return( (!$forbidUserSettings && isset($_SERVER['REMOTE_USER']) && !empty($_SERVER['REMOTE_USER'])) ? strtolower($_SERVER['REMOTE_USER']) : '' );
}

function getProfilePath( $user = null )
{
	global $profilePath;

	$ret = fullpath(isset($profilePath) ? $profilePath : '../share', dirname(__FILE__));
	if(is_null($user))
	        $user = getUser();
        if($user!='')
        {
        	$ret.=('/users/'.$user);
        	if(!is_dir($ret))
			makeDirectory( array($ret,$ret.'/settings',$ret.'/torrents') );
	}
	return($ret);
}

function getSettingsPath( $user = null )
{
	return( getProfilePath($user).'/settings' );
}

function getUploadsPath( $user = null )
{
	return( getProfilePath($user).'/torrents' );
}

function getUniqueFilename($fname)
{
	global $overwriteUploadedTorrents;	
	if(!$overwriteUploadedTorrents)
	{
		while(file_exists($fname))
		{
			$ext = '';
			$pos = strrpos($fname,'.');
			if($pos!==false) 
			{
				$ext = substr($fname,$pos);
				$fname = substr($fname,0,$pos);
			}
			$pos = preg_match('/.*\((?P<no>\d+)\)$/',$fname,$matches);
			$no = 1;
			if($pos)
			{		
				$no = intval($matches["no"])+1;
				$fname = substr($fname,0,strrpos($fname,'('));
			}
			$fname = $fname.'('.$no.')'.$ext;
		}
	}
	return($fname);
}

function getExternal($exe)
{
	global $pathToExternals;
	return( (isset($pathToExternals[$exe]) && !empty($pathToExternals[$exe])) ? $pathToExternals[$exe] : $exe );
}

function getPHP()
{
	return( getExternal("php") );
}

function findEXE( $exe )
{
	global $pathToExternals;
	if(isset($pathToExternals[$exe]) && !empty($pathToExternals[$exe]))
		return(is_executable($pathToExternals[$exe]) ? $pathToExternals[$exe] : false);
	$path = explode(":", getenv('PATH'));
	foreach($path as $tryThis)
	{
		$fname = $tryThis . '/' . $exe;
		if(is_executable($fname))
			return($fname);
	}
	return(false);
}

function cachedEcho( $content, $type = null, $cacheable = false, $exit = true )
{
	if($cacheable && isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD']=='GET'))
	{
		$etag = '"'.strtoupper(dechex(crc32($content))).'"';
		header('Expires: ');
		header('Pragma: ');
		header('Cache-Control: ');
		if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
		{
			header('HTTP/1.0 304 Not Modified');
			return;
		}
		header('Etag: '.$etag);
	}
	if(!is_null($type))
		header("Content-Type: ".$type."; charset=UTF-8");
	$len = strlen($content);
	if(ini_get("zlib.output_compression") && ($len<2048))
		ini_set("zlib.output_compression",false);
	if(!ini_get("zlib.output_compression"))
	{
	        if(PHP_USE_GZIP && isset($_SERVER['HTTP_ACCEPT_ENCODING']))
	        {
		        if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false ) 
		        	$encoding = 'x-gzip'; 
			else if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false )
		        	$encoding = 'gzip'; 
			if($encoding && ($len>=2048))
			{
				$gzip = getExternal('gzip');
				header('Content-Encoding: '.$encoding); 
				$randName = uniqid("/tmp/rutorrent-ans-");
				file_put_contents($randName,$content);
				passthru( $gzip." -".PHP_GZIP_LEVEL." -c < ".$randName );
				unlink($randName);
				return;
			}
		}
		header("Content-Length: ".$len);
	}
	if($exit)
		exit($content);
	else
		echo($content);
}

function makeDirectory( $dirs, $perms = null )
{
	global $profileMask;
	if(is_null($perms))
		$perms = isset($profileMask) ? $profileMask : 0777;
	$oldMask = umask(0);
	if(is_array($dirs))
		foreach($dirs as $dir)
			@mkdir($dir,$perms,true);
	else
		@mkdir($dirs,$perms,true);
	@umask($oldMask);
} 

function getFileName($path)
{
	$arr = explode('/',$path);
	return(end($arr));
}

function sendFile( $filename, $contentType = null, $nameToSent = null, $mustExit = true )
{
	$stat = @LFS::stat($filename);
	if($stat && @LFS::is_file($filename) && @LFS::is_readable($filename))
	{
		$etag = sprintf('"%x-%x-%x"', $stat['ino'], $stat['size'], $stat['mtime'] * 1000000);
		header('Cache-Control: ');
		header('Expires: ');
		header('Pragma: ');
		if( 	(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) ||
                       	(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $stat['mtime']))
			header('HTTP/1.0 304 Not Modified');
		else
		{
			header('Etag: '.$etag);
			header('Last-Modified: ' . date('r', $stat['mtime']));
			set_time_limit(0);
			header('Accept-Ranges: bytes');
			if(!ini_get("zlib.output_compression"))
				header('Content-Length:' . $stat['size']);
			header('Content-Type: '.(is_null($contentType) ? 'application/octet-stream' : $contentType));
			if(is_null($nameToSent))
				$nameToSent = end(explode('/',$filename));
			if(isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'],'MSIE'))
				$nameToSent = rawurlencode($nameToSent);
			header('Content-Disposition: attachment; filename="'.$nameToSent.'"');
			header('Content-Transfer-Encoding: binary');
			header('Content-Description: File Transfer');
			header('HTTP/1.0 200 OK');
			ob_end_flush();
			if($stat['size'] >= 2147483647)
				passthru('cat '.escapeshellarg($filename));
			else
				readfile($filename);
			if($mustExit)
				exit;
			else
				return(true);
		}
	}
	return(false);
}

?>