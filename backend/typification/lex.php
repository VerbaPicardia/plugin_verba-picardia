<?php
function lex_typification (){

    global $va_xxx;
    $db = $va_xxx;

    global $va_work_db_name;
    $dbname = $va_work_db_name;

    $can_write = current_user_can('va_typification_tool_write');
    ?>

<script type="text/javascript">
    var dbname = "<?php echo $dbname; ?>";
	var scanUrl = "<?php echo home_url('/dokumente/scans/', 'https');?>";
	var loadingUrl = "<?php echo VA_PLUGIN_URL . '/images/Loading.gif'; ?>";
	var writeMode = <?php echo $can_write ? "true" : "false";?>;
</script>

<style>
	.chosen-container {
		max-width : 300pt;
	}
</style>

<table style="width : 100%; height: 100%">
	<tr>
		<td style="width: 50%">
			<h1><?php _e('Lexical Types', 'verba-alpina');?></h1>

			<br />

			<h3>Belege</h3>

			<select id="filterAtlas" class="chosenSelect">
				<option value=""><?php _e('Choose Atlas', 'verba-alpina');?></option>
				<?php
				$atlanten = $db -> get_col('SELECT DISTINCT Erhebung FROM Stimuli JOIN Tokens USING(Id_Stimulus) ORDER BY Erhebung ASC', 0);
				foreach ($atlanten as $atlas) {
					echo '<option value="' . str_replace(' ', '_', $atlas) . '">' . $atlas . '</option>';
				}
				?>
			</select>

			<?php
				foreach ($atlanten as $atlas){
					if ($atlas == 'CROWD'){
						$stimuli = $db->get_results("SELECT DISTINCT Sprache, Sprache AS TStimulus, '' AS Karte FROM Informanten ORDER BY Sprache ASC", ARRAY_N);
					}
					else {
						$stimuli = $db->get_results("SELECT DISTINCT Id_Stimulus, CONCAT(Karte, '_', Nummer, ': ', REPLACE(Stimulus, '\"', '')) as TStimulus, Karte FROM Stimuli JOIN Tokens USING(Id_Stimulus) WHERE Erhebung = '$atlas' ORDER BY Stimulus ASC", ARRAY_N);
					}
					echo '<span class="stimulusList" id="' . str_replace(' ', '_', $atlas) . '" style="display : none">';
					echo '<select>';
					echo '<option value="">' . __('Choose Stimulus', 'verba-alpina') . '</option>';
					foreach ($stimuli as $stimulus){
						echo '<option value="' . $stimulus[0] . '" data-file="' . $atlas . '#' . $stimulus[2] . '.pdf">' . $stimulus[1] . '</option>';
					}
					echo '</select>';
					echo '</span>';
				}
			?>
			<br />
			<br />

			<input type="checkbox" id="AllorNot" checked="checked" /> Nur Belege ohne morph.-lex. Typisierung anzeigen
			<br />
			<input type="checkbox" id="AllorNotConcept" /> Nur Belege ohne Konzeptzuweisung anzeigen
			<br />
			<input type="checkbox" id="AllorNotAlpes" checked="checked" /> Nur Belege aus der Alpenkonvention anzeigen

			<br />
			<br />

			<div style="min-height: 20px" class="tokenInfo">
				<span style="color: red; font-size: 90%;">(Wenn "Strg" gedrückt ist, können mehrere Belege ausgewählt werden, wenn "Shift" gedrückt ist, werden alle orthographisch identischen Belege ausgewählt.)</span>
				<br />
				<br />
				Belege
					<select id="tokenAuswahlLex" multiple="multiple" style="width: 400pt">
					</select>
					<?php echo va_get_info_symbol('Noch nicht typisierte Belege sind fett markiert. Belege ohne Konzeptzuweisung werden kursiv dargestellt. Belege, die mit irrelevanten Konzepten verknüpft sind, werden grau hinterlegt.');?>
					<input type="button" id="emptySelection" value="Auswahl leeren" class="button button-primary" style="margin-left: 50px;" />
			</div>
			<img src="<?php echo VA_PLUGIN_URL . '/images/Loading.gif' ?>" style="display: none" id="tokensLoading" />

			<br />
			<br />

			<table id="recordSummary" class="widefat fixed striped tokenInfo">
				<tr>
					<th>Beleg</th>
					<th>Informanten</th>
					<th>Bemerkungen</th>
					<th>Konzept(e)</th>
					<th>VA-Typ</th>
					<th></th>
				</tr>
			</table>

			<br />
			<br />

			<h3>VA-Typ zuweisen</h3>

			<select id="morphTypenAuswahl" class="chosenSelect">
				<?php
				$typenVA = $db->get_results("SELECT Id_morph_Typ, lex_unique(Orth, Sprache, Genus) as Orth FROM morph_Typen WHERE Quelle = 'VA' ORDER BY Orth ASC", ARRAY_A);

				foreach ($typenVA as $vat) {
					echo '<option value="' . $vat['Id_morph_Typ'] . '">' . $vat['Orth'] . '</option>';
				}
				?>
			</select>
			<input id="assignVA" type="button" class="button button-primary assignButton" value="<?php _e('Assign type', 'verba-alpina');?>" <?php if(!$can_write) echo ' disabled';?> />
			<input id="newVAType" type="button" class="button button-primary" value="<?php _e('Create new type', 'verba-alpina');?>" <?php if(!$can_write) echo ' disabled';?> />
			<input id="editVAType" type="button" class="button button-primary" value="Typ bearbeiten" <?php if(!$can_write) echo ' disabled';?> />

			<br />
			<br />

			<h3>Konzept zuweisen</h3>

			<select id="konzeptAuswahl" class="chosenSelect">
				<?php
				$conceptsVA = $db->get_results("SELECT Id_Konzept, IF(Name_D != '', Name_D, Beschreibung_D) as Name FROM Konzepte WHERE NOT Grammatikalisch ORDER BY Name ASC", ARRAY_A);

				foreach ($conceptsVA as $vac) {
					echo '<option value="' . $vac['Id_Konzept'] . '">' . $vac['Name'] . '</option>';
				}
				?>
			</select>
			<input id="assignConcept" type="button" class="button button-primary conceptButton" value="<?php _e('Assign concept', 'verba-alpina');?>" <?php if(!$can_write) echo ' disabled';?> />
			<input id="newConcept" type="button" class="button button-primary" value="<?php _e('Create new concept', 'verba-alpina');?>" <?php if(!$can_write) echo ' disabled';?> />

			<br />
			<br />

			<div>
				<h3><?php _e('Typification not necessary', 'verba-alpina');?></h3>

				Belege sind
				<select id="keinTypAuswahl" class="chosenSelect">
					<?php
					$konzeptNamen = $db -> get_results('SELECT Id_Konzept, Beschreibung_D FROM Konzepte WHERE Grammatikalisch', ARRAY_A);

					foreach ($konzeptNamen as $name) {
						echo '<option value="' . $name['Id_Konzept'] . '">' . $name['Beschreibung_D'] . '</option>';
					}
					?>
				</select>
				<input type="button" class="button button-primary conceptButton" id="noTypeButton" value="<?php _e('Confirm', 'verba-alpina');?>" <?php if(!$can_write) echo ' disabled';?>>
			</div>

		</td>

		<td style="width: 50%;">
			<iframe src="about:blank" style="width : 100%; height: 600pt;" id="pdfFrame">

			</iframe>
		</td>
	</tr>
</table>

<?php
createTypeOverlay($db, $dbname);

}
?>