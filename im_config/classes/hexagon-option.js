/**
 * @struct
 * @constructor
 * @implements {GuiOption}
 * 
 */
function HexagonOption (){
	
	/**
	 * @type {string}
	 */
	this.key;
	
	/**
	 * @override
	 * 
	 * @param {Element} parent
	 */
	this.appendHtmlElements = function (parent){
		//Do nothing (html element already exists)
	};
	
	/**
	 * @override
	 * 
	 * @param{boolean} val
	 * 
	 * @return {undefined}
	 */
	this.setEnabled = function (val){
		if(val)
			jQuery('.mode_switch_label').removeClass("disabled");
		else
			jQuery('.mode_switch_label').addClass("disabled");
	};
	
	/**
	 * @override
	 * 
	 * @param{string} val
	 * @param{Object<string,?>=} details
	 * 
	 * @return {undefined}
	 */
	this.applyState = function (val, details){
		if(!details || details["first"] !== true){

		   var is_quantified = symbolClusterer.checkQuantify();
			
		   this.setHexMenus(val !== "phy");
		   
		   if(val !== "phy"){
			   categoryManager.addAjaxData("hexgrid", val);
			   optionManager.setOption("ak", "false", {"first" : true});
			}
			else {
				categoryManager.removeAjaxData("hexgrid");
			}
		   
		   	mapInterface.updateMapStyle(is_quantified !== false);
		
		   	if(details && details["load"] !== undefined)
		   		categoryManager.loadData(6, details["load"], "costum");
		   
		   	for (var i = 0; i < legend.getLength(); i++){
		   		if(legend.getElement(i).category == categories.Polygon && legend.getElement(i).filterData){
		   			delete(legend.getElement(i).filterData["removed"]);
		   			break;
		   		}
		   	}

			optionManager.enableOptions(false);
			legend.reloadOverlays(function (){
				optionManager.enableOptions(true);
				if(val == "phy"){
					jQuery("#phy_label").addClass("active");
					jQuery("#hex_label").removeClass("active");
					jQuery("#hex_label").removeClass("focus");
					jQuery("#phy_label").children().first().prop("checked", true);
					jQuery("#hex_label").children().first().prop("checked", false);
				}
				else {
					jQuery("#phy_label").removeClass("active");
					jQuery("#hex_label").addClass("active");
					jQuery("#phy_label").removeClass("focus");
					jQuery("#phy_label").children().first().prop("checked", false);
					jQuery("#hex_label").children().first().prop("checked", true);
				}
			});
		}
	};
	
	/**
	 * @override
	 * 
	 * @param {string} key
	 * 
	 * @return {undefined}
	 */
	this.setKey = function (key){
		this.key = key;
	};

	/**
	 * @override
	 * 
	 * @return {string}
	 */
	this.getDefaultValue = function (){
		return "phy";
	};
	
	/**
	 * @private
	 * 
	 * @param {boolean} isHex
	 * 
	 * @return {undefined}
	 */
	this.setHexMenus = function (isHex){
		//TODO remove line strings from menus
		if(isHex){
			jQuery("#polygonSelect").chosen("destroy");
			jQuery("#polygonSelect").toggle(false);
			jQuery("#hexagonSelect").chosen(chosenSettings);
			categoryManager.setElementID(categories.Polygon, "hexagonSelect");
		}
		else {
			jQuery("#hexagonSelect").chosen("destroy");
			jQuery("#hexagonSelect").toggle(false);
			jQuery("#polygonSelect").chosen(chosenSettings);
			categoryManager.setElementID(categories.Polygon, "polygonSelect");
		}
	};
	
	/**
	 * @override
	 * 
	 * @return {boolean}
	 */
	this.isSaved = function (){
		return true;
	};
}