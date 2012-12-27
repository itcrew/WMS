<?php 
class WMS {
	const PF_ROS = 1;
	const R_GOOD = 0;
	const R_NULL = 1;
	const R_ERROR = 2;
	private $_platform;
	private $_db;
	protected $_serial;
	protected $_next;
	protected $_logparam;
	protected $_logmodule;

	public function __construct () {
		require_once($_SERVER['WMS_PATH'] . '/config.php');
		$this->_db = array(
			'host' => $DBHOST,
			'name' => $DBNAME,
			'user' => $DBUSER,
			'pass' => $DBPASS
		);
		openlog('wms', LOG_ODELAY, LOG_LOCAL3);
		header('Content-Type: text/plain');
		if (isset($_REQUEST['pf'])) {
			switch ($_REQUEST['pf']) {
			case 'ros':
				$this->_platform = WMS::PF_ROS;
				break;
			}
		} elseif (isset($_SERVER['HTTP_USER_AGENT'])) {
			if (substr($_SERVER['HTTP_USER_AGENT'], 0, 9) == 'Mikrotik/') {
				// "Mikrotik/3.x Fetch"
				$this->_platform = WMS::PF_ROS;
			}
		}
		switch ($this->_platform) {
		case WMS::PF_ROS:
			if (isset($_REQUEST['serial'])) {
				$this->_serial = $_REQUEST['serial'];
			}
			break;
		}
	}

	public function boot () {
		if (!$this->_platform) {
			$this->bail('Unsupported platform');
			return;
		}
		$response = WMS::R_GOOD;
		while (true) {
			if (!$this->_next) {
				break;
			}
			$jump = $this->_next;
			$this->_next = null;
			if (!is_callable($jump)) {
				if (is_array($jump)) {
					$this->_log(LOG_ERR, 'Uncallable jump: ' . get_class($jump[0]) . '::' . $jump[1] . '()');
				}
				$response = WMS::R_ERROR;
				break;
			}
			if (!call_user_func($jump)) {
				$response = WMS::R_NULL;
				break;
			}
		}
		call_user_func(array($this, '_boot_' . $response));
	}

	private function _boot_0 () {
		$this->_reqlog("GOOD");
	}

	private function _boot_1 () {
		$this->_reqlog("NULL");
		$this->bail('Nothing of interest');
	}

	private function _boot_2 () {
		$this->_reqlog("ERROR");
		$this->bail('We haz a problem', 500);
	}

	private function _reqlog ($status) {
		$msg = $this->_logmodule . ': request[' . $status . ']: ';
		$msg .= $this->_getLogIP() . '[' . $this->getPlatform();
		if ($this->_serial) {
			$msg .= '/' . $this->_serial;
		}
		$msg .= ']';
		$params = $this->_logparam;
		if (sizeof($params) > 0) {
			$msg .= ' (';
			$delim = '';
			foreach ($params as $key => $val) {
				$msg .= $delim . $key . '=' . $val;
				$delim = ', ';
			}
			$msg .= ')';
		}
		syslog(LOG_INFO, $msg);
	}

	private function _getLogIP () {
		if (!isset($_SERVER['REMOTE_ADDR'])) {
			return 'local';
		}
		if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			return $_SERVER['REMOTE_ADDR'];
		}
		$xff = '';
		foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $xffip) {
			$xff .= '/' . trim($xffip);
		}
		if (!isset($xff{0})) {
			return $_SERVER['REMOTE_ADDR'];
		}
		return $_SERVER['REMOTE_ADDR'] . $xff;
	}

	protected function _log ($priority, $msg) {
		syslog($priority, $this->_logmodule . ': ' . $msg);
	}

	protected function _dbConnect () {
		if (!is_array($this->_db)) {
			return $this->_db;
		}
		$db = $this->_db;
		$this->_db = new PDO('mysql:dbname=' . $db['name'] . ';host=' . $db['host'], $db['user'], $db['pass']);
		return $this->_db;
	}

	public function bail ($msg, $code = '404') {
		header('HTTP/1.0 ' . $code . ' ' . $msg);
		header('Content-Length: ' . (strlen($msg)+1));
		die($msg . "\n");
	}

	public function getPlatform () {
		if (!$this->_platform) {
			return;
		}
		$pmethod = array($this, '_getPlatform_' . $this->_platform);
		return call_user_func($pmethod);
	}

	private function _getPlatform_1 () {
		return 'ros';
	}
}