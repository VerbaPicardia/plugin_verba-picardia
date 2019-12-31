<?php
function createTypeOverlay (&$db, $dbname){
?>

<style type="text/css">
	#auswahlBestandteile_chosen .chosen-drop, #auswahlReferenz_chosen .chosen-drop, #auswahlBasistyp_chosen .chosen-drop {
		border-bottom: 0;
		border-top: 1px solid #aaa;
		top: auto;
		bottom: 40px;
	}
</style>

<div id="VATypeOverlay" title="Morpho-Lexikalischer Typ" style="display : none">
	<form name="eingabeMorphTyp">
		<h2>Typ-Information</h2>
		<table>
			<tr>
				<td>Orth</td>
				<td><input name="Orth" type="text" /></td>
			</tr>
			<tr>
				<td>Sprache</td>
				<td><?php echo im_enum_select('morph_Typen', 'Sprache', 'Sprache', '---', false, '', NULL, $dbname);?></td>
			</tr>
			<tr>
				<td>Wortart</td>
				<td><?php echo im_enum_select('morph_Typen', 'Wortart', 'Wortart', '---', true, '', NULL, $dbname);?></td>
			</tr>
			<tr>
				<td>Praefix</td>
				<td><input name="Praefix" type="text" /></td>
			</tr>
			<tr>
				<td>Infix</td>
				<td><input name="Infix" type="text" /></td>
			</tr>
						<tr>
				<td>Suffix</td>
				<td><input name="Suffix" type="text" /></td>
			</tr>
			<tr>
				<td>Genus</td>
				<td><?php echo im_enum_select('morph_Typen', 'Genus', 'Genus', '---', false, '', NULL, $dbname);?></td>
			</tr>
			<tr>
				<td>Kommentar_Intern</td>
				<td><textarea  name="Kommentar_Intern"></textarea></td>
			</tr>
		</table>
	</form>
		
	<h2>Bestandteile</h2>
	<select id="auswahlBestandteile" multiple="multiple">
		<?php
		$parts = $db->get_results("SELECT Id_morph_Typ, lex_unique(Orth, Sprache, Genus) FROM morph_Typen", ARRAY_N);
		foreach ($parts as $part){
			echo "<option value='{$part[0]}'>{$part[1]}</option>";
		}
		?>
	</select>
	
	<h2>Zugeordnete Referenzen</h2>
	<select id="auswahlReferenz" multiple="multiple">
		<?php
		$lemmas = $db->get_results('SELECT * FROM Lemmata', ARRAY_A);
		foreach ($lemmas as $lemma){
			$genus_info = ' (' . str_replace('+', ',', $lemma['Genera']) . ')';
			echo "<option value='{$lemma['Id_Lemma']}'>{$lemma['Quelle']}: {$lemma['Subvocem']}" . ($genus_info != ' ()'? $genus_info : '') . "</option>";
		}
		?>
	</select>
	
	<input type="button" class="button button-primary" id="newReferenceButton" value="Neue Referenz anlegen">
	
	<h2>Zugeordnete Basistypen</h2>
	<select id="auswahlBasistyp">
		<?php
		$btypes = $db->get_results("SELECT Id_Basistyp, Orth FROM Basistypen", ARRAY_N);
		foreach ($btypes as $btype){
			echo "<option value='{$btype[0]}'>{$btype[1]}</option>";
		}
		?>
	</select>

	<input type="button" class="button button-primary" id="newBaseTypeButton" value="Neuen Basistyp anlegen" />
	
	<table id="baseTypeTable">
		<tbody>
			
		</tbody>
	</table>
	
	<br />
	<br />
	<br />
	
	<input type="button" class="button button-primary" id="newMTypeButton" value="Bestätigen" />
	<input type="hidden" id="saveCaller" />
</div>

<?php

	echo createBaseTypeOverlay($db, $dbname);

	echo im_table_entry_box('NeueReferenzFuerZuweisung', new IM_Row_Information('Lemmata', array(
		new IM_Field_Information('Quelle', 'F WHERE Referenzwoerterbuch', true),
		new IM_Field_Information('Subvocem', 'V', true),
		new IM_Field_Information('Genera', 'S', false),
		new IM_Field_Information('Bibl_Verweis', 'V', false),
		new IM_Field_Information('Link', 'V', false),
		new IM_Field_Information('Kommentar_Intern', 'T', false)
	), 'Angelegt_Von'), $dbname);
	
	va_echo_new_concept_fields('NeuesKonzept');
}

function createBaseTypeOverlay (&$db, $dbname, $edit = false){
	ob_start();
	?>
	<div id="VABasetypeOverlay" title="Basistyp" style="display : none">
		<form name="eingabeBasistyp">
			<h2>Typ-Information</h2>
			<table>
				<tr>
					<td>Orth:</td>
					<td><input name="Orth" type="text" /></td>
				</tr>
				<tr>
					<td>Quelle:</td>
					<td>
						<select id="auswahlSourceBasetype" name="Quelle">
						<?php
						$sources = $db->get_results("SELECT Abkuerzung FROM bibliographie WHERE Referenzwoerterbuch", ARRAY_A);
						foreach ($sources as $source){
							echo "<option value='{$source['Abkuerzung']}'>{$source['Abkuerzung']}</option>";
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Sprache:</td>
					<td>
						<select id="auswahlLangBasetype" name="Sprache">
						<?php
						$langs = $db->get_results("SELECT Abkuerzung, Bezeichnung_D FROM Sprachen WHERE Basistyp_Sprache", ARRAY_A);
						foreach ($langs as $lang){
							echo "<option value='{$lang['Abkuerzung']}'>{$lang['Bezeichnung_D']}</option>";
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Alpenwort:</td>
					<td><input name="Alpenwort" type="checkbox" /></td>
				</tr>
				<tr>
					<td>Kommentar_Intern:</td>
					<td><textarea name="Kommentar_Intern" /></textarea></td>	
				</tr>
			</table>
		
			<h2>Zugeordnete Referenzen</h2>
			<select id="auswahlReferenzBasetype" multiple="multiple">
				<?php
				$lemmas = $db->get_results('SELECT * FROM Lemmata_Basistypen', ARRAY_A);
				foreach ($lemmas as $lemma){
					echo "<option value='{$lemma['Id_Lemma']}'>{$lemma['Quelle']}: {$lemma['Subvocem']}" . "</option>";
				}
				?>
			</select>
			
			<input type="button" class="button button-primary" id="newBasetypeReferenceButton" value="Neue Referenz anlegen">
		</form>
		
		<br />
		<br />
		<br />
		
		<input type="button" class="button button-primary" id="newBTypeButton" value="<?php echo ($edit? 'Ändern' : 'Einfügen'); ?>" />
	</div>
	<?php
	
	echo im_table_entry_box('NeueReferenzFuerBasistyp', new IM_Row_Information('Lemmata_Basistypen', array(
			new IM_Field_Information('Quelle', 'F WHERE Referenz_Basistyp', true),
			new IM_Field_Information('Subvocem', 'V', true),
			new IM_Field_Information('Bibl_Verweis', 'V', false),
			new IM_Field_Information('Link', 'V', false),
			new IM_Field_Information('Kommentar_Intern', 'T', false)
	), 'Angelegt_Von'), $dbname);
	
	return ob_get_clean();
}
?>
