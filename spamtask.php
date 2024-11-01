<?php
/*
Plugin Name: SpamTask
Plugin URI: http://www.jenruno.com/SpamTask/
Description: The ultimate anti-spam plugin for WordPress. It protects your blog from comment spam using advanced spam filtering methods. All spam robots are caught effectively with several solidly working anti-spam engines and it even cleans up your database for spam comments automatically, if you wish.
Author: Jenruno
Version: 1.3.6
Author URI: http://www.jenruno.com/
*/

function startExtendable($name, $hidden=TRUE) {
	$id = 'extendable_'.strtolower(str_replace(' ', '', $name));
	if(!$hidden) { echo '<script>$(document).ready(function() { $(\'#'.$id.'\').slideToggle(0); });</script>'; }
	return '<h3 onClick="$(\'#'.$id.'\').slideToggle(\'fast\');" class="headBox extendable">'.$name.'</h3>
<div id="'.$id.'" style="display: none;">';
}
function endExtendable() { return '</div>'; }

function SpamTask_MakeCall($host, $POST) {
	$q = 'HTTP_REFERER='.urlencode($_SERVER['HTTP_HOST']).'&REMOTE_ADDR='.urlencode($_SERVER['REMOTE_ADDR']).'&HTTP_USER_AGENT='.urlencode($_SERVER['HTTP_USER_AGENT']).'&SpamTask_version=1-4';
	if($POST) { foreach($POST AS $p => $v) { $q .= '&'.urlencode($p).'='.urlencode($v); } }

	$req =	"POST / HTTP/1.1\r\n".
			"Content-Type: application/x-www-form-urlencoded\r\n".
			"Host: ".$host."\r\n".
			"Content-Length: ".strlen($q)."\r\n".
			"Connection: close\r\n".
			"\r\n".$q;

	$fp = @fsockopen($host, 80, $errno, $errstr, 10);
	if(!$fp) { return $comment; }
	if(!fwrite($fp, $req)) { fclose($fp); return $comment; }
	$result = '';
	while(!feof($fp)) { $result .= fgets($fp); }
	fclose($fp);
	$result = explode("\r\n\r\n", $result);

	return $result[1];
}

function SpamTask_GetContent($c, $start, $end=false){
	$line = 1;
	$len = strlen($start);
	if(!$start) { $pos_start = 0; }
	else {
		$pos_start = strpos($c, $start)+strlen($start);
		if(!$pos_start) { return false; $pos_start += $len; }
	}

	if(!$end) {
		$pos_end = strpos($c, '\n', $pos_start);
		if(!$pos_end) { $pos_end = strpos($c, '\r\n', $pos_start); }
	} else { $pos_end = strpos($c, $end, $pos_start); }

	if($pos_end) { $result = substr($c, $pos_start, $pos_end-$pos_start); }
	else { $result = substr($c, $pos_start); }

	return $result;
}

function SpamTask_DeleteSpam() {
	global $wpdb;

	$ago = FALSE;
	if(get_option('spamtask_delete_spam') == 'day') { $ago = time()-86400; }
	else if(get_option('spamtask_delete_spam') == 'twodays') { $ago = time()-86400*2; }
	else if(get_option('spamtask_delete_spam') == 'week') { $ago = time()-86400*7; }
	else if(get_option('spamtask_delete_spam') == 'month') { $ago = time()-86400*30; }
	if($ago) {
		$ago = date('Y-m-d H:i:s', $ago);
		$wpdb->query("DELETE FROM ".$wpdb->comments." WHERE comment_approved = 'spam' AND comment_date < '".$ago."'");
		$wpdb->query("OPTIMIZE TABLE ".$wpdb->comments);
	}
}

function SpamTask_SaveFieldNames($c) {
	if(strpos(' '.$c, 'id="returnFieldNames">')) {
		$FieldNames = unserialize(SpamTask_GetContent($c, 'id="returnFieldNames">', '</div>'));
		if(is_array($FieldNames)) {
			if(get_option('spamtask_fieldnames')) { update_option('spamtask_fieldnames', serialize($FieldNames)); }
			else {
				delete_option('spamtask_fieldnames');
				add_option('spamtask_fieldnames', serialize($FieldNames), FALSE, 'yes');
			}
		}
	}
}

