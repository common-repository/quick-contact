<?php 
/*
Plugin Name: Quick Contact
Plugin URI: http://quickcontact.squarecompass.com/
Description: Places a basic contact form on your website. See <a href="http://quickcontact.squarecompass.com/documentation/installation/">http://quickcontact.squarecompass.com/documentation/installation/</a> for quick instalation instructions.
Version: 0.5.4 Beta
Author: Square Compass, LLC
Author URI: http://squarecompass.com/
*/
session_start();
/*
* Respond to AJAX Requests
*/
if(@$_POST["quick_contact_action"] == "send_message") { //Process Ajax
	//Load WordPress
	$wp_root = explode("wp-content",$_SERVER["SCRIPT_FILENAME"]);
	$wp_root = $wp_root[0];
	if($wp_root == $_SERVER["SCRIPT_FILENAME"]) {
		$wp_root = explode("index.php",$_SERVER["SCRIPT_FILENAME"]);
		$wp_root = $wp_root[0];
	}
	chdir($wp_root);
	if(!function_exists("add_action")) require_once(file_exists("wp-load.php")?"wp-load.php":"wp-config.php");
	$v = quick_contact_trim_array($_POST); //Get POST variables
	if(!empty($_POST["quick_contact_message"])) //Get POST With newlines
		$v["quick_contact_message"] = wp_kses(
			str_replace(array("<br>","<br >","<br/>","<br />","\n","\r"."\r\n"),"\r\n",stripslashes($_POST["quick_contact_message"])),
			array() //We are sending a text email, so get rid of all HTML 
		);
	//Check for Errors
	$errors = array();
	if(empty($v["quick_contact_name"]))
		$errors[] = "Please enter your name.";
	elseif(!preg_match("/^[\w\d\s]+$/",$v["quick_contact_name"]))
		$errors[] = "Your name must be alpha-numeric.";
	if(empty($_POST["quick_contact_email"]) && empty($_POST["quick_contact_phone"]))
		$errors[] = "Please enter either an email address or a phone number for us to contact you.";
	if(
		(!empty($v["quick_contact_email"]) && !is_email($v['quick_contact_email'])) ||
		(empty($v["quick_contact_email"]) && !empty($_POST["quick_contact_email"]))
	)
		$errors[] = "The entered email is not valid.";
	if(
		(!empty($v["quick_contact_phone"]) && !preg_match("/^[\w\d\s\(\)\+\.\-_]{7,20}$/",$v["quick_contact_phone"])) || 
		(empty($v["quick_contact_phone"]) && !empty($_POST["quick_contact_phone"]))
	)
		$errors[] = "The entered phone is not valid. Please enter you phone in the form, ###.###.#### or (###) ###-####.";
	if(empty($v["quick_contact_message"]) && !empty($_POST["quick_contact_message"]))
		$errors[] = "Your message contains special characters that cannot be sent.";
	$reset_captcha = false;
	if(empty($v["quick_contact_captcha"]))
		$errors[] = "Please enter the text from the image.";
	elseif(empty($_SESSION["quick_contact_captcha"]))
		die("There was an error submitting your message. Please refresh the page and try again.");
	elseif(strtoupper($v["quick_contact_captcha"]) != strtoupper($_SESSION["quick_contact_captcha"])) {
		$errors[] = "The entered text did not match the image, please re-enter the text from the image.";
		$reset_captcha = true;
	}
	if(count($errors) > 0) {
		//AJAX Response
		$errors_str = "";
		foreach($errors as $err)
			$errors_str .= "- ".$err."\\n";
		die(
			($reset_captcha?"quick_contact_reset_captcha();":"").
			"var quick_contact_submit_message = document.getElementById('quick_contact_submit_message');".
			"quick_contact_submit_message.className = 'quick_contact_error';".
			"quick_contact_submit_message.innerHTML = 'Please fix errors and submit again';".
			"var quick_contact_submit = document.getElementById('quick_contact_submit');".
			"quick_contact_submit.disabled = false;".
			"alert(\"Unable to send message:\\n$errors_str\\n\\nPlease fix the above error".(count($errors) > 1?"s":"")." and resubmit.\");"
		);
	} else {
		//Get Reply to Email
		$site_url = strtolower(get_option("siteurl"));
		$reply_to = $v["quick_contact_email"];
		if(empty($reply_to)) { //Build no-reply email address
			//Remove http:// or https://
			if(substr($site_url,0,7) == "http://")
				$reply_to = substr($site_url,7);
			elseif(substr($site_url,0,8) == "https://")
				$reply_to = substr($site_url,8);
			//Remove www.
			if(substr($site_url,0,4) == "www.")
				$reply_to = substr($site_url,3);
			//Remove subdomain
			if(strpos($reply_to,'.') != strrpos($reply_to,'.'))
				$reply_to = substr($reply_to,strpos($reply_to,'.')+1);
			$reply_to = "no-reply@$reply_to";
		}
		//Send Mail
		wp_mail(
			get_option("admin_email"),
			"A Message from ".$v["quick_contact_name"],
			$v["quick_contact_name"]." has submitted the following message:\r\n\r\n".
				(empty($v["quick_contact_message"])?"No Message Submitted":stripslashes($v["quick_contact_message"]))."\r\n\r\n".
				"Reply To:\r\n".
				(empty($v["quick_contact_phone"])?"":$v["quick_contact_phone"]."\r\n").$v["quick_contact_email"]."\r\n\r\n".
				"Sent via Quick Contact on ".get_option("siteurl"),
			"From: ".$v["quick_contact_name"]." <$reply_to>"
		);
		//AJAX Response
		die(
			"quick_contact_reset_form();".
			"quick_contact_reset_captcha();".
			"var quick_contact_submit_message = document.getElementById('quick_contact_submit_message');".
			"quick_contact_submit_message.className = 'quick_contact_message';".
			"quick_contact_submit_message.innerHTML = 'Your Message has been Sent';"
		);
	}
	die("alert(\"An error occurred while sending message. Please refresh the page and try again.\")");
}
/*
*  Display Captcha
*/
if(@$_REQUEST["quick_contact_action"] == "get_captcha") { 
	quick_contact_captcha(); 
}
/*
* Add functions
*/
//Add Actions
add_action('wp_head','quick_contact_js_header'); //Add Ajax to the admin side
//Add Actions
add_shortcode("quick_contact","quick_contact_display_form"); //Add ShortCode for "Add Form"
/*
*  Variables
*/
$quick_contact_name_autofill = "Please enter your name...";
$quick_contact_email_autofill = "So that we can reply to you...";
$quick_contact_phone_autofill = "So that we can reply to you...";
$quick_contact_message_autofill = "Questions? Comments? Enter them here (no HTML please)...";
$quick_contact_captcha_autofill = "Type text at left...";
$quick_contact_url = get_option("siteurl")."/wp-content/plugins/quick-contact/quick-contact.php";
/*
* Display Contact Form
*/
function quick_contact_display_form() {
	return 
		"<style>".
			"#quick_contact_form{ ".
				"width:100%; ".
				"padding:5px 10px 15px 10px; ".
				"position:relative; ".
			"} ".
			"#quick_contact_form th,#quick_contact_form td{ vertical-align:top; } ".
			"#quick_contact_form th{ ".
				"text-align:right; ".
				"padding-right:5px; ".
				"font-weight:bold; ".
				"width:25%; ".
			"} ".
			"#quick_contact_form td{ ".
				"text-align:left; ".
				"width:75%; ".
			"} ".
			"#quick_contact_form input{ width:80%; } ".
			"#quick_contact_form textarea{ ".
				"width:95%; ".
				"height:75px; ".
			"} ".
			"#quick_contact_submit,#quick_contact_cancel{ width:50px; } ".
			".quick_contact_message{ color:#009900; } ".
			".quick_contact_error{ color:#FF0000; } ".
			".quick_contact_faded{ color:#888888; } ".
			".quick_contact_focused{ color:#000000; } ".
		"</style>".
		"<form id='quick_contact_form'>".quick_contact_form()."</form>"
	;
}
function quick_contact_form($with_slashes = false) {
	global $quick_contact_name_autofill,$quick_contact_email_autofill,$quick_contact_phone_autofill,$quick_contact_message_autofill,$quick_contact_captcha_autofill;
	//Form
	$form =
		"<table width='100%'  border='0' cellspacing='0' cellpadding='0'>".
		  "<tr>".
			"<th>Name:</th>".
			"<td>".
				"<input ".
					"type='text' ".
					"id='quick_contact_name' ".
					"class='quick_contact_faded' ".
					"value='$quick_contact_name_autofill' ".
					"maxlength='100' ".
					"onFocus='if(this.value == \"$quick_contact_name_autofill\"){ this.value = \"\"; this.className = \"quick_contact_focused\"; }'".
				"/>".
			"</td>".
		  "</tr>".
		  "<tr>".
			"<th>Email:</th>".
			"<td>".
				"<input ".
					"type='text' ".
					"id='quick_contact_email' ".
					"class='quick_contact_faded' ".
					"value='$quick_contact_email_autofill' ".
					"maxlength='150' ".
					"onFocus='if(this.value == \"$quick_contact_email_autofill\"){ this.value = \"\"; this.className = \"quick_contact_focused\"; };'".
				"/>".
			"</td>".
		  "</tr>".
		  "<tr>".
			"<th>Phone:</th>".
			"<td>".
				"<input ".
					"type='text' ".
					"id='quick_contact_phone' ".
					"class='quick_contact_faded' ".
					"value='$quick_contact_phone_autofill' ".
					"maxlength='100' ".
					"onFocus='if(this.value == \"$quick_contact_phone_autofill\"){ this.value = \"\"; this.className = \"quick_contact_focused\"; };'".
				"/>".
			"</td>".
		  "</tr>".
		  "<tr>".
			"<th>Message:</th>".
			"<td>".
				"<textarea ".
					"id='quick_contact_message' ".
					"class='quick_contact_faded' ".
					"onFocus='if(this.value == \"$quick_contact_message_autofill\"){ this.value = \"\"; this.className = \"quick_contact_focused\"; };'".
				">$quick_contact_message_autofill</textarea>". 
			"</td>".
		  "</tr>".
		  "<tr>".
			"<th><img id='quick_contact_captcha' src='$quick_contact_url?quick_contact_action=get_captcha' align='right'/></th>".
			"<td>".
				"<input ".
					"type='text' ".
					"id='quick_contact_captcha_text' ".
					"class='quick_contact_faded' ".
					"style='width:110px;' ".
					"value='$quick_contact_captcha_autofill' ".
					"maxlength='20' ".
					"onFocus='if(this.value == \"$quick_contact_captcha_autofill\"){ this.value = \"\"; this.className = \"quick_contact_focused\"; };'".
				"/> ".
				"<small><a href='javascript:quick_contact_reset_captcha();'>I can't read the text, please reset it.</a></small>".
			"</td>".
		  "</tr>".
		  "<tr>".
			"<th>&nbsp;</th>".
			"<td style='padding-top:2px;'>".
				"<input type='submit' id='quick_contact_submit' value='Send' onClick='quick_contact_send(); return false;' style='width:50px;'>".
				"<input type='button' id='quick_contact_cancel' value='Cancel' style='width:60px;' onClick='quick_contact_reset_form();'> ".
				"<span style='font-size:0.7em;'>Powered by <a href='http://quickcontact.squarecompass.com/'>Quick Contact</a></span><br/>".
				"<div id='quick_contact_submit_message'>&nbsp;</div>".
			"</td>".
		  "</tr>".
		  "<tr>".
			"<th>&nbsp;</th>".
			"<td></td>".
		  "</tr>".
		"</table>"
	;
	if($with_slashes)
		return str_replace("'","\'",$form);
	return $form;
}
/*
*  Set Header for Ajax Form Submit
*/
function quick_contact_js_header() {
	global $quick_contact_name_autofill,$quick_contact_email_autofill,$quick_contact_phone_autofill;
	global $quick_contact_message_autofill,$quick_contact_captcha_autofill,$quick_contact_url;
	wp_print_scripts(array('sack'));//Include Ajax SACK library 
	// Define custom JavaScript function
	?>
		<script type="text/javascript">
			function quick_contact_send() { //Send Message Ajax Call
				//Deactivate submit button 
				var submit_button = document.getElementById('quick_contact_submit');
				submit_button.disabled = true;
				//Get Input Fields (Clear them if they are the autofill message)
				var name = document.getElementById('quick_contact_name').value;
				if(name == "<?=$quick_contact_name_autofill?>")
					name = "";
				var email = document.getElementById('quick_contact_email').value;
				if(email == "<?=$quick_contact_email_autofill?>")
					email = "";
				var phone = document.getElementById('quick_contact_phone').value;
				if(phone == "<?=$quick_contact_phone_autofill?>")
					phone = "";
				var message = document.getElementById('quick_contact_message').value;
				if(message == "<?=$quick_contact_message_autofill?>")
					message = "";
				var captcha = document.getElementById('quick_contact_captcha_text').value;
				if(captcha == "<?=$quick_contact_captcha_autofill?>")
					captcha = "";
				//Ensure that there is at least a name and an email or phone
				var error_default = "Please fill out the following before submitting:\n";
				var error = error_default;
				if(name == "" || name == null)
					error += "  - Please enter your name.\n";
				if((email == "" || email == null) && (phone == "" || phone == null)) 
					if(error != "") error += "  - Please enter either a phone number or email for us to contact you.\n";
				if(captcha == "" || captcha == null)
					error += "  - Please enter the text from the image.\n";
				if(error != error_default) {
					submit_button.disabled = false;
					alert(error);
					return;
				}
				//If the message is empty ask the user to conferm that they ment to hit the submit button
				if(message == "" && !confirm("Are you sure you want to send without entering a message?")) {
					submit_button.disabled = false;
					return;
				}
				//Display processing message
				submit_message = document.getElementById('quick_contact_submit_message');
				submit_message.className = "quick_contact_message";
				submit_message.innerHTML = "Submitting Message, Please Wait...";
				//Build SACK Call
				var mysack = new sack("<?=$quick_contact_url?>");
				mysack.execute = 1;
				mysack.method = 'POST';
				mysack.setVar("quick_contact_name",name);
				mysack.setVar("quick_contact_email",email);
				mysack.setVar("quick_contact_phone",phone);
				mysack.setVar("quick_contact_message",message);
				mysack.setVar("quick_contact_captcha",captcha);
				mysack.setVar("quick_contact_action","send_message");
				mysack.onError = function() { alert('An ajax error occured while sending your message. Please reload the page and try again.') };
				mysack.runAJAX();//excecute
				return;
			}
			
			function quick_contact_reset_form() { //Reset Form Javascript Call
				document.getElementById('quick_contact_form').innerHTML = '<?=quick_contact_form(true)?>';
			}
			
			var quick_contact_reset_captcha_count = 0;
			function quick_contact_reset_captcha() { //Reset Form Javascript Call
				quick_contact_reset_captcha_count++;
				document.getElementById('quick_contact_captcha').src = '<?=$quick_contact_url?>?quick_contact_action=get_captcha&count='+quick_contact_reset_captcha_count;
			}
		</script>
	<? 
}
/*
*  Captcha Functions
*/
function quick_contact_captcha() {
	//Get Random String$string = '';
	$chars = "ABCDGHJKMNPRUVWXY346789";
	$code = "";
    for($i=0;$i<5;$i++) {
        $pos = mt_rand(0,strlen($chars)-1);
        $code .= $chars{$pos};
    }
	$_SESSION["quick_contact_captcha"] = $code;
	//Create Image
	$width = 60; 
	$height = 20; 
	$image = imagecreate($width, $height); 
	imagefill($image,0,0,imagecolorallocate($image,255,255,255)); //Set Backround
	//Draw 2 rectangles
	imagefilledrectangle($image,rand(10,50),rand(3,17),rand(5,55),rand(5,20),imagecolorallocate($image,rand(175,255),rand(175,255),rand(175,255)));
	imagefilledrectangle($image,rand(10,50),rand(3,17),rand(5,55),rand(5,20),imagecolorallocate($image,rand(175,255),rand(175,255),rand(175,255)));
	//Draw an elipse
	imageellipse($image,rand(10,50),rand(3,17),rand(5,55),rand(5,20),imagecolorallocate($image,rand(175,255),rand(175,255),rand(175,255)));
	//Draw 2 lines to make it a little bit harder for any bots to break 
	imageline($image,0,3+rand(0,5),$width,3+rand(0,5),imagecolorallocate($image,rand(0,255),rand(0,255),rand(0,255)));
	imageline($image,0,11+rand(0,5),$width,11+rand(0,5),imagecolorallocate($image,rand(0,255),rand(0,255),rand(0,255)));
	//Add randomly generated string to the image
	$start = 3;
	for($i=0;$i<5;$i++) {
		$start += rand(0,3);
		imagestring($image,5,$start,rand(2,5),substr($code,$i,1),imagecolorallocate($image,rand(0,125),rand(0,125),rand(0,125)));
		$start += 9;
	}
	imagerectangle($image,0,0,59,19,imagecolorallocate($image,0,0,0)); //Put Border around image
	header("Content-Type: image/jpeg"); //Add JPG Header
	imagejpeg($image); //Output the newly created image in jpeg format 
	imagedestroy($image); //Free up resources
} 
/*
*  HELPER FUNCTIONS
*/
function quick_contact_trim_array($v,$stripHTML = true,$keepEmpty = false,$unset = false) {
	if(is_array($v)) {
		$array = stripslashes_deep($v);
		if(is_array($unset))
			foreach($unset as $key)
				unset($array[$key]);
		$res = array();
		foreach($array as $key=>$value) {
			$k = str_replace(array("\r\n","\n","\r"),"",strip_tags(trim($key)));
			$v = $stripHTML?str_replace(array("\r\n","\n","\r"),"",strip_tags(trim($value))):str_replace(array("\r\n","\n","\r"),"",trim($value));
			if(!empty($k) && (is_string($k) || is_numeric($k)) && ((!empty($v) && (is_string($v)) || is_numeric($v)) || ($keepEmpty && empty($v))))
				$res[$k] = $v;
		}
		return $res;
	}
	return array();
}
