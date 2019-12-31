ajaxurl = ajax_object.ajaxurl;

/**@enum {number}*/
var categories = {
	Informant : 0,
	Concept : 1,
	PhoneticType: 2,
	MorphologicType : 3,
	BaseType : 4,
	ExtraLing : 5,
	Polygon : 6,
	Custom : 7
};

//TODO fix eling error if z_geo is empty, at least "no data" should be shown again

chosenSettings["normalize_search_text"] = removeDiacriticsPlusSpecial;

jQuery(function (){
	jQuery(".conceptTooltip").each(/** @this{Element} */ function (){
		var /** string */ id = /** @type{string} */ (jQuery(this).data("id"));
		jQuery(this).qtip({
			"content" : {
				"text" : createConceptToolTipContent(id)
			},
			"position" : {
				"my" : "bottom right",
				"at" : "top left"
			}
		});
	});
	
	addMouseOverHelp(jQuery("#trSelectionBar"));
	bindMenuSlide();
	
	if (PATH["tk"] != undefined || PATH["single"] != undefined){
		jQuery('#legend_heading').trigger('click');
	}
});

/**
 * @return Array<Array<{id: string, name: string}>|boolean>
 */
function getHexagonChoices (){
	var /** Array<{id: string, name: string}>*/ possibleValues = [];
	jQuery("#hexagonSelect option[value!='']").each(function (){
		possibleValues.push({"id" : "A" + /**@type{string}*/ (jQuery(this).val()), "name" : /** @type{string}*/ (jQuery(this).text())});
	});
	
	var /** boolean|string */ areaId = false;
	for (var i = 0; i < legend.getLength(); i++){
		var /**LegendElement|MultiLegendElement*/ element = legend.getElement(i);
		if (element.category == 6 /** Areas */){
			areaId = element.key;
			var /** number */ indexPipe = areaId.indexOf("|");
			if(indexPipe !== -1)
				areaId = areaId.substring(0, indexPipe);
			break;
		}
	}
	
	if (areaId === false){
		return [possibleValues, true];
	}
	else {
		var /** Array<{id: string, name: string}>*/ restrictedList = [];
		for (i = 0; i < possibleValues.length; i++){
			var /**string */ hexaCategory = possibleValues[i]["id"];
			hexaCategory = hexaCategory.substring(0, hexaCategory.indexOf("|"));
			if (hexaCategory == areaId){
				restrictedList.push(possibleValues[i]);
			}
		}
		if (restrictedList.length == 0){
			return [possibleValues, true];
		}
		else {
			return [restrictedList, false];
		}
	}
}

/**
 * 
 * @return {undefined}
 */
