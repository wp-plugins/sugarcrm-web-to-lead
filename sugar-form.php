<?php
/*
Plugin Name: SugarCRM web-to-lead
Plugin URI: https://www.kenmoredesign.com/
Description: The plugin generates a form on any page, post, or a sidebar of your website and pushes it to Sugar CRM. Simply insert [SUGAR-FORM] within any content / HTML area.
Author: Kenmore Design LLC
Author URI: https://www.kenmoredesign.com/
Version: 1.0
*/

$cuf_version = '1.0';
$cuf_script_printed = 0;
$sugar_form = new SugarForm();

class SugarForm {

var $o;
var $captcha;
var $userdata;
var $nr = 0; 

function SugarForm() {
	$this->o = get_option('sugar_form');

	add_action('widgets_init', array( &$this, 'register_widgets'));

	add_action('admin_menu', array( &$this, 'addOptionsPage'));

	add_shortcode('SUGAR-FORM', array( &$this, 'shortcode'));

	add_action('wp_head', array( &$this, 'addStyle'));

	if ( function_exists('register_uninstall_hook') )
		register_uninstall_hook(ABSPATH.PLUGINDIR.'/sugar-form/sugar-form.php', array( &$this, 'uninstall')); 

	add_filter('plugin_action_links', array( &$this, 'pluginActions'), 10, 2);

	$this->setRecources();
}


function showForm( $params = '' ) {
	$n = ($this->nr == 0) ? '' : $this->nr;
	$this->nr++;

	if (isset($_POST['cuf_sendit'.$n]))
		$result = $this->sendMail($n, $params);
		
	$captcha = new ContactUsFormCaptcha(rand(1000000000, 9999999999));
	
	$form = '<div class="contactform" id="cuform'.$n.'">';
	
	if (!empty($result)) {
		if ($result == $this->o['msg_ok'])
			$form .= '<p class="contactform_respons">'.$result.'</p>';
		else
			$form .= '<p class="contactform_error">'.$result.'</p>';
	}

	if (empty($result) || (!empty($result) && !$this->o['hideform'])) {		
		$form .= '
			<form action="#cuform'.$n.'" method="post" id="tinyform'.$n.'">
			<div>
			<input name="cuf_name'.$n.'" id="cuf_name'.$n.'" value="" class="cuf_input" />
			<input name="cuf_sendit'.$n.'" id="cuf_sendit'.$n.'" value="1" class="cuf_input" />
			';
		for ($x = 1; $x <=15; $x++) {
			$i = 'cuf_field_'.$x.$n;
			
			$cuf_f = (isset($_POST[$i])) ? $_POST[$i] : '';
			
			$f = $this->o['field_'.$x];
			$g = $this->o['sugar_field_'.$x];
			$h = $this->o['required_field_'.$x];
			if (!empty($f)) {
				$form .= '
					<label for="'.$i.'" class="cuf_label">'.$f.':</label>';
				if ($f == 'Message') {
					$form .= '
					<textarea name="'.$g.'" id="'.$i.'" class="cuf_textarea '.(($h == 1) ? "required" : "").'" cols="50" rows="10">'.$cuf_f.'</textarea>';
				} else {
					$form .= '
					<input name="'.$g.'" id="'.$i.'" size="30" value="'.$cuf_f.'" class="cuf_field '.(($h == 1) ? "required" : "").'" />';
				}
			}
		}
		if ( $this->o['captcha'] )
			$form .= $captcha->getCaptcha($n);
		if ( $this->o['captcha2'] )
			$form .= '
			<label for="cuf_captcha2_'.$n.'" class="cuf_label">'.$this->o['captcha2_question'].'</label>
			<input name="cuf_captcha2_'.$n.'" id="cuf_captcha2_'.$n.'" size="30" class="cuf_field" />
			';
			
		$title = (!empty($this->o['submit'])) ? 'value="'.$this->o['submit'].'"' : '';
		$form .= '	
			<input type="submit" name="submit'.$n.'" id="contactsubmit'.$n.'" class="cuf_submit" '.$title.'  onclick="return checkForm(\''.$n.'\');" />
			<input type="hidden" name="required_id" value="'.(($this->o['req_id'] != "") ? $this->o['req_id'] : "").'" />
			<input type="hidden" name="campaign_id" value="'.(($this->o['campaign_id'] != "") ? $this->o['campaign_id'] : "").'" />
			<input type="hidden" name="assigned_user_id" value="'.(($this->o['assigned_user_id'] != "") ? $this->o['assigned_user_id'] : "").'" />
			</div>
			<div id="kenmorecontent" style="float:right;">
			<a href="https://www.kenmoredesign.com/" title="Web Development Company" alt="Web Development Company" target="_blank">Web Development Company</a>
			</div>
			<div style="clear:both;"></div>
			</form>';
	}
	$form .= '</div>'; 
	$form .= $this->addScript();
	return $form;
}

function addScript() {
	global $cuf_script_printed;
	if ($cuf_script_printed) 
		return;

	$script = "
		<script type=\"text/javascript\">
		function checkForm(n) {
			var f = new Array();
	";
	for ($x = 1; $x <= 15; $x++)
		if ($this->o['required_field_'.$x] == 1)
			$script .= 'f['.($x).'] = document.getElementById("cuf_field_'.$x.'").value;'."\n";

	$script .= '
		var msg = "";
		for (var i in f) {
			if (f[i] == "") {
				msg = "a";
				document.getElementById("cuf_field_"+i).setAttribute("class", "cuf_field error");
			} else {
				document.getElementById("cuf_field_"+i).setAttribute("class", "cuf_field");
			} 
		}
		if (msg != "") {
			return false;
		}
	}
	document.getElementById("kenmorecontent").style.visibility = "hidden";
	</script>
	';
	$cuf_script_printed = 1;
	return $script;
}


function sendMail($n = '', $params = '') {
	$result = $this->checkInput($n);
    if ($result == 'OK') {
    	$result = '';
    	
		$content = (is_array($_POST)) ? http_build_query($_POST) : '';

		// Parse the array of headers and strip values we will be setting
		$headers = array();
		
		// Add our pre-set headers
		$headers['Content-Type'] = 'application/x-www-form-urlencoded';
		$headers['Content-Length'] = strlen($content);
		$headers['Connection'] = 'close';
		
		// Build headers into a string
		$header = array();
		foreach ($headers as $name => $value) {
		if (is_array($value)) {
		  foreach ($value as $multi) {
		    $header[] = "$name: $multi";
		  }
		} else {
		  $header[] = "$name: $value";
		}
		}
		$header = implode("\r\n", $header);
		
		// Create the stream context
		$params = array(
		'http' => array(
		  'method' => 'POST',
		  'header' => $header,
		  'content' => $content
		)
		);
		$ctx = stream_context_create($params);
		// Make the request
		$url = $this->o['sugar_url'].'/index.php?entryPoint=WebToLeadCapture';
		$fp = @fopen($url, 'rb', FALSE, $ctx);
		if (!$fp) {
			throw new Exception("Problem with $url, $php_errormsg");
		}
		
		$response = @stream_get_contents($fp);
		if ($response === FALSE) {
			$result = $this->o['msg_err'];
		} else {
			if ( $this->o['hideform'] ) {
				unset($_POST['cuf_sender'.$n]);
				unset($_POST['cuf_email'.$n]);
				unset($_POST['cuf_subject'.$n]);
				unset($_POST['cuf_msg'.$n]);
				foreach ($_POST as $k => $f )
					if ( strpos( $k, 'cuf_field_') !== false )
						unset($k);
			}
			$result = $this->o['msg_ok'];
		}	
    }
    return $result;
}

function optionsPage() {	
	global $cuf_version;
	if (!current_user_can('manage_options'))
		wp_die(__('Sorry, but you have no permissions to change settings.'));
		
	if ( isset($_POST['cuf_save']) ) {
		$to = stripslashes($_POST['cuf_to_email']);
		if ( empty($to) )
			$to = get_option('admin_email');
		$msg_ok = stripslashes($_POST['cuf_msg_ok']);
		if ( empty($msg_ok) )
			$msg_ok = "Thank you! Your message was sent successfully.";
		$msg_err = stripslashes($_POST['cuf_msg_err']);
		if ( empty($msg_err) )
			$msg_err = "Sorry. An error occured while sending the message!";
		$captcha = ( isset($_POST['cuf_captcha']) ) ? 1 : 0;
		$captcha2 = ( isset($_POST['cuf_captcha2']) ) ? 1 : 0;
		$hideform = ( isset($_POST['cuf_hideform']) ) ? 1 : 0;
		
		$this->o = array(
			'to_email'		=> $to,
			'from_email'	=> stripslashes($_POST['cuf_from_email']),
			'css'			=> stripslashes($_POST['cuf_css']),
			'msg_ok'		=> $msg_ok,
			'msg_err'		=> $msg_err,
			'submit'		=> stripslashes($_POST['cuf_submit']),
			'captcha'		=> $captcha,
			'captcha_label'	=> stripslashes($_POST['cuf_captcha_label']),
			'captcha2'		=> $captcha2,
			'captcha2_question'	=> stripslashes($_POST['cuf_captcha2_question']),
			'captcha2_answer'	=> stripslashes($_POST['cuf_captcha2_answer']),
			'subpre'		=> stripslashes($_POST['cuf_subpre']),
			'field_1'		=> stripslashes($_POST['cuf_field_1']),
			'field_2'		=> stripslashes($_POST['cuf_field_2']),
			'field_3'		=> stripslashes($_POST['cuf_field_3']),
			'field_4'		=> stripslashes($_POST['cuf_field_4']),
			'field_5'		=> stripslashes($_POST['cuf_field_5']),
			'field_6'		=> stripslashes($_POST['cuf_field_6']),
			'field_7'		=> stripslashes($_POST['cuf_field_7']),
			'field_8'		=> stripslashes($_POST['cuf_field_8']),
			'field_9'		=> stripslashes($_POST['cuf_field_9']),
			'field_10'		=> stripslashes($_POST['cuf_field_10']),
			'field_11'		=> stripslashes($_POST['cuf_field_11']),
			'field_12'		=> stripslashes($_POST['cuf_field_12']),
			'field_13'		=> stripslashes($_POST['cuf_field_13']),
			'field_14'		=> stripslashes($_POST['cuf_field_14']),
			'field_15'		=> stripslashes($_POST['cuf_field_15']),
			'sugar_field_1'		=> stripslashes($_POST['sugar_cuf_field_1']),
			'sugar_field_2'		=> stripslashes($_POST['sugar_cuf_field_2']),
			'sugar_field_3'		=> stripslashes($_POST['sugar_cuf_field_3']),
			'sugar_field_4'		=> stripslashes($_POST['sugar_cuf_field_4']),
			'sugar_field_5'		=> stripslashes($_POST['sugar_cuf_field_5']),
			'sugar_field_6'		=> stripslashes($_POST['sugar_cuf_field_6']),
			'sugar_field_7'		=> stripslashes($_POST['sugar_cuf_field_7']),
			'sugar_field_8'		=> stripslashes($_POST['sugar_cuf_field_8']),
			'sugar_field_9'		=> stripslashes($_POST['sugar_cuf_field_9']),
			'sugar_field_10'		=> stripslashes($_POST['sugar_cuf_field_10']),
			'sugar_field_11'		=> stripslashes($_POST['sugar_cuf_field_11']),
			'sugar_field_12'		=> stripslashes($_POST['sugar_cuf_field_12']),
			'sugar_field_13'		=> stripslashes($_POST['sugar_cuf_field_13']),
			'sugar_field_14'		=> stripslashes($_POST['sugar_cuf_field_14']),
			'sugar_field_15'		=> stripslashes($_POST['sugar_cuf_field_15']),
			'required_field_1'		=> stripslashes($_POST['required_cuf_field_1']),
			'required_field_2'		=> stripslashes($_POST['required_cuf_field_2']),
			'required_field_3'		=> stripslashes($_POST['required_cuf_field_3']),
			'required_field_4'		=> stripslashes($_POST['required_cuf_field_4']),
			'required_field_5'		=> stripslashes($_POST['required_cuf_field_5']),
			'required_field_6'		=> stripslashes($_POST['required_cuf_field_6']),
			'required_field_7'		=> stripslashes($_POST['required_cuf_field_7']),
			'required_field_8'		=> stripslashes($_POST['required_cuf_field_8']),
			'required_field_9'		=> stripslashes($_POST['required_cuf_field_9']),
			'required_field_10'		=> stripslashes($_POST['required_cuf_field_10']),
			'required_field_11'		=> stripslashes($_POST['required_cuf_field_11']),
			'required_field_12'		=> stripslashes($_POST['required_cuf_field_12']),
			'required_field_13'		=> stripslashes($_POST['required_cuf_field_13']),
			'required_field_14'		=> stripslashes($_POST['required_cuf_field_14']),
			'required_field_15'		=> stripslashes($_POST['required_cuf_field_15']),
			'campaign_id'		=> stripslashes($_POST['campaign_id']),
			'assigned_user_id'		=> stripslashes($_POST['assigned_user_id']),
			'req_id'		=> stripslashes($_POST['req_id']),
			'sugar_url'		=> stripslashes($_POST['sugar_url']),
			'hideform'			=> $hideform
			);
		update_option('sugar_form', $this->o);
	}
		
	?>
	<div id="poststuff" class="wrap">
		<h2>Sugar Contact Form</h2>
		<div class="postbox">
		<h3><?php _e('Options', 'cpd') ?></h3>
		<div class="inside">
		
		<form action="options-general.php?page=contact-us-form" method="post">
	    <table class="form-table">
	    		<tr>
		
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Use', 'cuf-lang'); ?></strong></td>
		</tr>
    	<tr>
	    <th>   </th>
	    	<td>To insert the form on the page simply put the following shortcode in the HTML: <b>[SUGAR-FORM]</b></td>
		<tr>
		
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Mail', 'cuf-lang'); ?></strong></td>
		</tr>
    	<tr>
			<th><?php _e('Thank You message', 'cuf-lang')?></th>
			<td><input name="cuf_msg_ok" type="text" size="70" value="<?php echo $this->o['msg_ok'] ?>" /></td>
		</tr>
    	<tr>
			<th><?php _e('Error Message:', 'cuf-lang')?></th>
			<td><input name="cuf_msg_err" type="text" size="70" value="<?php echo $this->o['msg_err'] ?>" /></td>
		</tr>
		<tr>
			<th><?php _e('Submit Button Text:', 'cuf-lang')?> <?php _e('(optional)', 'cuf-lang'); ?></th>
			<td><input name="cuf_submit" type="text" size="70" value="<?php echo $this->o['submit'] ?>" /></td>
		</tr>
    	<tr>
			<th><?php _e('Subject Prefix:', 'cuf-lang')?> <?php _e('(optional)', 'cuf-lang'); ?></th>
			<td><input name="cuf_subpre" type="text" size="70" value="<?php echo $this->o['subpre'] ?>" /></td>
		</tr>
		<tr>
		
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Sugar Form', 'cuf-lang'); ?></strong></td>
		</tr>
		<tr>
			<th><?php _e('Sugar URL:', 'cuf-lang')?></th>
			<td><input name="sugar_url" type="text" size="70" value="<?php echo $this->o['sugar_url'] ?>" /></td>
		</tr>
		<tr>
			<th><?php _e('Sugar Campaign ID:', 'cuf-lang')?></th>
			<td><input name="campaign_id" type="text" size="70" value="<?php echo $this->o['campaign_id'] ?>" /></td>
		</tr>
		<tr>
			<th><?php _e('Assigned User ID:', 'cuf-lang')?></th>
			<td><input name="assigned_user_id" type="text" size="70" value="<?php echo $this->o['assigned_user_id'] ?>" /></td>
		</tr>
		<tr>
			<th><?php _e('Required Field:', 'cuf-lang')?> <?php _e('(last_name; etc...)', 'cuf-lang'); ?></th>
			<td><input name="req_id" type="text" size="70" value="<?php echo $this->o['req_id'] ?>" /></td>
		</tr>
		
    	<tr>
			<th><?php _e('Fields:', 'cuf-lang')?></th>
			<td>
				<p><?php _e('Add a field by writting its name and the name of the value send to SugarCRM. <br> The Field Value is teh value that is defined by SugarCRM (See either your capmpagin HTML code or SugarCRM -> Admin -> Studio) ', 'cuf-lang'); ?></p>
			</td>
		</tr>
		<tr>
		<th></th>
		<td><span style="margin-right:100px;">Field Name</span><span style="margin-right:100px;">Field Value</span><span style="">Required</span></td>
		</tr>
		
		<?php
		for ( $x = 1; $x <= 15; $x++ )
			echo '<tr><th></th><td><input style="margin-right:10px; width:150px;" name="cuf_field_'.$x.'" type="text" size="30" value="'.$this->o['field_'.$x].'" /><input style="margin-right:25px; width:150px;" name="sugar_cuf_field_'.$x.'" type="text" size="30" value="'.$this->o['sugar_field_'.$x].'" /><input type="checkbox" '.(($this->o['required_field_'.$x] == 1) ? "checked" : "").' name="required_cuf_field_'.$x.'" value="1"></td></tr>';
		?>
		
    	<tr>
			<th><?php _e('Once Submitted', 'cuf-lang')?>:</th>
			<td><label for="cuf_hideform"><input name="cuf_hideform" id="cuf_hideform" type="checkbox" <?php if($this->o['hideform']==1) echo 'checked="checked"' ?> /> <?php _e('hide the form', 'cuf-lang'); ?></label></td>
		</tr>
		<tr>
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Captcha', 'cuf-lang'); ?></strong></td>
		</tr>
    	<tr>
			<th><?php _e('Captcha', 'cuf-lang')?>:</th>
			<td><label for="cuf_captcha"><input name="cuf_captcha" id="cuf_captcha" type="checkbox" <?php if($this->o['captcha']==1) echo 'checked="checked"' ?> /> <?php _e('add two small numbers "2 + 5 ="', 'cuf-lang'); ?></label></td>
		</tr>
    	<tr>
			<th><?php _e('Captcha Label:', 'cuf-lang')?></th>
			<td><input name="cuf_captcha_label" type="text" size="70" value="<?php echo $this->o['captcha_label'] ?>" /></td>
		</tr>
    	<tr style="border-top: 1px #ddd dashed;" >
			<th><?php _e('Question Captcha:', 'cuf-lang')?></th>
			<td><label for="cuf_captcha2"><input name="cuf_captcha2" id="cuf_captcha2" type="checkbox" <?php if($this->o['captcha2']==1) echo 'checked="checked"' ?> /> <?php _e('Set you own question and answer.', 'cuf-lang'); ?></label></td>
		</tr>
    	<tr>
			<th><?php _e('Question:', 'cuf-lang')?></th>
			<td><input name="cuf_captcha2_question" type="text" size="70" value="<?php echo $this->o['captcha2_question'] ?>" /></td>
		</tr>
    	<tr>
			<th><?php _e('Answer:', 'cuf-lang')?></th>
			<td><input name="cuf_captcha2_answer" type="text" size="70" value="<?php echo $this->o['captcha2_answer'] ?>" /></td>
		</tr>
		<tr>
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Style', 'cuf-lang'); ?></strong></td>
		</tr>
    	<tr>
			<th>
				<?php _e('StyleSheet:', 'cuf-lang'); ?><br />
				<a href="javascript:resetCss();"><?php _e('reset', 'cuf-lang'); ?></a>
			</th>
			<td>
				<textarea name="cuf_css" id="cuf_css" style="width:100%" rows="10"><?php echo $this->o['css'] ?></textarea><br />
				<?php _e('Use this field or the <code>style.css</code> in your theme directory.', 'cuf-lang') ?>
			</td>
		</tr>
		</table>
		<p class="submit">
			<input name="cuf_save" class="button-primary" value="<?php _e('Save Changes'); ?>" type="submit" />
		</p>
		</form>
		
		<script type="text/javascript">
		function resetCss() {
			css = ".contactform {}\n.contactform label {}\n.contactform input {}\n.contactform textarea {}\n"
				+ ".contactform_respons {}\n.contactform_error {}\n.widget .contactform { /* sidebar fields */ }";
			document.getElementById('cuf_css').value = css;
		}
		</script>
	</div>
	</div>
	

	
	</div>
	<?php
}


function addOptionsPage() {
	global $wp_version;
	$menutitle = '';
	if ( version_compare( $wp_version, '2.6.999', '>' ) )
	$menutitle .= 'Sugar Contact Form';
	add_options_page('Contact Us Form', $menutitle, 9, 'contact-us-form', array( &$this, 'optionsPage'));
}

function shortcode($atts) {	
	extract( shortcode_atts( array(
		'to' => '',
		'subject' => ''
	), $atts) );
	$this->userdata = array(
		'to' => $to,
		'subject' => $subject
	);
	return $this->showForm();
}

function checkInput($n = '') {
	if ( !isset($_POST['cuf_sendit'.$n]))
		return false;

	if ((isset($_POST['cuf_sendit'.$n]) && $_POST['cuf_sendit'.$n] != 1)) {
		return 'No Spam please!';
	}
	
	$o = get_option('sugar_form');

	$error = array();
	
	if ( $o['captcha'] && !ContactUsFormCaptcha::isCaptchaOk() )
		$error[] = $this->o['captcha_label'];
	if ( $o['captcha2'] && ( empty($_POST['cuf_captcha2_'.$n]) || $_POST['cuf_captcha2_'.$n] != $o['captcha2_answer'] ) )
		$error[] = $this->o['captcha2_question'];
	if ( !empty($error) )
		return __('Check these fields:', 'cuf-lang').' '.implode(', ', $error);
	
	return 'OK';
}

function uninstall() {
	delete_option('contact_us_form');
}

function addStyle() {
	if ($this->o['css']) {
		echo "\n<!-- Contact Us Form -->\n"
			."<style type=\"text/css\">\n"
			.".cuf_input {display:none !important; visibility:hidden !important;}\n"
			.$this->o['css']."\n"
			."</style>\n";
	} else {
		echo "\n<!-- Contact Us Form -->\n"
			."<style type=\"text/css\">\n"
			.".cuf_input {display:none !important; visibility:hidden !important;}\n"
			."#contactsubmit:hover, #contactsubmit:focus {
	background: #849F00 repeat-x;
	color: #FFF;
	text-decoration: none;
}
#contactsubmit:active {background: #849F00}
#contactsubmit {
	color: #FFF;
	background: #738c00 repeat-x;
	display: block;
	float: left;
	height: 28px;
	padding-right: 23px;
	padding-left: 23px;
	font-size: 12px;
	text-transform: uppercase;
	text-decoration: none;
	font-weight: bold;
	text-shadow: 0px 1px 0px rgba(0, 0, 0, 0.2);
	filter: dropshadow(color=rgba(0, 0, 0, 0.2), offx=0, offy=1);
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	border-radius: 5px;
	-webkit-transition: background 300ms linear;
-moz-transition: background 300ms linear;
-o-transition: background 300ms linear;
transition: background 300ms linear;
-webkit-box-shadow: 0px 2px 2px 0px rgba(0, 0, 0, 0.2);
-moz-box-shadow: 0px 2px 2px 0px rgba(0, 0, 0, 0.2);
box-shadow: 0px 2px 2px 0px rgba(0, 0, 0, 0.2);
text-align:center
}
.cuf_field {
	-moz-box-sizing:border-box;
	-webkit-box-sizing:border-box;
	box-sizing:border-box;
	background:#fff;
	border:1px solid #A9B3BC;
	padding:8px;
	width:100%;
	margin-top:5px;
margin-bottom:15px;
	outline:none
}
#tinyform {
clear: both;
	width:500px;
	margin-left:auto;
	margin-right:auto;
	/*margin-top:30px;*/
	padding:20px;
	-webkit-border-radius:5px;
	-moz-border-radius:5px;
	border-radius:5px;
	-webkit-box-shadow:0px 0px 10px 0px rgba(0,0,0,0.2);
	-moz-box-shadow:0px 0px 10px 0px rgba(0,0,0,0.2);
	box-shadow:0px 0px 10px 0px rgba(0,0,0,0.2);
	border:4px solid #FFF;
	-webkit-transition:all 200ms linear;
	-moz-transition:all 200ms linear;
	-o-transition:all 200ms linear;
	transition:all 200ms linear;
}
.cuf_textarea {
	-moz-box-sizing:border-box;
	-webkit-box-sizing:border-box;
	box-sizing:border-box;
	background:#fff;
	border:1px solid #A9B3BC;
	padding:8px;
	width:100%;
	margin-top:5px;
	outline:none;
margin-bottom:15px;
}\n"
			."</style>\n";
	}
}


