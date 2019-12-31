<?php

global $NO_CATEGORY;
$NO_CATEGORY = '(Keine Kategorie)';

function konzeptbaum (){
	global $NO_CATEGORY;
	?>
	<script type="text/javascript">
	var curNode;
	var update = false;
	var writeMode = <?php echo current_user_can('va_concept_tree_write')? 'true': 'false'?>;
	
	jQuery(function () {
		jQuery("#selectMKat").val("0");

		jQuery("#selectMKat").on("change", function(){
			var hkat = this.value;
			if(hkat == "0"){
				jQuery("#selectKat").toggle(false);
			}
			else {
				jQuery.post(ajax_object.ajaxurl, {
					"action": 'va', 
					"namespace" : "concept_tree",
					"query" : "get_sub_categories",
					"mcat" : hkat
				}, function (response){
					jQuery("#selectKat").html(response);
					jQuery("#selectKat").toggle(true);
					jQuery("#selectKat").val("0").trigger("change");
				});
			}
		});
		
		jQuery("#selectKat").on("change", function(){
			var kat = this.value;
			jQuery("#treeContainer").children().jstree("destroy");
			if(kat == "0"){
				jQuery("#treeContainer").html("");
			}
			else {
				jQuery.post(ajaxurl, {
						"action": 'va', 
						"namespace" : "concept_tree",
						"query" : "show_tree", 
						"id_cat" : kat
						}, function (response) {
					jQuery("#treeContainer").html(response);
					jQuery("#treeContainer").children().jstree({
						"core" : {
							"check_callback" : true
						},
						"plugins" : [ "dnd", "contextmenu" , "sort"],
						"contextmenu" : {
							"items" : function (node){
								if(writeMode)
									return {
										"newAtTop" : {"label" : "Neues Konzept auf höchster Ebene anlegen", action : function (){update = false; curNode = jQuery("#treeContainer ul > li:first"); neuesKonzept();}},
										"newAtPos" : {"label" : "Neues Unterkonzept an dieser Stelle anlegen", action : function (){update = false; curNode = jQuery("#" + node.id); neuesKonzept();}},
										"editConcept" : {"label" : "Dieses Konzept bearbeiten", action : function () {update = true; curNode = jQuery("#" + node.id); neuesKonzept();}}, 
									};
								else
									return {};	
							}
						}
					});
					
					jQuery('.konzeptbaum').on('move_node.jstree', function (e, data){
						var id_konzept = data.node.data.konzept * 1;
						var id_ueberkonzept = jQuery("#treeContainer").children().jstree(true).get_node(data.node.parent).data.konzept * 1;
						jQuery.post(ajaxurl, {
							'action' : 'va',
							'namespace' : 'concept_tree',
							'query' : 'update_node',
							'concept' : id_konzept,
							'superconcept' : id_ueberkonzept
						}, function (response){
							if(response != "success"){
								alert(response);
							}
						});
					});
				});
			}
		});

		
		
		jQuery.jstree.defaults.dnd.is_draggable = function (nodes) {
			if(!writeMode)
				return false;
			
			var id_konzept = nodes[0].li_attr["data-konzept"] * 1;
			if(id_konzept == 707){
				alert("Bitte das oberste Konzept nicht verschieben!");
				return false;
			}
			return true;
		};
		
		jQuery.jstree.defaults.sort = function (a, b){
			return this.get_text(a).toLowerCase() > this.get_text(b).toLowerCase() ? 1 : -1;
		};

		jQuery("#newCatButton").click(function (){
			showTableEntryDialog(
				"NewCategory",
				function (data){
					if(jQuery("#selectMKat option[value='" + data["Hauptkategorie"] + "']").length == 0){
						jQuery("#selectMKat").append("<option value='" + data["Hauptkategorie"] + "'>" + data["Hauptkategorie"] + "</option>");
					}
					if(jQuery("#selectMKat").val () == data["Hauptkategorie"]){
						var newOption = "<option value='" + data["id"] + "'>" + data["Kategorie"] + "</option>";
						jQuery("#selectKat").append(newOption);
						jQuery("#NewConceptForTree select[name=Id_Kategorie]").append(newOption);
					}
				}
			);
		});

		addNewEnumValueScript();
	
	});
	
	function neuesKonzept (){
		var e = document.forms["inputNewConceptForTree"].elements;
		if(update){
			jQuery.post(ajaxurl, {
				'action' : 'va',
				'namespace' : 'concept_tree',
				'query' : 'get_concept_info',
				'concept' : curNode.data("konzept")
			}, function (response){
				var data = JSON.parse(response);
				for (var i = 0; i < data[0].length; i++){
					if(i <= 5 || i == 9){
						e[i].value = data[0][i];
					}
					else {
						if(data[0][i] == 1)
							e[i].checked = true;
						else
							e[i].checked = false;
					}
				}
				showTableEntryDialog(
					"NewConceptForTree", 
					conceptCallback,
					undefined,
					undefined,
					true,
					"Id_Konzept",
					curNode.data("konzept")
				);
			});
		}
		else {
			for (var i = 0; i < e.length; i++){
				if(e[i].name == "Id_Kategorie"){
					e[i].value = jQuery("#selectKat").val();
				}
				else if(e[i].name == "Relevanz"){
					e[i].value = true;
				}
				else if(e[i].type != "hidden") {
					e[i].value = "";
				}	
			}
			showTableEntryDialog(
				"NewConceptForTree",
				conceptCallback,
				undefined,
				undefined,
				false);
		}
			
	}
	
	
	function conceptCallback (result){

		var name = result["Name_D"] == ""? result["Beschreibung_D"]: result["Name_D"] + " (" + result["Beschreibung_D"] + ")";
		var kategorieNeu = result["Id_Kategorie"];
		//TODO maybe change color here if main category has changed
		
		if(kategorieNeu == jQuery("#selectKat").val()){
			if(update){
				jQuery("#treeContainer").children().jstree('set_text', curNode, name);
			}
			else {
				var newId = jQuery("#treeContainer").children().jstree(true).create_node(curNode, {
					"text" : name, 
					"data" : {
						"konzept" : result["id"]
					}, 
					"li_attr" : {
						"class" : "specificConcept",
						"data-konzept" : result["id"]
					}
				});
			}
		}
		else {
			if(update){
				jQuery("#treeContainer").children().jstree(true).delete_node(curNode);
			}
		}
	}
	</script>
	
	<h1> Konzeptbaum </h1>
	
	<br />
	<br />
	
	<select id="selectMKat">
		<option value="0" selected>--- Hauptkategorie wählen ---</option>
	<?php
	global $va_xxx;
	$kategorien = $va_xxx->get_col('SELECT DISTINCT Hauptkategorie FROM Konzepte_Kategorien ORDER BY CAST(Hauptkategorie AS CHAR) ASC');
	
	foreach ($kategorien as $kat){
		echo '<option value="' . $kat . '">' . ($kat == ''? $NO_CATEGORY: $kat) . '</option>';
	}
	?>
	</select>
	
	<select id="selectKat" style="display : none"></select>
	
	<input type="button" class="button button-primary" value="Neue Kategorie anlegen" id="newCatButton" />
	
	<div id="treeContainer">
		
	</div>
			
	<?php	
	
	va_echo_new_concept_fields('NewConceptForTree');
	
	echo im_table_entry_box ('NewCategory', new IM_Row_Information('Konzepte_Kategorien', array(
			new IM_Field_Information('Hauptkategorie', 'E', true, true),
			new IM_Field_Information('Kategorie', 'V', true)
	)));
}