function bindMenuSlide(){

	jQuery('.mode_switch_label').on('click', function(){
		if(!jQuery(this).hasClass("disabled")){
			if(jQuery(this).attr('id') == "phy_label"){
				optionManager.setOption("polymode", "phy");
			}
			else {
				var /** Array<Array<{id: string, name: string}>|boolean>*/ result = getHexagonChoices();
				var /** Array<{id: string, name: string}>*/ listValues = /** @type{Array<{id: string, name: string}>}*/ (result[0]);
				if(listValues.length == 1){
					var /** string */ id = listValues[0]["id"];
					optionManager.setOption("polymode", id);
				}
				else {
					buildHexModal(listValues, /** @type{boolean} */ (result[1]));
				}
				
			}
		}
	});

	jQuery('.move_menu').on('click',function (){

		jQuery( ".move_menu .active").fadeOut('fast', function(){
			jQuery( ".move_menu .inactive").fadeIn('fast', function(){
				jQuery(".move_menu span").toggleClass("active inactive");
			});
		})

		jQuery( "#leftTable" ).slideToggle(function(){
			adjustlegendTable();
			jQuery('.tablecontainer').toggleClass('menu_closed');
		});	
	})
	
	jQuery('.menu_heading').on('click',function (e){

		var target = jQuery(e.target);

		if(!target.hasClass('mode_switch_label') && !target.hasClass('l_disabled')){

			var id = jQuery(this).attr('id');
			var that = jQuery(this).parent();
			if(id=="legend_heading")that.addClass('keep_shadow');
		
			that.find('.menu_caret').toggleClass("fa-caret-right fa-caret-down");
			that.find('.menu_collapse').slideToggle(function(){
				checkNavBar();
				if(id=="legend_heading" && !that.hasClass('active')){
					that.removeClass('keep_shadow');
				}
			});

			jQuery('.menu_grp').each(function(){
				if(jQuery(this).find('.menu_heading').attr('id')!=id){
					jQuery(this).removeClass('active');
					if(jQuery(this).hasClass('keep_shadow'))jQuery(this).removeClass('keep_shadow');
				}
			});

			that.toggleClass('active');

			jQuery('.menu_collapse').each(function(){
				if(!jQuery(this).parent().hasClass('active')){
					jQuery(this).slideUp();
			
					if(jQuery(this).parent().find('.menu_caret').hasClass('fa-caret-down')){
						jQuery(this).parent().find('.menu_caret').removeClass('fa-caret-down').addClass('fa-caret-right');
					}
				}
			});
		}
	});

	jQuery(document).on('im_before_load_data', function(event, data){
		
		if(data["trigger"] == "menu"){
			if(data["category"] == categories.Polygon && optionManager.getOptionState("polymode") !== "phy"){
				//Update ajax data
				data["ajaxData"]["hexgrid"] = data["key"]; //Ajax data already computed => has to be changed here
				categoryManager.addAjaxData("hexgrid", data["key"]);
			
				//Reload other legend entries
				for (var i = 0; i < legend.getLength(); i++){
					var /** LegendElement|MultiLegendElement */ element = legend.getElement(i);
					if(element.category != categories.Polygon){
						element.reloadSymbols();
					}
				}
			}
		}
		
		if(jQuery('#legend_heading').hasClass('l_disabled'))
			jQuery('#legend_heading').removeClass('l_disabled');
		
		if(data["trigger"] == "menu"){
				if(!(jQuery('#legend_heading').next('.menu_collapse').is(':visible')))jQuery('#legend_heading').trigger('click');	
		}
	});

	jQuery(document).on('im_legend_after_update', function(){
		adjustlegendTable();
		setTableRowWidth();
	});

	jQuery(document).on('im_load_syn_map', function(){
		jQuery('#legend_heading').trigger('click');
	});
	
	jQuery(document).on("im_server_extra_data", function (event, data){
		for (let /** string */ key in data["BIB"]){
			if(jQuery("#VAbibDiv #" + key).length == 0){
				jQuery("#VAbibDiv").append(data["BIB"][key]);
			}
		}
		
		for (let /** string */ key in data["STI"]){
			if(jQuery("#VAstiDiv #" + key).length == 0){
				jQuery("#VAstiDiv").append(data["STI"][key]);
			}
		}
	});


	jQuery('#syn_heading').one('click',function(){
	    jQuery("#IM_Syn_Map_Selection").chosen('destroy');
		jQuery("#IM_Syn_Map_Selection").val("").chosen({allow_single_deselect: true});
	});
	

	jQuery(window).resize(function() {
	  if(jQuery('.menu-toggle').hasClass('toggled-on'))jQuery('.menu-toggle').trigger('click');
	  setTableRowWidth();
	  adjustlegendTable();	
	});
}

/**
 * 
 * @param {Array<{id: string, name: string}>} list_values
 * @param {boolean} trigger_load
 * 
 * @return {undefined}
 */
function buildHexModal(list_values, trigger_load){

	var /** jQuery */ modal_content = getHexModalContent(list_values);
	
	jQuery('.select_hex_popup .hex-modal-btn').remove();
 	jQuery('.select_hex_popup .hex-modal-btn-grp').append(modal_content);
 	jQuery('.select_hex_popup').modal();

 	jQuery('.hex-modal-btn').one('click',function(){
 		var /** string*/ id = /** @type{string} */ (jQuery(this).attr('id'));
 		
 		var /** string */ shortenedId = id.substring(0, id.indexOf("|"));
 		var /** Object<string, string> */ optionData = {};
 		if (trigger_load){
			optionData["load"] = shortenedId;
		}
 		optionManager.setOption("polymode", id, optionData);

		jQuery('.select_hex_popup').modal('hide');
 	});


 	jQuery('.select_hex_popup').on('hide.bs.modal',function(){
 		syncOptionAndBtnStates();
 	});
}

