/**
 * @constructor
 * @struct
 * 
 */
function DescriptionList (){
	var list = {};
	var ortho = {};
	var currentId = -1;
	
	this.addDescription = function (ajaxObject){
		var description = new TokenDescription (this, ++currentId, ajaxObject["Art"], ajaxObject["Id_Typ"], 
			ajaxObject["Token"], ajaxObject["IPA"], ajaxObject["Original"], ajaxObject["Id_Stimulus"], ajaxObject["Erhebung"], 
			ajaxObject["Genus"], ajaxObject["Konzepte"], ajaxObject["Informanten"], ajaxObject["Tokengruppe"], ajaxObject["Bemerkungen"], 
			ajaxObject["Id_morph_Typ"],	ajaxObject["Typ"], ajaxObject["Relevanz"], ajaxObject["TokenIds"], ajaxObject["Aeusserungen"]);
		list[currentId] = description;
		if(ortho[description.name] == undefined){
			ortho[description.name] = []
		}
		ortho[description.name].push(currentId);
		return description;
	};
	
	this.getDescription = function (id){
		return list[id];
	};
	
	this.remove = function (id){
		delete list[id];
	};
	
		
	this.removeAll = function (){
		list = {};
		currentId = -1;
	};
	
	this.getNumber = function (orth, id){
		if(ortho[orth].length <= 1)
			return -1;
		else
			return ortho[orth].indexOf(id) + 1;
	}
	
	this.removeDuplicatesOf = function (descr){
		var removed = [];
		for (var id in list){
			var element = list[id];
			if(element.id != descr.id && element.equals(descr)){
				descr.addInformants(element.informants);
				this.remove(id);
				removed.push(id);
			}
		}
		return removed;
	};
	
	this.getOptionsHtml = function (){
		var result = "";
		for (var id in list){
			result += list[id].createOptionHtml();
		}
		return result;
	};
	
	this.changeTypeName = function (id_vatype, newTypeName){
		for (var id in list){
			var element = list[id];
			if(element.id_vatype == id_vatype){
				element.vatype = newTypeName;
			}
		}
	};
	
	this.getIdenticalNames = function (name, id){
		ids = ortho[name];
		var indexId = ids.indexOf(id);
		return ids.filter(i => i != id);
	};
}

/**
 * @constructor
 * @struct
 * 
 * @param {number} id
 * @param {string} kind
 * @param {number} id_type
 * @param {string} token
 * @param {string} ipa
 * @param {string} original
 * @param {number} id_stimulus
 * @param {string} source
 * @param {string} gender
 * @param {Array<number>|null} concepts
 * @param {string} informants
 * @param {number} id_vatype
 * @param {number} vatype
 */
