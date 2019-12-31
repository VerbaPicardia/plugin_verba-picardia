/**
 *
 * @constructor
 * @implements {FilterComponent} 
 * 
 * @param {string} defaultVal
 * 
 */
function SQL_Filter (defaultVal){
	
	/**
	 * @const
	 * @type {string}
	 */
	this.defaultVal = defaultVal;
	
	/**
	 * @override
	 * 
	 * @param {number} categoryId
	 * @param {string} elementId
	 * 
	 * @return {Element} 
	 */
	this.getFilterScreenElement = function (categoryId, elementId){
		var div = document.createElement("div");
		
		var nameField = document.createElement("input");
		nameField["type"] = "text";
		nameField["autocomplete"] = "off";
		nameField["id"] = "va_sql_name";
		nameField["style"]["margin-left"] = "5px";
		
		div.appendChild(document.createTextNode(Ue["NAME"]));
		div.appendChild(nameField);
		
		var text = document.createElement("div");
		text["style"]["margin-top"] = "40px";
		text.appendChild(document.createTextNode("WHERE"));
		
		var icon = document.createElement("i");
		icon["className"] = "far fa-question-circle";
		icon["id"] = "va_sql_help";
		icon["style"]["marginLeft"] = "10px";
		text.appendChild(icon);
		
		div.appendChild(text);
		
		var input = document.createElement("textarea");
		input["style"]["width"] = "500px";
		input["style"]["height"] = "150px";
		input["style"]["margin-top"] = "10px";
		input["style"]["margin-bottom"] = "50px";
		input["id"] = "va_sql_textarea";
		input["autocomplete"] = "off";
		input.appendChild(document.createTextNode(this.defaultVal));
		
		div.appendChild(input);

		return div;
	};
	
	/**
	 * @override
	 * 
	 * @param {Object<string, ?>} data
	 * 
	 * @return {boolean} 
	 */
	this.storeData = function (data){
		data["where"] = jQuery("#va_sql_textarea").val();
		data["id"] = jQuery("#va_sql_name").val();
		return true;
	};
	
	/**
	 * @override
	 * 
	 * @param {Object<string, ?>} data
	 * @param {number} categoryId
	 * @param {string} elementId
	 * 
	 * @return {undefined} 
	 */
	this.storeDefaultData = function (data, categoryId, elementId){
		data["where"] = this.defaultVal;
	};
	
	/**
	 * @override
	 * 
	 * @param {Element} element
	 * @param {number} mainCategoryId
	 * @param {string} elementId
	 * 
	 * @return {undefined}
	 * 
	 */
	this.afterAppending = function (element, mainCategoryId, elementId){
		
		addMouseOverHelpSingleElement(jQuery(element).find("#va_sql_help"), /** @type{string} */ (jQuery("#va_sql_help_div").html()));
	};
}