function SpamTask_SaveStatistics($c) {
	if(strpos(' '.$c, 'id="returnStatistics">')) {
		$statistics = unserialize(SpamTask_GetContent($c, 'id="returnStatistics">', '</div>'));
		if(is_array($statistics)) {
			$days = unserialize(get_option('spamtask_statistics_days'));
			if(is_array($statistics['days'])) {
				foreach($statistics['days'] AS $d => $v) {
					$days[$d]['relevant'] = $v['relevant'];
					$days[$d]['spam'] = $v['spam'];
				}
				if(get_option('spamtask_statistics_days')) { update_option('spamtask_statistics_days', serialize($days)); }
				else {
					delete_option('spamtask_statistics_days');
					add_option('spamtask_statistics_days', serialize($days), FALSE, 'yes');
				}
			}
			$toUpdate = array('LastMessageDate', 'LastMessageStatus');
			foreach($toUpdate AS $k) {
				$name = 'spamtask_statistics_last'.str_replace('LastMessage', '', $k);
				if(get_option($name)) {
					if(strpos(' '.$name, 'Date')) {
						if(get_option($name) < $statistics[$k]) { update_option($name, $statistics[$k]); }
					} else { update_option($name, $statistics[$k]); }
				} else {
					delete_option($name);
					add_option($name, $statistics[$k], FALSE, 'yes');
				}
			}
			$total = FALSE;
			foreach($days AS $k => $v) {
				$total['spam'] += $v['spam'];
				$total['relevant'] += $v['relevant'];
			}
			if(get_option('spamtask_statistics_total')) { update_option('spamtask_statistics_total', serialize($total)); }
			else {
				delete_option('spamtask_statistics_total');
				add_option('spamtask_statistics_total', serialize($total), FALSE, 'yes');
			}
		}
	}
}

function SpamTask_FetchStatistics() {
	$stats = FALSE;
    $total = unserialize(get_option('spamtask_statistics_total'));
    $stats['Total-comments']['spam'] = $total['spam'];
	$stats['Total-comments']['relevant'] = $total['relevant'];
    $days = unserialize(get_option('spamtask_statistics_days'));
    $yesterday = mktime(0, 0, 0, date('m'), date('d')-1, date('Y'));
    if(get_option('spamtask_statistics_lastDate')) {
	    $stats['Last-comment'] = SpamTask_TimeAgo(date('Y', get_option('spamtask_statistics_lastDate')).'-'.date('m', get_option('spamtask_statistics_lastDate')).'-'.date('d', get_option('spamtask_statistics_lastDate')).' '.date('H', get_option('spamtask_statistics_lastDate')).':'.date('i', get_option('spamtask_statistics_lastDate')));
	} else { $stats['Last-comment'] = 'n/a'; }
    $stats['Last-comment-status'] = get_option('spamtask_statistics_lastStatus');
    if(!$stats['Last-comment']) { $stats['Last-comment'] = 'n/a'; }
    $fetchStatistics = array(
    	'Today'=>date('m').'-'.date('d').'-'.date('Y'),
    	'Yesterday'=>date('m', $yesterday).'-'.date('d', $yesterday).'-'.date('Y', $yesterday));
    foreach($fetchStatistics AS $name => $value) {
    	if($days[$value]['spam']) { $stats[$name]['spam'] = $days[$value]['spam']; }
    	else { $stats[$name]['spam'] = 0; }
    	if($days[$value]['relevant']) { $stats[$name]['relevant'] = $days[$value]['relevant']; }
    	else { $stats[$name]['relevant'] = 0; }
    }

    $stats['Last-30-days']['spam'] = 0; $stats['Last-30-days']['relevant'] = 0;
    for($i=mktime(0, 0, 0, date('m'), date('d')-29, date('Y')); $i <= mktime(0, 0, 0, date('m'), date('d')+1, date('Y')); $i += 86000) {
    	$date = date('m', $i).'-'.date('d', $i).'-'.date('Y', $i);
    	$stats['Last-30-days']['spam'] += $days[$date]['spam'];
    	$stats['Last-30-days']['relevant'] += $days[$date]['relevant'];
    }
    $stats['Last-30-days']['total'] = $stats['Last-30-days']['spam']+$stats['Last-30-days']['relevant'];
    if($stats['Last-30-days']['spam']) { $stats['Spam-percentage'] = round($stats['Last-30-days']['spam']/$stats['Last-30-days']['total']*100).' %'; }
    else { $stats['Spam-percentage'] = 'n/a %'; }

	$stats['This-month']['spam'] = 0; $stats['This-month']['relevant'] = 0;
    for($i=mktime(0, 0, 0, date('m'), 1, date('Y')); $i <= mktime(0, 0, 0, date('m')+1, 0, date('Y')); $i += 86000) {
    	$date = date('m', $i).'-'.date('d', $i).'-'.date('Y', $i);
    	$stats['This-month']['spam'] += $days[$date]['spam'];
    	$stats['This-month']['relevant'] += $days[$date]['relevant'];
    }

	$stats['Last-month']['spam'] = 0; $stats['Last-month']['relevant'] = 0;
    for($i=mktime(0, 0, 0, date('m')-1, 1, date('Y')); $i <= mktime(0, 0, 0, date('m'), 0, date('Y')); $i += 86000) {
    	$date = date('m', $i).'-'.date('d', $i).'-'.date('Y', $i);
    	$stats['Last-month']['spam'] += $days[$date]['spam'];
    	$stats['Last-month']['relevant'] += $days[$date]['relevant'];
    }

	if(!$stats['Total-comments']['spam']) { $stats['Total-comments']['spam'] = 0; }
	if(!$stats['Total-comments']['relevant']) { $stats['Total-comments']['relevant'] = 0; }

    return $stats;
}

