<?php namespace Utilities;

use \PHPMailer; 

class NotificationUtil {
	
	public $db = null;
	
	public $mail = null;
	
	public function __construct( $db = null ) {

		$this->db = $db;

		//Create a new PHPMailer instance
		$this->mail = new PHPMailer;
	
		self::emailConfig();
				
	}
	
	private function emailConfig(){

		//Set character encoding
		$this->mail->CharSet="UTF-8";		
		
		//Tell PHPMailer to use SMTP
		$this->mail->isSMTP();
		
		//Enable SMTP debugging
		// 0 = off (for production use)
		// 1 = client messages
		// 2 = client and server messages
		$this->mail->SMTPDebug = 2;
		
		//Ask for HTML-friendly debug output
		$this->mail->Debugoutput = 'html';
		
		//Set the hostname of the mail server
		$this->mail->Host = "smtp.gmail.com";
		
		//Set the SMTP port number - likely to be 25, 465 or 587
		$this->mail->Port = 587;
		
		//Whether to use SMTP authentication
		$this->mail->SMTPAuth = true;
		
		//Secure
		//$this->mail->SMTPSecure = 'ssl';
		$this->mail->SMTPSecure = 'tls';
		
		//Username to use for SMTP authentication
		$this->mail->Username = "d0ngix.mabulay@gmail.com";
		
		//Password to use for SMTP authentication
		$this->mail->Password = "d0ngix777";
		
		//Set who the message is to be sent from
		//$this->mail->setFrom('d0ngix.mabulay@gmail.com', 'FoodTri.PH');
		
		//Set an alternative reply-to address
		$this->mail->addReplyTo('d0ngix.mabulay@gmail.com', 'FoodTri.PH');		
	}
	
	//email notification for new user
	public function emailNewUser($data) {
		

		//Set who the message is to be sent to
		$this->mail->addAddress($data['email'], "$data[first_name] @$data[last_name]");
		
		//Set the subject line
		$this->mail->Subject = 'FoodTri.PH Email Verification';
		
		//Read an HTML message body from an external file, convert referenced images to embedded,
		//convert HTML into a basic plain-text alternative body		
		$this->mail->msgHTML(file_get_contents(ROOT_DIR . "/public/email/emailNewUser.html"));
		//$this->mail->Body    = 'This is the HTML message body <b>in bold!</b>';

		//Replace the firsname place holder
		$this->mail->Body = str_replace('[USER_FIRSTNAME]', ucfirst($data['first_name']), $this->mail->Body);						
		
		//Generate email verification
		$isSSL = empty($_SERVER['HTTPS']) ? 'http' : 'https';
		$strEmailVerifiy = $isSSL . '://' . $_SERVER['HTTP_HOST'] . '/user/verify/email?email=dGVzdEB0ZXN0LmNvbQ==&hash=c4ca4238a0b923820dcc509a6f75849b';
		$this->mail->Body = str_replace('[VERIFY_URL]', $strEmailVerifiy, $this->mail->Body);

		//Replace the plain text body with one created manually
		//$this->mail->AltBody = 'This is a plain-text message body';

		//Attach an image file
		//$this->mail->addAttachment('images/phpmailer_mini.png');
		
		//send the message, check for errors
		if (!$this->mail->send()) {
		    echo "Mailer Error: " . $this->mail->ErrorInfo;
		    
		} else {
		    echo "Message sent!";
		}
		die;
	}
	
	//send notification reciept
	
	//send status update
	
	//send promotions
	
	//send sms verfication
	
}