function TokenDescription (owner, id, kind, id_type, token, ipa, original, id_stimulus, source, gender, concepts, informants, group, remarks, id_vatype, vatype, relevance, idlist, aelist){
	this.id = id;
	this.kind = kind;
	this.id_type = id_type;
	
	if(kind == "T" || kind == "G"){
		var first = original? original : token;
		var second = ipa? ' --- ' + ipa : '';
		var third = ' (' + (gender == ''? '?': gender) + ')';
		this.name = first + second + third;
	}
	else {
		this.name = token + ' (' + (gender == ''? '?': gender) + ')';
	}
	
	this.owner = owner;
	this.token = token;
		
	this.id_stimulus = id_stimulus;
	this.source = source;
	this.gender = gender;
	this.concepts = concepts == null? []: concepts.split(",");
	this.conceptLoadingList = [];
	
	this.informants = informants;
	this.group = group;
	this.remarks = remarks;
	this.relevant = relevance;
	this.idlist = idlist.split(",");
	this.aelist = aelist.split("###");
	
	this.vatype = vatype;
	this.id_vatype = id_vatype;
	
	this.shortenInformants = function (){
		if(this.informants.length > 50){
			var sub = this.informants.substring(0,50);
			var lastSem = sub.lastIndexOf(",");
			this.informants = this.informants.substring(0, lastSem) + ",...";
		}
	}
	this.shortenInformants();
	
	this.createOptionHtml = function (){
		var style = "";
		if(this.vatype == null){
			style += "font-weight : bold;";
		}
		if(this.concepts.length == 0){
			style += "font-style: italic;";
		}
		if (this.relevant != "1"){
			style += "background: #e1e1e1;";
		}
		var number = this.owner.getNumber(this.name, this.id);
		return '<option value="' + this.id + '" style="' + style + '">' + this.name + (number == -1? "": " [" + number + "]") + '</option>';
	};
	
	this.createTableRow = function (){
		var result = "<tr data-id-description='" + this.id + "'>";
		var number = this.owner.getNumber(this.name, this.id);
		result += "<td style='font-size: 16px;'>" + this.name + (number == -1? "": " [" + number + "]") + "</td>";
		result += "<td>" + this.informants + "</td>";
		result += "<td>" + (this.group != ""? "<b>Tokengruppe: " + this.group + "</b><br />": "") + this.remarks + "</td>";
		var conceptList = this.concepts.map(this.getConceptName.bind(this));
		result += "<td>" + conceptList.join("") + "</td>";
		if(this.vatype == null)
			result += "<td></td>";
		else if (this.vatype == "---LOADING---")
			result += "<td><img src='" + loadingUrl + "' /></td>";
		else
			result += "<td><span class='chosen-like-button" + (writeMode? " chosen-like-button-del" : "") + "'><span>" 
				+ this.vatype + "</span><a class='deleteTypification' /></span></td>";
		result += "<td><input type='button' class='button button-secondary correctButton' value='Korrigieren' /></td>";
		result += "</tr>";
		return result;
	};
	
	this.getConceptName = function (id){
		if(id){
			if(this.conceptLoadingList.indexOf(id) === -1){
				return "<span class='chosen-like-button" + (writeMode? " chosen-like-button-del" : "") + "' id='" + id + "'><span>" 
					+ jQuery("#konzeptAuswahl option[value=" + id + "]").text() 
					+ "</span><a class='deleteConcept' /></span>";
			}
			else {
				return "<img src='" + loadingUrl + "' />";
			}
		}
		return "";
	};
	
	this.setConceptLoading = function (id, loading){
		if(loading){
			this.conceptLoadingList.push(id);
		}
		else {
			var ind = this.conceptLoadingList.indexOf(id);
			if(ind !== -1){
				this.conceptLoadingList.splice(ind, 1);
			}
		}
	};
	
	this.equals = function (obj){
		return this.token == obj.token && this.id_stimulus == obj.id_stimulus && this.gender == obj.gender && this.remarks == obj.remarks && this.group == obj.group &&
			this.kind == obj.kind && this.id_type == obj.id_type && this.id_vatype == obj.id_vatype && arraysEqual(this.concepts, obj.concepts);
	};
	
	this.removeConcept = function (id){
		this.concepts.splice(this.concepts.indexOf(id), 1);
		
		var ind = this.conceptLoadingList.indexOf(id);
		if(ind !== -1){
			this.conceptLoadingList.splice(ind, 1);
		}
	};
	
	this.addConcept = function (id){
		this.concepts.push(id);
		this.concepts.sort(function (a, b){
			return a * 1 - b * 1;
		});
	};
	
	this.hasConcept = function (id){
		return this.concepts.indexOf(id) !== -1;
	};
	
	this.addInformants = function (str){
		str = str.replace(",...", "");
		var strOld = this.informants.replace(",...", "");
		var numsAll = strOld.split(",").concat(str.split(","));
		numsAll.sort();
		this.informants = numsAll.join(",");
		this.shortenInformants();
	};
	
	this.getLockName = function (){
		return this.token + "%%%" + this.gender + "%%%" + this.id_stimulus + "%%%" + this.remarks.substring(0,70) + "%%%" + this.group + "%%%" + this.kind + "%%%" + JSON.stringify(this.concepts.map(x => x * 1));
	}
}

function callbackSaveReference (data){
	var genderInfo = " (" + data["Genera"] + ")";
	jQuery('#auswahlReferenz').append("<option value='" + data["id"] + "'>" + data["Quelle"] + ": " + data["Subvocem"] + (genderInfo != " ()"? genderInfo: "") + "</option>").trigger("chosen:updated");
}

function callbackSaveReferenceBType (data){
	jQuery('#auswahlReferenzBasetype').append("<option value='" + data["id"] + "'>" + data["Quelle"] + ": " + data["Subvocem"] + "</option>").trigger("chosen:updated");
}

function setMorphTypeData (data){
	var e = document.forms["eingabeMorphTyp"].elements;
	
	jQuery(e["Orth"]).val(data.type.Orth);
	jQuery(e["Sprache"]).val(data.type.Sprache).trigger("chosen:updated");
	jQuery(e["Wortart"]).val(data.type.Wortart).trigger("chosen:updated");
	jQuery(e["Praefix"]).val(data.type.Praefix);
	jQuery(e["Infix"]).val(data.type.Infix);
	jQuery(e["Suffix"]).val(data.type.Suffix);
	jQuery(e["Genus"]).val(data.type.Genus).trigger("chosen:updated");
	jQuery(e["Kommentar_Intern"]).val(data.type.Kommentar_Intern);

	jQuery("#auswahlBestandteile").val(data.parts).trigger("chosen:updated");
	jQuery("#auswahlReferenz").val(data.refs).trigger("chosen:updated");
	//jQuery("#auswahlBasistyp").val(data.btypes).trigger("chosen:updated");
	jQuery("#baseTypeTable").empty();
	jQuery("#auswahlBasistyp option").prop("disabled", false);
	for (var i = 0; i < data.btypes.length; i++){
		addBaseType(data.btypes[i][0], jQuery("#auswahlBasistyp option[value=" + data.btypes[i][0] + "]").text(), data.btypes[i][1] == "1");
	}
}

