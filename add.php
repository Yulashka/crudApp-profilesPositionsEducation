<?php
	//make the database connection and leave it in the variable $pdo
	require_once "pdo.php";
	require_once "util.php";

	session_start();
	
	//if the user is not logged in redirect back to index.pnp
	//with an error
	if ( ! isset($_SESSION['user_id']) ) {
    	die('ACCESS DENIED');
    	return;
	}

	//if the user requested cancel go back to index.php
	if( isset($_POST['cancel']) ) {
	  // Redirect the browser to index.php
	  header('Location: index.php');
	  return;
	}

	//validating auto data
	if ( isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['email']) 
     && isset($_POST['headline']) && isset($_POST['summary']) ) {

     	$msg = validateProfile();
     	if( is_string($msg) ) {
     		$_SESSION['error'] = $msg;
     		header("Location: add.php");
     		return;
     	}

     	//Validate education entries if present
     	$msg = validateEdu();
     	if( is_string($msg) ) {
     		$_SESSION['error'] = $msg;
     		header("Location: add.php");
     		return;
     	}

     	//Validate position entries if present
     	$msg = validatePos();
     	if( is_string($msg) ) {
     		$_SESSION['error'] = $msg;
     		header("Location: add.php");
     		return;
     	}

     	//data is valid - time to insert
 		$stmt = $pdo->prepare('INSERT INTO Profile
    		(user_id, first_name, last_name, email, headline, summary) VALUES ( :uid, :fn, :ln, :em, :he, :su)');
    	$stmt->execute(array(
	    	':uid' => $_SESSION['user_id'],
	        ':fn' => $_POST['first_name'],
	        ':ln' => $_POST['last_name'],
	        ':em' => $_POST['email'],
	    	':he' => $_POST['headline'],
	    	':su' => $_POST['summary'])
    	);
    	$profile_id = $pdo->lastInsertId();

    	//Insert the position entries
    	$rank = 1;
    	for ($i=1; $i < 9; $i++) { 
    		if(! isset($_POST['year'.$i]) ) continue;
			if(! isset($_POST['desc'.$i]) ) continue; 
			$year = $_POST['year'.$i];
			$desc = $_POST['desc'.$i];

			$stmt = $pdo->prepare('INSERT INTO Position
				(profile_id, rank, year, description)
				VALUES ( :pid, :rank, :year, :desc)');
			$stmt->execute(array(
				':pid' => $profile_id,
				':rank' => $rank,
				':year' => $year,
				':desc' => $desc)
			);
			$rank++;
    	}

    	//Insert the education entries
    	// insertEducations($pdo, $_REQUEST['profile_id']);
    	$edu_rank = 1;
    	for ($i=1; $i <= 9; $i++) { 
			if(! isset($_POST['edu_year'.$i]) ) continue;
			if(! isset($_POST['edu_school'.$i]) ) continue;
			$year = $_POST['edu_year'.$i];
			$school = $_POST['edu_school'.$i];

			//Look up the school if it is there
			$institution_id = false;
			$stmt = $pdo->prepare('SELECT institution_id FROM 
				Institution WHERE name = :name');
			$stmt->execute(array(':name' => $school));
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if( $row !== false ) $institution_id = $row['institution_id'];

			//if there was no institutional, insert it
			if ( $institution_id === false) {
				$stmt = $pdo->prepare('INSERT INTO Institution
					(name) VALUES (:name)');
				$stmt->execute( array( ':name' => $school ) );
				$institution_id = $pdo->lastInsertId();
			}

			$stmt = $pdo->prepare('INSERT INTO Education
				(profile_id, rank, year, institution_id)
				VALUES ( :pid, :rank, :year, :iid)');
			$stmt->execute(array(
				':pid' => $profile_id,
				':rank' => $edu_rank,
				':year' => trim($year),
				':iid' => trim($institution_id))
			);
			$edu_rank++;
		}

    	$_SESSION['success'] = "Profile added";
		header("Location: index.php");
		return;
	}
?>

<!DOCTYPE html>
<html>
  <head>
	  <title>Iuliia Zemliana - Profiles, Positions and Education Database CRUD</title>
	  <?php require_once "head.php"; ?>
	</head>

<body>
	<div class="container">
		<?php
		echo('<h1>Adding Profile for '.htmlentities( $_SESSION["name"])."</h1>\n");
		//flash messages
		flashMessages();
		?>
		<form method="post">
			<p>First Name:
				<input type="text" name="first_name" size="60"/></p>
			<p>Last Name:
				<input type="text" name="last_name" size="60"/></p>
			<p>Email:
				<input type="text" name="email"/></p>
			<p>Headline:
				<input type="text" name="headline"/></p>
			<p>Summary:<br>
				<textarea name="summary" rows="8" cols="80"></textarea></p>
			<p>
				Education: <input type="submit" id="addEdu" value="+">
				<div id="edu_fields">
				</div>
			</p>
			<p>
				Position: <input type="submit" id="addPos" value="+">
				<div id="position_fields">
				</div>
			</p>
			<p>
				<input type="submit" value="Add">
				<input type="submit" name="cancel" value="Cancel">
			</p>
		</form>
		<script>
		countPos = 0;
		countEdu = 0;

		// http://stackoverflow.com/questions/17650776/add-remove-html-inside-div-using-javascript
		$(document).ready(function(){
		    window.console && console.log('Document ready called');

		    $('#addPos').click(function(event){
		        // http://api.jquery.com/event.preventdefault/
		        event.preventDefault();
		        if ( countPos >= 9 ) {
		            alert("Maximum of nine position entries exceeded");
		            return;
		        }
		        countPos++;
		        window.console && console.log("Adding position "+countPos);
		        $('#position_fields').append(
		            '<div id="position'+countPos+'"> \
		            <p>Year: <input type="text" name="year'+countPos+'" value="" /> \
		            <input type="button" value="-" onclick="$(\'#position'+countPos+'\').remove();return false;"><br>\
		            <textarea name="desc'+countPos+'" rows="8" cols="80"></textarea>\
		            </div>');
		    });

		    $('#addEdu').click(function(event){
		        event.preventDefault();
		        if ( countEdu >= 9 ) {
		            alert("Maximum of nine education entries exceeded");
		            return;
		        }
		        countEdu++;
		        window.console && console.log("Adding education "+countEdu);

		        $('#edu_fields').append(
		            '<div id="edu'+countEdu+'"> \
		            <p>Year: <input type="text" name="edu_year'+countEdu+'" value="" /> \
		            <input type="button" value="-" onclick="$(\'#edu'+countEdu+'\').remove();return false;"><br>\
		            <p>School: <input type="text" size="80" name="edu_school'+countEdu+'" class="school" value="" />\
		            </p></div>'
		        );

		        $('.school').autocomplete({
		            source: "school.php"
		        });
		    });
		});
		</script>
	</div>
	</body>
</html>