/**
 * 
 * @return {undefined}
 */
function syncOptionAndBtnStates(){
	var /** string */ state = /** @type {string} */ (optionManager.getOptionState('polymode'));
	if(state != "phy"){
		state = "hex";
	}
	
	var /** string */ id = /** @type{string}*/ (jQuery('.map_mode_selection label.active').attr('id'));
	id = id.replace("_label", "");

	 if(id != state){
	 	jQuery('.map_mode_selection label').removeClass('active');
	 	var label_id = state += "_label";
	 	jQuery('#' + label_id).addClass('active');	
	 }
}

/**
 * 
 * @param {Array<{id: string, name: string}>} list_values
 * 
 * @return {jQuery}
 */
function getHexModalContent(list_values){
	var /** string*/ content = "";
	for(var i = 0; i < list_values.length; i++){
		var /** {id: string, name: string} */ value = list_values[i];
		content += '<button id="'+ value["id"] +'" type="button" class="btn btn-secondary hex-modal-btn">' + value["name"] + '</button>';
	}

	return jQuery(content);
}


/**
 * 
 * @return {undefined}
 */
function adjustlegendTable(){
	var window_height = window.innerHeight;
	var legend_menu_offset = jQuery('#leftTable .menu_grp:nth-child(2)').offset().top;
	var legend_menu_height = jQuery('#leftTable .menu_grp:nth-child(2) > .menu_heading').outerHeight();
    var syn_menu_height =    jQuery('#leftTable .menu_grp:nth-child(3) > .menu_heading').outerHeight();
    var search_height =  jQuery('#IM_main_div .search_container').outerHeight();
	var move_menu_height = jQuery('.move_menu').outerHeight();
	var remaining = window_height - legend_menu_offset - legend_menu_height - syn_menu_height - search_height - 40; 
	jQuery('.legendtable tbody').css('max-height',remaining+"px");

}

/**
 * 
 * @return {undefined}
 */
function setTableRowWidth(){
	var /** number */ width = /** @type{number}*/ (jQuery('.tablecontainer').width());	
	jQuery('.legendtable tr').width(width + "px");
}

/**
 * 
 * @param {string} key
 * 
 * @return {string}
 */
function simplifyELingKey (key){
	var /** number */ posPipe = key.indexOf("|");
	if(posPipe !== -1)
		return key.substring(1, posPipe);
	return key.substring(1);
}

jQuery(document).on("im_map_initialized", function (){

//	if(ajax_object.db != "xxx")
//		categoryManager.addAjaxData("db", ajax_object.db);
	
	categoryManager.addInfoWindowContentConstructor("record", RecordInfoWindowContent);
	
	if (PATH["tk"] == undefined){
//		categoryManager.loadData(6, "A17", "custom", {"subElementCategory" : -1});
	}
	
	commentManager.commentTabOpened = /** @param {jQuery} element */ function (element){
		try {
			addBiblioQTips(element);
			element.find(".quote").qtip({
				"show" : "click",
				"hide" : "unfocus"
			});
			jQuery("#commentTitle").append("&nbsp;");
			jQuery("#commentTitle").append(element.find(".quote"));
		}
		catch (/** @type{string} */ e){
			console.log(e);
		}
	}
	
	commentManager.commentTabClosed = /** @param {jQuery} element */ function (element){
		element.find(".bibl").qtip("destroy", true);
		jQuery("#commentTitle").find(".quote").qtip("destroy", true);
	}
	
	/**
	 * @param {number} categoryID
	 * @param {string} elementID
	 */
	commentManager.showCommentMenu = function (categoryID, elementID){
		return ajax_object.db == "xxx";
	};
});

var /** boolean*/ backupCommunities;
jQuery(document).on("im_edit_mode_started", function (){
	backupCommunities = /** @type{boolean}*/ (optionManager.getOptionState("comm"));
	optionManager.setOption("comm", false, {"reload" : false});
});

