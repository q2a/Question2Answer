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

class Q2A_Admin_Recalc_DeleteHiddenPosts_ProcessManager extends Q2A_Admin_Recalc_AbstractProcessManager
{
	public function __construct()
	{
		$this->stateOption = 'qa_recalc_delete_hidden_posts_state';

		$this->steps = [
			Q2A_Admin_Recalc_DeleteHiddenPosts_StepDeleteHiddenComments::class,
			Q2A_Admin_Recalc_DeleteHiddenPosts_StepDeleteHiddenAnswers::class,
			Q2A_Admin_Recalc_DeleteHiddenPosts_StepDeleteHiddenQuestions::class,
		];
	}
}