function pluginActions($links, $file) {
	if( $file == plugin_basename(__FILE__)
		&& strpos( $_SERVER['SCRIPT_NAME'], '/network/') === false )
	{
		$link = '<a href="options-general.php?page=contact-us-form">'.__('Settings').'</a>';
		array_unshift( $links, $link );
	}
	return $links;
}

function setRecources() {
	if ( isset($_GET['resource']) && !empty($_GET['resource']) )
	{			 
		if ( array_key_exists($_GET['resource'], $resources) )
		{
			$content = base64_decode($resources[ $_GET['resource'] ]);
			$lastMod = filemtime(__FILE__);
			$client = ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false );
			if (isset($client) && (strtotime($client) == $lastMod))
			{
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 304);
				exit;
			}
			else
			{
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 200);
				header('Content-Length: '.strlen($content));
				header('Content-Type: image/' . substr(strrchr($_GET['resource'], '.'), 1) );
				echo $content;
				exit;
			}
		}
	}
}

function getResource( $resourceID ) {
	return trailingslashit( get_bloginfo('url') ).'?resource='.$resourceID;
}

function register_widgets()
{
	register_widget('ContactUsForm_Widget');
}

} 

class ContactUsFormCaptcha {
	
var $first;
var $operation;
var $second;
var $answer;
var $captcha_id;


function ContactUsFormCaptcha( $seed ) {
	$this->captcha_id = $seed;
	if ( $seed )
		srand($seed);
	$operation = 1;
	switch ( $operation )
	{
		case 1:
			$this->operation = '+';
			$this->first = rand(1, 10);
			$this->second = rand(0, 10);
			$this->answer = $this->first + $this->second;
			break;
	}
}


function getAnswer() {
	return $this->answer;
}


function getQuestion() {
	return $this->first.' '.$this->operation.' '.$this->second.' = ';
}


function isCaptchaOk() {
	$ok = true;
	if ($_POST[base64_encode(strrev('current_time'))] && $_POST[base64_encode(strrev('captcha'))])
	{

		if ((time() - strrev(base64_decode($_POST[base64_encode(strrev('current_time'))]))) > 1800)
			$ok = false;

		$valid = new ContactUsFormCaptcha(strrev(base64_decode($_POST[base64_encode(strrev('captcha'))])));
		if ($_POST[base64_encode(strrev('answer'))] != $valid->getAnswer())
			$ok = false;
	}
	return $ok;
}
	
function getCaptcha( $n = '' ) {
	global $contact_us_form;
	return '<input name="'.base64_encode(strrev('current_time')).'" type="hidden" value="'.base64_encode(strrev(time())).'" />'."\n"
		.'<input name="'.base64_encode(strrev('captcha')).'" type="hidden" value="'.base64_encode(strrev($this->captcha_id)).'" />'."\n"
		.'<label class="cuf_label" style="display:inline" for="cuf_captcha'.$n.'">'.$contact_us_form->o['captcha_label'].' <b>'.$this->getQuestion().'</b></label> <input id="cuf_captcha'.$n.'" name="'.base64_encode(strrev('answer')).'" type="text" size="2" />'."\n";
}

} 

