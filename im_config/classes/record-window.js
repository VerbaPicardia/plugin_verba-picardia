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
function RecordInfoWindowContent (categoryID, elementID, overlayType, data){
	
	/**
	 * @type {number}
	 */
	this.numRecords = 1;
	
	/**
	 * @type {string} record 
	 */
	this.record = data["record"];
	
	/**
	 * @type {Array<string>}
	 */
	this.original = [data["original"]];
	
	/**
	 * @type {number}
	 */
	this.encoding = data["encoding"] * 1;
	
	/**
	 * @type {Array<string>} 
	 */
	this.concepts = data["concepts"].length == 0? [""]: data["concepts"].slice(0);
	
	/**
	 * @type {Array<string>} 
	 */
	this.sources = new Array(this.concepts.length);
	this.sources[0] = data["source"];
	for (var /** number */ i = 1; i < this.concepts.length; i++){
		this.sources[i] = "";
	}
	
	/** 
	 * @type {string} 
	 */
	this.typeTable = data["typeTable"];
	
	/**
	 * @type {string} 
	 */
	this.communityName = data["community"];
	
	/**
	 * @type {string} 
	 */
	this.geonamesID = data["geonames"];
	
	/**
	 * @type {Array<Object>}
	 */
	this.tooltipApis = [];
	
	/** 
	 * @override
	 * 
	 * @param {number} index
	 * 
	 * @return {string} 
	 */
	this.getHtml = function (index){

		var /** string */ result = "<div>";
		var /** number */ hashIndex = this.record.indexOf("###");

		var /** string */ crecord = hashIndex == -1? this.record : this.record.substring(0, hashIndex);
		if (this.encoding == 4){
			crecord = escapeHtml(crecord);
		}
		
		var geonames = this.geonamesID? "<a target='_BLANK' href='http://www.geonames.org/" + this.geonamesID + "'><img class='geonamesLogo' src='" + ajax_object["plugin_url"] + "/images/geonames-icon.svg' /></a>": "";
		
		if(crecord.substring(1,4) == "TYP"){
			result += "<table style='width : 100%'><tr><td>" + Ue['KEIN_BELEG'] + "</td>";
//			if(index == 0 || optionManager.getOptionState("polymode") == "hex"){
//				result += "<td><h2 class='community singleRecord'>" + this.communityName + geonames + "</h2></td>";
//			}
			result += "</tr></table>";
		}
		else {
			var /** string */ recordName = hashIndex == -1? crecord : crecord + "<font color='red'>*</font>";
			result += "<table style='width : 100%'><tr><td><h1 class='singleRecord'>" + recordName + "</h1><div style='display: none'>";
			if (this.encoding == 1){
				result += "Darstellung: IPA " + Ue["QUELLE"];
			}
			else if (this.encoding == 2){
				result += "Darstellung: IPA VA";
			}
			else {
				result += "Darstellung: DST " + Ue["QUELLE"];
			}
			
			var /** string */ originalString;
			var /** string */ firstOriginal = this.original[0];
			var /** boolean */ allIdentical = true;
			var /** boolean */ emptyValues = false;
			for (var j = 0; j < this.original.length; j++){
				if(!this.original[j]){
					emptyValues = true;
					break;
				}
				if(this.original[j] != firstOriginal){
					allIdentical = false;
				}
			}
			
			if(!emptyValues){
				if(allIdentical){
					originalString = firstOriginal;
				}
				else {
					originalString = this.original.join(" / ");
				}
			}

			if(this.encoding < 3 && originalString){
				result += "<br /><br /><span>DST " + Ue["QUELLE"] + ": </span><span class='originalRecord'>" + originalString + "</span>";
			}
			result += "</div><span>(" + Ue['EINZELBELEG'] + ")</span></td>";
//			if(index == 0  || optionManager.getOptionState("polymode") == "hex")
//				result += "<td><h2 class='community singleRecord'>" + this.communityName + geonames + "</h2></td>";
			result += "</tr></table>";
		}
		result += "<br /><br />" + this.typeTable + "<br /><br /><table class='easy-table easy-table-default va_record_source_table'><tr><th>" + Ue["QUELLE"] + "</th><th>" + Ue["KONZEPT"] + "</th></tr>";
		
		for (var /** number */ i = 0; i < this.sources.length; i++){
			var /** string */ cid = this.concepts[i].substring(1);
			var /** Array<Array<string>|string|null>> */ conceptArray = Concepts[cid];
			var /**string */ conceptName;
			var /**string */ conceptDescription;
			if(conceptArray !== undefined){
				conceptName = /** @type {string} */ (conceptArray[0]);
				conceptDescription = /** @type {string} */ (conceptArray[1]);
			}
			else {
				conceptName = "";
				conceptDescription = Ue["KEIN_KONZEPT"];
			}
			
			var /** string */ wdataLink = "";
			if(QIDS[cid]){
				wdataLink = " <a target='_BLANK' href='https://www.wikidata.org/wiki/Q" + QIDS[cid] + "'>(Wikidata)</a>";
			}
			
			if(conceptName == "" || conceptName == conceptDescription)
				result += "<tr><td class='atlasSource'>" + this.sources[i] + "</td><td>" + conceptDescription + wdataLink + "</td></tr>";
			else
				result += "<tr><td class='atlasSource'>" + this.sources[i] + "</td><td><span class='currentRecordWindowConcept' data-concept-descr='" + conceptDescription.replace("'", "&apos;") + "'>" + conceptName + "</span>" + wdataLink + "</td></tr>";
		}
		
		result += "</table>";
		
		if(hashIndex != -1){
			result += "<br /><span class='fullRecordInfo'>* " + Ue["BELEG_TEIL"] + " <span>" + this.record.substring(hashIndex + 3) + "</span></font>";
		}
		
		return result + "</div>";
	};
	
	/** 
	 * 
	 * @param {InfoWindowContent} oldContent
	 * 
	 * @return {boolean}
	 *  
	 */
	this.tryMerge = function (oldContent){
		if(oldContent instanceof RecordInfoWindowContent && oldContent.record == this.record){
			for(var j = 0; j < this.sources.length; j++){
				oldContent.sources.push(this.sources[j]);
				oldContent.concepts.push(this.concepts[j]);
				oldContent.original.push(this.original[0]);
			}
			oldContent.numRecords++;
			return true;
		}
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

		var /** jQuery*/ concepts = jQuery(content).find(".currentRecordWindowConcept");
		concepts.qtip({
			"content" : {
				"attr" : 'data-concept-descr'
			},
			"position" : {
				"my" : "bottom left",
				"at" : "top left"
			}
		});
		var /** Object */ capi = concepts.qtip("api");
		if(capi != null)
			this.tooltipApis.push(capi);
		
		var /** jQuery */ records = jQuery(content).find(".singleRecord:not(.community)");
		
		records.each(function (){
			var /** jQuery*/ textElement = jQuery(this).next();
			if(textElement.html() != ""){
				jQuery(this).qtip({
					"content" : {
						text : textElement
					},
					"position" : {
						"my" : "top left",
						"at" : "bottom left"
					},
					"style" : {
						"classes" : "qtip-record"
					}
				});
			}
		});
		
		var /** RecordInfoWindowContent */ thisObject = this;
		records.each(function (){
			thisObject.tooltipApis.push(jQuery(this).qtip("api"));
		});

		//Listener for multiple typings
		jQuery(content).find(".infoWindowTypeSelect").each(/** @this{Element} */ function (){
			jQuery(this).change(/** @this{Element} */ function(){
				//TODO use class or something
				jQuery(this).parent().parent().children().eq(1).html(/** @type{string} */ (jQuery(this).find("option:selected").data("tname")));
			});
		});
		
		var /** Array<Object>*/ apis = addBibLikeQTips(jQuery(content).find(".va_record_source_table"), ["bibl", "stimulus"], ["blue", "blue"], ["", "sti"]);
		for (let i = 0; i < apis.length; i++){
			thisObject.tooltipApis.push(apis[i]);
		}
		
		apis = addBibLikeQTips(jQuery(content).find(".va_type_table"), ["iso"], ["light"], ["ISO_"]);
		for (let i = 0; i < apis.length; i++){
			thisObject.tooltipApis.push(apis[i]);
		}
	};
	
	/**
	 * @override
	 * 
	 * @param {Element} content
	 * 
	 * @return {undefined} 
	 */
	this.onClose = function (content){
		for(var i = 0; i < this.tooltipApis.length; i++){
			if(this.tooltipApis[i])
				this.tooltipApis[i]["destroy"](true);
		}
	};
	
	/**
	 * @override
	 * 
	 * @return {Array<Object<string, string>>} 
	 */
	this.getData = function () {
		return []; //TODO implement
	};
	
	/**
	 * @override
	 * 
	 * @return {string}
	 */
	this.getName = function (){
		return "";
	};
	
	/**
	*
	* @override
	*
	* @return {undefined} 
	*/
	this.resetState = function (){
		this.original = [data["original"]];
		this.concepts = data["concepts"].length == 0? [""]: data["concepts"].slice(0);
		this.sources = new Array(this.concepts.length);
		this.sources[0] = data["source"];
		for (var /** number */ i = 1; i < this.concepts.length; i++){
			this.sources[i] = "";
		}
		this.numRecords = 1;
	};
	
	/**
	 * @override
	 * 
	 * @return {number}
	 */
	this.getNumElements = function (){
		return this.numRecords;
	};
}