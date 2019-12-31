<?php

//All AJAX calls of the VA plugin are delegated in this file
add_action('wp_ajax_va', 'va_ajax_handler');
add_action('wp_ajax_nopriv_va', 'va_ajax_handler');

include_once('va_ajax_typification.php');
include_once('va_ajax_transcription.php');
include_once('va_ajax_concept_tree.php');
include_once('va_ajax_overview.php');

function va_ajax_handler (){
	global $va_xxx;
	global $admin;
	global $va_mitarbeiter;

	$db = $va_xxx;

	if(isset($_REQUEST['dbname']))
		$db->select($_REQUEST['dbname']);
	else if (isset($_REQUEST['db'])){
		$db->select('va_' . $_REQUEST['db']);
	}

	$intern = $admin || $va_mitarbeiter;
	switch($_REQUEST['namespace']){

		//Typification tool
		case 'typification':
			if(!current_user_can('va_typification_tool_read'))
				break;

			va_ajax_typification($db);
			break;

		//Transcription tool
		case 'transcription':
			if(!current_user_can('va_transcription_tool_read'))
				break;

			va_ajax_transcription($db);
		break;

		//Concept tree
		case 'concept_tree':
			if(!current_user_can('va_concept_tree_read'))
				break;

			va_ajax_concept_tree($db);
		break;

		//Overview page
		case 'overview':
			if(!current_user_can('va_see_progress_page'))
				break;

			va_ajax_overview($db);
			break;

		//IPA conversion
		case 'ipa':
			if(!$intern)
				break;

			switch ($_REQUEST['query']){
				case 'get_tokens':
					$tokens = $va_xxx->get_col($va_xxx->prepare("
						SELECT distinct Token
						FROM Tokens JOIN Stimuli USING (ID_Stimulus) LEFT JOIN VTBL_Token_Konzept USING (Id_Token)
						WHERE Erhebung = %s" . ($_POST['all'] === 'true'? '' : " AND IPA = ''") . " AND Token != '' AND (Id_Konzept is null or Id_Konzept != 779)", $_POST['source']), 0);

					echo json_encode($tokens);
					break;

				case 'compute':
					$tokens = json_decode(stripslashes($_POST['data']));
					$missing_chars = array();
					$quelle = $_POST['source'];
					$transformations = '';
					$errors = '';

					$akzente = $va_xxx->get_results("SELECT Beta, IPA FROM Codepage_IPA WHERE Art = 'Akzent' AND Erhebung = '$quelle'", ARRAY_N);
					$vokale = $va_xxx->get_var("SELECT group_concat(DISTINCT SUBSTR(Beta, 1, 1) SEPARATOR '') FROM Codepage_IPA WHERE Art = 'Vokal' AND Erhebung = '$quelle'", 0, 0);
					$numComplete = 0;

					foreach ($tokens as $token){
						$complete = true;
						$result = '';
						$akzentExplizit = false;
						$indexLastVowel = false;

						foreach ($token as $index => $character) {
							foreach ($akzente as $akzent) {
								$ak_qu = preg_quote($akzent[0], '/');
								$character = preg_replace_callback('/([' . $vokale . '][^' . $ak_qu . 'a-zA-Z]*)' . $ak_qu . '/',
									function ($matches) use (&$result, $akzent, &$akzentExplizit){
										$result .= $akzent[1];
										$akzentExplizit = true;
										return $matches[1];
								}, $character);
							}

							$ipa = $va_xxx->get_var("SELECT IPA from Codepage_IPA WHERE Erhebung = '" . ($quelle == 'ALD-I'? 'ALD-II': $quelle) . "' AND Beta = '" . addcslashes($character, "\'") . "' AND IPA != ''");
							if($ipa){
								$result .= $ipa;

								if(strpos($vokale, $character[0]) !== false){
									$indexLastVowel = mb_strlen($result) - mb_strlen($ipa);
								}
							}
							else {
								if(!in_array($character, $missing_chars)){
									$missing_chars[] = $character;
									$errors .= "Eintrag \"$character\" fehlt fuer \"" . ($quelle == 'ALD-I'? 'ALD-II': $quelle) . "\"!\n";
								}
								$complete = false;
							}
						}

						//Akzent auf letzer Silbe, falls nicht gesetzt
						$addAccent = !$akzentExplizit && $indexLastVowel !== false && ($quelle === 'ALP' || $quelle === 'ALJA' || $quelle === 'ALL');

						if($addAccent){
							$result = mb_substr($result, 0, $indexLastVowel) . $akzente[0][1] . mb_substr($result, $indexLastVowel);
						}


						if($complete){
							$transformations .= implode('', $token) . ' -> ' . $result . ($addAccent? ' (Akzent hinzugefügt)' : '') . "\n";
							$va_xxx->query("UPDATE Tokens SET IPA = '" . addslashes($result) . "', Trennzeichen_IPA = (SELECT IPA FROM Codepage_IPA WHERE Art = 'Trennzeichen' AND Beta = Trennzeichen AND Erhebung = '$quelle')
						 WHERE EXISTS (SELECT * FROM Stimuli WHERE Stimuli.Id_Stimulus = Tokens.Id_Stimulus AND Erhebung = '$quelle') AND Token = '" . addslashes(implode('', $token)) . "'");
							$numComplete++;
						}
					}

					echo json_encode(array($transformations, $errors, $numComplete));
					break;
			}

			break;

		//Util tools
		case 'util':
			if ($_REQUEST['query'] == 'get_print_overlays'){
				$db->select('va_xxx');
				echo json_encode($db->get_col('SELECT AsText(Polygone_Vereinfacht.Geodaten) FROM Orte JOIN Polygone_Vereinfacht USING (Id_Ort) WHERE Id_Kategorie = 63 AND Epsilon = 0.003'));
				break;
			}

			//TODO maybe better user control
			if(!$intern && !current_user_can('va_transcription_tool_write') && !current_user_can('va_typification_tool_write') && !current_user_can('va_glossary'))
				break;

			switch ($_REQUEST['query']){
				case 'check_external_link':
					va_check_external_link($_POST['link']);
				break;

				case 'get_external_links':
					va_get_external_links();
				break;

				case 'get_glossary_link':
					echo va_get_glossary_link($_POST['id']);
				break;

				case 'get_comments_link':
					echo va_get_comments_link($_POST['id']);
				break;

				case 'addLock':
					echo va_check_lock($db, $_POST);
				break;

				case 'removeLock':
					$db->query($db->prepare("DELETE FROM Locks where (Wert = %s AND Tabelle = %s AND Gesperrt_von = %s) or hour(timediff(Zeit,now())) > 0", $_REQUEST['value'], $_REQUEST['table'], wp_get_current_user()->user_login));
					echo 'success';
				break;

				case 'removeAllLocks':
					$db->query($db->prepare("DELETE FROM Locks where (Tabelle = %s AND Gesperrt_von = %s) or hour(timediff(Zeit,now())) > 0", $_REQUEST['table'], wp_get_current_user()->user_login));
					echo 'success';
				break;

				case 'markTodo':
				    $db->update('Todos', ['Fertig' => $_POST['marked'] == '1'? current_time('mysql'): null], ['Id_Todo' => $_POST['id']]);
				    echo 'success';
			   break;

				case 'addTodo':
				    $text = stripslashes($_POST['text']);
				    $insert_array = ['Todo' => $text, 'Kuerzel' => $_POST['owner'], 'Kontext' => $_POST['context']];
				    if($_POST['parent'] != -1){
				        $insert_array['Ueber'] = $_POST['parent'];
				    }
				    $db->insert('Todos', $insert_array);

				    $options = [
				        'Id_Todo' => $db->insert_id,
				        'Todo' => $text,
				        'Ueber' =>  $_POST['parent'] == -1? null: $_POST['parent'],
				        'Fertig' => null,
				        'Blockiert' => false,
				    	'Kontext' => $_POST['context']
				    ];

				    $res = ['row' => va_get_todo_row($options)];

				    if ($_POST['parent'] == -1){
				        $res['option'] = va_get_todo_parent_option($options);
				        $res['context'] = $_POST['context'];
				    }

				    echo json_encode($res);
			     break;

				 case 'checkTokens':
				 	va_check_tokens_call($db);
				 	break;

				 case 'check_tokenizer':
				 	va_tokenization_test_ajax($db);
				 	break;

				 case 'tokenizeRecord':
				 	$tokenizer = va_create_tokenizer($_POST['source']);
				 	try {
				 		$_POST['extraData']['notes'] = stripslashes($_POST['extraData']['notes']);
				 		echo va_create_token_table($tokenizer->tokenize(stripslashes($_POST['record']), $_POST['extraData']), $_POST['id']);
				 	}
				 	catch (Exception $e){
				 		echo 'ERR:' . $e;
				 	}
				 	break;

				 case 'get_community_name':
				 	echo $commName = $db->get_var($db->prepare('SELECT Name FROM Orte WHERE Id_Kategorie = 62 AND ST_WITHIN(GeomFromText(%s), Geodaten)', $_POST['point']));
				 	break;
			}
		break;

		case 'tokenize':
			if(!$intern)
				break;

			switch ($_POST['query']){
				case 'updateTable':
					echo va_tokenization_info_for_stimulus($_POST['stimulus']);
				break;

				case 'tokenize':
					echo va_tokenize_for_stimulus($_POST['stimulus'], $_POST['preview']);
					break;
			}
		break;

		case 'bsa_import':
			if(!$intern)
				break;

			switch ($_POST['query']){
				case 'getRecords':
					echo json_encode(va_get_bsa_records($_POST['stimulus'], $_POST['concept'], $db));
					break;

				case 'getOptions':
					echo va_bsa_get_options($_POST['filter'], $_POST['ignoreEmpty'], $db);
					break;

				case 'import':
					echo va_bsa_import_records($_POST['data'], $db);
					break;
			}
			break;

		default:
			echo 'No namespace given!';
	}
	die;
}


/**
 * Returns a string with %d's for integer list
 */
function keyPlaceholderList ($arr){
	return '(' . implode(',', array_fill(0, count($arr), '%d')) . ')';
}

function va_check_lock (&$db, $data){
    ob_start();
    $db->query($db->prepare("DELETE FROM Locks where (Wert = %s AND Tabelle = %s AND Gesperrt_von = %s) or hour(timediff(Zeit,now())) > 0", $data['value'], $data['table'], wp_get_current_user()->user_login));
    if($db->insert('Locks', array('Tabelle' => $data['table'], 'Gesperrt_von' => wp_get_current_user()->user_login, 'Wert' => $data['value']))){
        $res = 'success';
    }
    else {
        $res = 'locked';
    }
    ob_end_clean();
    return $res;
}
?>