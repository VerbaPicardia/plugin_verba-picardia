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
function SyntaxInfoWindowContent (categoryID, elementID, overlayType, data){
	
	/**
	 * @type{Array<jQuery>}
	 */
	this.apis = [];
	
	/**
	 * @type {InfoWindowContent}
	 */
	this.original;
	 
	if (overlayType == OverlayType.Polygon){
		this.original = new PolygonInfoWindowContent(categoryID, elementID, overlayType, data);
	}
	else {
		this.original = new SimpleInfoWindowContent(categoryID, elementID, overlayType, data);
	}
	
	/** 
	 * @override
	 * 
	 * @param {number} index
	 * 
	 * @return {string|Element} 
	 */
	this.getHtml = function (index){
		return this.original.getHtml(index);
	};
	
	/** 
	 * 
	 * @param {InfoWindowContent} oldContent
	 * 
	 * @return {boolean}
	 *  
	 */
	this.tryMerge = function (oldContent){
		return this.original.tryMerge(oldContent);
	};
	
	/**
	 * @override
	 * 
	 * @param {Element} tabContent
	 * @param {number} tabIndex
	 * @param {Object} infoWindow
	 * @param {Object} overlay
	 * 
	 * @return {undefined} 
	 */
	this.onOpen = function (tabContent, tabIndex, infoWindow, overlay){
		this.original.onOpen(tabContent, tabIndex, infoWindow, overlay);
		this.apis = addBiblioQTips(jQuery(tabContent));
	};
	
	/**
	 * @override
	 * 
	 * @param {Element} content
	 * 
	 * @return {undefined} 
	 */
	this.onClose = function (content){
		this.original.onClose(content);
		
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
		return this.original.getData();
	};
	
	/**
	 * @override
	 * 
	 * @return {string}
	 */
	this.getName = function (){
		return this.original.getName();
	};
	
	/**
	*
	* @override
	*
	* @return {undefined} 
	*/
	this.resetState = function (){
		this.original.resetState();
	};
	
	/**
	 * @override
	 * 
	 * @return {number}
	 */
	this.getNumElements = function (){
		return this.original.getNumElements();
	};
}