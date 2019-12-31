/**
 * @constructor
 * @struct
 * @implements {InfoWindowContent}
 * 
 * @param {number} categoryID
 * @param {string} elementID
 * @param {OverlayType} overlayType
 * @param {Object<string, ?>} data
 */
function InformantInfoWindowContent (categoryID, elementID, overlayType, data){
	
	/**
	 * Object<string, string>
	 */
	this.data = data;
	
	/**
	 * @type{Array<jQuery>}
	 */
	this.apis = [];
	
	/** 
	 * @override
	 * 
	 * @param {number} index
	 * 
	 * @return {string} 
	 */
	this.getHtml = function (index){
		var /** string */ res = '<h2>' + this.data["locationName"] + '</h2><br /><table class="informantDetails"><tr><td>' + Ue['INFORMANT_NUMMER'] + '</td><td>' + this.data["number"] + '</td></tr>';
		
		if (this.data["gender"]){
			res += '<tr><td>' + Ue['GESCHLECHT'] + '</td><td>' + this.data["gender"] + '</td></tr>';
		}
		
		if (this.data["age"]){
			res += '<tr><td>' + Ue['ALTER'] + '</td><td>' + this.data["age"] + '</td></tr>';
		}

		res += '</table>';
		
		if (this.data["description"]){
			res += '<br /><div>' + this.data["description"] + '</div>';
		}
		
		return res;
		
	};
	
	/** 
	 * 
	 * @param {InfoWindowContent} oldContent
	 * 
	 * @return {boolean}
	 *  
	 */
	this.tryMerge = function (oldContent){
		return false;
	};
	
	/**
	 * @override
	 * 
	 * @param {Element} content
	 * 
	 * @return {undefined} 
	 */
	this.onOpen = function (content){
		this.apis = addBiblioQTips(jQuery(content));
	};
	
	/**
	 * @override
	 * 
	 * @param {Element} content
	 * 
	 * @return {undefined} 
	 */
	this.onClose = function (content){
		for(var i = 0; i < this.apis.length; i++){
			if(this.apis[i])
				this.apis[i]["destroy"](true);
		}
	};
	
	/**
	 * @override
	 * 
	 * @return {Array<Object<string, string>>} 
	 */
	this.getData = function () {
		return [this.data];
	};
	
	/**
	 * @override
	 * 
	 * @return {string}
	 */
	this.getName = function (){
		return this.data["locationName"];
	};
	
	/**
	*
	* @override
	*
	* @return {undefined} 
	*/
	this.resetState = function (){
		
	};
	
	/**
	 * @override
	 * 
	 * @return {number}
	 */
	this.getNumElements = function (){
		return 1;
	};
}