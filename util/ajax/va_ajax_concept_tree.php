<?php
function va_ajax_concept_tree (&$db){
	switch ($_POST['query']){
		case 'update_node':
			if(!current_user_can('va_concept_tree_write'))
				break;
				
			if($db->query($db->prepare('UPDATE Ueberkonzepte SET Id_Ueberkonzept = %d WHERE ID_Konzept = %d', $_POST['superconcept'], $_POST['concept'])) !== false){
				echo 'success';
			}
			break;
			
		case 'show_tree':
			$db->query('CALL buildConceptCount()');
			echo va_show_concept_tree($_POST['id_cat']);
			break;
			
		case 'get_concept_info':
			echo json_encode($db->get_results($db->prepare('
					SELECT Name_D, Beschreibung_D, Id_Kategorie, Taxonomie, QID, Kommentar_Intern, Relevanz, Pseudo, Grammatikalisch, VA_Phase 
					FROM Konzepte WHERE Id_Konzept = %d', $_POST['concept']), ARRAY_N));
			break;
			
		case 'get_sub_categories':
			echo '<option value="0">--- Kategorie wählen ---</option>';
			$cats = $db->get_results($db->prepare('SELECT Id_Kategorie, Kategorie FROM Konzepte_Kategorien WHERE Hauptkategorie = %s', $_POST['mcat']), ARRAY_A);
			foreach ($cats as $cat){
				echo '<option value="' . $cat['Id_Kategorie'] . '">' . $cat['Kategorie'] . '</option>';
			}
			break;
	}
}
?>