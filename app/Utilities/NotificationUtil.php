<?php namespace Utilities;

use \PHPMailer; 

class NotificationUtil {
	
	public $db = null;
	
	public $mail = null;
	
	public function __construct( $db = null, $jwt, $manifest ) {
	
		$this->db = $db;
		$this->jwt = $jwt;
		$this->manifest = $manifest;

		//Create a new PHPMailer instance
		$this->mail = new PHPMailer;		
		self::emailConfig();

		//MonoLogger
		$this->logger = new \Monolog\Logger($this->manifest->company->code . '_log');
		$file_handler = new \Monolog\Handler\StreamHandler("logs/smtp_err.log");
		$this->logger->pushHandler($file_handler);		
	}	
		
	//email config
	private function emailConfig() {
		
		//Set character encoding
		$this->mail->CharSet = $this->manifest->smtp->CharSet;		
		
		//Tell PHPMailer to use SMTP
		$this->mail->isSMTP();
		
		//Enable SMTP debugging
		// 0 = off (for production use)
		// 1 = client messages
		// 2 = client and server messages
		$this->mail->SMTPDebug = $this->manifest->smtp->SMTPDebug;
		
		//Ask for HTML-friendly debug output
		$this->mail->Debugoutput = $this->manifest->smtp->Debugoutput;
		
		//Set the hostname of the mail server
		$this->mail->Host = $this->manifest->smtp->Host;
		
		//Set the SMTP port number - likely to be 25, 465 or 587
		$this->mail->Port = $this->manifest->smtp->Port;
		
		//Whether to use SMTP authentication
		$this->mail->SMTPAuth = $this->manifest->smtp->SMTPAuth;
		
		//Secure
		$this->mail->SMTPSecure = $this->manifest->smtp->SMTPSecure;
		
		//Username to use for SMTP authentication
		$this->mail->Username = $this->manifest->smtp->account->Username;
		
		//Password to use for SMTP authentication
		$this->mail->Password = $this->manifest->smtp->account->Password;
		
		//Set who the message is to be sent from
		$this->mail->setFrom(
				$this->manifest->smtp->setFrom->email,
				$this->manifest->smtp->setFrom->name
		);
		
		//Set an alternative reply-to address
		$this->mail->addReplyTo(
				$this->manifest->smtp->addReplyTo->email,
				$this->manifest->smtp->addReplyTo->name
		);				
	}
	
	//email notification for new user
	public function emailNewUser($data) {

		//Set who the message is to be sent to
		$this->mail->addAddress($data['email'], "$data[first_name] @$data[last_name]");
		
		//Set the subject line
		$this->mail->Subject = '['.$this->manifest->company->name.'] Email Verification';
		
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
		    $this->logger->addError("Mailer Error: " . $this->mail->ErrorInfo);
		    return false;
		} 
		
		return true;
	}
	
	//send notification new order reciept
	public function emailNewOrder ($data) {

		//format the order items in TR
		$intSN = 0;
		$strItems = '';
		foreach ($data['items'] as $arrItem) {
			$intSN++;
						
			$strAddOns = '';
			if (!empty($arrItem['add_ons'])) {

				foreach (json_decode($arrItem['add_ons'], true) as $arrAddOns) {
					$strAddOns .= $arrAddOns['name'] . ' - ' . $arrAddOns['price'] . '  x ' . $arrAddOns['qty'] . '<br>';
					    
				}
			}
			
			$arrItem['price'] = money_format('%i', $arrItem['price']);
			$arrItem['total_amount'] = money_format('%i', $arrItem['total_amount']);
			
			$strItems .= <<<EOT
				<tr>
					<td align="center">$intSN</td>
					<td>
						<p>$arrItem[menu_name]</p>
						<p>$strAddOns</p>
					</td>
					<td align="right">$arrItem[price]</td>
					<td align="center">$arrItem[qty]</td>
					<td align="right">$arrItem[total_amount]</td>
				</tr>		
EOT;
		}
		
		//Retrieve vendor name
		$selectStmt = $this->db->select(array('name'))->from('vendors')->where('id','=',$data['transac']['vendor_id']);
		$arrResult = $selectStmt->execute()->fetch();
		$strVendorName = $arrResult['name'];
		
		//Read an HTML message body from an external file, convert referenced images to embedded,
		//convert HTML into a basic plain-text alternative body
		$this->mail->msgHTML(file_get_contents(ROOT_DIR . "/public/email/emailNewOrder.html"));
		//$this->mail->Body    = 'This is the HTML message body <b>in bold!</b>';
		
		//Replace the place holders
		$this->mail->Body = str_replace('[USER_FIRSTNAME]', ucfirst($this->jwt->user->first_name), $this->mail->Body);
		$this->mail->Body = str_replace('[VENDOR_NAME]', $strVendorName, $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSAC_REF]', $data['transac']['uuid'], $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSAC_DATE]', date('d-M-Y h:ia'), $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSAC_PAYMENT_METHOD]', $data['transac']['payment_method'], $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSC_ITEMS]', $strItems, $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSAC_SUBTOTAL]', money_format('%i', $data['transac']['sub_total']), $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSAC_DISCOUNT]', money_format('%i', $data['transac']['discount']), $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSAC_DELIVERY_COST]', money_format('%i', $data['transac']['delivery_cost']), $this->mail->Body);
		$this->mail->Body = str_replace('[TRANSAC_TOTAL_AMOUNT]', money_format('%i', $data['transac']['total_amount']), $this->mail->Body);
		
		//Set who the message is to be sent to
		$this->mail->addAddress($this->jwt->user->email, $this->jwt->user->first_name);
		
		//Set the subject line
		$this->mail->Subject = '['.$strVendorName.'] Order confirmation';
		
		//Attach an image file
		//$this->mail->addAttachment('images/phpmailer_mini.png');
		
		//send the message, check for errors
		if (!$this->mail->send()) {
			$this->logger->addError("Mailer Error: " . $this->mail->ErrorInfo);
			return false;
		}
		
		return true;		
		
	}
	
	//send status update
	
	//send promotions
	
	//send sms verfication
	
}


