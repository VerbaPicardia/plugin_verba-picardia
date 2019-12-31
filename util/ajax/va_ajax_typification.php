<?php
function va_ajax_typification (&$db){
	switch ($_POST['query']){
		case 'getTokenList':
			if (!is_numeric($_POST['id'])){
				echo json_encode($db->get_results($db->prepare('CALL getRecords(%d, %d, %d, %d, %s);', 90322, $_POST['all'], $_POST['allC'], $_POST['allA'], $_POST['id'])));
			}
			else {
				echo json_encode($db->get_results($db->prepare('CALL getRecords(%d, %d, %d, %d, %s);', $_POST['id'], $_POST['all'], $_POST['allC'], $_POST['allA'], '')));
			}
			break;
			
		case 'removeTypification':
			if(!current_user_can('va_typification_tool_write'))
				break;
				
				$description = json_decode(stripslashes($_POST['description']));
				$tids = getTokenIds($db, $description);
				if($description->kind === 'G' || $description->kind === 'K' || $description->kind === 'GP'){
					$db->query($db->prepare("DELETE VTBL_Tokengruppe_morph_Typ FROM VTBL_Tokengruppe_morph_Typ JOIN morph_Typen m USING (Id_morph_Typ) WHERE m.Quelle = 'VA' AND Id_Tokengruppe IN " . keyPlaceholderList($tids), $tids));
				}
				else {
					$db->query($db->prepare("DELETE VTBL_Token_morph_Typ FROM VTBL_Token_morph_Typ JOIN morph_Typen m USING (Id_morph_Typ) WHERE m.Quelle = 'VA' AND Id_Token IN " . keyPlaceholderList($tids), $tids));
				}
				echo 'success';
				break;
				
		case 'removeConcept':
			if(!current_user_can('va_typification_tool_write'))
				break;
				
				$description = json_decode(stripslashes($_POST['description']));
				$tids = getTokenIds($db, $description);
				if (empty($tids))
					error_log(json_encode($_POST)); //TODO remove if bug is fixed
					
					$placeholder_list = keyPlaceholderList($tids);
					array_push($tids, $_POST['concept']);
					if($description->kind === 'G' || $description->kind === 'K' || $description->kind = 'GP'){
						$db->query($db->prepare("DELETE FROM VTBL_Tokengruppe_Konzept WHERE Id_Tokengruppe IN " . $placeholder_list . " AND Id_Konzept = %d", $tids));
					}
					else {
						$db->query($db->prepare("DELETE FROM VTBL_Token_Konzept WHERE Id_Token IN " . $placeholder_list . " AND Id_Konzept = %d", $tids));
					}
					echo 'success';
					break;
					
		case 'addTypification':
			if(!current_user_can('va_typification_tool_write'))
				break;
				
				$descriptions = json_decode(stripslashes($_POST['descriptionList']));
				foreach ($descriptions as $description){
					
					$tids = getTokenIds($db, $description);
					if (empty($tids))
						error_log(json_encode($_POST)); //TODO remove if bug is fixed
						
						if($description->kind === 'G' || $description->kind === 'K' || $description->kind == 'GP'){
							$db->query("DELETE VTBL_Tokengruppe_morph_Typ FROM VTBL_Tokengruppe_morph_Typ JOIN morph_Typen m WHERE m.Quelle = 'VA' AND Id_Tokengruppe IN (" . implode(',', $tids) . ')');
							$db->query($db->prepare("
						INSERT INTO VTBL_Tokengruppe_morph_Typ (Id_Tokengruppe, Id_morph_Typ, Angelegt_Von, Angelegt_Am)
						SELECT Id_Tokengruppe, %d, %s, NOW()
						FROM Tokengruppen WHERE Id_Tokengruppe IN (" . implode(',', $tids) . ')', $_POST['newTypeId'], wp_get_current_user()->user_login));
						}
						else {
							$db->query("DELETE VTBL_Token_morph_Typ FROM VTBL_Token_morph_Typ JOIN morph_Typen m USING (Id_morph_Typ) WHERE m.Quelle = 'VA' AND Id_Token IN (" . implode(',', $tids) . ')');
							$db->query($db->prepare("
						INSERT INTO VTBL_Token_morph_Typ (Id_Token, Id_morph_Typ, Angelegt_Von, Angelegt_Am)
						SELECT Id_Token, %d, %s, NOW()
						FROM Tokens WHERE Id_Token IN (" . implode(',', $tids) . ')', $_POST['newTypeId'], wp_get_current_user()->user_login));
						}
				}
				echo 'success';
				break;
				
		case 'addConcept':
			if(!current_user_can('va_typification_tool_write'))
				break;
				
				$results = array();
				$descriptions = json_decode(stripslashes($_POST['descriptionList']));
				foreach ($descriptions as $description){
					$tids = getTokenIds($db, $description);
					
					if(!isset($_REQUEST['allowMultipleConcepts'])){
						if($description->kind === 'G' || $description->kind === 'K' || $description->kind == 'GP'){
							$olds = $db->get_col($db->prepare('
							SELECT DISTINCT Id_Konzept FROM VTBL_Tokengruppe_Konzept
							WHERE Id_Tokengruppe IN (' . implode(',', $tids) . ')
							AND Id_Konzept != %d', $_POST['newConceptId']), 0);
						}
						else {
							$olds = $db->get_col($db->prepare('
							SELECT DISTINCT Id_Konzept FROM VTBL_Token_Konzept
							WHERE Id_Token IN (' . implode(',', $tids) . ')
							AND Id_Konzept != %d', $_POST['newConceptId']), 0);
						}
					}
					
					if(!isset($_REQUEST['allowMultipleConcepts']) && !empty($olds)){
						$name = $db->get_var('SELECT Beschreibung_D FROM Konzepte WHERE Id_Konzept IN (' . implode(',', $olds) . ')', 0, 0);
						$results[] = $name;
					}
					else {
						if($description->kind === 'G' || $description->kind === 'K' || $description->kind === 'GP'){
							$db->query($db->prepare('INSERT IGNORE INTO VTBL_Tokengruppe_Konzept (Id_Tokengruppe, Id_Konzept) SELECT Id_Tokengruppe, %d FROM Tokengruppen
							WHERE Id_Tokengruppe IN (' . implode(',', $tids) . ')', $_POST['newConceptId']));
						}
						else {
							$db->query($db->prepare('INSERT IGNORE INTO VTBL_Token_Konzept (Id_Token, Id_Konzept) SELECT Id_Token, %d FROM Tokens
							WHERE Id_Token IN (' . implode(',', $tids) . ')', $_POST['newConceptId']));
						}
						$results[] = 'success';
					}
				}
				echo json_encode($results);
				break;
				
		case 'saveMorphType':
			if(!current_user_can('va_typification_tool_write'))
				break;
				
				//Store type information
				if(isset($_POST['id'])){
					$mtype_id = $_POST['id'];
					
					$db->update('morph_Typen', $_POST['type'], array('Id_morph_Typ' => $mtype_id));
				}
				else {
					$db->hide_errors();
					$_POST['type']['Angelegt_Von'] = wp_get_current_user()->user_login;
					if(!$db->insert('morph_Typen', $_POST['type'])){
						$last_err = $db->last_error;
						if(substr($last_err, 0, 9) === 'Duplicate'){
							echo 'Fehler: Es gibt bereits einen identischen Typ!';
						}
						else {
							echo 'Fehler: ' . $last_err;
						}
						return;
					}
					
					$db->show_errors();
					
					$mtype_id = $db->insert_id;
				}
				
				//Connect base types
				$db->delete('VTBL_morph_Basistyp', array('Id_morph_Typ' => $mtype_id));
				if(!empty($_POST['btypes'])){
					foreach ($_POST['btypes'] as $index => $btype){
						$db->insert('VTBL_morph_Basistyp', array('Id_morph_Typ' => $mtype_id, 'Id_Basistyp' => $btype, 'Quelle' => 'VA',
								'Angelegt_Von' => wp_get_current_user()->user_login, 'Unsicher' => $_POST['unsures'][$index]));
					}
				}
				
				//Connect references
				$db->delete('VTBL_morph_Typ_Lemma', array('Id_morph_Typ' => $mtype_id));
				if(!empty($_POST['refs'])){
					foreach ($_POST['refs'] as $ref){
						$db->insert('VTBL_morph_Typ_Lemma', array('Id_morph_Typ' => $mtype_id, 'Id_Lemma' => $ref, 'Quelle' => 'VA',
								'Angelegt_Von' => wp_get_current_user()->user_login));
					}
				}
				
				//Connect components
				$db->delete('VTBL_morph_Typ_Bestandteile', array('Id_morph_Typ' => $mtype_id));
				if(empty($_POST['parts'])){
					//If there are no components, the type itself is its only component
					$db->insert('VTBL_morph_Typ_Bestandteile', array('Id_morph_Typ' => $mtype_id, 'Id_Bestandteil' => $mtype_id));
				}
				else {
					foreach ($_POST['parts'] as $part){
						$db->insert('VTBL_morph_Typ_Bestandteile', array('Id_morph_Typ' => $mtype_id, 'Id_Bestandteil' => $part));
					}
				}
				$result = array('Id' => $mtype_id, 'Name' => $db->get_var("SELECT lex_unique(Orth, Sprache, Genus) FROM morph_Typen WHERE Id_morph_Typ = $mtype_id"));
				echo json_encode($result);
				break;
				
		case 'saveBaseType':
			if(!current_user_can('va_typification_tool_write'))
				break;
				
				
				//Store type information
				if(isset($_POST['id'])){
					$btype_id = $_POST['id'];
					
					$db->update('Basistypen', $_POST['type'], array('Id_Basistyp' => $btype_id));
				}
				else {
					$db->hide_errors();
					
					$_POST['type']['Angelegt_Von'] = wp_get_current_user()->user_login;
					if(!$db->insert('Basistypen', $_POST['type'])){
						$last_err = $db->last_error;
						if(substr($last_err, 0, 9) === 'Duplicate'){
							echo 'Fehler: Es gibt bereits einen Basistyp mit dem gleichen Namen!';
						}
						else {
							echo 'Fehler: ' . $last_err;
						}
						return;
					}
					
					$db->show_errors();
					
					$btype_id = $db->insert_id;
				}
				
				//Connect references
				$refs_added = false;
				$db->delete('VTBL_Basistyp_Lemma', array('Id_Basistyp' => $btype_id));
				if(!empty($_POST['refs'])){
					foreach ($_POST['refs'] as $ref){
						$db->insert('VTBL_Basistyp_Lemma', array('Id_Basistyp' => $btype_id, 'Id_Lemma' => $ref, 'Angelegt_Von' => wp_get_current_user()->user_login));
					}
					$refs_added = true;
				}
				
				$result = array('Id' => $btype_id, 'Name' =>  $_POST['type']['Orth'], 'Refs' => $refs_added, 'Sprache' => $_POST['type']['Sprache']);
				echo json_encode($result);
				break;
				
		case 'getMorphTypeDetails':
			$typ_info = $db->get_row($db->prepare("SELECT * FROM morph_Typen WHERE Id_morph_Typ = %d", $_POST['id']));
			$parts = $db->get_col($db->prepare("SELECT Id_Bestandteil FROM VTBL_morph_Typ_Bestandteile WHERE Id_morph_Typ = %d AND Id_Bestandteil != %d", $_POST['id'], $_POST['id']));
			$refs = $db->get_col($db->prepare("SELECT Id_Lemma FROM VTBL_morph_Typ_Lemma WHERE Id_morph_Typ = %d", $_POST['id']));
			$btypes = $db->get_results($db->prepare("SELECT Id_Basistyp, Unsicher FROM VTBL_morph_Basistyp WHERE Id_morph_Typ = %d", $_POST['id']), ARRAY_N);
			echo json_encode(array('type' => $typ_info, 'parts' => $parts, 'refs' => $refs, 'btypes' => $btypes));
			break;
			
		case 'getBaseTypeDetails':
			$typ_info = $db->get_row($db->prepare("SELECT * FROM Basistypen WHERE Id_Basistyp = %d", $_POST['id']));
			$refs = $db->get_col($db->prepare("SELECT Id_Lemma FROM VTBL_Basistyp_Lemma WHERE Id_Basistyp = %d", $_POST['id']));
			echo json_encode(array('type' => $typ_info, 'refs' => $refs));
			break;
			
		case 'checkFileExists':
			$file_loc = substr($_POST['file'], 0, strpos($_POST['file'], '#')) . '/' . $_POST['file'];
			$file = get_home_path() . 'dokumente/scans/' . $file_loc;
			echo file_exists($file)? $file_loc: 'no';
			break;
	}
}


function getTokenIds (&$db, $description){
	return $description->idlist;
}
?>