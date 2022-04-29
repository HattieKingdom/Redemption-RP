<?php

require_once 'modules/Scheduling/includes/calcSeats0.fnc.php';

// TABBED FY,SEM,QTR
// REPLACE DBDate() & date() WITH USER ENTERED VALUES
// ERROR HANDLING

DrawHeader( ProgramTitle() );

$_REQUEST['include_inactive'] = issetVal( $_REQUEST['include_inactive'], '' );
$_REQUEST['include_seats'] = issetVal( $_REQUEST['include_seats'], '' );

$date = RequestedDate( 'date', '' );

if ( ! $date )
{
	$min_date = DBGetOne( "SELECT min(SCHOOL_DATE) AS MIN_DATE
		FROM ATTENDANCE_CALENDAR
		WHERE SYEAR='" . UserSyear() . "'
		AND SCHOOL_ID='" . UserSchool() . "'" );

	$date = DBDate();

	// If today < first attendance day.

	if ( $min_date
		&& $date < $min_date )
	{
		$date = $min_date;
	}

	$date_exploded = ExplodeDate( $date );

	$_REQUEST['day_date'] = $date_exploded['day'];
	$_REQUEST['month_date'] = $date_exploded['month'];
	$_REQUEST['year_date'] = $date_exploded['year'];
}

$_SESSION['_REQUEST_vars']['modfunc'] = false;

Widgets( 'course' );
Widgets( 'request' );

Search( 'student_id', $extra );

// Add eventual Dates to $_REQUEST['schedule'].
AddRequestedDates( 'schedule', 'post' );

if ( $_REQUEST['modfunc'] === 'modify'
	&& ! empty( $_REQUEST['schedule'] )
	&& AllowEdit() )
{
	foreach ( (array) $_REQUEST['schedule'] as $course_period_id => $start_dates )
	{
		foreach ( (array) $start_dates as $start_date => $columns )
		{
			$sql = "UPDATE SCHEDULE SET ";

			if ( isset( $columns['MARKING_PERIOD_ID'] ) )
			{
				$mp = GetMP( $columns['MARKING_PERIOD_ID'], 'MP' );

				if ( $mp )
				{
					// Update MP column on MARKING_PERIOD_ID update!
					$columns['MP'] = $mp;
				}
			}

			foreach ( (array) $columns as $column => $value )
			{
				$sql .= DBEscapeIdentifier( $column ) . "='" . $value . "',";
			}

			$sql = mb_substr( $sql, 0, -1 ) . " WHERE STUDENT_ID='" . UserStudentID() . "'
			AND COURSE_PERIOD_ID='" . $course_period_id . "'
			AND START_DATE='" . $start_date . "'";

			DBQuery( $sql );

			if ( ! empty( $columns['START_DATE'] ) || ! empty( $columns['END_DATE'] ) )
			{
				$start_end_RET = DBGet( "SELECT START_DATE,END_DATE
				FROM SCHEDULE
				WHERE STUDENT_ID='" . UserStudentID() . "'
				AND COURSE_PERIOD_ID='" . $course_period_id . "'
				AND END_DATE<START_DATE" );

				if ( ! empty( $start_end_RET ) )
				{
					// User is asked if he wants absences and grades to be deleted.
					$delete_ok = DeletePrompt(
						_( 'Student\'s Absences and Grades' ),
						_( 'also delete' ),
						false
					);

					if ( $delete_ok )
					{
						// If user clicked Cancel or OK then pass else Display Prompt.
						if ( ! isset( $_REQUEST['delete_cancel'] ) )
						{
							// If user clicked OK.
							$delete_sql = "DELETE FROM GRADEBOOK_GRADES
								WHERE STUDENT_ID='" . UserStudentID() . "'
								AND COURSE_PERIOD_ID='" . $course_period_id . "';";

							$delete_sql .= "DELETE FROM STUDENT_REPORT_CARD_GRADES
								WHERE STUDENT_ID='" . UserStudentID() . "'
								AND COURSE_PERIOD_ID='" . $course_period_id . "';";

							$delete_sql .= "DELETE FROM STUDENT_REPORT_CARD_COMMENTS
								WHERE STUDENT_ID='" . UserStudentID() . "'
								AND COURSE_PERIOD_ID='" . $course_period_id . "';";

							$delete_sql .= "DELETE FROM ATTENDANCE_PERIOD
								WHERE STUDENT_ID='" . UserStudentID() . "'
								AND COURSE_PERIOD_ID='" . $course_period_id . "';";

							DBQuery( $delete_sql );
						}

						// Else simply delete schedule entry.
						DBQuery( "DELETE FROM SCHEDULE
							WHERE STUDENT_ID='" . UserStudentID() . "'
							AND COURSE_PERIOD_ID='" . $course_period_id . "'" );

						// Hook.
						do_action( 'Scheduling/Schedule.php|drop_student' );
					}
					else
					{
						$schedule_deletion_pending = true;
					}
				}
				else
				{
					DBQuery( "DELETE FROM ATTENDANCE_PERIOD
						WHERE STUDENT_ID='" . UserStudentID() . "'
						AND COURSE_PERIOD_ID='" . $course_period_id . "'
						AND (" . ( $columns['START_DATE'] ? "SCHOOL_DATE<'" . $columns['START_DATE'] . "'" : 'FALSE' ) .
							' OR ' . ( $columns['END_DATE'] ? "SCHOOL_DATE>'" . $columns['END_DATE'] . "'" : 'FALSE' ) . ")" );
				}
			}
		}
	}

	if ( empty( $schedule_deletion_pending ) )
	{
		// Unset modfunc & schedule & redirect URL.
		RedirectURL( [ 'modfunc', 'schedule' ] );
	}
}

if ( UserStudentID()
	&& ! $_REQUEST['modfunc'] )
{
	echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=modify' ) . '" method="POST">';

	DrawHeader( PrepareDate( $date, '_date', false, [ 'submit' => true ] ), SubmitButton() );

	DrawHeader(
		CheckBoxOnclick( 'include_inactive', _( 'Include Inactive Courses' ) ),
		( AllowEdit() ?
			CheckBoxOnclick( 'include_seats', _( 'Show Available Seats' ) ) :
			'' )
	);

	// Add Horizontal format option.
	$print_schedules_link = 'Modules.php?modname=Scheduling/PrintSchedules.php&modfunc=save&st_arr[]=' .
		UserStudentID() . '&_ROSARIO_PDF=true&schedule_table=Yes';
	?>
	<script>
		function horizontalFormatSwitch()
		{
			var byId = function( id ) {
				return document.getElementById( id );
			};

			if (byId("horizontalFormat").checked==true) {
				byId("printSchedulesLink").href=byId("printSchedulesLink").href+'&horizontalFormat';
			}
			else {
				byId("printSchedulesLink").href=byId("printSchedulesLink").href.replace('&horizontalFormat','');
			}
		}
	</script>
	<?php
//FJ add schedule table
	?>
	<script>
		function timeTableSwitch()
		{
			var byId = function( id ) {
				return document.getElementById( id );
			};

			if (byId("schedule_table").checked==true) {
				byId("printSchedulesLink").href=byId("printSchedulesLink").href.replace('Yes','No');
			}
			else {
				byId("printSchedulesLink").href=byId("printSchedulesLink").href.replace('No','Yes');
			}
		}
	</script>
	<?php
	if ( AllowUse( 'Scheduling/PrintSchedules.php' ) )
	{
		DrawHeader(
			'<a href="' . URLEscape( $print_schedules_link ) . '" target="_blank" id="printSchedulesLink">' .
			_( 'Print Schedule' ) . '</a><br />' .
			'<label><input name="schedule_table" type="radio" value="Yes" checked onchange="timeTableSwitch();" />&nbsp;' .
			_( 'Table' ) . '</label>' .
			' &nbsp;<label><input name="schedule_table" id="schedule_table" type="radio" value="No" onchange="timeTableSwitch();" />&nbsp;' .
			_( 'List' ) . '</label>' .
			' &nbsp;<label><input type="checkbox" id="horizontalFormat" name="horizontalFormat" value="Y" onchange="horizontalFormatSwitch();" /> ' .
			_( 'Horizontal Format' ) . '</label>'
		);
	}

	//FJ multiple school periods for a course period
	/*$sql = "SELECT
	s.COURSE_ID,s.COURSE_PERIOD_ID,
	s.MARKING_PERIOD_ID,s.START_DATE,s.END_DATE,
	extract(EPOCH FROM s.START_DATE) AS START_EPOCH,extract(EPOCH FROM s.END_DATE) AS END_EPOCH,sp.PERIOD_ID,
	cp.PERIOD_ID,cp.MARKING_PERIOD_ID AS COURSE_MARKING_PERIOD_ID,cp.MP,cp.CALENDAR_ID,cp.TOTAL_SEATS,
	c.TITLE,cp.COURSE_PERIOD_ID AS PERIOD_PULLDOWN,
	s.STUDENT_ID,ROOM,DAYS,SCHEDULER_LOCK
	FROM SCHEDULE s,COURSES c,COURSE_PERIODS cp,SCHOOL_PERIODS sp
	WHERE
	s.COURSE_ID = c.COURSE_ID AND s.COURSE_ID = cp.COURSE_ID
	AND s.COURSE_PERIOD_ID = cp.COURSE_PERIOD_ID
	AND s.SCHOOL_ID = sp.SCHOOL_ID AND s.SYEAR = c.SYEAR AND sp.PERIOD_ID = cp.PERIOD_ID
	AND s.STUDENT_ID='".UserStudentID()."'
	AND s.SYEAR='".UserSyear()."'
	AND s.SCHOOL_ID = '".UserSchool()."'";*/
	$sql = "SELECT s.COURSE_ID,s.COURSE_PERIOD_ID,s.MARKING_PERIOD_ID,s.START_DATE,s.END_DATE,
		extract(EPOCH FROM s.START_DATE) AS START_EPOCH,extract(EPOCH FROM s.END_DATE) AS END_EPOCH,
		cp.MARKING_PERIOD_ID AS COURSE_MARKING_PERIOD_ID,cp.MP,cp.CALENDAR_ID,cp.TOTAL_SEATS,
		c.TITLE,cp.COURSE_PERIOD_ID AS PERIOD_PULLDOWN,s.STUDENT_ID,ROOM,SCHEDULER_LOCK
		FROM SCHEDULE s,COURSES c,COURSE_PERIODS cp
		WHERE s.COURSE_ID=c.COURSE_ID
		AND s.COURSE_ID=cp.COURSE_ID
		AND s.COURSE_PERIOD_ID=cp.COURSE_PERIOD_ID
		AND s.SYEAR=c.SYEAR
		AND s.STUDENT_ID='" . UserStudentID() . "'
		AND s.SYEAR='" . UserSyear() . "'
		AND s.SCHOOL_ID='" . UserSchool() . "'";

	if ( $_REQUEST['include_inactive'] != 'Y' )
	{
		$sql .= " AND ('" . $date . "' BETWEEN s.START_DATE AND s.END_DATE OR (s.END_DATE IS NULL AND s.START_DATE<='" . $date . "')) ";
	}

	//$sql .= " ORDER BY sp.SORT_ORDER,s.MARKING_PERIOD_ID";
	$sql .= " ORDER BY c.TITLE,cp.SHORT_NAME,s.MARKING_PERIOD_ID";

	$schedule_RET = DBGet(
		$sql,
		[
			'PERIOD_PULLDOWN' => '_makePeriodSelect',
			'COURSE_MARKING_PERIOD_ID' => '_makeMPSelect',
			'SCHEDULER_LOCK' => '_makeLock',
			'START_DATE' => '_makeDate',
			'END_DATE' => '_makeDate',
		]
	);

	//FJ bugfix SQL bug $_SESSION['student_id'] is not set
	$link['add']['link'] = '# onclick=\'popups.open("Modules.php?modname=' . $_REQUEST['modname'] .
		'&modfunc=choose_course&day_date=' . $_REQUEST['day_date'] .
		'&month_date=' . $_REQUEST['month_date'] .
		'&year_date=' . $_REQUEST['year_date'] . '","",
		"scrollbars=yes,resizable=yes,width=900,height=400"); return false;\'';

	$link['add']['title'] = _( 'Add a Course' );

	$columns = [
		'TITLE' => _( 'Course' ),
		'PERIOD_PULLDOWN' => _( 'Period' ) . ' ' . _( 'Days' ) . ' - ' . _( 'Short Name' ) . ' - ' . _( 'Teacher' ),
		'ROOM' => _( 'Room' ),
		'COURSE_MARKING_PERIOD_ID' => _( 'Term' ),
		'SCHEDULER_LOCK' => '<img src="assets/themes/' . Preferences( 'THEME' ) .
			'/btn/locked.png" class="button bigger" alt="' . AttrEscape( _( 'Locked' ) ) . '">' .
			'<span class="a11y-hidden">' . _( 'Locked' ) . '</span>',
		'START_DATE' => _( 'Enrolled' ),
		'END_DATE' => _( 'Dropped' ),
	];

	/*//FJ multiple school periods for a course period
	//$days_RET = DBGet( "SELECT DISTINCT DAYS FROM COURSE_PERIODS WHERE SCHOOL_ID='".UserSchool()."' AND SYEAR='".UserSyear()."'" );
	$days_RET = DBGet( "SELECT DISTINCT cpsp.DAYS FROM COURSE_PERIODS cp, COURSE_PERIOD_SCHOOL_PERIODS cpsp WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID AND cp.SCHOOL_ID='".UserSchool()."' AND cp.SYEAR='".UserSyear()."'" );
	if (count($days_RET)==1)
	unset($columns['DAYS']);

	//FJ days display to locale
	$days_convert = array('U' => _('Sunday'),'M' => _('Monday'),'T' => _('Tuesday'),'W' => _('Wednesday'),'H' => _('Thursday'),'F' => _('Friday'),'S' => _('Saturday'));
	//FJ days numbered
	if (SchoolInfo('NUMBER_DAYS_ROTATION') !== null)
	$days_convert = array('U' => '7','M' => '1','T' => '2','W' => '3','H' => '4','F' => '5','S' => '6');

	for ($j = 1; $j <= count($schedule_RET); $j++) {
	$columns_DAYS_locale = '';
	$days_strlen = mb_strlen($schedule_RET[ $j ]['DAYS']);
	for ($i = 0; $i < $days_strlen; $i++) {
	$columns_DAYS_locale .= mb_substr($days_convert[mb_substr($schedule_RET[ $j ]['DAYS'], $i, 1)],0,3) . '.&nbsp;';
	}
	$schedule_RET[ $j ]['DAYS'] = $columns_DAYS_locale;
	}*/

	VerifySchedule( $schedule_RET );

	ListOutput( $schedule_RET, $columns, 'Course', 'Courses', $link );

	echo '<br /><div class="center">' . SubmitButton() . '</div>';
	echo '</form>';

	if ( AllowEdit() )
	{
		// Add proper Unfilled Requests list.
		unset( $extra );
		unset( $link );
		unset( $columns );

		// Include calcSeats, _makeRequestTeacher & _makeRequestPeriod functions.
		require_once 'modules/Scheduling/includes/unfilledRequests.inc.php';

		$extra['WHERE'] = " AND s.STUDENT_ID='" . UserStudentID() . "'";
		$extra['FROM'] = ',SCHEDULE_REQUESTS sr,COURSES c';

		$custom_fields_RET = DBGet( "SELECT ID,TITLE,TYPE
			FROM CUSTOM_FIELDS
			WHERE ID=200000000", [], [ 'ID' ] );

		if ( $custom_fields_RET['200000000'] && $custom_fields_RET['200000000'][1]['TYPE'] == 'select' )
		{
			$extra['SELECT'] = ',s.CUSTOM_200000000,c.TITLE AS COURSE,sr.SUBJECT_ID,sr.COURSE_ID,sr.WITH_TEACHER_ID,sr.NOT_TEACHER_ID,sr.WITH_PERIOD_ID,sr.NOT_PERIOD_ID,\'0\' AS AVAILABLE_SEATS,(SELECT count(*) AS SECTIONS FROM COURSE_PERIODS cp WHERE cp.COURSE_ID=sr.COURSE_ID AND (cp.GENDER_RESTRICTION=\'N\' OR cp.GENDER_RESTRICTION=substring(s.CUSTOM_200000000,1,1)) AND (sr.WITH_TEACHER_ID IS NULL OR sr.WITH_TEACHER_ID=cp.TEACHER_ID) AND (sr.NOT_TEACHER_ID IS NULL OR sr.NOT_TEACHER_ID!=cp.TEACHER_ID)) AS SECTIONS ';
		}
		else //'None' as GENDER
		{
			$extra['SELECT'] = ',\'None\' AS CUSTOM_200000000,c.TITLE AS COURSE,sr.SUBJECT_ID,sr.COURSE_ID,sr.WITH_TEACHER_ID,sr.NOT_TEACHER_ID,sr.WITH_PERIOD_ID,sr.NOT_PERIOD_ID,\'0\' AS AVAILABLE_SEATS,(SELECT count(*) AS SECTIONS FROM COURSE_PERIODS cp WHERE cp.COURSE_ID=sr.COURSE_ID AND (cp.GENDER_RESTRICTION=\'N\' OR cp.GENDER_RESTRICTION=substring(\'None\',1,1)) AND (sr.WITH_TEACHER_ID IS NULL OR sr.WITH_TEACHER_ID=cp.TEACHER_ID) AND (sr.NOT_TEACHER_ID IS NULL OR sr.NOT_TEACHER_ID!=cp.TEACHER_ID)) AS SECTIONS ';
		}

		$extra['WHERE'] .= ' AND sr.STUDENT_ID=ssm.STUDENT_ID AND sr.SYEAR=ssm.SYEAR AND sr.SCHOOL_ID=ssm.SCHOOL_ID AND sr.COURSE_ID=c.COURSE_ID AND NOT EXISTS (SELECT \'\' FROM SCHEDULE s WHERE s.STUDENT_ID=sr.STUDENT_ID AND s.COURSE_ID=sr.COURSE_ID)';
		$extra['functions'] = [ 'WITH_TEACHER_ID' => '_makeRequestTeacher', 'WITH_PERIOD_ID' => '_makeRequestPeriod' ];

		$columns = [
			'COURSE' => _( 'Request' ),
			'SECTIONS' => _( 'Sections' ),
			'WITH_TEACHER_ID' => _( 'Teacher' ),
			'WITH_PERIOD_ID' => _( 'Period' ),
		];

		if ( ! empty( $_REQUEST['include_seats'] ) )
		{
			$columns += [ 'AVAILABLE_SEATS' => _( 'Available Seats' ) ];
			$extra['functions'] += [ 'AVAILABLE_SEATS' => 'CalcSeats' ];
		}

		$link['COURSE']['link'] = 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=choose_course';

		$link['COURSE']['variables'] = [
			'subject_id' => 'SUBJECT_ID',
			'course_id' => 'COURSE_ID',
			'student_id' => 'STUDENT_ID',
		];

		$link['COURSE']['js'] = true;

		$options = [ 'search' => false, 'save' => false ];

		$unfilled_requests_RET = GetStuList( $extra );

		ListOutput(
			$unfilled_requests_RET,
			$columns,
			'Unfilled Request',
			'Unfilled Requests',
			$link,
			[],
			$options
		);
	}
}

if ( $_REQUEST['modfunc'] == 'choose_course' )
{
	if ( empty( $_REQUEST['course_period_id'] ) )
	{
		require_once 'modules/Scheduling/Courses.php';
	}
	else
	{
		//FJ multiple school periods for a course period
		$mp_RET = DBGet( "SELECT cp.COURSE_PERIOD_ID,cp.MARKING_PERIOD_ID,cp.MP,
			cpsp.DAYS,cpsp.PERIOD_ID,cp.MARKING_PERIOD_ID,cp.TOTAL_SEATS,cp.CALENDAR_ID
			FROM COURSE_PERIODS cp,COURSE_PERIOD_SCHOOL_PERIODS cpsp
			WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
			AND cp.COURSE_PERIOD_ID='" . (int) $_REQUEST['course_period_id'] . "'" );

		if ( ! empty( $_REQUEST['course_marking_period_id'] ) )
		{
			$mp_RET[1]['MARKING_PERIOD_ID'] = $_REQUEST['course_marking_period_id'];
			$mp_RET[1]['MP'] = GetMP( $_REQUEST['course_marking_period_id'], 'MP' );
		}

		$mps = GetAllMP( $mp_RET[1]['MP'], $mp_RET[1]['MARKING_PERIOD_ID'] );

		if ( $mp_RET[1]['TOTAL_SEATS'] )
		{
			$seats = calcSeats0( $mp_RET[1], $date );

			if ( $seats != '' && $seats >= $mp_RET[1]['TOTAL_SEATS'] )
			{
				$warnings[] = _( 'This section is already full.' );
			}
		}

		// the course being scheduled has start date of $date but no end date by default, and scheduled into the course marking period by default
		// if marking periods overlap and dates overlap (already scheduled course does not end or ends after $date) then not okay
		$current_RET = DBGet( "SELECT COURSE_PERIOD_ID
			FROM SCHEDULE
			WHERE STUDENT_ID='" . UserStudentID() . "'
			AND COURSE_ID='" . (int) $_REQUEST['course_id'] . "'
			AND MARKING_PERIOD_ID IN (" . $mps . ")
			AND (END_DATE IS NULL OR '" . DBDate() . "'<=END_DATE)" );

		if ( ! empty( $current_RET ) )
		{
			$warnings[] = _( 'This student is already scheduled into this course.' );
		}

		//FJ multiple school periods for a course period
		//if marking periods overlap and same period and same day then not okay
		//$period_RET = DBGet( "SELECT cp.DAYS FROM SCHEDULE s,COURSE_PERIODS cp WHERE cp.COURSE_PERIOD_ID=s.COURSE_PERIOD_ID AND s.STUDENT_ID='".UserStudentID()."' AND cp.PERIOD_ID='".$mp_RET[1]['PERIOD_ID']."' AND s.MARKING_PERIOD_ID IN (".$mps.") AND (s.END_DATE IS NULL OR '".DBDate()."'<=s.END_DATE)" );
		$period_RET = DBGet( "SELECT cpsp.DAYS
		FROM SCHEDULE s,COURSE_PERIOD_SCHOOL_PERIODS cpsp
		WHERE cpsp.COURSE_PERIOD_ID=s.COURSE_PERIOD_ID
		AND s.STUDENT_ID='" . UserStudentID() . "'
		AND cpsp.PERIOD_ID='" . $mp_RET[1]['PERIOD_ID'] . "'
		AND s.MARKING_PERIOD_ID IN (" . $mps . ")
		AND (s.END_DATE IS NULL OR '" . DBDate() . "'<=s.END_DATE)" );

		$days_conflict = false;

		foreach ( (array) $period_RET as $existing )
		{
			if ( mb_strlen( $mp_RET[1]['DAYS'] ) + mb_strlen( $existing['DAYS'] ) > 7 )
			{
				$days_conflict = true;
				break;
			}
			else
			{
				foreach ( _str_split( $mp_RET[1]['DAYS'] ) as $i )
				{
					if ( mb_strpos( $existing['DAYS'], $i ) !== false )
					{
						$days_conflict = true;
						break 2;
					}
				}
			}
		}

		if ( $days_conflict )
		{
			$warnings[] = _( 'There is already a course scheduled in that period.' );
		}

		if ( empty( $warnings ) || Prompt( 'Confirm', _( 'There is a conflict.' ) . ' ' . _( 'Are you sure you want to add this section?' ), ErrorMessage( $warnings, 'warning' ) ) )
		{
			DBQuery( "INSERT INTO SCHEDULE (SYEAR,SCHOOL_ID,STUDENT_ID,START_DATE,COURSE_ID,COURSE_PERIOD_ID,MP,MARKING_PERIOD_ID)
				VALUES('" . UserSyear() . "','" . UserSchool() . "','" . UserStudentID() . "','" .
				$date . "','" . $_REQUEST['course_id'] . "','" . $_REQUEST['course_period_id'] . "','" .
				$mp_RET[1]['MP'] . "','" . $mp_RET[1]['MARKING_PERIOD_ID'] . "')" );

			// Hook.
			do_action( 'Scheduling/Schedule.php|schedule_student' );

			$opener_URL = "'Modules.php?modname=" . $_REQUEST['modname'] .
			'&year_date=' . $_REQUEST['year_date'] .
			'&month_date=' . $_REQUEST['month_date'] .
			'&day_date=' . $_REQUEST['day_date'] .
			'&time=' . time() . "'";

			echo '<script>window.opener.ajaxLink(' . $opener_URL . '); window.close();</script>';
		}
	}
}

/**
 * @param $value
 * @param $column
 * @return mixed
 */
function _makeLock( $value, $column )
{
	global $THIS_RET;

	static $js_included = false;

	if ( isset( $_REQUEST['_ROSARIO_PDF'] ) )
	{
		return $value == 'Y' ? _( 'Locked' ) : _( 'Unlocked' );
	}

	$return = '';

	if ( ! $js_included )
	{
		if ( AllowEdit() )
		{
			$return = "<script>function switchLock(el,lockid){
				if (el.src.indexOf('unlocked')==-1) {
					el.src = el.src.replace('locked', 'unlocked');
					el.title = el.alt = " . json_encode( _( 'Unlocked' ) ) . "
					document.getElementById(lockid).value='';
				} else {
					el.src = el.src.replace('unlocked', 'locked');
					el.title = el.alt = " . json_encode( _( 'Locked' ) ) . "
					document.getElementById(lockid).value='Y';
				}
			}</script>";
		}

		$js_included = true;
	}

	$lock_id = GetInputID( 'lock' . $THIS_RET['COURSE_PERIOD_ID'] . '-' . $THIS_RET['START_DATE'] );

	$lock_name = 'schedule[' . $THIS_RET['COURSE_PERIOD_ID'] . '][' . $THIS_RET['START_DATE'] . '][SCHEDULER_LOCK]';

	$title_alt = $value == 'Y' ? _( 'Locked' ) : _( 'Unlocked' );

	return $return . '<img src="assets/themes/' .
	Preferences( 'THEME' ) . '/btn/' . ( $value == 'Y' ? 'locked' : 'unlocked' ) .
		'.png" title="' . AttrEscape( $title_alt ) . '"
		alt="' . AttrEscape( $title_alt ) . '"
		class="button bigger" style="cursor: pointer;"' .
		( AllowEdit() ? ' onclick="switchLock(this, \'' . $lock_id . '\');" />
			<input type="hidden" name="' . AttrEscape( $lock_name ) . '" id="' . $lock_id . '" value="' . AttrEscape( $value ) . '" />' :
		' />' );
}

/**
 * @param $course_period_id
 * @param $column
 */
function _makePeriodSelect( $course_period_id, $column )
{
	global $THIS_RET;

	$fy_id = GetFullYearMP();

	//FJ multiple school periods for a course period
	//$orders_RET = DBGet( "SELECT COURSE_PERIOD_ID,PARENT_ID,TITLE,MARKING_PERIOD_ID,MP,CALENDAR_ID,(SELECT SHORT_NAME FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID=cp.PARENT_ID) AS PARENT,TOTAL_SEATS FROM COURSE_PERIODS cp WHERE COURSE_ID='".$THIS_RET['COURSE_ID']."' ORDER BY (SELECT SORT_ORDER FROM SCHOOL_PERIODS WHERE PERIOD_ID=cp.PERIOD_ID),TITLE" );
	$orders_RET = DBGet( "SELECT COURSE_PERIOD_ID,PARENT_ID,TITLE,MARKING_PERIOD_ID,MP,CALENDAR_ID,
		(SELECT SHORT_NAME FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID=cp.PARENT_ID) AS PARENT,TOTAL_SEATS
		FROM COURSE_PERIODS cp
		WHERE COURSE_ID='" . $THIS_RET['COURSE_ID'] . "'
		ORDER BY SHORT_NAME,TITLE" );

	foreach ( (array) $orders_RET as $value )
	{
		if ( $value['TOTAL_SEATS'] && $_REQUEST['include_seats'] )
		{
			$seats = calcSeats0( $value );
		}

		$periods[$value['COURSE_PERIOD_ID']] = $value['TITLE'] .
			( ( $value['MARKING_PERIOD_ID'] != $fy_id
				&& $value['COURSE_PERIOD_ID'] != $course_period_id ) ?
				' (' . GetMP( $value['MARKING_PERIOD_ID'] ) . ')' : '' ) .
			( ( $value['TOTAL_SEATS']
				&& $_REQUEST['include_seats']
				&& $seats != '' ) ?
				' ' . sprintf( _( '(%d seats)' ), ( $value['TOTAL_SEATS'] - $seats ) ) : '' ) .
			( ( $value['COURSE_PERIOD_ID'] != $course_period_id
				&& $value['COURSE_PERIOD_ID'] != $value['PARENT_ID']
				&& $value['PARENT'] ) ?
				' -> ' . $value['PARENT'] : '' );
	}

	return SelectInput(
		$course_period_id,
		'schedule[' . $THIS_RET['COURSE_PERIOD_ID'] . '][' . $THIS_RET['START_DATE'] . '][COURSE_PERIOD_ID]',
		'',
		$periods,
		false,
		'style="max-width: 300px;"'
	);
}

/**
 * @param $mp_id
 * @param $name
 */
function _makeMPSelect( $mp_id, $name )
{
	global $_ROSARIO, $THIS_RET;

	$fy_id = GetFullYearMP();

	if ( empty( $_ROSARIO['_makeMPSelect'] ) )
	{
		$semesters_RET = DBGet( "SELECT MARKING_PERIOD_ID,TITLE,NULL AS PARENT_ID
			FROM SCHOOL_MARKING_PERIODS
			WHERE MP='SEM'
			AND SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			ORDER BY SORT_ORDER" );

		$quarters_RET = DBGet( "SELECT MARKING_PERIOD_ID,TITLE,PARENT_ID
			FROM SCHOOL_MARKING_PERIODS
			WHERE MP='QTR'
			AND SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			ORDER BY SORT_ORDER" );

		$_ROSARIO['_makeMPSelect'][$fy_id][1] = [
			'MARKING_PERIOD_ID' => $fy_id,
			'TITLE' => _( 'Full Year' ),
			'PARENT_ID' => '',
		];

		foreach ( (array) $semesters_RET as $sem )
		{
			$_ROSARIO['_makeMPSelect'][$fy_id][] = $sem;
		}

		foreach ( (array) $quarters_RET as $qtr )
		{
			$_ROSARIO['_makeMPSelect'][$fy_id][] = $qtr;
		}

		$quarters_indexed_RET = DBGet( "SELECT MARKING_PERIOD_ID,TITLE,PARENT_ID
			FROM SCHOOL_MARKING_PERIODS
			WHERE MP='QTR'
			AND SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			ORDER BY SORT_ORDER", [], [ 'PARENT_ID' ] );

		foreach ( (array) $semesters_RET as $sem )
		{
			$_ROSARIO['_makeMPSelect'][$sem['MARKING_PERIOD_ID']][1] = $sem;

			if ( ! empty( $quarters_indexed_RET[$sem['MARKING_PERIOD_ID']] ) )
			{
				foreach ( (array) $quarters_indexed_RET[$sem['MARKING_PERIOD_ID']] as $qtr )
				{
					$_ROSARIO['_makeMPSelect'][$sem['MARKING_PERIOD_ID']][] = $qtr;
				}
			}
		}

		foreach ( (array) $quarters_RET as $qtr )
		{
			$_ROSARIO['_makeMPSelect'][$qtr['MARKING_PERIOD_ID']][] = $qtr;
		}
	}

	if ( is_array( $_ROSARIO['_makeMPSelect'][$mp_id] ) )
	{
		foreach ( (array) $_ROSARIO['_makeMPSelect'][$mp_id] as $value )
		{
			if ( $value['MARKING_PERIOD_ID'] != $THIS_RET['MARKING_PERIOD_ID']
				&& $THIS_RET['TOTAL_SEATS']
				&& $_REQUEST['include_seats'] )
			{
				$seats = calcSeats0( $THIS_RET );
			}

			$mps[$value['MARKING_PERIOD_ID']] = ( ( $value['MARKING_PERIOD_ID'] == $THIS_RET['MARKING_PERIOD_ID']
				&& $value['MARKING_PERIOD_ID'] != $mp_id ) ?
				'* ' : '' ) .
				$value['TITLE'] .
				( ( $value['MARKING_PERIOD_ID'] != $THIS_RET['MARKING_PERIOD_ID']
					&& $THIS_RET['TOTAL_SEATS']
					&& $_REQUEST['include_seats']
					&& $seats != '' ) ?
				' ' . sprintf( _( '(%d seats)' ), ( $THIS_RET['TOTAL_SEATS'] - $seats ) ) : '' );
		}
	}
	else
	{
		$mps = [];
	}

	return SelectInput(
		$THIS_RET['MARKING_PERIOD_ID'],
		'schedule[' . $THIS_RET['COURSE_PERIOD_ID'] . '][' . $THIS_RET['START_DATE'] . '][MARKING_PERIOD_ID]',
		'',
		$mps,
		false
	);
}

/**
 * @param $value
 * @param $column
 */
function _makeDate( $value, $column )
{
	global $THIS_RET;

	if ( $column == 'START_DATE' )
	{
		$allow_na = false;
	}
	else
	{
		$allow_na = true;
	}

	return DateInput(
		$value,
		'schedule[' . $THIS_RET['COURSE_PERIOD_ID'] . '][' . $THIS_RET['START_DATE'] . '][' . $column . ']',
		'',
		true,
		$allow_na
	);
}

/**
 * @param $schedule
 */
function VerifySchedule( &$schedule )
{
	$conflicts = [];

	$ij = count( $schedule );

	for ( $i = 1; $i < $ij; $i++ )
	{
		for ( $j = $i + 1; $j <= $ij; $j++ )
		{
			if ( empty( $conflicts[$i] ) || empty( $conflicts[$j] ) )
			// the following two if's are equivalent, the second matches the 'Add a Course' logic, the first is the demorgan equivalent and easier to follow
			// if -not- marking periods don't overlap -or- dates don't overlap (i ends and j starts after i -or- j ends and i starts after j) then check further
			//if ( ! (mb_strpos(GetAllMP(GetMP($schedule[ $i ]['MARKING_PERIOD_ID'],'MP'),$schedule[ $i ]['MARKING_PERIOD_ID']),"'".$schedule[ $j ]['MARKING_PERIOD_ID']."'")===false
			//|| $schedule[ $i ]['END_EPOCH'] && $schedule[ $j ]['START_EPOCH']>$schedule[ $i ]['END_EPOCH'] || $schedule[ $j ]['END_EPOCH'] && $schedule[ $i ]['START_EPOCH']>$schedule[ $j ]['END_EPOCH']))
			// if marking periods overlap -and- dates overlap (i doesn't end or j starts before i ends -and- j doesn't end or i starts before j ends) check further

			{
				if ( mb_strpos( GetAllMP( GetMP( $schedule[$i]['MARKING_PERIOD_ID'], 'MP' ), $schedule[$i]['MARKING_PERIOD_ID'] ), "'" . $schedule[$j]['MARKING_PERIOD_ID'] . "'" ) !== false
					&& ( ! $schedule[$i]['END_EPOCH'] || $schedule[$j]['START_EPOCH'] <= $schedule[$i]['END_EPOCH'] ) && ( ! $schedule[$j]['END_EPOCH'] || $schedule[$i]['START_EPOCH'] <= $schedule[$j]['END_EPOCH'] ) )
				// should not be enrolled in the same course with overlapping marking periods and dates

				{
					if ( $schedule[$i]['COURSE_ID'] == $schedule[$j]['COURSE_ID'] )
					{
						$conflicts[$i] = $conflicts[$j] = true;
					}
					else
					// if different periods then okay

					if ( ! empty( $schedule[$i]['PERIOD_ID'] )
						&& $schedule[$i]['PERIOD_ID'] == $schedule[$j]['PERIOD_ID'] )
					// should not be enrolled in the same period on the same day

					{
						if ( mb_strlen( $schedule[$i]['DAYS'] ) + mb_strlen( $schedule[$j]['DAYS'] ) > 7 )
						{
							$conflicts[$i] = $conflicts[$j] = true;
						}
						else
						{
							foreach ( _str_split( $schedule[$i]['DAYS'] ) as $k )
							{
								if ( mb_strpos( $schedule[$j]['DAYS'], $k ) !== false )
								{
									$conflicts[$i] = $conflicts[$j] = true;
									break;
								}
							}
						}
					}
				}
			}
		}
	}

	foreach ( (array) $conflicts as $i => $true )
	{
		$schedule[$i]['TITLE'] = '<span style="color:red">' . $schedule[$i]['TITLE'] . '</span>';
	}
}

/**
 * @param $str
 * @return mixed
 */
function _str_split( $str )
{
	$ret = [];
	$len = mb_strlen( $str );

	for ( $i = 0; $i < $len; $i++ )
	{
		$ret[] = mb_substr( $str, $i, 1 );
	}

	return $ret;
}
