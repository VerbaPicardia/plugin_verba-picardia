/**
 *
 * @constructor
 * @implements {FilterComponent} 
 * 
 */
function ConceptFilterComponent (){
	
	/**
	 * @override
	 * 
	 * @param {Object<string, ?>} filterData
	 * @param {Element} element
	 * 
	 * @return {boolean} 
	 */
	this.storeData = function (filterData, element){

		var /**Array<string> */ ids = [];
		var /** Array<string> */ elements = jQuery(element).children("div").jstree("get_selected");
		jQuery.each(elements, /** @this{string} */ function (){
			ids.push(/** @type {string} */ (jQuery("#" + this).data("concept")));
		});
		
		if(ids.length == 0)
			return false;
		
		filterData["conceptIds"] = ids;
		
		jQuery(element).children("div").jstree("destroy");
		return true;
	};
	
	/**
	 * @override
	 * 
	 * @param {Object<string, ?>} filterData
	 * 
	 * @return {undefined} 
	 */
	this.storeDefaultData = function (filterData){
		filterData["conceptIds"] = "ALL";
	};
	
	/**
	 * @override
	 * 
	 * @param {number} categoryId
	 * @param {string} elementId
	 * 
	 * @return {Element} 
	 */
	this.getFilterScreenElement = function (categoryId, elementId){
		var /** Element */ result = document.createElement("div");
		result["style"]["max-height"] = "250pt";
		result["style"]["overflow-y"] = "auto";
		result["style"]["margin-bottom"] = "2em";
		
		var /** Element */ h2 = document.createElement("h2");
		h2.appendChild(document.createTextNode(Ue["UNTERKONZEPTE_LISTE"]));
		result.appendChild(h2);
		
		var /**Element */ treeDiv = document.createElement("div");
		treeDiv["dataset"]["conceptId"] = elementId.substring(1);
		result.appendChild(treeDiv);
		
		return result;
	};
	
	/**
	 * @override
	 * 
	 * @param {Element} element
	 * 
	 * @return {undefined}
	 * 
	 */
	this.afterAppending = function (element){
		var /**jQuery*/ divElement = jQuery(element).children("div");
		
		var /**{json: Object<string, ?>, count: number}*/ treeArray = createSubTreeJSON(/** @type{string}*/ (divElement.data("conceptId")));
		divElement.css("height", (24 * treeArray["count"]) + "px");
		
		divElement.jstree({
			"core" : {
				"data" : treeArray["json"],
				"themes" : {
					"icons" : false
					}
				},
			"plugins" : ["checkbox"],
			"checkbox" : {
				"three_state" : false,
				"visible" : true
			}
		});
	};
}

/** 
 * @param {string} id_concept
 * 
 * @return {{json: Object<string, ?>, count: number}}
 */
function createSubTreeJSON (id_concept){
	var /** Array */ conceptArray = Concepts[id_concept];
	var /** number */ count = 0;
	
	var /** number */ numChildren = /** @type{Array<number>} */ (conceptArray[3]).length;
	var /** Array<Object<string,?>> */ children = [numChildren];
	for (var i = 0; i < numChildren; i++){
		var /** {json: Object<string, ?>, count: number} */ childResult = createSubTreeJSON(conceptArray[3][i]);
		children[i] = childResult["json"];
		count += childResult["count"];
	}
	
	var numRecords = /** @type{number} */ (optionManager.getOptionState("ak")? conceptArray[2]: conceptArray[5]);
	
	var /** Object<string, ?>*/ result = {
			"text" : getNodeText(/** @type{string} */ (conceptArray[0]), /** @type{string} */ (conceptArray[1]), numRecords),
	  		"state"       : {
	  			"selected"  : true
	  		},
	  		"li_attr"     : {"data-concept" : id_concept}
	};
	if(numChildren > 0){
		result["children"] = children;
	}
	
	return {"json" : result, "count" : count + 1};
}

/**
 * 
 * @param {string} name
 * @param {string} description
 * @param {number} count
 * @returns {string}
 */
function getNodeText (name, description, count){
	return (name == "" || name == description? description: name + " (" + description + ")") 
		+ " (" + count + " " + (count == '1'? Ue['BELEG'] : Ue['BELEGE']) + ")";
}
