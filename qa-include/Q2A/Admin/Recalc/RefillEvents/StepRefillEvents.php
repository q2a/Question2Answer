<?php

/*
	Question2Answer by Gideon Greenspan and contributors
	http://www.question2answer.org/

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

class Q2A_Admin_Recalc_RefillEvents_StepRefillEvents extends Q2A_Admin_Recalc_AbstractStep
{
	const BATCH_AMOUNT = 1;

	public function __construct()
	{
		$this->messageLangId = 'admin/refill_events_refilled';
	}

	public function setup()
	{
		require_once QA_INCLUDE_DIR . 'db/admin.php';

		$this->totalItems = qa_db_count_posts(['Q', 'Q_HIDDEN', 'Q_QUEUED']);
	}

	public function execute()
	{
		// For qa_db_select_with_pending()
		require_once QA_INCLUDE_DIR . 'db/selects.php';

		// For qa_db_qs_get_for_event_refilling()
		require_once QA_INCLUDE_DIR . 'db/recalc.php';

		$questionids = qa_db_qs_get_for_event_refilling($this->nextItemId, self::BATCH_AMOUNT);

		if (!empty($questionids)) {
			require_once QA_INCLUDE_DIR . 'app/events.php';
			require_once QA_INCLUDE_DIR . 'app/updates.php';
			require_once QA_INCLUDE_DIR . 'util/sort.php';

			$lastquestionid = max($questionids);

			foreach ($questionids as $questionid) {
				// Retrieve all posts relating to this question

				list($question, $childposts, $achildposts) = qa_db_select_with_pending(
					qa_db_full_post_selectspec(null, $questionid),
					qa_db_full_child_posts_selectspec(null, $questionid),
					qa_db_full_a_child_posts_selectspec(null, $questionid)
				);

				// Merge all posts while preserving keys as postids

				$posts = array($questionid => $question);

				foreach ($childposts as $postid => $post) {
					$posts[$postid] = $post;
				}

				foreach ($achildposts as $postid => $post) {
					$posts[$postid] = $post;
				}

				// Creation and editing of each post

				foreach ($posts as $postid => $post) {
					$followonq = ($post['basetype'] == 'Q') && ($postid != $questionid);

					if ($followonq) {
						$updatetype = QA_UPDATE_FOLLOWS;
					} elseif ($post['basetype'] == 'C' && @$posts[$post['parentid']]['basetype'] == 'Q') {
						$updatetype = QA_UPDATE_C_FOR_Q;
					} elseif ($post['basetype'] == 'C' && @$posts[$post['parentid']]['basetype'] == 'A') {
						$updatetype = QA_UPDATE_C_FOR_A;
					} else {
						$updatetype = null;
					}

					qa_create_event_for_q_user($questionid, $postid, $updatetype, $post['userid'], @$posts[$post['parentid']]['userid'], $post['created']);

					if (isset($post['updated']) && !$followonq) {
						qa_create_event_for_q_user($questionid, $postid, $post['updatetype'], $post['lastuserid'], $post['userid'], $post['updated']);
					}
				}

				// Tags and categories of question

				qa_create_event_for_tags($question['tags'], $questionid, null, $question['userid'], $question['created']);
				qa_create_event_for_category($question['categoryid'], $questionid, null, $question['userid'], $question['created']);

				// Collect comment threads

				$parentidcomments = array();

				foreach ($posts as $postid => $post) {
					if ($post['basetype'] == 'C') {
						$parentidcomments[$post['parentid']][$postid] = $post;
					}
				}

				// For each comment thread, notify all previous comment authors of each comment in the thread (could get slow)

				foreach ($parentidcomments as $parentid => $comments) {
					$keyuserids = array();

					qa_sort_by($comments, 'created');

					foreach ($comments as $comment) {
						foreach ($keyuserids as $keyuserid => $dummy) {
							if ($keyuserid != $comment['userid'] && $keyuserid != @$posts[$parentid]['userid']) {
								qa_db_event_create_not_entity($keyuserid, $questionid, $comment['postid'], QA_UPDATE_FOLLOWS, $comment['userid'], $comment['created']);
							}
						}

						if (isset($comment['userid'])) {
							$keyuserids[$comment['userid']] = true;
						}
					}
				}
			}

			$this->nextItemId = $lastquestionid + 1;
			$this->processedItems += count($questionids);
			$this->totalItems = max($this->totalItems, $this->processedItems);
		}

		if (count($questionids) < self::BATCH_AMOUNT) {
			$this->isFinished = true;
		}
	}
}
