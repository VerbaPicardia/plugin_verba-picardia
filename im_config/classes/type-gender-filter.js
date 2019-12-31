/**
 *
 *
 * @constructor
 * @implements {FilterComponent} 
 * 
 */
function TypeGenderFilterComponent (){
	/**
	 * @override
	 * 
	 * @param {number} categoryId
	 * @param {string} elementId
	 * 
	 * @return {Element} 
	 */
	this.getFilterScreenElement = function (categoryId, elementId){
		var /** Array<string> */ idList = elementId.substring(1).split("+");
		
		if(idList.length < 2)
			return null;
		
		var /** Element */ result = document.createElement("div");
		result["className"] = "filterComponent";
		result["id"] = "typeGenderComponent";
		result["dataset"]["prefix"] = elementId[0];
		
		var /** Element */ caption = document.createElement("h2");
		caption.appendChild(document.createTextNode(Ue["GENUS"]));
		result.appendChild(caption);
		
		for (var i = 0; i < idList.length; i++){
			if (optionManager.getOptionState("ak") || TypeOccs["L" + idList[i]] == "1"){
				var /** string */ gender = TypeGenders[idList[i]];
				
				var /** Element */ input = document.createElement("input");
				input["type"] = "checkbox";
				input["checked"] = "checked";
				input["className"] = "typeGenderCheckbox";
				input["dataset"]["id"] = idList[i];
				input["style"]["margin-right"] = "5px";
				
				result.appendChild(input);
				result.appendChild(document.createTextNode(Ue["GENUS_" + gender.toUpperCase()]));
				result.appendChild(document.createElement("br"));
			}
		}
		return result;
	};
	
	/**
	 * @override
	 * 
	 * @param {Object<string, ?>} data
	 * 
	 * @return {boolean} 
	 */
	this.storeData = function (data){
		var usedGenders = jQuery("#typeGenderComponent .typeGenderCheckbox:checked");
		
		if(usedGenders.length == 0)
			return false;
		
		var /** Array<string> */ newIds = [];
		usedGenders.each(function (){
			newIds.push(/** @type{string} */ (jQuery(this).data("id")));
		});
		
		data["id"] = jQuery("#typeGenderComponent").data("prefix") + newIds.join("+");
		data["adjustSubElementSymbols"] = true;
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
		//Use all genders, but set adjust flag
		data["adjustSubElementSymbols"] = true;
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
		//Do nothing
	};
}