jQuery(document).on("im_edit_mode_stopped", function (){
	if(backupCommunities !== undefined){
		optionManager.setOption("comm", backupCommunities, {"reload" : false});
	}
});

jQuery(document).on("im_syn_map_before_loading", function (event, mapInfos, data){
	var /** string */ tdb = mapInfos["Options"]["tdb"];
	
	if(tdb == ajax_object["next_version"]){ //Future version
		tdb = "xxx";
	}
	
	if(tdb != ajax_object["db"]){		
		mapInfos["continue"] = false;
		reloadPageWithParam(["db", "tk"], [tdb, mapInfos["id"]]);
	}
});

jQuery(document).on("im_add_options", function (){
	
	categoryManager.addAjaxData("outside", false);
	
	if(ajax_object.dev == "1"){
		optionManager.addOption("wkt", new ClickOption("Show WKT", function (){
			var wkt = prompt("WKT:");
			if(wkt){
				var geoList = wkt.split(";");
				for (var i = 0; i < geoList.length; i++){
					var /** Object */ shapeObject = mapInterface.createShape(geoManager.parseGeoDataUnformated(geoList[i]), null, "debug");
					mapInterface.addShape(shapeObject);
				}
			}
		}));
	}
	
	optionManager.addOption("tdb", new HiddenOption((ajax_object.db == "xxx"? ajax_object.next_version: ajax_object.db), true));
	
	optionManager.addOption("polymode", new HexagonOption());
	
	
	optionManager.addOption("ak", new BoolOption(false, TRANSLATIONS["ALPENKONVENTTION_INFORMANTEN"], function(val, details) {
		categoryManager.addAjaxData("outside", val);
		if(!details || details["first"] !== true){
			optionManager.enableOptions(false);
			legend.reloadOverlays(function (){
				optionManager.enableOptions(true);
			});
		}
	}, true));
	
	categoryManager.addAjaxData("community", false);
	optionManager.addOption("comm", new BoolOption(false, TRANSLATIONS["AUF_GEMEINDE"], function(val, details) {
		categoryManager.addAjaxData("community", val);
		if(!details || (details["reload"] !== false && details["first"] !== true)){
			optionManager.enableOptions(false);
			legend.reloadOverlays(function (){
				optionManager.enableOptions(true);
			});
		}
	}, true));
	
	if(ajax_object["db"] == "xxx" || ajax_object["db"] * 1 > 171){
		categoryManager.addAjaxData("simple_polygons", true);
		optionManager.addOption("simple_polygons", new BoolOption(true, Ue["VEREINFACHTE_POLYGONE"], function(val, details) {
			categoryManager.addAjaxData("simple_polygons", val);
			if(!details || (details["reload"] !== false && details["first"] !== true)){
				optionManager.enableOptions(false);
				legend.reloadOverlays(function (){
					optionManager.enableOptions(true);
				});
			}
		}, true));
	}
	
	if (mapInterfaceType == MapInterfaceType.GoogleMaps){
		if(ajax_object.va_staff == "1") {
			var /** BoolOption */ printOption = new BoolOption(false, TRANSLATIONS["DRUCKFASSUNG"], function (val, details){
				mapInterface.showPrintVersion(val);
			}, false);
			
			 jQuery.ajax({
				dataType: "json",
				url: ajax_object["plugin_url"] + "/im_config/map_styles/empty.json",
				success: function(data){
					/** @type {GoogleMapsInterface} */ (mapInterface).addMapStyle("empty", data);
				}
		 	});
			
			jQuery(document).on("im_quantify_mode", function (event, val){
				printOption.setEnabled(!val);
			});
	
			optionManager.addOption("print", printOption);
			
			optionManager.addOption("sql", new ClickOption("SQL Query", function (){
				categoryManager.showFilterScreen(categories.Custom, "SQL");
			}));
		}
		else {
			if(optionManager.getOptionState("print")){
				mapInterface.showPrintVersion(true);
			}
		}
	}
	
	jQuery(document).on("im_url_changed", function (event, state){
		if(state && state["tk"]){
			jQuery(".modal_lang_cont a").each(function (){
				jQuery(this).attr("href", addParamToUrl(/** @type{string} */ (jQuery(this).attr("href")), "tk", state["tk"]));
			});
		}
	});
});

