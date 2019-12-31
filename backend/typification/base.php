<?php
function va_edit_base_type_page (){

	global $va_xxx;
	global $va_work_db_name;
	$dbname = $va_work_db_name;
	?>

	<script type="text/javascript">

	var dbname = "<?php echo $dbname; ?>";
	var currentSelect;

	jQuery(function (){
		jQuery("#showOnlyNotRef").change(switchSelects);
		jQuery("#showOnlyNotLang").change(switchSelects);
		switchSelects();

		jQuery(".baseselect").change(editBaseType);
		jQuery("#newBTypeButton").click(saveBaseType);

		jQuery("#newBasetypeReferenceButton").click(function (){
			showTableEntryDialog('NeueReferenzFuerBasistyp', callbackSaveReferenceBType, selectModes.Chosen, dbname);
		});
	});

	function switchSelects (){
		jQuery("select").val([]).chosen("destroy");

		var name = "#baseTypeSelect";

		if(jQuery("#showOnlyNotRef").is(":checked")){
			name += "R";
		}
		if(jQuery("#showOnlyNotLang").is(":checked")){
			name += "L";
		}

		jQuery(".baseselect").toggle(false);
		jQuery(name).toggle(true);

		currentSelect =jQuery(name);

		jQuery("select:visible").val([]).chosen({"allow_single_deselect" : true, "normalize_search_text" : removeDiacriticsPlusSpecial, "search_contains": true});
	}

	function editBaseType (){
		var id = jQuery(this).val();
		if(id){
			var data = {
				"action" : "va",
				"namespace" : "typification",
				"query" : "getBaseTypeDetails",
				"id" : id,
				"dbname" : dbname
			};

			jQuery.post(ajaxurl, data, function (response){
				try {
					var data = JSON.parse(response);
					openBaseTypeDialog(function (){
						currentSelect.val("").trigger("chosen:updated");
					});
					setBaseTypeData(data);
				}
				catch (e) {
					alert(response);
				}
			});
		}
	}

	function saveBaseType (){
		var data = getBaseTypeData(currentSelect.val());

		if(data.type.Orth == ""){
			alert("Das Feld \"Orth\" darf nicht leer sein!");
			return;
		}

		if(!data.type.Quelle) {
			alert("Das Feld \"Quelle\" darf nicht leer sein!");
			return;
		}

		if(!data.type.Sprache){
			alert("Das Feld \"Sprache\" darf nicht leer sein!");
			return;
		}

		jQuery.post(ajaxurl, data, function (response){
			try {
				if(response.startsWith("Fehler")){
					alert(response);
					return;
				}

				var typeInfo = JSON.parse(response);
				if(typeInfo.Refs){
					jQuery("#baseTypeSelectR option[value=" + typeInfo.Id + "]").remove();
					jQuery("#baseTypeSelectR").trigger("chosen:updated");

					jQuery("#baseTypeSelectRL option[value=" + typeInfo.Id + "]").remove();
					jQuery("#baseTypeSelectRL").trigger("chosen:updated");
				}
				else {
					if(jQuery("#baseTypeSelectR option[value=" + typeInfo.Id + "]").length == 0){
						jQuery("#baseTypeSelectR").append("<option value='" + typeInfo["Id"] + "'>" + typeInfo["Name"] + "</option>").trigger("chosen:updated");
					}
					if(jQuery("#baseTypeSelectRL option[value=" + typeInfo.Id + "]").length == 0){
						jQuery("#baseTypeSelectRL").append("<option value='" + typeInfo["Id"] + "'>" + typeInfo["Name"] + "</option>").trigger("chosen:updated");
					}
				}

				if(typeInfo.Sprache){
					jQuery("#baseTypeSelectL option[value=" + typeInfo.Id + "]").remove();
					jQuery("#baseTypeSelectL").trigger("chosen:updated");

					jQuery("#baseTypeSelectRL option[value=" + typeInfo.Id + "]").remove();
					jQuery("#baseTypeSelectRL").trigger("chosen:updated");
				}

				currentSelect.val("").trigger("chosen:updated");
				closeBaseTypeDialog();
			}
			catch (e) {
				alert(e + "(" + response + ")");
			}
		});
	}
	</script>

	<h1>Basistypen bearbeiten</h1>

	<br />
	<br />

	<input type="checkbox" id="showOnlyNotRef" /> Nur Basistypen ohne Referenzen anzeigen
	<input type="checkbox" id="showOnlyNotLang" /> Nur Basistypen ohne Sprachzuordnung anzeigen

	<br />
	<br />

	<?php

	echo im_table_select('Basistypen', 'Id_Basistyp', array('Orth'), 'baseTypeSelect', array('class_name' => 'baseselect'));
	echo im_table_select('Basistypen', 'Id_Basistyp', array('Orth'), 'baseTypeSelectR',
			array('filter' => 'NOT EXISTS (SELECT * FROM VTBL_Basistyp_Lemma v WHERE v.Id_Basistyp = Basistypen.Id_Basistyp)', 'class_name' => 'baseselect'));
	echo im_table_select('Basistypen', 'Id_Basistyp', array('Orth'), 'baseTypeSelectL',
			array('filter' => "Sprache IS NULL", 'class_name' => 'baseselect'));
	echo im_table_select('Basistypen', 'Id_Basistyp', array('Orth'), 'baseTypeSelectRL',
			array('filter' => "NOT EXISTS (SELECT * FROM VTBL_Basistyp_Lemma v WHERE v.Id_Basistyp = Basistypen.Id_Basistyp) AND Sprache is NULL", 'class_name' => 'baseselect'));


	echo createBaseTypeOverlay($va_xxx, $dbname, true);
}
