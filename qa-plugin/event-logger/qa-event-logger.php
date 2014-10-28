<?php

/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/


	File: qa-plugin/event-logger/qa-event-logger.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Event module class for event logger plugin


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

	class qa_event_logger {

		public function init_queries($tableslc)
		{
			if (qa_opt('event_logger_to_database')) {
				$tablename=qa_db_add_table_prefix('eventlog');

				if (!in_array($tablename, $tableslc)) {
					require_once QA_INCLUDE_DIR.'qa-app-users.php';
					require_once QA_INCLUDE_DIR.'qa-db-maxima.php';

					return 'CREATE TABLE ^eventlog ('.
						'datetime DATETIME NOT NULL,'.
						'ipaddress VARCHAR (15) CHARACTER SET ascii,'.
						'userid '.qa_get_mysql_user_column_type().','.
						'handle VARCHAR('.QA_DB_MAX_HANDLE_LENGTH.'),'.
						'cookieid BIGINT UNSIGNED,'.
						'event VARCHAR (20) CHARACTER SET ascii NOT NULL,'.
						'params VARCHAR (800) NOT NULL,'.
						'KEY datetime (datetime),'.
						'KEY ipaddress (ipaddress),'.
						'KEY userid (userid),'.
						'KEY event (event)'.
					') ENGINE=MyISAM DEFAULT CHARSET=utf8';
				}
			}
		}


		public function admin_form(&$qa_content)
		{
			$eventoptions = $this->prepare_event_options();

		//	Process form input

			$saved=false;

			if (qa_clicked('event_logger_save_button')) {
				$selectedevents = qa_post_array('event_logger_enabled_events_field');

				qa_opt('event_logger_to_database', (int)qa_post_text('event_logger_to_database_field'));
				qa_opt('event_logger_to_files', qa_post_text('event_logger_to_files_field'));
				qa_opt('event_logger_directory', qa_post_text('event_logger_directory_field'));
				qa_opt('event_logger_hide_header', !qa_post_text('event_logger_hide_header_field'));
				qa_opt('event_logger_only_log_enabled_events', (int)qa_post_text('event_logger_only_log_enabled_events_field'));
				qa_opt('event_logger_enabled_events', $this->array_to_string($selectedevents));
				qa_opt('event_logger_log_plugin_events', (int)qa_post_text('event_logger_log_plugin_events_field'));

				$saved=true;
			}

		//	Check the validity of the currently entered directory (if any)

			$directory=qa_opt('event_logger_directory');

			$note=null;
			$error=null;

			if (!strlen($directory))
				$note='Please specify a directory that is writable by the web server.';
			elseif (!file_exists($directory))
				$error='This directory cannot be found. Please enter the full path.';
			elseif (!is_dir($directory))
				$error='This is a file. Please enter the full path of a directory.';
			elseif (!is_writable($directory))
				$error='This directory is not writable by the web server. Please choose a different directory, use chown/chmod to change permissions, or contact your web hosting company for assistance.';

		//	Create the form for display

			qa_set_display_rules($qa_content, array(
				'event_logger_directory_display' => 'event_logger_to_files_field',
				'event_logger_hide_header_display' => 'event_logger_to_files_field',
				'event_logger_enabled_events_field_display' => 'event_logger_only_log_enabled_events_field',
				'event_logger_log_plugin_events_display' => 'event_logger_only_log_enabled_events_field',
			));

			return array(
				'ok' => ($saved && !isset($error)) ? 'Event log settings saved' : null,

				'fields' => array(
					array(
						'label' => 'Log events to <code>'.QA_MYSQL_TABLE_PREFIX.'eventlog</code> database table',
						'tags' => 'name="event_logger_to_database_field"',
						'value' => qa_opt('event_logger_to_database'),
						'type' => 'checkbox',
					),

					array(
						'label' => 'Log events to daily log files',
						'tags' => 'name="event_logger_to_files_field" id="event_logger_to_files_field"',
						'value' => qa_opt('event_logger_to_files'),
						'type' => 'checkbox',
					),

					array(
						'id' => 'event_logger_directory_display',
						'label' => 'Directory for log files - enter full path:',
						'value' => qa_html($directory),
						'tags' => 'name="event_logger_directory_field"',
						'note' => $note,
						'error' => qa_html($error),
					),

					array(
						'id' => 'event_logger_hide_header_display',
						'label' => 'Include header lines at top of each log file',
						'type' => 'checkbox',
						'tags' => 'name="event_logger_hide_header_field"',
						'value' => !qa_opt('event_logger_hide_header'),
					),

					array(
						'label' => 'Only log selected events',
						'tags' => 'name="event_logger_only_log_enabled_events_field" id="event_logger_only_log_enabled_events_field"',
						'value' => qa_opt('event_logger_only_log_enabled_events'),
						'type' => 'checkbox',
					),

					array(
						'id' => 'event_logger_enabled_events_field_display',
						'type' => 'multi-select',
						'options' => $eventoptions,
						'tags' => 'name="event_logger_enabled_events_field[]" size="10"',
						'match_by' => 'key',
						'values' => $this->string_to_array(qa_opt('event_logger_enabled_events')),
						'note' => 'It is possible to select multiple events by clicking them holding Ctrl or the Command (Mac) key',
					),

					array(
						'id' => 'event_logger_log_plugin_events_display',
						'label' => 'Log events generated by plugins',
						'tags' => 'name="event_logger_log_plugin_events_field" id="event_logger_log_plugin_events_field"',
						'value' => qa_opt('event_logger_log_plugin_events'),
						'type' => 'checkbox',
					),
				),

				'buttons' => array(
					array(
						'label' => 'Save Changes',
						'tags' => 'name="event_logger_save_button"',
					),
				),
			);
		}


		public function value_to_text($value)
		{
			if (is_array($value))
				$text='array('.count($value).')';
			elseif (strlen($value)>40)
				$text=substr($value, 0, 38).'...';
			else
				$text=$value;

			return strtr($text, "\t\n\r", '   ');
		}


		public function process_event($event, $userid, $handle, $cookieid, $params)
		{
			$selectedevents = $this->string_to_array(qa_opt('event_logger_enabled_events'));
			if (qa_opt('event_logger_only_log_enabled_events') && !in_array($event, $selectedevents))
				if (in_array($event, array_keys($this->prepare_event_options())) || !qa_opt('event_logger_log_plugin_events'))  // If in core or log plugin events is disabled
					return;  // Event not monitored

			if (qa_opt('event_logger_to_database')) {
				$paramstring='';

				foreach ($params as $key => $value)
					$paramstring.=(strlen($paramstring) ? "\t" : '').$key.'='.$this->value_to_text($value);

				qa_db_query_sub(
					'INSERT INTO ^eventlog (datetime, ipaddress, userid, handle, cookieid, event, params) '.
					'VALUES (NOW(), $, $, $, #, $, $)',
					qa_remote_ip_address(), $userid, $handle, $cookieid, $event, $paramstring
				);
			}

			if (qa_opt('event_logger_to_files')) {

			//	Substitute some placeholders if certain information is missing

				if (!strlen($userid))
					$userid='no_userid';

				if (!strlen($handle))
					$handle='no_handle';

				if (!strlen($cookieid))
					$cookieid='no_cookieid';

				$ip=qa_remote_ip_address();
				if (!strlen($ip))
					$ip='no_ipaddress';

			//	Build the log file line to be written

				$fixedfields=array(
					'Date' => date('Y\-m\-d'),
					'Time' => date('H\:i\:s'),
					'IPaddress' => $ip,
					'UserID' => $userid,
					'Username' => $handle,
					'CookieID' => $cookieid,
					'Event' => $event,
				);

				$fields=$fixedfields;

				foreach ($params as $key => $value)
					$fields['param_'.$key]=$key.'='.$this->value_to_text($value);

				$string=implode("\t", $fields);

			//	Build the full path and file name

				$directory=qa_opt('event_logger_directory');

				if (substr($directory, -1)!='/')
					$directory.='/';

				$filename=$directory.'q2a-log-'.date('Y\-m\-d').'.txt';

			//	Open, lock, write, unlock, close (to prevent interference between multiple writes)

				$exists=file_exists($filename);

				$file=@fopen($filename, 'a');

				if (is_resource($file)) {
					if (flock($file, LOCK_EX)) {
						if ( (!$exists) && (filesize($filename)===0) && !qa_opt('event_logger_hide_header') )
							$string="Question2Answer ".QA_VERSION." log file generated by Event Logger plugin.\n".
								"This file is formatted as tab-delimited text with UTF-8 encoding.\n\n".
								implode("\t", array_keys($fixedfields))."\textras...\n\n".$string;

						fwrite($file, $string."\n");
						flock($file, LOCK_UN);
					}

					fclose($file);
				}
			}
		}

		private function prepare_event_options()
		{
			$eventoptions = array(
				'q_post' => 'Question created',
				'a_post' => 'Answer created',
				'c_post' => 'Comment created',
				'q_queue' => 'Question queued for moderation',
				'a_queue' => 'Answer queued for moderation',
				'c_queue' => 'Comment queued for moderation',
				'q_edit' => 'Question edited',
				'a_edit' => 'Answer edited',
				'c_edit' => 'Comment edited',
				'q_close' => 'Question closed',
				'q_reopen' => 'Question repoened',
				'a_select' => 'Answer selected',
				'a_unselect' => 'Answer unselected',
				'q_flag' => 'Question flagged',
				'a_flag' => 'Answer flagged',
				'c_flag' => 'Comment flagged',
				'q_unflag' => 'Question unflagged',
				'a_unflag' => 'Answer unflagged',
				'c_unflag' => 'Comment unflagged',
				'q_clearflags' => 'Question flags cleared',
				'a_clearflags' => 'Answer flags cleared',
				'c_clearflags' => 'Comment flags cleared',
				'q_hide' => 'Question hidden',
				'a_hide' => 'Answer hidden',
				'c_hide' => 'Comment hidden',
				'q_reshow' => 'Question reshown',
				'a_reshow' => 'Answer reshown',
				'c_reshow' => 'Comment reshown',
				'q_approve' => 'Question approved during moderation',
				'a_approve' => 'Answer approved during moderation',
				'c_approve' => 'Comment approved during moderation',
				'q_reject' => 'Question rejected during moderation',
				'a_reject' => 'Answer rejected during moderation',
				'c_reject' => 'Comment rejected during moderation',
				'q_requeue' => 'Question requeued into moderation queue',
				'a_requeue' => 'Answer requeued into moderation queue',
				'c_requeue' => 'Comment requeued into moderation queue',
				'q_delete' => 'Question deleted',
				'a_delete' => 'Answer deleted',
				'c_delete' => 'Comment deleted',
				'q_claim' => 'Question from anonymous user claimed',
				'a_claim' => 'Answer from anonymous user claimed',
				'c_claim' => 'Comment from anonymous user claimed',
				'q_move' => 'Question moved to different category',
				'a_to_c' => 'Answer converted to comment',
				'q_vote_up' => 'Question voted up',
				'q_vote_down' => 'Question voted down',
				'q_vote_nil' => 'Question vote removed',
				'a_vote_up' => 'Answer voted up',
				'a_vote_down' => 'Answer voted down',
				'a_vote_nil' => 'Answer vote removed',
				'q_favorite' => 'Question favorited by user',
				'q_unfavorite' => 'Question unfavorited by user',
				'u_register' => 'New user registered',
				'u_login' => 'User logged in',
				'u_logout' => 'User logged out',
				'u_confirmed' => 'User email address confirmed',
				'u_reset' => 'User password reset',
				'u_save' => 'User profile saved',
				'u_password' => 'User password saved',
				'u_edit' => 'User modified by another user',
				'u_message' => 'Private message sent',
				'u_wall_post' => 'Wall post created',
				'u_wall_delete' => 'Wall post deleted',
				'u_level' => 'User privilege modified',
				'u_block' => 'User blocked',
				'u_unblock' => 'User unblocked',
				'u_delete' => 'User deleted',
				'u_favorite' => 'User favorited',
				'u_unfavorite' => 'User unfavorited',
				'u_points' => 'Possible user point update',
				'ip_block' => 'IP address blocked',
				'ip_unblock' => 'IP address unblocked',
				'tag_favorite' => 'Tag favorited',
				'tag_unfavorite' => 'Tag unfavorited',
				'cat_favorite' => 'Category favorited',
				'cat_unfavorite' => 'Category unfavorited',
				'feedback' => 'Feedback message sent',
				'search' => 'Search performed',
			);
			foreach ($eventoptions as $key => $value)
				$eventoptions[$key] = $key . ': ' . $eventoptions[$key];
			return $eventoptions;
		}

		private function array_to_string($array)
		{
			return implode(';', $array);
		}

		private function string_to_array($string)
		{
			return explode(';', $string);
		}

	}


/*
	Omit PHP closing tag to help avoid accidental output
*/