jQuery(document).on("im_legend_before_rebuild", function (event, legend){
	//Remove old qtips
	jQuery("#IM_legend tr td:nth-child(3)").qtip("destroy", true);
});

jQuery(document).on("im_show_edit_mode", 
	/**
	* @param {Event} event
	* @param {{result : boolean}} paramObject
	* 
	* @return {undefined}
	*/
	function (event, paramObject){
		paramObject.result = ajax_object.db == "xxx";
	}
);
	
jQuery(document).on("im_legend_element_created", 
	/**
	 * @param {Object} event
	 * @param {LegendElement} legendElement
	 * @param {Element} DOMElement
	 */
	function (event, legendElement, DOMElement){
		addConceptQTip(legendElement);
	});

/**
 *
 * @param {LegendElement|MultiLegendElement} currentElement
 * 
 * @returns {undefined}
 */
function addConceptQTip(currentElement){
	if(currentElement.category == categories.Concept && currentElement.key != -1){
		var /**jQuery*/ element = createConceptToolTipContent(currentElement.key.substring(1));
		
		if(element){
			jQuery(currentElement.htmlElement).find("td:nth-child(3)").qtip({
				"content" : {
					text : element
				},
				"position" : {
					"my" : "bottom left",
					"at" : "top left"
				}
			});
		}
	}
}

/**
 * @param {string} id
 * 
 * @return {jQuery}
 */
function createConceptToolTipContent (id){
	var /** Array */ concept = Concepts[id];
	
	var /** string */ conceptName = /** @type{string} */ (concept[0]);
	var /** string */ conceptDescr = /** @type{string} */ (concept[1]);
	var /** string */ conceptImg = /** @type{string} */ (concept[4]);
	
	if((conceptName && conceptName != conceptDescr) || conceptImg){
		var /** Element */ result = document.createElement("div");
		
		if(conceptImg){
			var /** Element */ img = document.createElement("img");
			img["src"] = concept[4];
			img["style"]["display"] = "block";
			img["style"]["margin"] = "auto";
			img["style"]["max-width"] = "100%";
			result.appendChild(img);
			result.appendChild(document.createElement("br"));
		}
		
		if(conceptName && conceptName != conceptDescr){
			var /** Element */ span = document.createElement("span");
			span["style"]["text-align"] = "center";
			span.appendChild(document.createTextNode(concept[1]));
			result.appendChild(span);
		}
		
		return jQuery(result);
	}
	return null;
}

var /**AlphabetSorter */ alphabetSorter = new AlphabetSorter();
var /**RecordNumberSorter */ numRecSorter = new RecordNumberSorter();

var il = new SimpleListBuilder(["name", "description"]);
il.addListPrinter(new JsonListPrinter());
il.addListPrinter(new HtmlListPrinter());
il.addListPrinter(new CsvListPrinter());

var /** FieldType */ stringInput = new StringInputType();

var /** EditConfiguration */ informatEditConfig = new EditConfiguration();
informatEditConfig.setFieldData(OverlayType.PointSymbol, [
	new FieldInformation("Erhebung", stringInput, true),
	new FieldInformation("Nummer", stringInput, true),
	new FieldInformation("Ortsname", stringInput, false),
	new FieldInformation("Bemerkungen", stringInput, false)
]);
informatEditConfig.allowNewOverlays(OverlayType.PointSymbol);
informatEditConfig.allowGeoDataChange(OverlayType.PointSymbol);

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.Informant, 
		"categoryPrefix" : "I",
		"name" : Ue["INFORMANTEN"],
		"elementID" : "informantSelect", 
		"textForNewComment" : Ue["KOMMENTAR_INFORMANT_SCHREIBEN"],
		"textForListRetrieval" : "Informanten-Daten exportieren",
		"listBuilder" : il,
		"editConfiguration" : informatEditConfig
	})
);