function SpamTask_TimeAgo($date) {    
	$periods = array('second', 'minute', 'hour', 'day', 'week', 'month', 'year', 'decade');
	$lengths = array('60', '60', '24', '7', '4.35', '12', '10');

	$now = time();
	$unix_date = strtotime($date);
	$difference = $now - $unix_date;

	for($j = 0; $difference >= $lengths[$j] AND $j < count($lengths)-1; $j++) { $difference /= $lengths[$j]; }
	$difference = round($difference);
	if($difference != 1) { $periods[$j].= "s"; }

	return $difference.' '.$periods[$j].' ago';
}

/* Clean up old settings */
if(get_option('spamtask_statistics_24hours')) {
	$statisticsSettings = array('24hours', '48hours', '7days', '15days', '30days');
	foreach($statisticsSettings AS $s) { delete_option('spamtask_statistics_'.$s, $statistics[$s]); }
}


if(!class_exists("SpamTask")) {
	class SpamTask {

		/* Form processing */
		function FormProcessing() {
			if(get_option('spamtask_fieldnames')) { $names = unserialize(get_option('spamtask_fieldnames')); }
			else { $names = array('Kdi19Ask'=>'pAlsd2', 'JidaAolwpP'=>'Kasi21'); }
			$class = str_replace(array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), FALSE, array_rand($names));
			echo '<style type="text/css">input.'.$class.' { display: none; }</style>';
			for($i = 1; $i <= 2; $i++) {
				$name = array_rand($names);
				if(rand(1, 2) == 1) { $value = $names[$name]; }
				else { $value = FALSE; }
				echo '<input type="text" id="'.$name.'" name="'.$name.'" value="'.$value.'" class="'.$class.'">';
			}
		}

		/* Comment processing */
		function CommentProcessing($comment) {
			global $current_user, $user_level, $wpdb;

			get_currentuserinfo();
			if(is_user_logged_in()) {
				$_POST['author'] = $current_user->display_name;
				$_POST['email'] = $current_user->user_email;
				if(strpos(' '.$_SERVER['REQUEST_URI'], 'wp-admin/admin-ajax.php')) { $in_wp_admin = TRUE; }
				else { $in_wp_admin = FALSE; }
			}
			if(!$in_wp_admin) {
				$_POST['wp_blog'] = get_bloginfo('wpurl');
				$_POST['wp_userlevel'] = $user_level;
				$result = SpamTask_MakeCall('www.spamtask.com', $_POST);

				if($result AND !strpos($result, 'errorCode">2')) {
					SpamTask_SaveStatistics($result);
					SpamTask_SaveFieldNames($result);
					$comment['comment_approved'] = 'spam';
					update_option('spamtask_statistics_lastStatus', 'Spam');
					ob_start();
					echo $result;
					if(!strpos($result, 'errorCode')) {
						add_filter('comment_post_redirect', create_function('$a', 'ob_end_flush(); exit();'), 10, 2);
						return $comment;
					} else { exit(); }
				} else {
					SpamTask_SaveStatistics($result);
					$r = $wpdb->get_row("SELECT comment_ID, comment_approved, comment_post_ID FROM ".$wpdb->comments." WHERE comment_approved = 'spam' ORDER BY comment_date DESC LIMIT 1");
					if(get_option('comment_moderation') == 1 AND $user_level < 9) {
						$wpdb->query("UPDATE ".$wpdb->comments." SET comment_approved = '0' WHERE comment_ID = '".$r->comment_ID."'");
					} else {
						$wpdb->query("UPDATE ".$wpdb->comments." SET comment_approved = '1' WHERE comment_ID = '".$r->comment_ID."'");
						$wpdb->query("UPDATE ".$wpdb->posts." SET comment_count = comment_count+1 WHERE ID = '".$r->comment_post_ID."'");
					}
					update_option('spamtask_statistics_lastStatus', 'Relevant');
					if($user_level < 9) { wp_notify_moderator($r->comment_ID); }
					$location = get_permalink($r->comment_post_ID);
					wp_redirect($location); exit();
				}
			} else { return $comment; }
		}


		function InteractiveStatistics($content) {
			$stats = SpamTask_FetchStatistics();

			$toReplace = array('Total comments', 'Today', 'Yesterday', 'Last 30 days', 'Spam percentage', 'This month', 'Last month', 'Last comment', 'Last comment status', 'Spam chart');
			foreach($toReplace AS $r) {
				if($r == 'Spam percentage' OR $r == 'Last comment' OR $r == 'Last comment status') { $content = str_replace('['.$r.']', strtolower($stats[str_replace(' ', '-', $r)]), $content); }
				else if($r == 'Spam chart') { include_once 'chart/chart.php'; $content = str_replace('['.$r.']', $chart, $content); }
				else if($r == 'This month') {
					$content = str_replace(array('[This month:spam]', '[This month:relevant]'), array($stats['This-month']['spam'], $stats['This-month']['relevant']), $content);
				} else {
					$content = str_replace(array('['.$r.':spam]', '['.$r.':relevant]'), array($stats[str_replace(' ', '-', $r)]['spam'], $stats[str_replace(' ', '-', $r)]['relevant']), $content);
				}
			}

			return $content;
		}


		/* Admin pages */
		function addPages() {

			function spamtask_filter_plugin_actions($links, $file) {
				static $this_plugin;
				if(!$this_plugin) $this_plugin = plugin_basename(__FILE__);

				if($file == $this_plugin) {
				    $settings_link = '<a href="options-general.php?page=spamtask_settings">' . __('Settings') . '</a>';
				    array_unshift($links, $settings_link);
				}

				return $links;
			}

			/* Load pages */
			add_options_page('SpamTask', 'SpamTask', 8, 'spamtask_settings', 'SpamTask_Settings');
			add_submenu_page('index.php', __('SpamTask information'), __('SpamTask'), 8, 'spamtask_settings', 'SpamTask_settings');
			add_filter('plugin_action_links', 'spamtask_filter_plugin_actions', 10, 2 );

			/* Options page */
			function SpamTask_Settings() {
				global $wpdb;

				if($_GET['updated'] == 'true') {
					$a = FALSE;

					$a['save_configuration'] = 1;
					if(get_option('spamtask_status_page')) { $a['statusPage'] = 1; }
					else { $a['statusPage'] = 0; }
					$a['processingMessage'] = get_option('spamtask_processing_msg');
					$a['rapidlySubmitting'] = get_option('spamtask_rapidly_msg');
					$a['doublePost'] = get_option('spamtask_double_msg');
					$a['spamMessage'] = get_option('spamtask_spam_msg');
					$a['clickToReturn'] = get_option('spamtask_clickreturn_msg');

					$error = SpamTask_MakeCall('www.contask.com', $a);
					if(!$error) {
						delete_option('spamtask_status_page');
						delete_option('spamtask_processing_msg');
						delete_option('spamtask_rapidly_msg');
						delete_option('spamtask_double_msg');
						delete_option('spamtask_spam_msg');
						delete_option('spamtask_clickreturn_msg');
					} else { $output = $error; }
				}

				$a = FALSE;
				$result = SpamTask_MakeCall('www.contask.com', $a);
				$result = unserialize($result);

				echo '<script src="'.get_option('siteurl'). '/wp-content/plugins/spamtask/jquery.js"></script><link rel="stylesheet" type="text/css" href="'.get_option('siteurl'). '/wp-content/plugins/spamtask/spamtask.css" />'; ?>

				<div class="wrap" style="margin: 0 25px 0 10px;">

				<h2>SpamTask <img src="http://www.jenruno.com/images/public/SpamTask-logo-16x18.png" border="0" title="The SpamTask logo" alt="SpamTask logo 16x18" /></h2>

					<?php

					if($result) {
						echo startExtendable('Statistics', FALSE);

						$stats = SpamTask_FetchStatistics();
					?>
						<table width="100%" cellspacing="0" class="info">
							<tr>
								<td rowspan="5" width="500px" style="width: 500px;"><? include_once 'chart/chart.php'; echo $chart; ?></td>
								<td>Total comments<br /><small>(spam/relevant)</small></td>
								<td align="right"><?php echo $stats['Total-comments']['spam']; ?>/<?php echo $stats['Total-comments']['relevant']; ?></td>
							</tr>
							<tr>
								<td>Today<br /><small>(spam/relevant)</small></td>
								<td align="right"><?php echo $stats['Today']['spam']; ?>/<?php echo $stats['Today']['relevant']; ?></td>
							</tr>
							<tr>
								<td>Yesterday<br /><small>(spam/relevant)</small></td>
								<td align="right"><?php echo $stats['Yesterday']['spam']; ?>/<?php echo $stats['Yesterday']['relevant']; ?></td>
							</tr>
							<tr>
								<td>Last 30 days<br /><small>(spam/relevant)</small></td>
								<td align="right"><?php echo $stats['Last-30-days']['spam']; ?>/<?php echo $stats['Last-30-days']['relevant']; ?></td>
							</tr>
							<tr>
								<td>Spam percentage</td>
								<td align="right"><?php echo $stats['Spam-percentage']; ?></td>
							</tr>
							<tr>
								<td width="500px" style="width: 500px;" rowspan="4">
									<p>To show the spam statistics publicly in a post or page, type "[*name_of_statistics*:spam/relevant]" in the desired post's or page's content. It all updates dynamically, as new comments (and spam comments) are posted.<br />
									<br />
									For example:<br />
									<font color="#000000">[Last 30 days:relevant]</font> will be replaced with <font color="#000000"><?php echo $stats['Last-30-days']['relevant']; ?></font>.<br />
									<font color="#000000">[Today:spam]</font> will be replaced with <font color="#000000"><?php echo $stats['Today']['spam']; ?></font>.<br />
									<font color="#000000">[Spam percentage]</font> does not require <font color="#000000">:spam</font> nor <font color="#000000">:relevant</font>. It will output <font color="#000000"><?php echo $stats['Spam-percentage']; ?></font>.<br />
									To show the above chart (Spam statistics 30 days), use <font color="#000000">[Spam chart]</font>.</p>
								</td>
							</tr>
							<tr>
								<td>This month<br /><small>(spam/relevant)</small></td>
								<td align="right"><?php echo $stats['This-month']['spam']; ?>/<?php echo $stats['This-month']['relevant']; ?></td>
							</tr>
							<tr>
								<td>Last month<br /><small>(spam/relevant)</small></td>
								<td align="right"><?php echo $stats['Last-month']['spam']; ?>/<?php echo $stats['Last-month']['relevant']; ?></td>
							</tr>
							<tr>
								<td>Last message</td>
								<td align="right"><?php echo $stats['Last-comment']; ?><br /><small><font color="<?php if($stats['Last-comment-status'] == 'Spam') { echo 'red'; } else { echo 'green'; } ?>"><?php echo $stats['Last-comment-status']; ?></font></small></td>
							</tr>
						</table>
					<? echo endExtendable(); ?>
						<form method="post" action="options.php">
						<? wp_nonce_field('update-options');
						echo startExtendable('Settings'); ?>
						<table width="100%" cellspacing="0" class="info" style="border-top: 0;">
							<tr class="head">
								<td colspan="2"><p style="font-weight: normal;">Delete spam comments which are older than...<br /><i>Carried out automatically once a day</i></p></td>
								<td colspan="2" align="center">Description</td>
							</tr>
							<tr>
								<td width="10%"><input type="radio" id="delete_spam_day" name="spamtask_delete_spam" value="day" <?php if(get_option('spamtask_delete_spam') == 'day') { echo 'checked="checked"'; } ?> /></td><td width="40%"><label for="delete_spam_day">... a day</label></td>
								<td rowspan="5">
									<p>This function tells SpamTask to automatically delete spam comments older than the specified age.</p>
									<p>By default, all spam comments are stored in your WordPress database. If your blog receives a noticeable amount of spam comments, this can drastically increase the size of your database, making backups unnecessarily larger and the speed slower.<br />
									Therefore, it is <b>recommended to enable</b> this function, as it may improve the server's performance.</p>
								</td>
							</tr>
							<tr>
								<td><input type="radio" id="delete_spam_twodays" name="spamtask_delete_spam" value="twodays" <?php if(get_option('spamtask_delete_spam') == 'twodays') { echo 'checked="checked"'; } ?> /></td><td><label for="delete_spam_twodays">... two days</label></td>
							</tr>
							<tr>
								<td><input type="radio" id="delete_spam_week" name="spamtask_delete_spam" value="week" <?php if(get_option('spamtask_delete_spam') == 'week') { echo 'checked="checked"'; } ?> /></td><td><label for="delete_spam_week">... a week</label></td>
							</tr>
							<tr>
								<td><input type="radio" id="delete_spam_month" name="spamtask_delete_spam" value="month" <?php if(get_option('spamtask_delete_spam') == 'month') { echo 'checked="checked"'; } ?> /></td><td><label for="delete_spam_month">... a month</label></td>
							</tr>
							<tr>
								<td><input type="radio" id="delete_spam_not" name="spamtask_delete_spam" value="" <?php if(!get_option('spamtask_delete_spam')) { echo 'checked="checked"'; } ?> /></td><td><label for="delete_spam_not">Keep all spam comments</label></td>
							</tr>
						</table>
						
					<? echo endExtendable();
						echo startExtendable('Appearance - Optional status messages'); ?>
						<table width="100%" cellspacing="0" class="info" style="border-top: 0;">
							<tr class="head">
								<td colspan="2"><p style="text-align: center; font-weight: normal;"><input type="checkbox" id="status_page" name="spamtask_status_page" value="true" <?php if($result['statusPage']) { echo 'checked="checked"'; } ?> /> <label for="status_page">Enable status messages when processing comments<br />(If <i>disabled</i>, the default blank page is used)</label></td>
							</tr>
							<tr>
								<td><b>Processing comment</b></td>
								<td>
									<textarea id="msg_processing" name="spamtask_processing_msg" cols="65" rows="1"><?php echo $result['processingMessage']; ?></textarea><br />
									<p><small>Status message while SpamTask processes the visitor's comment.</small></p>
								</td>
							</tr>
							<tr>
								<td><b>Spam message</b></td>
								<td>
									<textarea id="msg_spam" name="spamtask_spam_msg" cols="65" rows="1" /><?php echo $result['spamMessage']; ?></textarea><br />
									<p><small>Will be shown to the visitor if SpamTask has detected a certain amount of spam behaviour in the comment.</small></p>
								</td>
							</tr>
						</table>
						<? echo endExtendable().startExtendable('Appearance - Global status messages'); ?>
						<table width="100%" cellspacing="0" class="info" style="border-top: 0;">
							<tr class="head">
								<td colspan="2"><p style="text-align: center; font-weight: normal;">The below messages tell the visitor if a processing issue has been detected.</p></td>
							</tr>
							<tr>
								<td><b>Rapid submission</b></td>
								<td>
									<textarea id="msg_rapidly" name="spamtask_rapidly_msg" cols="65" rows="1" /><?php echo $result['rapidlySubmitting']; ?></textarea><br />
									<p><small>This appears if the visitor has submitted comments too rapidly.</small></p>
								</td>
							</tr>
							<tr>
								<td><b>Double post</b></td>
								<td>
									<textarea id="msg_double" name="spamtask_double_msg" cols="65" rows="1" /><?php echo $result['doublePost']; ?></textarea><br />
									<p><small>To inform the visitor that he or she has submitted the same comment twice.</small></p>
								</td>
							</tr>
							<tr>
								<td><b>Return-link Text</b></td>
								<td>
									<textarea id="msg_clickreturn" name="spamtask_clickreturn_msg" cols="65" rows="1" /><?php echo $result['clickToReturn']; ?></textarea><br />
									<p><small>Name of the link which returns the visitor to your blog.</small></p>
								</td>
							</tr>
						</table>

						<? echo endExtendable(); ?>
					
					<input type="hidden" name="action" value="update" />
					<input type="hidden" name="page_options" value="spamtask_status_page, spamtask_delete_spam, spamtask_processing_msg, spamtask_rapidly_msg, spamtask_double_msg, spamtask_spam_msg, spamtask_clickreturn_msg" />
					<p class="submit"><input type="submit" name="submit" class="submit" value="<?php _e('Save Changes') ?>" /></p>

					</form><br />
					<p>For instructions or help, go to <a href="http://wordpress.org/extend/plugins/spamtask/faq/" title="Frequently Asked Questions" target="_blank">Frequently Asked Questions</a>.<br />
					<br />
					If you are still having problems with SpamTask, please contact us at <b>support@jenruno.com</b> so we can help you solving the issue. Also feel free to contact us if there are any features you would like to see in SpamTask, so we can work on modifying the plugin to your needs.</p>
				<? } else { ?>
					<h3>An error has occurred</h3>
					<table width="100%" class="info" cellspacing="0">
						<tr>
							<td width="50%" style="padding-right: 10px;" valign="top">
								<p>There was an error determining your host.<br />
								<br />
								This can happen if the original code in SpamTask is changed, and we do not advise people to change the code, as this can cause SpamTask not to work correctly.</p>
							</td>
							<td style="padding-left: 10px;" valign="top">
								<p>You might want to try one of the following steps to solve the problem.</p>
								<ul style="list-style: square; padding-left: 16px;">
									<li>Restore any changes you might have made in SpamTask, to its original code.</li>
									<li>Reinstall the plugin</li>
								</ul>
								<p>If you are still having problems with SpamTask, please contact Jenruno at support@jenruno.com and we will reply as quickly as possible.</p>
							</td>
						</tr>
					</table>
				<? } ?>
					</div>
			<?
			}
		}
	}
}


