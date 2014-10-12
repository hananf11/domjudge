<?php
/**
 * View a problem
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();
$title = 'Problem p'.htmlspecialchars(@$id);
$title = ucfirst((empty($_GET['cmd']) ? '' : htmlspecialchars($_GET['cmd']) . ' ') .
                 'problem' . ($id ? ' p'.htmlspecialchars(@$id) : ''));

if ( isset($_POST['cmd']) ) {
	$pcmd = $_POST['cmd'];
} elseif ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.$pagename.'?id='.urlencode($id);
}

if ( !empty($pcmd) ) {

	if ( empty($id) ) error("Missing problem id");

	if ( isset($pcmd['toggle_submit']) ) {
		$DB->q('UPDATE problem SET allow_submit = %i WHERE probid = %i',
			   $_POST['val']['toggle_submit'], $id);
		auditlog('problem', $id, 'set allow submit', $_POST['val']['toggle_submit']);
	}

	if ( isset($pcmd['toggle_judge']) ) {
		$DB->q('UPDATE problem SET allow_judge = %i WHERE probid = %i',
			   $_POST['val']['toggle_judge'], $id);
		auditlog('problem', $id, 'set allow judge', $_POST['val']['toggle_judge']);
	}

}

// This doesn't return, call before sending headers
if ( isset($cmd) && $cmd == 'viewtext' ) putProblemText($id);

$jscolor=true;

require(LIBWWWDIR . '/header.php');

if ( isset($_POST['upload']) ) {
	if ( !empty($_FILES['problem_archive']['tmp_name'][0]) ) {
		foreach($_FILES['problem_archive']['tmp_name'] as $fileid => $tmpname) {
			checkFileUpload( $_FILES['problem_archive']['error'][$fileid] );
			$zip = openZipFile($_FILES['problem_archive']['tmp_name'][$fileid]);
			$newid = importZippedProblem($zip, empty($id) ? NULL : $id);
			$zip->close();
			auditlog('problem', $newid, 'upload zip',
			         $_FILES['problem_archive']['name'][$fileid]);
		}
		if ( count($_FILES['problem_archive']['tmp_name']) == 1 ) {
			$probid = empty($newid) ? $id : $newid;
			$data = $DB->q('TUPLE SELECT shortname, name FROM problem
			                WHERE probid = %i', $probid);

			echo '<p><a href="' . $pagename.'?id='.urlencode($probid) .
			    '">Return to problem ' . $data['shortname'] . ': ' .
			    $data['shortname'] . ".</a></p>\n";
		}
		echo "<p><a href=\"problems.php\">Return to problems overview.</a></p>\n";
	} else {
		error("Missing filename for problem upload");
	}

	require(LIBWWWDIR . '/footer.php');
	exit;
}

if ( !empty($cmd) ):

	requireAdmin();

	echo "<h2>$title</h2>\n\n";

	echo addForm('edit.php', 'post', null, 'multipart/form-data');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Problem ID:</td><td>";
		$row = $DB->q('TUPLE SELECT p.probid,p.cid,p.shortname,p.name,p.allow_submit,p.allow_judge,
		                            p.timelimit,p.special_run,p.special_compare,p.color,
		                            p.problemtext_type, COUNT(testcaseid) AS testcases
		               FROM problem p
		               LEFT JOIN testcase USING (probid)
		               WHERE probid = %i GROUP BY probid', $id);
		echo addHidden('keydata[0][probid]', $row['probid']);
		echo "p" . htmlspecialchars($row['probid']);
		echo "</td></tr>\n";
	}

?>
<tr><td><label for="data_0__shortname_">Shortname:</label></td><td>
<?php echo addInput('data[0][shortname]', @$row['shortname'], 8, 10,
                    " required pattern=\"" . IDENTIFIER_CHARS . "+\"") .
           "(alphanumerics only)"; ?></td></tr>

<tr><td><label for="data_0__name_">Problem name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 30, 255, 'required')?></td></tr>

<tr><td>Allow submit:</td>
<td><?php echo addRadioButton('data[0][allow_submit]', (!isset($row['allow_submit']) || $row['allow_submit']), 1)?> <label for="data_0__allow_submit_1">yes</label>
<?php echo addRadioButton('data[0][allow_submit]', (isset($row['allow_submit']) && !$row['allow_submit']), 0)?> <label for="data_0__allow_submit_0">no</label></td></tr>

<tr><td>Allow judge:</td>
<td><?php echo addRadioButton('data[0][allow_judge]', (!isset($row['allow_judge']) || $row['allow_judge']), 1)?> <label for="data_0__allow_judge_1">yes</label>
<?php echo addRadioButton('data[0][allow_judge]', (isset($row['allow_judge']) && !$row['allow_judge']), 0)?> <label for="data_0__allow_judge_0">no</label></td></tr>
<?php
    if ( !empty($row['probid']) ) {
		echo '<tr><td>Testcases:</td><td>' .
			$row['testcases'] . ' <a href="testcase.php?probid=' .
			urlencode($row['probid']) . "\">details/edit</a></td></tr>\n";
	}
?>
<tr><td><label for="data_0__timelimit_">Timelimit:</label></td>
<td><?php echo addInputField('number','data[0][timelimit]', @$row['timelimit'],
	' min="1" max="10000" required')?> sec</td></tr>

<tr><td><label for="data_0__color_">Balloon colour:</label></td>
<td><?php echo addInputField('color','data[0][color]', @$row['color'],
	' class="color {required:false,adjust:false,hash:true,caps:false}"')?>
<a target="_blank"
href="http://www.w3schools.com/cssref/css_colornames.asp"><img
src="../images/b_help.png" class="smallpicto" alt="?" /></a></td></tr>

<tr><td><label for="data_0__problemtext_">Problem text:</label></td>
<td><?php
echo addFileField('data[0][problemtext]', 30, ' accept="text/plain,text/html,application/pdf"');
if ( !empty($row['problemtext_type']) ) {
	echo addCheckBox('unset[0][problemtext]') .
		'<label for="unset_0__problemtext_">delete</label>';
}
?></td></tr>

<tr><td><label for="data_0__special_run_">Special run script:</label></td>
<td>
<?php
$execmap = $DB->q("KEYVALUETABLE SELECT execid,description FROM executable
			WHERE type = 'run'
			ORDER BY execid");
$execmap[''] = 'none';
echo addSelect('data[0][special_run]', $execmap, @$row['special_run'], True);
?>
</td></tr>

<tr><td><label for="data_0__special_compare_">Special compare script:</label></td>
<td>
<?php
$execmap = $DB->q("KEYVALUETABLE SELECT execid,description FROM executable
			WHERE type = 'compare'
			ORDER BY execid");
$execmap[''] = 'none';
echo addSelect('data[0][special_compare]', $execmap, @$row['special_compare'], True);
?>
</td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','problem') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
	addEndForm();


if ( class_exists("ZipArchive") ) {
	echo "<br /><em>or</em><br /><br />\n" .
	addForm($pagename, 'post', null, 'multipart/form-data') .
	addHidden('id', @$row['probid']) .
	'<label for="problem_archive__">Upload problem archive:</label>' .
	addFileField('problem_archive[]') .
	addSubmit('Upload', 'upload') .
	addEndForm();
}

require(LIBWWWDIR . '/footer.php');
exit;

endif;

$data = $DB->q('TUPLE SELECT p.probid,p.cid,p.shortname,p.name,p.allow_submit,p.allow_judge,
                             p.timelimit,p.special_run,p.special_compare,p.color,
			     p.problemtext_type, count(rank) AS ntestcases
                FROM problem p
                LEFT JOIN testcase USING (probid)
                WHERE probid = %i GROUP BY probid', $id);
$numcontests = $DB->q("VALUE SELECT COUNT(*) AS contestcount
		       FROM gewis_contestproblem
		       WHERE probid = %i", $id);

if ( ! $data ) error("Missing or invalid problem id");

echo "<h1>Problem ".htmlspecialchars($data['shortname']).
	" - ".htmlspecialchars($data['name'])."</h1>\n\n";

echo addForm($pagename . '?id=' . urlencode($id),
             'post', null, 'multipart/form-data') . "<p>\n" .
	addHidden('id', $id) .
	addHidden('val[toggle_judge]',  !$data['allow_judge']) .
	addHidden('val[toggle_submit]', !$data['allow_submit']).
	"</p>\n";
?>
<table>
<tr><td>ID:          </td><td>p<?php echo htmlspecialchars($data['probid'])?></td></tr>
<tr><td>Shortname:   </td><td class="probid"><?php echo htmlspecialchars($data['shortname'])?></td></tr>
<tr><td>Name:        </td><td><?php echo htmlspecialchars($data['name'])?></td></tr>
<tr><td>Contests:    </td><td><?php
	if ( $numcontests==0 ) {
		echo '<em>no contests</em>';
	} else {
		echo (int)$numcontests;
	}
	echo ' <a href="contestproblem.php?probid='.urlencode($data['probid']).'">details/edit</a>';
?></td></tr>
<tr><td>Allow submit:</td><td class="nobreak"><?php echo printyn($data['allow_submit']) . ' '.
	addSubmit('toggle', 'cmd[toggle_submit]',
		"return confirm('" . ($data['allow_submit'] ? 'Disallow' : 'Allow') .
		" submissions for this problem?')"); ?>
</td></tr>
<tr><td>Allow judge: </td><td><?php echo printyn($data['allow_judge']) . ' '.
	addSubmit('toggle', 'cmd[toggle_judge]',
		"return confirm('" . ($data['allow_judge'] ? 'Disallow' : 'Allow') .
		" judging for this problem?')"); ?>
</td></tr>
<tr><td>Testcases:   </td><td><?php
    if ( $data['ntestcases']==0 ) {
		echo '<em>no testcases</em>';
	} else {
		echo (int)$data['ntestcases'];
	}
	echo ' <a href="testcase.php?probid='.urlencode($data['probid']).'">details/edit</a>';
?></td></tr>
<tr><td>Timelimit:   </td><td><?php echo (int)$data['timelimit']?> sec</td></tr>
<?php
if ( !empty($data['color']) ) {
	echo '<tr><td>Colour:</td><td><div class="circle" style="background-color: ' .
		htmlspecialchars($data['color']) .
		';"></div> ' . htmlspecialchars($data['color']) .
		"</td></tr>\n";
}
if ( !empty($data['problemtext_type']) ) {
	echo '<tr><td>Problem text:</td><td class="nobreak"><a href="problem.php?id=' .
	    urlencode($id) . '&amp;cmd=viewtext"><img src="../images/' .
	    urlencode($data['problemtext_type']) . '.png" alt="problem text" ' .
	    'title="view problem description" /></a> ' . "</td></tr>\n";
}
if ( !empty($data['special_compare']) ) {
	echo '<tr><td>Special run script:</td><td class="filename">' .
		'<a href="executable.php?id=' . urlencode($data['special_run']) . '">' .
		htmlspecialchars($data['special_run']) . "</a></td></tr>\n";
}
if ( !empty($data['special_compare']) ) {
	echo '<tr><td>Special compare script:</td><td class="filename">' .
		'<a href="executable.php?id=' . urlencode($data['special_compare']) . '">' .
		htmlspecialchars($data['special_compare']) . "</a></td></tr>\n";
}

echo "</table>\n" . addEndForm();

echo "<br />\n" . rejudgeForm('problem', $id) . "\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		exportLink($id) . "\n" .
		editLink('problem',$id) . "\n" .
		delLink('problem','probid', $id) . "</p>\n\n";
}

echo "<h2>Submissions for " . htmlspecialchars($data['shortname']) .
	" - " . htmlspecialchars($data['name']) . "</h2>\n\n";

$restrictions = array( 'probid' => $id );
putSubmissions($cdatas, $restrictions, 0, null, true);

require(LIBWWWDIR . '/footer.php');