var /** Array<{tag: string, name : string}>*/ lingTags = [{"tag" : "ERHEBUNG", "name": "Atlas"}]; ///TODO translate Atlas

var lingTagFunction =
/** 
 * @param {number} categoryID
 * @param {string} elementID
 * 
 * @return {Object<string, Array<string>>}
 */
function (categoryID, elementID){
	var /** Array<string>*/ ids = elementID.substring(1).split("+");
	var /** Array<string>*/ resultList = [];
	var /** string*/ prefix = elementID[0];
	
	for (var i = 0; i < ids.length; i++){
		var newTags = SourceMapping[prefix + ids[i]].split(",");
		for (var j = 0; j < newTags.length; j++){
			if(resultList.indexOf(newTags[j]) == -1){
				resultList.push(newTags[j]);
			}
		}
	}
	
	return {"ERHEBUNG" : resultList};
};

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.PhoneticType,
		"categoryPrefix" : "P",
		"name" : Ue["PHON_TYP"],
		"nameEmpty" : Ue["NICHT_TYPISIERT"],
		"elementID" : "phonTypeSelect",
		"filterComponents" : [
			new GroupingComponent([categories.Concept], categories.Concept, new Sorter([alphabetSorter, numRecSorter]), undefined, lingTags),
			new MarkingComponent(lingTagFunction)],
		"countNames" : [Ue["BELEG"], Ue["BELEGE"]],
		"textForNewComment" : Ue["KOMMENTAR_PTYP_SCHREIBEN"]
	})
);

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.MorphologicType,
		"categoryPrefix" : "L",
		"name" : Ue["MORPH_TYP"],
		"nameEmpty" : Ue["NICHT_TYPISIERT"],
		"elementID" : "morphTypeSelect",
		"filterComponents" : [
			new GroupingComponent([categories.PhoneticType, categories.Concept], categories.Concept, new Sorter([alphabetSorter, numRecSorter]), undefined, lingTags),
			new MarkingComponent(lingTagFunction),
			new TypeGenderFilterComponent()],
		"countNames" : [Ue["BELEG"], Ue["BELEGE"]],
		"textForNewComment" : Ue["KOMMENTAR_MTYP_SCHREIBEN"],
		"costumGetNameFunction" : function (key, regularNameFun){
			var /**string */ regular = regularNameFun(key);
			var /**string*/ base;
			var /** string */ id =  key.substring(1);
			if(regular){
				base = regular;
			}
			else {
				var candidates = jQuery("#morphTypeSelect option[value*='+" + key.substring(1) + "'], #morphTypeSelect option[value*='" + key.substring(1) + "+']");
				var regexp = new RegExp("(^|\\+)" + id + "($|\\+)");
				for (let i = 0; i < candidates.length; i++){
					var /** jQuery*/ option = candidates.eq(i);
					
					if(option.val().match(regexp)){
						base = /** @type{string} */ (option.text());
						break;
					}
				}
			}
			var /** string*/ gender = TypeGenders[id];
			var /** string*/ insertString = "";
			
			if(gender){
				insertString = "\u00A0" + gender + ".";
			}
			
			return base.substring(0, base.length - 1) + insertString + ")";
		}
	})
);

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.BaseType,
		"categoryPrefix" : "B",
		"name" : Ue["BASISTYP"],
		"nameEmpty" : Ue["NICHT_TYPISIERT"],
		"elementID" : "baseTypeSelect",
		"filterComponents" : [
			new GroupingComponent([categories.PhoneticType, categories.MorphologicType, categories.Concept], categories.Concept, new Sorter([alphabetSorter, numRecSorter]), undefined, lingTags),
			new MarkingComponent(lingTagFunction)],
		"countNames" : [Ue["BELEG"], Ue["BELEGE"]],
		"textForNewComment" :Ue["KOMMENTAR_BASIS_SCHREIBEN"]
	})
);