function va_show_concept_tree ($id_kat){
	global $va_xxx;
	global $Ue;
	
	$ueber_kat = $va_xxx->get_var($va_xxx->prepare('SELECT Id_Ueberkategorie FROM Konzepte_Kategorien WHERE Id_Kategorie = %d', $id_kat));
	
	$top_konzepte = $va_xxx->get_col($va_xxx->prepare('
		SELECT Id_Konzept FROM Konzepte JOIN Ueberkonzepte USING (Id_Konzept) 
		WHERE Id_Ueberkonzept = 707 AND (Id_Kategorie = %d OR Id_Kategorie = %d)', $id_kat, $ueber_kat));
	
	$result = '<div class="konzeptbaum" id="DivKat' . $id_kat . '"><ul><li class="jstree-open" data-konzept="707"> (KONZEPTE)';
	
	foreach ($top_konzepte as $tk){
		$cres = va_concept_tree_concept_by_id($tk, 'D', $Ue, $id_kat, $ueber_kat);
		if($cres !== false)
			$result .= $cres;
	}
	$result .= '</ul></li></div>';
	return $result;
}


function va_concept_tree_concept_by_id ($id, $lang, &$Ue, $id_cat, $id_ueber){
	
	global $va_xxx;
	
	$conceptInfo =  $va_xxx->get_row("
		SELECT 
			Name_$lang AS Name, 
			Beschreibung_$lang AS Beschreibung, 
			IF(Anzahl_Allein IS NULL, 0, Anzahl_Allein) AS Allein,
			Id_Kategorie 
		FROM Konzepte LEFT JOIN A_Anzahl_Konzept_Belege USING (Id_Konzept) 
		WHERE Id_Konzept = $id", ARRAY_A);

		
	$children = $va_xxx->get_col("SELECT Id_Konzept FROM Ueberkonzepte WHERE Id_Ueberkonzept = $id");
	if(empty($children) && $conceptInfo['Id_Kategorie'] != $id_cat){
		return false;
	}
	
	$res = '<ul><li class="' . ($conceptInfo['Id_Kategorie'] == $id_ueber? ' generalConcept' : 'specificConcept') .
		'" data-konzept="' . $id . '">' . ($conceptInfo['Name'] == ''? ($conceptInfo['Beschreibung']): $conceptInfo['Name'] . ' (' . ($conceptInfo['Beschreibung']) . ')') . 
		' (' . $conceptInfo['Allein'] . ' ' . ($conceptInfo['Allein'] == '1'? $Ue['BELEG'] : $Ue['BELEGE']) . ')';
	
	if(!empty($children)){
		$child_used = false;
		foreach ($children as $child){
			$cres =  va_concept_tree_concept_by_id($child, $lang, $Ue, $id_cat, $id_ueber);
			if($cres !== false){
				$child_used = true;
				$res .= $cres;
			}
		}
		
		if(!$child_used && $conceptInfo['Id_Kategorie'] != $id_cat){
			return false;
		}
	}
	
	return $res . '</li></ul>';
}
?>