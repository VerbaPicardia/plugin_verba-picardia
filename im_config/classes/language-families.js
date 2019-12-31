/**
 * @constructor
 * @implements {SortType} 
 * 
 */
function LanguageFamilySortComponent (){
		/**
	 * @type {number} 
	 */
	this.sortOrder;
	
	/**
	 * @type{Object<string,string>} 
	 */
	this.nameMapping;
	
	/**
	 * @type{Object<string,number>} 
	 */
	this.langFamMapping;
	
	/**
	 * @override 
	 * 
	 * @param {Array<string>} keyArray The sub-element keys
	 * @param {Object<string,?>} data The original data array as returned from the server.
	 * @param {number} subElementCategory
	 * 
	 * @return{undefined}
	 */
this.initFields = function (keyArray, data, subElementCategory){
		this.nameMapping = Sorter.createNameMapping(keyArray, subElementCategory);
		
		this.langFamMapping = {};
		
		var /** Object<string,number>}*/ charToInt;
		
		switch (this.sortOrder * 1){
			case 0:	charToInt = {"g" : 0, "r" : 1, "s" : 2}; break;
			case 1:	charToInt = {"g" : 0, "s" : 1, "r" : 2}; break;
			case 2:	charToInt = {"r" : 0, "g" : 1, "s" : 2}; break;
			case 3:	charToInt = {"r" : 0, "s" : 1, "g" : 2}; break;
			case 4:	charToInt = {"s" : 0, "g" : 1, "r" : 2}; break;
			case 5:	charToInt = {"s" : 0, "r" : 1, "g" : 2}; break;		
		}
		
		for (var i = 0; i < keyArray.length; i++){
			var /** string */ name = this.nameMapping[keyArray[i]];
			
			var /** number */ pos = name.indexOf("(");
			
			if(pos == -1){
				this.langFamMapping[keyArray[i]] = 3; //Records without family last
			}
			var /** string */ indexLang = charToInt[name.substring(pos + 1, pos + 2)];
			if(indexLang == undefined)
				this.langFamMapping[keyArray[i]] = 3; //Records without family last
			else
				this.langFamMapping[keyArray[i]] = indexLang;
			
		}
	};
	
	/**
	 * @override
	 * 
	 * @param {string} a First element key
	 * @param {string} b Second element key
	 * 
	 * 
	 * @return {number} The sorted key array
	 */
	this.compareFunction = function (a, b){
		
		if(a == -1)
			return -1;
		if(b == -1)
			return 1;
		
		if(this.langFamMapping[a] == this.langFamMapping[b]){
			return this.nameMapping[a].localeCompare(this.nameMapping[b]);
		}

		return this.langFamMapping[a] - this.langFamMapping[b];
	};
	
	/** 
	 * @override
	 * 
	 * @return {string} 
	 */
	this.getName = function (){
		return Ue["NACH_SPRACHFAMILIEN"];
	};
	
	/**
	 * @override
	 * 
	 * return{number} 
	 */
	this.getNumSortOrders = function (){
		return 6;
	};
	
	/**
	 * @override
	 * 
	 * @param {number} index
	 * 
	 * @return {string} 
	 */
	this.getSortOrderName = function (index){
		switch (index ){
			case 0: return "ger,rom,sla";
			case 1: return "ger,sla,rom";
			case 2: return "rom,ger,sla";
			case 3: return "rom,sla,ger";
			case 4: return "sla,ger,rom";
			default: return "sla,rom,ger";
		}
	};
	
	/**
	 * @override
	 * 
	 * @return {number} 
	 */
	this.getDefaultSortOrder = function (){
		return 0;
	};
}