var /** function(number,string,number,number):boolean */ conceptSorterFunc = function (mainCategoryId, elementId, subCategoryId, filterId){
	return filterId != 1 || subCategoryId == categories.MorphologicType;
};

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.Concept,
		"categoryPrefix" : "C",
		"name" : Ue["KONZEPT"],
		"nameEmpty" : Ue["KEIN_KONZEPT"],
		"elementID" : "conceptSelect",
		"filterComponents" : [
			new ConceptFilterComponent(), 
			new GroupingComponent(
				[categories.PhoneticType, categories.MorphologicType, categories.BaseType], 
				categories.MorphologicType, 
				new Sorter([alphabetSorter, new LanguageFamilySortComponent (), numRecSorter]), 
				conceptSorterFunc,
				lingTags
				), 
			new MarkingComponent(lingTagFunction)],
		"countNames" : [Ue["BELEG"], Ue["BELEGE"]],
		"textForNewComment" : Ue["KOMMENTAR_KONZEPT_SCHREIBEN"],
		"costumGetNameFunction" : function (key){
			key = key.substring(1); //Remove prefix
			return Concepts[key][0] == ""? /** @type{string} */ (Concepts[key][1]): /** @type{string} */ (Concepts[key][0]);
		}
	})
);

var /** function (number, string) : Object<string, Array<string>> */ elingTagFunction =  function (categoryID, elementID){
	var /** Object<string, Array<string>>*/ result = {};
	var /** boolean */ ak = /** @type{boolean}*/ (optionManager.getOptionState("ak"));
	var /** Object<string, Array<{value: string, ak: number}>> */ tagObject = ELing[simplifyELingKey(elementID)][1];
	
	if(tagObject){
		for (var /** string */ key in tagObject){
			for (var i = 0; i <  tagObject[key].length; i++){
				if(ak || tagObject[key][i]["ak"] == "1"){
					if(!result.hasOwnProperty(key)){
						result[key] = [];
					}
					result[key].push(tagObject[key][i]["value"]);
				}
			}
		}
	}
	
	return  result;
};

var /** function (number, string): Array<{tag:string, name:string}> */ elingGroupFunction = function (categoryID, elementID){
	var /**Array<{tag:string, name:string}>*/ result = [];
	var /** boolean */ ak = /** @type{boolean} */ (optionManager.getOptionState("ak"));
	var /** Object<string, Array<{value: string, ak: number}>> */ tagObject = ELing[simplifyELingKey(elementID)][1];
	if(tagObject){
		for (var key in tagObject){
			for (var i = 0; i <  tagObject[key].length; i++){
				if(ak || tagObject[key][i]["ak"] == "1"){
					var /**string*/ translTag = Ue[key];
					result.push({tag : key, name : (translTag? translTag : key)});
					break;
				}
			}
		}
	}
	
	return result;
};

var /** TagComponent */ elingTag = new TagComponent(elingTagFunction);
var /** GroupingComponent */ elingGroupingE = new GroupingComponent([], undefined, undefined, undefined, elingGroupFunction)

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.ExtraLing,
		"categoryPrefix" : "E",
		"name" : Ue["AUSSERSPR"],
		"nameEmpty" : Ue["EMPTY"],
		"elementID" : "extraLingSelect",
		"filterComponents" : [elingTag, elingGroupingE],
		"textForNewComment" : Ue["KOMMENTAR_AUSSERSPR_SCHREIBEN"],
		"costumGetNameFunction" : function (key){
			return /** @type{string} */ (ELing[simplifyELingKey(key)][0]);
		}
	})
);

var /** GroupingComponent */ elingGroupingP = new GroupingComponent(function (categoryID, elementID){
	var /**Array<number>*/ result = [];
	
	if(ajax_object.va_staff == "1" && (elementID == "A62" || elementID == "A60")){
		result.push(-4);
	}
	
	if(simplifyELingKey(elementID) == "63" || simplifyELingKey(elementID) == "17" || simplifyELingKey(elementID) == "74"){
		result.push(-1);
	}
	
	return result;
}, function (categoryID, elementID){
	if(simplifyELingKey(elementID) == "63" || simplifyELingKey(elementID) == "17" || simplifyELingKey(elementID) == "74"){
		return -1;
	}
	if(simplifyELingKey(elementID) == "62" || simplifyELingKey(elementID) == "60"){
		return "LAND";
	}
	return undefined;
}, undefined, undefined, elingGroupFunction);