class ContactUsForm_Widget extends WP_Widget {
	var $fields = array('Title', 'Subject', 'To');

	function ContactUsForm_Widget() {
		parent::WP_Widget('cuform_widget', 'Contact Us Form', array('description' => 'Contact Us Form'));	
	}
 

	function widget( $args, $instance) {
		global $contact_us_form;
		extract($args, EXTR_SKIP);
		$title = empty($instance['title']) ? '&nbsp;' : apply_filters('widget_title', $instance['title']);
		echo $before_widget;
		if ( !empty( $title ) )
			echo $before_title.$title.$after_title;
		echo $contact_us_form->showForm( $instance );
		echo $after_widget;
	}
 
	
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		foreach ( $this->fields as $f )
			$instance[strtolower($f)] = strip_tags($new_instance[strtolower($f)]);
		return $instance;
	}
 

	function form( $instance ) {
		$default = array('title' => 'Contact Us Form');
		$instance = wp_parse_args( (array) $instance, $default );
 
		foreach ( $this->fields as $field )
		{ 
			$f = strtolower( $field );
			$field_id = $this->get_field_id( $f );
			$field_name = $this->get_field_name( $f );
			echo "\r\n".'<p><label for="'.$field_id.'">'.__($field, 'cuf-lang').': <input type="text" class="widefat" id="'.$field_id.'" name="'.$field_name.'" value="'.attribute_escape( $instance[$f] ).'" /><label></p>';
		}
	}
} 
?>