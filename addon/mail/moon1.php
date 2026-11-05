<?php
class SMTP 
{
	public $SMTP_PORT = 25;
  	public $CRLF = "\r\n";
  	public $do_debug; 
  	public $Debugoutput     = "echo";
  	public $do_verp = false;
  	public $Timeout         = 15;
  	public $Timelimit       = 30;
  	public $Version         = '5.2.2';
  	private $smtp_conn;
  	private $error;
  	private $helo_rply;
  	private function edebug($str) 
	{
    		if ($this->Debugoutput == "error_log") 
    		{
        		error_log($str);
    		} 
    		else 
		{
        		echo $str;
    		}
	}
	public function __construct() 
	{
    		$this->smtp_conn = 0;
    		$this->error = null;
    		$this->helo_rply = null;
    		$this->do_debug = 0;
	}
	public function Connect($host, $port = 0, $tval = 30) 
	{
    		$this->error = null;
    		if($this->connected()) 
		{
      			$this->error = array("error" => "Already connected to a server");
      			return false;
    		}
		if(empty($port)) 
		{
      			$port = $this->SMTP_PORT;
    		}
    		$this->smtp_conn = @fsockopen($host,$port,$errno,$errstr,$tval);
		if(empty($this->smtp_conn)) 
		{
      			$this->error = array("error" => "Failed to connect to server",
                           	"errno" => $errno,
                           	"errstr" => $errstr);
      			if($this->do_debug >= 1) 
			{
        			$this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": $errstr ($errno)" . $this->CRLF . '<br />');
      			}
      			return false;
    		}
    		if(substr(PHP_OS, 0, 3) != "WIN") 
		{
     			$max = ini_get('max_execution_time');
     			if ($max != 0 && $tval > $max) 
			{
      				@set_time_limit($tval);
     			}
     			stream_set_timeout($this->smtp_conn, $tval, 0);
    		}
    	$announce = $this->get_lines();
	if($this->do_debug >= 2) 
	{
      		$this->edebug("SMTP -> FROM SERVER:" . $announce . $this->CRLF . '<br />');
    	}
    	return true;
}
public function StartTLS() 
{
	$this->error = null; # to avoid confusion
    	if(!$this->connected()) 
	{
      		$this->error = array("error" => "Called StartTLS() without being connected");
      		return false;
    	}
    	fputs($this->smtp_conn,"STARTTLS" . $this->CRLF);
    	$rply = $this->get_lines();
    	$code = substr($rply,0,3);
    	if($this->do_debug >= 2) 
	{
      		$this->edebug("SMTP -> FROM SERVER:" . $rply . $this->CRLF . '<br />');
    	}
    	if($code != 220) 
	{
      		$this->error =
         	array("error"     => "STARTTLS not accepted from server",
               		"smtp_code" => $code,
               		"smtp_msg"  => substr($rply,4));
      		if($this->do_debug >= 1) 
		{
        		$this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      		}
      		return false;
    	}
	if(!stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) 
	{
      		return false;
	}
	return true;
}
public function Authenticate($username, $password, $authtype='LOGIN', $realm='', $workstation='') 
{
	if (empty($authtype)) 
	{
      		$authtype = 'LOGIN';
    	}
 	switch ($authtype) 
	{
      		case 'PLAIN':
        	// Start authentication
        	fputs($this->smtp_conn,"AUTH PLAIN" . $this->CRLF);
    		$rply = $this->get_lines();
        	$code = substr($rply,0,3);
        	if($code != 334) 
		{
          		$this->error =
            		array("error" => "AUTH not accepted from server",
                  		"smtp_code" => $code,
                  		"smtp_msg" => substr($rply,4));
          		if($this->do_debug >= 1) 
			{
            			$this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
          		}
          		return false;
        	}
        	fputs($this->smtp_conn, base64_encode("\0".$username."\0".$password) . $this->CRLF);
        	$rply = $this->get_lines();
        	$code = substr($rply,0,3);
        	if($code != 235) 
		{
          		$this->error =
            			array("error" => "Authentication not accepted from server",
                  		"smtp_code" => $code,
                  		"smtp_msg" => substr($rply,4));
          			if($this->do_debug >= 1) 
				{
            				$this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
          			}
          			return false;
        	}
        	break;
      		case 'LOGIN':
        		// Start authentication
        		fputs($this->smtp_conn,"AUTH LOGIN" . $this->CRLF);
        		$rply = $this->get_lines();
        		$code = substr($rply,0,3);
        		if($code != 334) 
			{
          			$this->error =
            				array("error" => "AUTH not accepted from server",
                  			"smtp_code" => $code,
                  			"smtp_msg" => substr($rply,4));
          			if($this->do_debug >= 1) 
				{
            				$this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
          			}
          			return false;
        		}
        		fputs($this->smtp_conn, base64_encode($username) . $this->CRLF);
        		$rply = $this->get_lines();
        		$code = substr($rply,0,3);
        		if($code != 334) 
			{
          			$this->error =
            			array("error" => "Username not accepted from server",
                  		"smtp_code" => $code,
                  		"smtp_msg" => substr($rply,4));
          			if($this->do_debug >= 1) 
				{
            				$this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
          			}
          			return false;
        		}
        		fputs($this->smtp_conn, base64_encode($password) . $this->CRLF);
        		$rply = $this->get_lines();
        		$code = substr($rply,0,3);
        		if($code != 235) 
			{
          			$this->error =
            			array("error" => "Password not accepted from server",
                  		"smtp_code" => $code,
                  		"smtp_msg" => substr($rply,4));
          			if($this->do_debug >= 1) 
				{
            				$this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
          			}
          			return false;
        		}
        		break;
      			case 'NTLM':
        			require_once('ntlm_sasl_client.php');
        			$temp = new stdClass();
        			$ntlm_client = new ntlm_sasl_client_class;
        			if(! $ntlm_client->Initialize($temp))
				{
					//let's test if every function its available
            				$this->error = array("error" => $temp->error);
            				if($this->do_debug >= 1) {
                			$this->edebug("You need to enable some modules in your php.ini file: " . $this->error["error"] . $this->CRLF);
            			}
            			return false;
        		}
        		$msg1 = $ntlm_client->TypeMsg1($realm, $workstation);//msg1
        		fputs($this->smtp_conn,"AUTH NTLM " . base64_encode($msg1) . $this->CRLF);
        		$rply = $this->get_lines();
        $code = substr($rply,0,3);
        if($code != 334) {
            $this->error =
                array("error" => "AUTH not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF);
            }
            return false;
        }
        $challange = substr($rply,3);//though 0 based, there is a white space after the 3 digit number....//msg2
        $challange = base64_decode($challange);
        $ntlm_res = $ntlm_client->NTLMResponse(substr($challange,24,8),$password);
        $msg3 = $ntlm_client->TypeMsg3($ntlm_res,$username,$realm,$workstation);//msg3
        // Send encoded username
        fputs($this->smtp_conn, base64_encode($msg3) . $this->CRLF);
        $rply = $this->get_lines();
        $code = substr($rply,0,3);
        if($code != 235) {
            $this->error =
                array("error" => "Could not authenticate",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF);
            }
            return false;
        }
        break;
    }
    return true;
  }
  public function Connected() {
    if(!empty($this->smtp_conn)) {
      $sock_status = socket_get_status($this->smtp_conn);
      if($sock_status["eof"]) {
        // the socket is valid but we are not connected
        if($this->do_debug >= 1) {
            $this->edebug("SMTP -> NOTICE:" . $this->CRLF . "EOF caught while checking if connected");
        }
        $this->Close();
        return false;
      }
      return true; // everything looks good
    }
    return false;
  }
  public function Close() {
    $this->error = null; // so there is no confusion
    $this->helo_rply = null;
    if(!empty($this->smtp_conn)) {
      // close the connection and cleanup
      fclose($this->smtp_conn);
      $this->smtp_conn = 0;
    }
  }
  public function Data($msg_data) {
    $this->error = null; // so no confusion is caused

    if(!$this->connected()) {
      $this->error = array(
              "error" => "Called Data() without being connected");
      return false;
    }
    fputs($this->smtp_conn,"DATA" . $this->CRLF);
    $rply = $this->get_lines();
    $code = substr($rply,0,3);
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER:" . $rply . $this->CRLF . '<br />');
    }
    if($code != 354) {
      $this->error =
        array("error" => "DATA command not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      }
      return false;
    }
    $msg_data = str_replace("\r\n","\n",$msg_data);
    $msg_data = str_replace("\r","\n",$msg_data);
    $lines = explode("\n",$msg_data);
    $field = substr($lines[0],0,strpos($lines[0],":"));
    $in_headers = false;
    if(!empty($field) && !strstr($field," ")) {
      $in_headers = true;
    }
    $max_line_length = 998; // used below; set here for ease in change
    while(list(,$line) = @each($lines)) {
      $lines_out = null;
      if($line == "" && $in_headers) {
        $in_headers = false;
      }
      while(strlen($line) > $max_line_length) {
        $pos = strrpos(substr($line,0,$max_line_length)," ");
        if(!$pos) {
          $pos = $max_line_length - 1;
          $lines_out[] = substr($line,0,$pos);
          $line = substr($line,$pos);
        } else {
          $lines_out[] = substr($line,0,$pos);
          $line = substr($line,$pos + 1);
        }
        if($in_headers) {
          $line = "\t" . $line;
        }
      }
      $lines_out[] = $line;
      while(list(,$line_out) = @each($lines_out)) {
        if(strlen($line_out) > 0)
        {
          if(substr($line_out, 0, 1) == ".") {
            $line_out = "." . $line_out;
          }
        }
        fputs($this->smtp_conn,$line_out . $this->CRLF);
      }
    }
    fputs($this->smtp_conn, $this->CRLF . "." . $this->CRLF);
    $rply = $this->get_lines();
    $code = substr($rply,0,3);
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER:" . $rply . $this->CRLF . '<br />');
    }
    if($code != 250) {
      $this->error =
        array("error" => "DATA not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      }
      return false;
    }
    return true;
  }
  public function Hello($host = '') {
    $this->error = null; // so no confusion is caused
    if(!$this->connected()) {
      $this->error = array(
            "error" => "Called Hello() without being connected");
      return false;
    }
    if(empty($host)) {
      // determine appropriate default to send to server
      $host = "localhost";
    }
    if(!$this->SendHello("EHLO", $host)) {
      if(!$this->SendHello("HELO", $host)) {
        return false;
      }
    }
    return true;
  }
  private function SendHello($hello, $host) {
    fputs($this->smtp_conn, $hello . " " . $host . $this->CRLF);
    $rply = $this->get_lines();
    $code = substr($rply,0,3);
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER: " . $rply . $this->CRLF . '<br />');
    }
    if($code != 250) {
      $this->error =
        array("error" => $hello . " not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      }
      return false;
    }
    $this->helo_rply = $rply;
    return true;
  }
  public function Mail($from) {
    $this->error = null; // so no confusion is caused
    if(!$this->connected()) {
      $this->error = array(
              "error" => "Called Mail() without being connected");
      return false;
    }
    $useVerp = ($this->do_verp ? " XVERP" : "");
    fputs($this->smtp_conn,"MAIL FROM:<" . $from . ">" . $useVerp . $this->CRLF);
    $rply = $this->get_lines();
    $code = substr($rply,0,3);
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER:" . $rply . $this->CRLF . '<br />');
    }
    if($code != 250) {
      $this->error =
        array("error" => "MAIL not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      }
      return false;
    }
    return true;
  }
  public function Quit($close_on_error = true) {
    $this->error = null; // so there is no confusion
    if(!$this->connected()) {
      $this->error = array(
              "error" => "Called Quit() without being connected");
      return false;
    }
    fputs($this->smtp_conn,"quit" . $this->CRLF);
    $byemsg = $this->get_lines();
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER:" . $byemsg . $this->CRLF . '<br />');
    }
    $rval = true;
    $e = null;
    $code = substr($byemsg,0,3);
    if($code != 221) {
      $e = array("error" => "SMTP server rejected quit command",
                 "smtp_code" => $code,
                 "smtp_rply" => substr($byemsg,4));
      $rval = false;
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $e["error"] . ": " . $byemsg . $this->CRLF . '<br />');
      }
    }
    if(empty($e) || $close_on_error) {
      $this->Close();
    }

    return $rval;
  }
  public function Recipient($to) {
    $this->error = null; // so no confusion is caused
    if(!$this->connected()) {
      $this->error = array(
              "error" => "Called Recipient() without being connected");
      return false;
    }
    fputs($this->smtp_conn,"RCPT TO:<" . $to . ">" . $this->CRLF);
    $rply = $this->get_lines();
    $code = substr($rply,0,3);
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER:" . $rply . $this->CRLF . '<br />');
    }
    if($code != 250 && $code != 251) {
      $this->error =
        array("error" => "RCPT not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      }
      return false;
    }
    return true;
  }
  public function Reset() {
    $this->error = null; // so no confusion is caused

    if(!$this->connected()) {
      $this->error = array(
              "error" => "Called Reset() without being connected");
      return false;
    }
    fputs($this->smtp_conn,"RSET" . $this->CRLF);
    $rply = $this->get_lines();
    $code = substr($rply,0,3);
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER:" . $rply . $this->CRLF . '<br />');
    }
    if($code != 250) {
      $this->error =
        array("error" => "RSET failed",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      }
      return false;
    }
    return true;
  }
  public function SendAndMail($from) {
    $this->error = null; // so no confusion is caused
    if(!$this->connected()) {
      $this->error = array(
          "error" => "Called SendAndMail() without being connected");
      return false;
    }
    fputs($this->smtp_conn,"SAML FROM:" . $from . $this->CRLF);
    $rply = $this->get_lines();
    $code = substr($rply,0,3);
    if($this->do_debug >= 2) {
      $this->edebug("SMTP -> FROM SERVER:" . $rply . $this->CRLF . '<br />');
    }
    if($code != 250) {
      $this->error =
        array("error" => "SAML not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if($this->do_debug >= 1) {
        $this->edebug("SMTP -> ERROR: " . $this->error["error"] . ": " . $rply . $this->CRLF . '<br />');
      }
      return false;
    }
    return true;
  }
  public function Turn() {
    $this->error = array("error" => "This method, TURN, of the SMTP ".
                                    "is not implemented");
    if($this->do_debug >= 1) {
      $this->edebug("SMTP -> NOTICE: " . $this->error["error"] . $this->CRLF . '<br />');
    }
    return false;
  }
  public function getError() {
    return $this->error;
  }
  private function get_lines() {
    $data = "";
    $endtime = 0;
    if (!is_resource($this->smtp_conn)) {
      return $data;
    }
    stream_set_timeout($this->smtp_conn, $this->Timeout);
    if ($this->Timelimit > 0) {
      $endtime = time() + $this->Timelimit;
    }
    while(is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
      $str = @fgets($this->smtp_conn,515);
      if($this->do_debug >= 4) {
        $this->edebug("SMTP -> get_lines(): \$data was \"$data\"" . $this->CRLF . '<br />');
        $this->edebug("SMTP -> get_lines(): \$str is \"$str\"" . $this->CRLF . '<br />');
      }
      $data .= $str;
      if($this->do_debug >= 4) {
        $this->edebug("SMTP -> get_lines(): \$data is \"$data\"" . $this->CRLF . '<br />');
      }
      if(substr($str,3,1) == " ") { break; }
      $info = stream_get_meta_data($this->smtp_conn);
      if ($info['timed_out']) {
        if($this->do_debug >= 4) {
          $this->edebug("SMTP -> get_lines(): timed-out (" . $this->Timeout . " seconds) <br />");
        }
        break;
      }
      if ($endtime) {
        if (time() > $endtime) {
          if($this->do_debug >= 4) {
            $this->edebug("SMTP -> get_lines(): timelimit reached (" . $this->Timelimit . " seconds) <br />");
          }
          break;
        }
      }
    }
    return $data;
  }
}
?>