var /** EditConfiguration */ polyEditConfig = new EditConfiguration();
polyEditConfig.allowGeoDataChange(OverlayType.PointSymbol, function (elementID){
	return elementID == "A62";
});

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.Polygon,
		"categoryPrefix" : "A",
		"name" : Ue["POLYGONE"],
		"nameEmpty" : Ue["EMPTY"],
		"elementID" : "polygonSelect",
		"filterComponents" : [elingTag, elingGroupingP,	new CenterPointFilterComponent()],
		"textForNewComment" : Ue["KOMMENTAR_AUSSERSPR_SCHREIBEN"],
		"costumGetNameFunction" : function (key){
			return /** @type{string} */ (ELing[simplifyELingKey(key)][0]);
		},
		"editConfiguration" : polyEditConfig,
		"singleSelect" : true,
		"forbidRemovingFunction" : function (key){
			return optionManager.getOptionState("polymode") !== "phy";
		}
		})
);

categoryManager.registerCategory (
	buildCategoryInformation ({
		"categoryID" : categories.Custom,
		"categoryPrefix" : "X",
		"name" : Ue["EIGENE_KATEGORIE"],
		"nameEmpty" : Ue["EMPTY"],
		"elementID" : undefined,
		"filterComponents" : [new SQL_Filter("Type_Kind = 'L' AND Type = 'Butter' AND Instance like 'p%'")]
	})
);

//"Real" tag translations
for (var i = 0; i < TagValues.length; i++){
	if(Ue[TagValues[i]]){
		categoryManager.addTagTranslation(TagValues[i], Ue[TagValues[i]]);
	}
}
//Pseudo tag translations
categoryManager.addTagTranslation("ERHEBUNG", Ue["ERHEBUNG"]);

/**
 * @type {MapPosition}
 */
var mapPosition = {
	"lat" : 50.6311167,
	"lng" : 3.0121411,
    "zoom" : 9,
    "minZoom" : 6	
};

/* The threshold used here is a "lat/lng distance" of 0.000898315284119521435127501256466 
* That corresponds to 100m in north/south direction and ca. 70m in east/west direction (at the average latitude of the alpine convention)
*/
var /** ClustererOptions */ clustererOptions = {
	"viewportLat" :	43.43132018706552, 
	"viewportLng" : 4.884734173789496, 
	"viewportHeight" : 4.93608093568811, 
	"viewportWidth" : 11.586466768725804, 
	"gridsizeLng" : 16, 
	"gridsizeLat" : 16, 
	"threshold" : 0.000898315284119521435127501256466
};

if (mapInterfaceType == MapInterfaceType.GoogleMaps){
	var GMData = {
	    mapTypeId : google.maps.MapTypeId.TERRAIN,
	    fullscreenControlOptions:{
	      position: google.maps.ControlPosition.TOP_RIGHT
	    },
	    streetViewControl: false,
	    mapTypeControl: false
	};
	
	jQuery(initMap(mapPosition, clustererOptions, GMData));
	
	 jQuery.ajax({
		dataType: "json",
		url: ajax_object["plugin_url"] + "/im_config/map_styles/black_no_labels.json",
		success: function(data){
			/** @type {GoogleMapsInterface} */ (mapInterface).addMapStyle("hex_quantify", data);
		}
	 });
	 
	 jQuery.ajax({
		dataType: "json",
		url: ajax_object["plugin_url"] + "/im_config/map_styles/no_labels.json",
		success: function(data){
			/** @type {GoogleMapsInterface} */ (mapInterface).addMapStyle("hex_normal", data);
		}
	 });
	 
	 jQuery(document).on("im_google_maps_style", function (event, data){
		 var state = optionManager.getOptionState('polymode');
		 
		 if(state != "phy"){
			 if(data["style"] == "normal"){
				 data["style"] = "hex_normal";
			 }
			 else {
				 data["style"] = "hex_quantify";
			 }
		 }
	 });
}
else if (mapInterfaceType == MapInterfaceType.PixiWebGL){
	jQuery(initMap(mapPosition, clustererOptions, {}));
}