function setBaseTypeData (data){
	var e = document.forms["eingabeBasistyp"].elements;
	
	jQuery(e["Orth"]).val(data.type.Orth);
	jQuery(e["Sprache"]).val(data.type.Sprache).trigger("chosen:updated");
	jQuery(e["Alpenwort"]).prop("checked", data.type.Alpenwort == "1");
	jQuery(e["Kommentar_Intern"]).val(data.type.Kommentar_Intern);

	jQuery("#auswahlReferenzBasetype").val(data.refs).trigger("chosen:updated");
}

function getMorphTypeData (id){
	var data = {};
	
	data.action = "va";
	data.namespace = "typification";
	data.query = "saveMorphType";
	data.dbname = dbname;
	
	data.id = id;
	
	data.type = {};
	
	var e = document.forms["eingabeMorphTyp"].elements;
	
	data.type.Orth = e["Orth"].value;
	data.type.Sprache = e["Sprache"].value;
	data.type.Wortart = e["Wortart"].value;
	data.type.Praefix = e["Praefix"].value;
	data.type.Infix = e["Infix"].value;
	data.type.Suffix = e["Suffix"].value;
	data.type.Genus = e["Genus"].value;
	data.type.Kommentar_Intern = e["Kommentar_Intern"].value;

	data.parts = jQuery("#auswahlBestandteile").val();
	data.refs = jQuery("#auswahlReferenz").val();
	data.btypes = jQuery("#auswahlBasistyp option:disabled").map(function () {return jQuery(this).val();}).get();
	data.unsures = jQuery("#baseTypeTable tr input[type=checkbox]").map(function (){return jQuery(this).is(":checked")? "1" : "0";}).get();
	
	return data;
}

function getBaseTypeData (id){
	var data = {};
	
	data.action = "va";
	data.namespace = "typification";
	data.query = "saveBaseType";
	data.dbname = dbname;
	
	data.id = id;
	
	data.type = {};
	
	var e = document.forms["eingabeBasistyp"].elements;
	
	data.type.Orth = e["Orth"].value;
	data.type.Sprache = e["Sprache"].value;
	data.type.Quelle = e["Quelle"].value;
	data.type.Alpenwort = e["Alpenwort"]["checked"]? "1":"0";
	data.type.Kommentar_Intern = e["Kommentar_Intern"].value;

	data.refs = jQuery("#auswahlReferenzBasetype").val();
	
	return data;
}

function openMorphTypeDialog(){
	jQuery("#saveCaller").val(this.id);
	var e = document.forms["eingabeMorphTyp"].elements;
	for (var i = 0; i < e.length; i++){
		jQuery(e[i]).val("");
	}
	
	jQuery("#auswahlBestandteile").val([]);
	jQuery("#auswahlReferenz").val([]);
	jQuery("#auswahlBasistyp").val([]);
	jQuery("#baseTypeTable").empty();
	jQuery("#auswahlBasistyp option").prop("disabled", false);
	
	jQuery('#VATypeOverlay').dialog({
		"minWidth" : 700,
		"modal": true,
		"close" : function (){
			jQuery("#VATypeOverlay select").chosen("destroy");
		}
	});
	
	jQuery("#VATypeOverlay form[name=eingabeMorphTyp] select").chosen({"allow_single_deselect" : true, "width": "165px", "normalize_search_text" : removeDiacritics});
	jQuery("#VATypeOverlay select[multiple=multiple], #auswahlBasistyp").chosen({"allow_single_deselect" : true, "width": "600px", "normalize_search_text" : removeDiacriticsPlusSpecial, "search_contains": true});
}

function closeMorphDialog (){
	jQuery("#VATypeOverlay").dialog("close");
}

function openBaseTypeDialog (closingFunction){
	
	jQuery("#VABasetypeOverlay input[name=Orth]").val("");
	jQuery("#VABasetypeOverlay input[name=Sprache]").val("");
	jQuery("#VABasetypeOverlay input[name=Alpenwort]").val("").prop("checked", false);
	jQuery("#VABasetypeOverlay textarea").val("");
	
	jQuery('#VABasetypeOverlay').dialog({
		"minWidth" : 700,
		"modal": true,
		"close" : function (){
			jQuery("#VABasetypeOverlay select").chosen("destroy");
			
			if (closingFunction)
				closingFunction ();
		}
	});
	
	jQuery("#VABasetypeOverlay select").val([]).chosen({"allow_single_deselect" : true, "width": "400px", "normalize_search_text" : removeDiacriticsPlusSpecial, "search_contains": true});
}

function closeBaseTypeDialog (){
	jQuery("#VABasetypeOverlay").dialog("close");
}
