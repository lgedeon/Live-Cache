/* 
 * 
 */

function Live_Cache_Obj() {
	var self = this,
		// ajaxurl and auto_updates values originally set by localize script
		ajaxurl = Live_Cache.ajaxurl,
		values = [],
		callbacks = [],
		timeStamp = 1;

	this.auto_updates = Live_Cache.auto_updates;
		
	// schedule first check
	this.timer = setInterval(function () {
		Live_Cache.check();
	}, 6000);

	// to customize what happens when a value is updated, set a callback for that key
	this.setCallback = function (key, callback ) {
		if (typeof callbacks[key] == "undefined") {
			callbacks[key] = jQuery.Callbacks( "unique" );
		}
		callbacks[key].add(callback);
	};

	this.check = function () {
		jQuery.get(
			ajaxurl,
			{ "live_cache_check": timeStamp },
			function (data, s, resp) {
				var time = resp.getResponseHeader('Date').split(" ")[4];
				timeStamp = time.substr(0,2) + time.substr(3,2) + time.substr(6,1);
				jQuery.each(data, function(key, value) {
					if ( typeof callbacks[key] !== "undefined" && (typeof values[key] == "undefined" || values[key] !== value) ){
						callbacks[key].fire(key, value); //pass key and value so that we can use the same callback for multiple keys
					}
				});
				values = data;
			},
			"json"
		);
	};
}

// initialize everything
var Live_Cache = new Live_Cache_Obj();

/*
 * parse requests to directly output to page
 */
for (var key in Live_Cache.auto_updates) {
	if(Live_Cache.auto_updates.hasOwnProperty(key)) {
		Live_Cache.setCallback(key, function (k, v) {
			jQuery(Live_Cache.auto_updates[k]).html(v);
		});
	}
}


/*
 * Use our own system - reset our timer if the refresh rate has been updated
 * Also a handy usage demo
 */
Live_Cache.setCallback('refresh_rate', function (key, value) {
	clearInterval(Live_Cache.timer);
	Live_Cache.timer = setInterval(function () {
		Live_Cache.check();
	}, value * 1000);
});