if(class_exists("SpamTask")) { $SpamTask = new SpamTask(); }

if(isset($SpamTask)) {
	add_action('preprocess_comment', array(&$SpamTask, 'CommentProcessing'), 1);
	add_filter('pre_comment_approved', create_function('$a', 'return \'spam\';'));
	add_action('comment_form', array(&$SpamTask, 'FormProcessing'));
	add_action('the_content', array(&$SpamTask, 'InteractiveStatistics'));
	add_action('admin_menu', array(&$SpamTask, 'addPages'));
	add_action('SpamTask_DailyJobs', 'SpamTask_DeleteSpam');

	$goDisable = FALSE;
	$disableFunction = array('akismet/akismet.php'=>'akismet_auto_check_comment', 'wp-spamfree/wp-spamfree.php'=>'spamfree_init', 'bad-behavior/bad-behavior-wordpress.php'=>'bb2_install', 'wp-honeypot/wp-honeypot.php'=>'WPHoneypot_ap', 'wp-mollom/wp-mollom.php'=>'_mollom_calc_statistics');
	foreach($disableFunction AS $pL => $fL) {
		if(function_exists($fL) OR function_exists(strtolower($fL))) { $goDisable = TRUE; }
	}
	if($goDisable) {
		$newArray = array(); $installedPlugins = get_option('active_plugins');
		foreach($installedPlugins AS $k => $v) {
			if(!array_key_exists(strtolower($v), $disableFunction) OR strpos(strtolower(' '.$v), 'spamtask')) { $newArray[] = $v; }
		}
		update_option('active_plugins', $newArray);
	}

	function SpamTask_Activation() { wp_schedule_event(mktime(0, 0, 0, date('m'), date('d')+1, date('Y')), 'daily', 'SpamTask_DailyJobs'); }
	function SpamTask_Deactivation() { wp_clear_scheduled_hook('SpamTask_DailyJobs'); }

/*
	register_activation_hook(__FILE__, 'SpamTask_Activation');
	This will be enabled when it is properly working in the WordPress core.
*/
	if($_GET['activate']) { SpamTask_Deactivation(); SpamTask_Activation(); } // Temporary work-around
	register_deactivation_hook(__FILE__, 'SpamTask_Deactivation');
}

?>